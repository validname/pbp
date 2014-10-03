<?
require_once("functions/config.php");
require_once("functions/auth.php");
require_once("functions/func_finance.php");
require_once("functions/func_web.php");
require_once("functions/func_time.php");
require_once("functions/func_table.php");

$doc_title = "Накопления";
//$doc_onLoad = "set_mode();";
include("header.php");
//print_header_menu();

// 1. флаги ошибок
$updating_invalid_value = 0;
$updating_error = 0;
$error_text = "";

// 2. подготовка переменных
$even_row_color = "#ffffff";
$odd_row_color = "#e0e0e0";

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$action = web_get_request_value($_REQUEST, "action", 's');
$form_id_sav = web_get_request_value($_REQUEST, "id_sav", 'i');
$form_id_account = web_get_request_value($_REQUEST, "id_account", 'i');
$form_sav_name = web_get_request_value($_REQUEST, "sav_name", 's');
$form_sav_value = web_get_request_value($_REQUEST, "sav_value", 'f');	//float
$form_sav_comment = web_get_request_value($_REQUEST, "sav_comment", 's');

// 2.2 значения по умолчанию для переменных формы

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

// 3.1 запрос счетов
$query = "SELECT id_account, name, comment FROM accounts ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса счетов. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$accounts[(int)$temp_array[0]] = array(db_strip_slashes($temp_array[1]), db_strip_slashes($temp_array[2]));
// (end) 3.1 запрос счетов

// 4. Отработка действий в формой
// Если форма сработала
if( $action )
{
	$action = strtolower($action);
//	echo "action: ".$action."<hr>";

	// 4.1 проверка переданных полей формы
	if( $action=='add' )
	{
		if( !$form_sav_name )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле имени";
		}
		if( !$form_id_account )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле идентификатора счета";
		}
	}
	if( $action=='change' )
	{
		if( !$form_id_sav )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле идентификатора накоплений";
		}
		if( !$form_sav_name )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле имени";
		}
/*		if( !$form_sav_value )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле суммы";
		}*/
	}	
	if( $action=='delete' )
	{
		if( !$form_id_sav )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле идентификатора накоплений";
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
	{// if( $updating_invalid_value )
		$insert_mode = false;
		if( $action=='add' )
		{
			$db_query  = "INSERT INTO savings(name, id_account, value,comment) VALUES (";
			$db_query .= "'".db_add_slashes($form_sav_name)."', ".$form_id_account.", '0.0', '') ";
			$insert_mode = true;
		}
		if( $action=='change' )
		{
			$db_query  = "UPDATE savings SET ";
			$db_query .= "name='".db_add_slashes($form_sav_name)."', ";
			$db_query .= "value='".$form_sav_value."', ";
			$db_query .= "comment='".db_add_slashes($form_sav_comment)."' ";
			$db_query .= "WHERE id_sav=".$form_id_sav;		
		}
		if( $action=='delete' )
		{
			$db_query  = "DELETE FROM savings ";
			$db_query .= "WHERE id_sav=".$form_id_sav;		
		}
		
		$result = db_query($db_query, $insert_mode);
		if( !$result )
		{	// ошибка операции
				$updating_error = 1;
				echo "<font color=red>Произошла ошибка запроса </font><br>";
		}
		else
		{
			if( $insert_mode )
				$form_id_sav = $result;
		}
	}// if( $updating_invalid_value )
} // if( $action )
else
{ // значения по умолчанию
}

// 5. Заполнение формы
// 5.1 запрос сбережений
$query = "SELECT id_sav, name, id_account, value, comment FROM savings ORDER BY id_account, name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса сбережений. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$savings[(int)$temp_array[2]][] = array((int)$temp_array[0], db_strip_slashes($temp_array[1]), (double)$temp_array[3], db_strip_slashes($temp_array[4]));
// (end) 5.1 запрос сбережений

// 6. Вывод формы

?>
<script language=Javascript>
function request_name(webform)
{
	var new_name = prompt('Введите название нового накопления');
	if( new_name==null || new_name.length==0 )
		return false;
	else
	{
		webform.sav_name.value = new_name;
		return true;
	}
}

function delete_sav(form)
{
	if (confirm('Вы хотите удалить это накопление?') == true )
	{
		form.action.value = 'delete';
		return true;
	}
	else
		return false;
}
</script>
<?

echo get_table_start("grid");
echo get_table_header(array("Название", "Сумма", "Комментарий", "=", "X"), "grid"); 

// ------------------------------------- Вывод
$col_mods_acc_wo_sav = array(
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM, 
TXT_ALIGN_CENTER|TXT_BOLD|CELL_LINE_BOTTOM,
TXT_ALIGN_LEFT|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM
);

$col_mods_acc_w_sav = array(
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER,
TXT_ALIGN_LEFT,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER
);

$col_mods_sav_last = array(
TXT_ALIGN_RIGHT|CELL_LINE_BOTTOM, 
TXT_ALIGN_CENTER|TXT_BOLD|CELL_LINE_BOTTOM,
TXT_ALIGN_LEFT|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM
);

$col_mods_sav = array(
TXT_ALIGN_RIGHT,
TXT_ALIGN_CENTER,
TXT_ALIGN_LEFT,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER
);

$main_form_num = 0;
$sav_form_num = 0;
// общий цикл вывода - по счетам
foreach( $accounts as $id_account => $account_array )
{
	$main_form_num++;
	if( isset($savings[$id_account]) )
		$saving_count = count($savings[$id_account]);
	else
		$saving_count = 0;
	$account_name = $account_array[0];
	$account_comment = $account_array[1]; 

	if( $saving_count ) // есть накопления, привязанные к этом счету
		$columns_modifiers = $col_mods_acc_w_sav;
	else
		$columns_modifiers = $col_mods_acc_wo_sav;

	$cells = array(
		"<table class=layout width=100%><tr>".	
		"<td class=layout width=1><div id=left><nobr><b>".$account_name."</b></nobr></div></td>".
		"<td class=layout>&nbsp;</td>".
		"<td class=layout width=1><div id=right>"."<input type=submit value=\"+\" onClick=\"return request_name(document.main_form".$main_form_num.")\">"."</div></td>".
		"</tr></table><a name=id_account_".$id_account.">",
		"",
		$account_comment,
		"",
		""
	);

	echo "<form name=\"main_form".$main_form_num."\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
	echo "<input type=hidden name=\"id_account\" value=\"".$id_account."\">\r\n";
	echo "<input type=hidden name=\"action\" value=\"add\">\r\n";
	echo "<input type=hidden name=\"sav_name\" value=\"\">";
	echo get_table_row($cells, "list", TXT_NONE, "", $columns_modifiers);
	echo "</form>\r\n";

	// работа со списком накоплений
	if( !$saving_count ) // самый простой случай - накоплений нет
		continue;
	
	for( $sav_num = 0; $sav_num < $saving_count; $sav_num++ )
	{
		$sav_form_num++;	
		$saving_array = $savings[$id_account][$sav_num];

		$id_sav = $saving_array[0];
		$sav_name = $saving_array[1];
		$sav_value = $saving_array[2];
		$sav_comment = $saving_array[3];

		if( $sav_num%2 )
			$row_style_mod = "background-color: ".$odd_row_color."; ";
		else	
			$row_style_mod = "background-color: ".$even_row_color."; ";

		if( $sav_num+1 == $saving_count )
			$columns_modifiers = $col_mods_sav_last;
		else
			$columns_modifiers = $col_mods_sav;

		$cells = array(
			"<input type=text maxlenth=100 size=20 name=\"sav_name\" value=\"".$sav_name."\">",
			"<input type=text maxlenth=6 size=6 name=\"sav_value\" value=\"".$sav_value."\">",
			"<input type=text maxlength=250 size=40 name=\"sav_comment\" value=\"".$sav_comment."\">",
			"<input type=submit value=\"=\">",
			"<input type=submit value=\"X\" onClick=\"return delete_sav(document.sav_form".$sav_form_num.");\">"
		);

		echo "<form name=\"sav_form".$sav_form_num."\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
		echo "<input type=hidden name=\"id_sav\" value=\"".$id_sav."\">\r\n";
		echo "<input type=hidden name=\"action\" value=\"change\">\r\n";
		echo get_table_row($cells, "grid", "", $row_style_mod, $columns_modifiers);
		echo "</form>\r\n";
	}
}

echo get_table_end();

echo "<div align=center>\r\n";
echo "<a href=\"".$_SERVER['PHP_SELF']."\">Обновить список</a>";
echo "</div>\r\n";

// 8. Конец скрипта.
include("footer.php");
?>
