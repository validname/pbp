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
$current_step = 3;

$doc_title = "Шаг ".$current_step.": Соответствие счетов";
//$doc_onLoad = "set_mode();";
include("header.php");

// 1. флаги ошибок
$updating_invalid_value = 0;
$updating_error = 0;
$error_text = "";

// 2. подготовка переменных

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$action = web_get_request_value($_REQUEST, "action", 's');
$form_account_name = web_get_request_value($_REQUEST, "account_name", 's');
$form_id_account = web_get_request_value($_REQUEST, "id_account", 'i');

// 2.2 значения по умолчанию для переменных формы

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

// 3.1 запрос названий счетов из импортируемых данных
$imp_account_names = array();
$query = "SELECT account FROM import_draft GROUP BY account ORDER BY account";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса названий импортируемых счетов. Экстренный выход."; exit; }
while( $temp_array = db_fetch_num_array($res) )
	$imp_account_names[] = $temp_array[0]; 

// 3.2 запрос внутренних счетов и их категорий
$account_names = array();
$acc_list_names = array();
$acc_lists = array();

$query  = "SELECT al.id_acc_list, al.name, id_account, a.name FROM accounts AS a ";
$query .= "INNER JOIN account_lists AS al ON al.id_acc_list=a.id_acc_list ";
$query .= "ORDER BY al.name, a.name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса внутренних счетов. Экстренный выход."; exit; }
while( $temp_array = db_fetch_num_array($res) )
{
	$id_acc_list = (int)($temp_array[0]);
	$id_account = (int)($temp_array[2]);
	$acc_list_names[$id_acc_list] = db_strip_slashes($temp_array[1]);
	$account_names[$id_acc_list][$id_account] = db_strip_slashes($temp_array[3]);
	$acc_lists[$id_account] = $id_acc_list; 
}
foreach( $account_names as $id_acc_list => $accounts_array )
	$account_names[$id_acc_list] = array(0 => "------ не импортировать ------") + $accounts_array;
$acc_list_names = array(0 => "------ не импортировать ------") + $acc_list_names;
$acc_lists[0] = 0;

// 4. Отработка действий в формой
// Если форма сработала
if( $action )
{
	$updating_invalid_value = 1;
	$error_text = "Неизвестное действие";
	$action = strtolower($action);
//	echo "action: ".$action."<hr>";

	// 4.1 проверка переданных полей формы
	if( $action=='set_mapping' )
	{
		$updating_invalid_value = 0;
		if( !$form_account_name )
		{
			$updating_invalid_value = 1;
			$error_text = "Пустое имя импортируемого счёта";
		}
		if( !$form_id_account )
		{
			$updating_invalid_value = 1;
			$error_text = "Пустой идентификатор внутреннего счета";
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
		if( $action=='set_mapping' )
		{
			$query_where = " WHERE name='".db_add_slashes($form_account_name)."' AND id_import_mod=".$id_import_mod; 
			// пытаемся прочитать значение  
			$query = "SELECT id_account FROM import_map_acc ";
			$query .= $query_where;
			$result = db_query($query);
			if( !$result )
			{
				$updating_error = 1;
				echo "<font color=red>Произошла ошибка запроса мэппинга счетов</font><br>";
			}
			else
			{//if( $result )
				if( !$temp_array=db_fetch_num_array($result) ) // пустая выборка
					$inserting = true;
				else
					$inserting = false;
				if( $inserting )
				{
					$db_query  = "INSERT INTO import_map_acc (id_import_mod, id_account, name) VALUES (";
					$db_query .= $id_import_mod.", ".$form_id_account.", '".db_add_slashes($form_account_name)."') ";
					$result = db_query($db_query, $insert_mode);
					if( !$result )
					{	// ошибка операции
						$updating_error = 1;
						echo "<font color=red>Произошла ошибка занесения мэппинга счетов</font><br>";
					}
				}//if( $inserting )
				else
				{//if( !$inserting )
					$query = "UPDATE import_map_acc SET id_account=".$form_id_account." ";
					$query .= $query_where;
					$result = db_query($query);
					if( !$result )
					{
						$updating_error = 1;
						echo "<font color=red>Произошла ошибка изменения мэппинга счетов</font><br>";
					}
				}//if( !$inserting )				
			}//if( $result )
		}
		if( !$result )
		{	// ошибка операции
				$updating_error = 1;
				echo "<font color=red>Произошла ошибка запроса </font><br>";
		}
	}// if( $updating_invalid_value )
} // if( $action )
else
{ //if( !$action )
	// форма грузится первый раз
	// сохраняем шаг
	if( !set_config_value($id_import_mod, "last_step", $current_step) )
		{ echo "<hr>Ошибка занесения конфигурационных данных. Экстренный выход."; exit; }
} //if( !$action )

// 5. Заполнение формы
// 5.1 запрос мэппинга счетов
$map_account_ids = array();
$query = "SELECT id_account, name FROM import_map_acc WHERE id_import_mod=".$id_import_mod." ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса мэппинга счетов. Экстренный выход."; exit; }
while( $temp_array = db_fetch_num_array($res) )
{
	$id = (int)($temp_array[0]);
	$name = db_strip_slashes($temp_array[1]);
	$map_account_ids[$name] = $id;
}

// 6. Вывод формы
?>
<script lang=Javascript>
function set_account_list(form)
{
	form.id_account.length=0;
	switch(form.id_acc_list.value)
	{
<?
foreach( $account_names as $id_acc_list => $accounts_array)
{
	echo "		case '".$id_acc_list."':\r\n";
	echo "			form.id_account.length=".count($accounts_array).";\r\n";
	
	$num = 0;
	foreach( $accounts_array as $id_account => $account_name )
	{
		echo "			form.id_account.options[".$num."].value=".$id_account.";\r\n";
		echo "			form.id_account.options[".$num."].text=\"".$account_name."\";\r\n";
		$num++;
	}
	echo "			break;\r\n";
}
?>
	}
	return true;
}
</script>

<div align=center>
<table class=list>
<tr class=list>
<th class=list id=framed>Название импортируемого счёта</th>
<th class=list id=framed>Категория счёта</th>
<th class=list id=framed>Внутренний счёт</th>
</tr>
<?

$num = 0;
$no_mapping_num = 0;
foreach($imp_account_names as $temp => $imp_account_name )
{
	$num++;
	
	if( isset($map_account_ids[$imp_account_name]) && $map_account_ids[$imp_account_name] )
	{
		$id_account = $map_account_ids[$imp_account_name];
		$id_acc_list = $acc_lists[$id_account];
		$accounts_array = $account_names[$id_acc_list];
	}
	else
	{
		$id_account = 0;
		$id_acc_list = 0;
		$accounts_array = array();
		$no_mapping_num++; 		
	}
	echo "<form name=\"form".$num."\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
	echo "<input type=hidden name=action value=set_mapping>";
	echo "<input type=hidden name=account_name value=\"".$imp_account_name."\">";
	echo "<tr class=list>\r\n";
	echo "<td class=list>\r\n";
	if( !$id_account )	echo "<font color=red>";
	echo $imp_account_name;
	if( !$id_account )	echo "</font>";		
	echo "</td>\r\n";
	echo "<td class=list>\r\n";
	echo web_get_output_webform_selectlist("id_acc_list", $acc_list_names, $id_acc_list, false, "set_account_list(document.form".$num.")");	
	echo "</td>\r\n";
	echo "<td class=list>\r\n";
	echo web_get_output_webform_selectlist("id_account", $accounts_array, $id_account);	
	echo "<input type=submit value=\"=\">";
	echo "</td>\r\n";
	echo "</tr>\r\n";
	echo "</form>\r\n";
}

?>
</table>
<br>
<?
if( $no_mapping_num )
	echo "<div align=center><font color=red>Без мэппинга: <b>".$no_mapping_num."</b></font></div><br>";
?>
<table border=0 width=90% align=center>
	<tr>
		<td align=left width=50%>
<?
echo "<form name=\"form\" action=\""."step".($current_step-1).".php"."\" method=".FORM_METHOD.">\r\n";
echo "<div align=left>\r\n";
echo "<input type=submit value=\"Назад\">\r\n";
echo "</div>\r\n";
echo "</form>\r\n";
?>
		</td>
		<td align=right width=50%>
<?
echo "<form name=\"form\" action=\"step".($current_step+1).".php\" method=".FORM_METHOD.">";
echo "<div align=right>\r\n";
echo "<input type=submit value=\"Далее\">\r\n";
echo "</div>\r\n";
echo "</form>\r\n";
?>
		</td>
	</tr>
</table>
</div>
<?

// 7. Конец скрипта.
include("footer.php");
?>
