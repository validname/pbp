<?
require_once('../../functions/config.php');
require_once('../../functions/auth.php');
require_once('../../functions/func_db.php');
require_once('../../functions/func_web.php');
require_once("../../functions/func_time.php");
require_once("../functions/import_config.php");

set_time_limit(0);

$module_name = "HomeBuh";
$id_import_mod = get_module_id($module_name);
$current_step = 2;

$last_step = get_config_value($id_import_mod, "last_step");
if( $last_step === false )
	$last_step = 1; // допустим, так

$doc_title = "Шаг ".$current_step.": Выбор начальной и конечной даты для импорта";
//$doc_onLoad = "set_mode();";
include("header.php");

// 1. флаги ошибок
$updating_invalid_value = 0;
$updating_error = 0;
$error_text = "";

// 2. подготовка переменных

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$action = web_get_request_value($_REQUEST, "action", 's');
$start_date = web_get_request_value($_REQUEST, "start_date", 's');
$finish_date = web_get_request_value($_REQUEST, "finish_date", 's');

// 2.2 значения по умолчанию для переменных формы

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

// 4. Отработка действий в формой
// Если форма сработала
if( $action )
{
	$updating_invalid_value = 1;
	$error_text = "Неизвестное действие";
	$action = strtolower($action);
//	echo "action: ".$action."<hr>";

	// 4.1 проверка переданных полей формы
	if( $action=='set_date' )
	{
		$updating_invalid_value = 0;
		if( !$start_date )
		{
			$updating_invalid_value = 1;
			$error_text = "Пустая начальная дата";
		}
		if( !$finish_date )
		{
			$updating_invalid_value = 1;
			$error_text = "Пустая конечная дата";
		}
	}	
	// 4.2 работа с БД, если поля переданы нормально
	if( $updating_invalid_value )
	{
		// 4.2.1 - есть ошибки в полях формы
		echo "<font color=red>Произошла ошибка: ".$error_text.".</font><br>";
		$error_text = "";
	}
	else
	{// if( !$updating_invalid_value )
		if( $action=='set_date' )
		{
			$result = set_config_value($id_import_mod, "start_date", $start_date);
			if( !$result )
				{ echo "<font color=red>Ошибка занесения конфигурационных данных.</font>"; }
			$result = set_config_value($id_import_mod, "finish_date", $finish_date);
			if( !$result )
				{ echo "<font color=red>Ошибка занесения конфигурационных данных.</font>"; }
		}
	}// if( $updating_invalid_value )
} // if( $action )
else
{ //if( !$action )
	// форма грузится первый раз
	if( $last_step<2 ) // заносим данные в import_draft только если еще не делали
	{	
		// проверка соединения и существования источника данных 
		$conn_conn = odbc_connect('HomeBuh', 'user', 'cupcdvum');
		if( !$conn_conn || !db_open() )
		{
			echo "Ошибка подключения к БД HomeBuh через ODBC-источник 'HomeBuh'. Экстренный выход\r\n";
			exit;
		}
		$res_odbc = odbc_exec($conn_conn, "SELECT Account, StartBalans1, Note FROM Accounts");
		if( !$res_odbc )
		{
			echo "Ошибка тестового запроса к БД HomeBuh. Экстренный выход\r\n";
			exit;
		}
	
		$id_account_debt = 0;
		$account_debt_name = "";
		$counter = 2;
		// проверяем существования "долгового" счета
		while( !$id_account_debt && $counter )
		{
			$counter--;
			$query  = "SELECT id_account, name FROM accounts WHERE is_debt=\"1\"";
			$result = db_query($query);
			if( !$result )
			{
				echo "Ошибка запроса долгового счета. Экстренный выход\r\n";
				exit;
			}
			$temp_array = db_fetch_num_array($result);
			if( !$temp_array )
			{
				db_query("INSERT INTO accounts(name, start_value, comment, is_debt) VALUES(\"долги\", \"0.0\", \"учет долгов\", \"1\")");
				continue;
			}
			$id_account_debt = (int)($temp_array[0]);
			$account_debt_name = db_strip_slashes($temp_array[1]);
		}

		echo "<pre>\r\n";
		echo "<h3>Копирование записей из HomeBuh 4.x в БД MySQL PersonalBudgetPlanner.</h3>";
	
		//------------------------------------------------------------------------------ 1. Расходы
		echo "<hr>1. Расходы.\r\n";
		flush();
		$res_odbc = odbc_exec($conn_conn, "SELECT * FROM Expenses");
		$rows_odbc = 0;
		while( $result_array = odbc_fetch_array($res_odbc) )
		{
			$id_homebuh = (int)$result_array['Cikl'];
			$account_name = $result_array['Account'];
			$date = $result_array['MyDate'];
			$category_name = $result_array['Category'];
			$subcategory_name = $result_array['Subcategory'];
			$quantity = (int)$result_array['Quantity'];
			$value = $result_array['Money1'];
			$comment = $result_array['Note'];
			$rows_odbc++;
			$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type) ";
			$query .= " VALUES(";
			$query .= "\"".$date."\", ";
			$query .= "\"".db_add_slashes($account_name)."\", ";
			$query .= "\"".db_add_slashes($category_name)."\", ";
			$query .= "\"".db_add_slashes($subcategory_name)."\", ";
			$query .= "\"\", ";
			$query .= "".$quantity.", ";
			$query .= "\"".$value."\", ";
			$query .= "\"".db_add_slashes($comment)."\", ";
			$query .= TRANS_TYPE_EXP.", ";	// расходы
			$query .= "\"".IMPORT_CHANGE_ADD."\")";
			if( !db_query($query) )
			{
				echo "Ошибка записи в import_draft. Экстренный выход\r\n";
				exit;
			}
		}
		echo "Найдено <b>".$rows_odbc."</b> записи/ей о расходах.\r\n";
			
		//------------------------------------------------------------------------------ 2. Доходы
		echo "<hr>2. Доходы.\r\n";
		flush();
		$res_odbc = odbc_exec($conn_conn, "SELECT * FROM Incomes");
		$rows_odbc = 0;
		while( $result_array = odbc_fetch_array($res_odbc) )
		{
			$id_homebuh = (int)$result_array['Cikl'];
			$account_name = $result_array['Account'];
			$date = $result_array['MyDate'];
			$category_name = $result_array['Category'];
			$subcategory_name = $result_array['Subcategory'];
			$quantity = (int)$result_array['Quantity'];
			$value = $result_array['Money1'];
			$comment = $result_array['Note'];
			$rows_odbc++;
			$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type) ";
			$query .= " VALUES(";
			$query .= "\"".$date."\", ";
			$query .= "\"".db_add_slashes($account_name)."\", ";
			$query .= "\"".db_add_slashes($category_name)."\", ";
			$query .= "\"".db_add_slashes($subcategory_name)."\", ";
			$query .= "\"\", ";
			$query .= "".$quantity.", ";
			$query .= "\"".$value."\", ";
			$query .= "\"".db_add_slashes($comment)."\", ";
			$query .= TRANS_TYPE_INC.", ";	// доходы
			$query .= "\"".IMPORT_CHANGE_ADD."\")";
			if( !db_query($query) )
			{
				echo "Ошибка записи в import_draft. Экстренный выход\r\n";
				exit;
			}
		}
		echo "Найдено <b>".$rows_odbc."</b> записи/ей о доходах.\r\n";
	
		//------------------------------------------------------------------------------ 3. Перемещения между счетами
		echo "<hr>3. Перемещения между счетами.\r\n";
		flush();
		$res_odbc = odbc_exec($conn_conn, "SELECT * FROM AccountTransfer");
		$rows_odbc = 0;
		while( $result_array = odbc_fetch_array($res_odbc) )
		{
			$id_homebuh	= $result_array['Cikl']; 
			$account1_name = $result_array['AccountOut']; // с которого
			$account2_name = $result_array['AccountIn'];	// на который
			$date = $result_array['MyDate'];
			$value = $result_array['MyMoney'];
			$comment = $result_array['Note'];
			$rows_odbc++;
			$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type) ";
			$query .= " VALUES(";
			$query .= "\"".$date."\", ";
			$query .= "\"".db_add_slashes($account1_name)."\", ";
			$query .= "\"".db_add_slashes($account2_name)."\", "; // as category
			$query .= "\"\", ";
			$query .= "\"\", ";
			$query .= "1, ";
			$query .= "\"".$value."\", ";
			$query .= "\"".db_add_slashes($comment)."\", ";
			$query .= TRANS_TYPE_ACC.", ";	// перемещение
			$query .= "\"".IMPORT_CHANGE_ADD."\")";
			if( !db_query($query) )
			{
				echo "Ошибка записи в import_draft. Экстренный выход\r\n";
				exit;
			}
		}
		echo "Найдено <b>".$rows_odbc."</b> записи/ей о перемещениях.\r\n";
	
		//------------------------------------------------------------------------------ 4. Кредиторы
		echo "<hr>4. Кредиторы.\r\n";
		flush();
		//  - что занимали у других
		$res_odbc = odbc_exec($conn_conn, "SELECT * FROM Creditors");
		$rows_odbc = 0;
		while( $result_array = odbc_fetch_array($res_odbc) )
		{
			// факт заема
			$id_homebuh	= $result_array['Cikl']; 
			$account_name = $result_array['Account'];
			$date = $result_array['MyDate'];
//			$date2 = $result_array['DateClose']; // дата полного погашения долга
//			$value = $result_array['Total1']; // остаток долга
//			$value2 = (float)$result_array['MoneyBack1']; // сумма возвращенного долга
//			$debt_procent = (float)$result_array['DebtPercent'];
			$value = (float)$result_array['Money1']; // сумма долга
			$comment = "[".$result_array['FIO']." - ".$result_array['Note']."] взяли в долг";
			$rows_odbc++;
			$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type) ";
			$query .= " VALUES(";
			$query .= "\"".$date."\", ";
			$query .= "\"".db_add_slashes($account_debt_name)."\", ";
			$query .= "\"".db_add_slashes($account_name)."\", ";
			$query .= "\"\", ";
			$query .= "\"\", ";
			$query .= "1, ";
			$query .= "\"".$value."\", ";
			$query .= "\"".db_add_slashes($comment)."\", ";
			$query .= "3, ";	// перемещение с кредитора (долговой счёт) на настоящий счет (мы взяли в долг)
			$query .= "\"".IMPORT_CHANGE_ADD."\")";
			if( !db_query($query) )
			{
				echo "Ошибка записи в import_draft. Экстренный выход\r\n";
				exit;
			}
			$comment = "[".$result_array['FIO']." - ".$result_array['Note']."] взяли в долг, возврат";
			
			//  возврат долга записывается как сумма возвратов
			$res_odbc2 = odbc_exec($conn_conn, "SELECT * FROM CreditorsBack WHERE CiklDebt=".$id_homebuh);
			while( $result_array2 = odbc_fetch_array($res_odbc2) )
			{
				// факт возврата долга
				$account_name = $result_array2['Account'];
				$date = $result_array2['MyDate'];
				$value = (float)$result_array2['Money1']; // сумма отданного
				$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type) ";
				$query .= " VALUES(";
				$query .= "\"".$date."\", ";
				$query .= "\"".db_add_slashes($account_name)."\", ";
				$query .= "\"".db_add_slashes($account_debt_name)."\", ";
				$query .= "\"\", ";
				$query .= "\"\", ";
				$query .= "1, ";
				$query .= "\"".$value."\", ";
				$query .= "\"".db_add_slashes($comment)."\", ";
				$query .= "3, ";	// перемещение с настоящего счета на кредитора (долговой счёт) (мы вернули долг)
				$query .= "\"".IMPORT_CHANGE_ADD."\")";
				if( !db_query($query) )
				{
					echo "Ошибка записи в import_draft. Экстренный выход\r\n";
					exit;
				}
			}
		}
		echo "Найдено <b>".$rows_odbc."</b> записи/ей о занятых у других людей деньгах.\r\n";
	
		//------------------------------------------------------------------------------ 4. Заёмщики
		echo "<hr>5. Заёмщики.\r\n";
		flush();
		
		//  возврат долга записывается как сумма возвратов в отдельной таблице - подгружаем её
		$debtorsback = array();
		$res_odbc2 = odbc_exec($conn_conn, "SELECT * FROM DebtorsBack");
		while( $result_array2 = odbc_fetch_array($res_odbc2) )
			$debtorsback[$result_array2['CiklDebt']][] = $result_array2;
			
		//  - что занимали другим
		$res_odbc = odbc_exec($conn_conn, "SELECT * FROM Debtors");
		$rows_odbc = 0;
		while( $result_array = odbc_fetch_array($res_odbc) )
		{
	
			$id_homebuh	= $result_array['MyCikl']; 
			$account_name = $result_array['Account'];
			$date = $result_array['MyDate'];
//			$date2 = $result_array['DateClose']; // дата полного погашения долга
//			$value = $result_array['Total1']; // остаток долга
//			$value2 = (float)$result_array['MoneyBack1']; // сумма возвращенного долга
//			$debt_procent = (float)$result_array['DebtPercent'];
			$value = (float)$result_array['Money1']; // сумма долга
			$comment = "[".$result_array['FIO']." - ".$result_array['Note']."] дали в долг";
			$rows_odbc++;
			$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type) ";
			$query .= " VALUES(";
			$query .= "\"".$date."\", ";
			$query .= "\"".db_add_slashes($account_name)."\", ";
			$query .= "\"".db_add_slashes($account_debt_name)."\", ";
			$query .= "\"\", ";
			$query .= "\"\", ";
			$query .= "1, ";
			$query .= "\"".$value."\", ";
			$query .= "\"".db_add_slashes($comment)."\", ";
			$query .= "3, ";	// перемещение с настоящего счета на заемщика (долговой счёт) (дали в долг)
			$query .= "\"".IMPORT_CHANGE_ADD."\")";
			if( !db_query($query) )
			{
				echo "Ошибка записи в import_draft. Экстренный выход\r\n";
				exit;
			}
			$comment = "[".$result_array['FIO']." - ".$result_array['Note']."] дали в долг, возврат";

			//  возврат долга записывается как сумма возвратов
			if( isset($debtorsback[$id_homebuh]) )
			{
				foreach($debtorsback[$id_homebuh] as $temp => $result_array2)
				{
					// факт возврата долга
					$account_name = $result_array2['Account'];
					$date = $result_array2['MyDate'];
					$value = (float)$result_array2['Money1']; // сумма отданного
					$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type) ";
					$query .= " VALUES(";
					$query .= "\"".$date."\", ";
					$query .= "\"".db_add_slashes($account_debt_name)."\", ";
					$query .= "\"".db_add_slashes($account_name)."\", ";
					$query .= "\"\", ";
					$query .= "\"\", ";
					$query .= "1, ";
					$query .= "\"".$value."\", ";
					$query .= "\"".db_add_slashes($comment)."\", ";
					$query .= "3, ";	// перемещение с заемщика (долговой счёт) на настоящий счет (получили долг обратно)
					$query .= "\"".IMPORT_CHANGE_ADD."\")";
					if( !db_query($query) )
					{
						echo "Ошибка записи в import_draft. Экстренный выход\r\n";
						exit;
					}
				}
			}
/*			
			//  возврат долга записывается как сумма возвратов
			$res_odbc2 = odbc_exec($conn_conn, "SELECT * FROM DebtorsBack WHERE CiklDebt=".$id_homebuh);
			while( $result_array2 = odbc_fetch_array($res_odbc2) )
			{
				// факт возврата долга
				$account_name = $result_array2['Account'];
				$date = $result_array2['MyDate'];
				$value = (float)$result_array2['Money1']; // сумма отданного
				$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type) ";
				$query .= " VALUES(";
				$query .= "\"".$date."\", ";
				$query .= "\"".db_add_slashes($account_debt_name)."\", ";
				$query .= "\"".db_add_slashes($account_name)."\", ";
				$query .= "\"\", ";
				$query .= "\"\", ";
				$query .= "1, ";
				$query .= "\"".$value."\", ";
				$query .= "\"".db_add_slashes($comment)."\", ";
				$query .= "3, ";	// перемещение с заемщика (долговой счёт) на настоящий счет (получили долг обратно)
				$query .= "\"".IMPORT_CHANGE_ADD."\")";
				if( !db_query($query) )
				{
					echo "Ошибка записи в import_draft. Экстренный выход\r\n";
					exit;
				}
			}	
*/					
		}
		echo "Найдено <b>".$rows_odbc."</b> записи/ей о занятых другим людям деньгах.\r\n";
	
		odbc_close($conn_conn);
		
		db_query("OPTIMIZE TABLE `import_draft`");
	}
	// сохраняем шаг
	if( !set_config_value($id_import_mod, "last_step", $current_step) )
		{ echo "<hr>Ошибка занесения конфигурационных данных. Экстренный выход."; exit; }
} //if( !$action )

// 5. Заполнение формы
// 5.1 даты в импорте
$query = "SELECT date_format(min(date), '".$sql_date_format."') AS min, date_format(max(date), '".$sql_date_format."') AS max FROM import_draft";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса дат импортируемых данных. Экстренный выход."; exit; }
$temp_array = db_fetch_num_array($res);
$min_imp_date = $temp_array[0]; 
$max_imp_date = $temp_array[1];
if( $last_step<2  )
{
	$start_date = $min_imp_date;
	$finish_date = $max_imp_date;
}
else
{
	$start_date = get_config_value($id_import_mod, "start_date");
	$finish_date = get_config_value($id_import_mod, "finish_date");
}
// (end) 5.1 запрос дат 

// 5.2 даты в имеющихся записях 
$query = "SELECT date_format(min(date), '".$sql_date_format."') AS min, date_format(max(date), '".$sql_date_format."') AS max FROM transactions";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса дат имеющихся данных. Экстренный выход."; exit; }
$temp_array = db_fetch_num_array($res);
$min_date = $temp_array[0]; 
$max_date = $temp_array[1];
// (end) 5.2 запрос дат

// 6. Вывод формы
echo "<div align=center>\r\n";
echo "<form name=\"form\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
?>
<input type=hidden name=action value="set_date">
<table class=list>
<tr class=list>
<th class=list id=framed></th><th class=list id=framed>Установки импорта</th><th class=list id=framed>Импортируемые данные</th><th class=list id=framed>Имеющиеся данные</th>
</tr>
<tr class=list>
<th class=list id=framed>Начальная дата</th>
<td class=list id=framed><div align=center><input type=text size=10 maxlengh=10 name=start_date value="<? echo $start_date; ?>"></div></td>
<td class=list id=framed><div align=center><? echo $min_imp_date; ?></div></td>
<td class=list id=framed><div align=center><? echo $min_date; ?></div></td>
</tr>
<tr class=list>
<th class=list id=framed>Конечная дата</th>
<td class=list id=framed><div align=center><input type=text size=10 maxlengh=10 name=finish_date value="<? echo $finish_date; ?>"></div></td>
<td class=list id=framed><div align=center><? echo $max_imp_date; ?></div></td>
<td class=list id=framed><div align=center><? echo $max_date; ?></div></td>
</tr>
<tr class=list>
<td class=list id=framed colspan=4><div id=center><input type=submit value="Установить"></div></td>
</tr>
</table>
</form>

<br>
<table border=0 width=90% align=center>
	<tr>
		<td align=left width=50%>
		</td>
		<td align=right width=50%>
<?
if( $last_step == $current_step )
{
	echo "<form name=\"form\" action=\"step".($current_step+1).".php\" method=".FORM_METHOD.">";
	echo "<div align=right>\r\n";
	echo "<input type=submit value=\"Далее\">\r\n";
	echo "</div>\r\n";
	echo "</form>\r\n";
}
?>
		</td>
	</tr>
</table>
</div>
<?
// 7. Конец скрипта.
include("footer.php");
?>
