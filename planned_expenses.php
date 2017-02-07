<?php
require_once("functions/config.php");
require_once("functions/auth.php");
require_once("functions/func_finance.php");
require_once("functions/func_web.php");
require_once("functions/func_time.php");
require_once("functions/func_table.php");

$doc_title = "Запланированные расходы";
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
$pexp_id = web_get_request_value($_REQUEST, "id_pexp", 'i');
$pexp_year = web_get_request_value($_REQUEST, "year", 'i');
$pexp_month = web_get_request_value($_REQUEST, "month", 'i');
$pexp_id_exp_subcat2 = web_get_request_value($_REQUEST, "id_exp_subcat2", 'i');
$pexp_value = web_get_request_value($_REQUEST, "value", 'f');	//float
$pexp_comment = web_get_request_value($_REQUEST, "comment", 's');

// 2.2 значения по умолчанию для переменных формы

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

$no_choice_string = "-------------------------------------------------------------";

// 3.1 массивы дат
$monthes = get_month_array();
$monthes[0] = "";

// 3.2 запрос категорий и подкатегорий расходов 
$cat_names = array();
$subcat_names = array();
$subcat2_names = array();
$categories  = array();

$query  = "SELECT c.id_exp_cat, c.name, sc.id_exp_subcat, sc.name, id_exp_subcat2, sc2.name FROM expense_subcats2 AS sc2 ";
$query .= "INNER JOIN expense_subcats AS sc ON sc.id_exp_subcat=sc2.id_exp_subcat ";
$query .= "INNER JOIN expense_cats AS c ON sc.id_exp_cat=c.id_exp_cat ";
$query .= "ORDER BY c.name, sc.name, sc2.name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса категорий расходов. Экстренный выход."; exit; }
while( $temp_array = db_fetch_num_array($res) )
{
	$id_cat = (int)($temp_array[0]);
	$id_subcat = (int)($temp_array[2]);
	$id_subcat2 = (int)($temp_array[4]);
	$cat_names[$id_cat] = db_strip_slashes($temp_array[1]);
	$subcat_names[$id_cat][$id_subcat] = db_strip_slashes($temp_array[3]);
	$subcat2_names[$id_subcat][$id_subcat2] = db_strip_slashes($temp_array[5]);
	$categories[$id_subcat2] = array($id_cat, $id_subcat); 
}
?>
<script language=Javascript>
function request_value(webform)
{
	var new_value = prompt('Введите сумму расхода');
	if( new_value==null || new_value.length==0 )
		return false;
	else
	{
		webform.value.value = new_value;
		return true;
	}
}

function delete_pexp(form)
{
	if (confirm('Вы хотите удалить этот расход?') == true )
	{
		form.action.value = 'delete';
		return true;
	}
	else
		return false;
}
</script>
<?php
// {end} 3.2 запрос категорий и подкатегорий расходов

// 4. Отработка действий в формой
// Если форма сработала
if( $action )
{
	$db_work = 1;
	
	$action = strtolower($action);
//	echo "action: ".$action."<hr>";

	if( !$pexp_month || $pexp_month > 12 )
	{
		$updating_invalid_value = 1;
		$error_text = "пустое или некорректное поле месяца";
	}
	if( !$pexp_year )
	{
		$updating_invalid_value = 1;
		$error_text = "пустое или некорректное поле года";
	}

	// 4.1 проверка переданных полей формы
	if( $action=='add' )
	{
		if( !$pexp_value )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле суммы";
		}
		if( !$pexp_id_exp_subcat2 )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле идентификатора под2категории";
		}
	}
	elseif( $action=='change' )
	{
		if( !$pexp_id )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле идентификатора покупки";
		}
		if( !$pexp_value )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле суммы";
		}
	}	
	elseif( $action=='delete' )
	{
		if( !$pexp_id )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле идентификатора покупки";
		}
	}
	else
	{
		$db_work = 0;
	}	
	// 4.2 работа с БД, если поля переданы нормально
	if( $db_work && $updating_invalid_value )
	{
		// 4.2.1 - есть ошибки в полях формы
		echo "<font color=red>Произошла ошибка: ".$error_text.".</font><br>";
		$error_text = "";
	}
	elseif( $db_work && !$updating_invalid_value )
	{// if( $updating_invalid_value )
		$insert_mode = false;
		if( $action=='add' )
		{
			$db_query  = "INSERT INTO planned_expenses(year, month, id_exp_subcat2, value, comment, id_transaction) VALUES (";
			$db_query .= $pexp_year.",".$pexp_month.",".$pexp_id_exp_subcat2.",'".$pexp_value."','".db_add_slashes($pexp_comment)."',0) ";
			$insert_mode = true;
		}
		if( $action=='change' )
		{
			$db_query  = "UPDATE planned_expenses SET ";
			$db_query .= "year=".$pexp_year.",";
			$db_query .= "month=".$pexp_month.",";
//			$db_query .= "id_exp_subcat2=".$pexp_id_exp_subcat2.",";
			$db_query .= "value='".$pexp_value."',";
			$db_query .= "comment='".db_add_slashes($pexp_comment)."' ";
			$db_query .= "WHERE id_planned_exp=".$pexp_id;		
		}
		if( $action=='delete' )
		{
			$db_query  = "DELETE FROM planned_expenses ";
			$db_query .= "WHERE id_planned_exp=".$pexp_id;		
		}
//		echo $db_query;
		
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
	if( !$pexp_month || $pexp_month > 12 )
		$pexp_month = (int)(date("m"));
	if( !$pexp_year )
		$pexp_year = (int)(date("Y"));
}

// 5. Заполнение формы
// 5.1 запрос запланированных расходов
$planned_expenses = array();
$query  = "SELECT id_planned_exp, id_exp_subcat2, value, comment FROM planned_expenses AS pe ";
$query  .= "WHERE year=".$pexp_year." AND (month=".$pexp_month." or month=0)";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса запланированных покупок. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$planned_expenses[(int)$temp_array[1]][(int)$temp_array[0]] = array((double)$temp_array[2], db_strip_slashes($temp_array[3]));
// (end) 5.1 запланированных расходов
//echo "<pre>";
//print_r($planned_expenses);

// 6. Вывод формы
$month_prev = $pexp_month - 1;
$year_prev = $pexp_year; 
if( $month_prev==0 )
	{	$month_prev = 12; $year_prev--;	} 

$month_next = $pexp_month + 1;
$year_next = $pexp_year; 
if( $month_next==13 )
	{	$month_next = 1; $year_next++;	} 


echo "<form name=\"filter_form\" action=\"".$_SERVER['PHP_SELF']."\" method=\"".FORM_METHOD."\">\r\n";
echo "<table class=layout><tr class=layout>\r\n";
echo "<td class=layout><div align=center>\r\n";
echo "<a href=\"".$_SERVER['PHP_SELF']."?action=change_date&year=".$year_prev."&month=".$month_prev."\">пред.</a>&nbsp;\r\n";
echo "<input type=text maxlength=4 size=4 name=year value=\"".$pexp_year."\">";
echo web_get_output_webform_selectlist("month", $monthes, $pexp_month);
echo "<input type=hidden name=action value=\"change_date\">\r\n";
echo "<a href=\"".$_SERVER['PHP_SELF']."?action=change_date&year=".$year_next."&month=".$month_next."\">след.</a>&nbsp;\r\n";
echo "<input type=submit value=\"Выбрать\">\r\n";
echo "</td>\r\n";
echo "</tr>\r\n";
echo "</table>\r\n";
echo "</form>\r\n";

echo get_table_start("list");
echo get_table_header(array("Подкатегория", "Сумма", "Комментарий", "=", "X"), "grid"); 

// ------------------------------------- Вывод
$col_mods_bottom = array(
TXT_ALIGN_LEFT|CELL_LINE_BOTTOM, 
TXT_ALIGN_CENTER|TXT_BOLD|CELL_LINE_BOTTOM,
TXT_ALIGN_LEFT|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM
);

$col_mods = array(
TXT_ALIGN_LEFT,
TXT_ALIGN_CENTER,
TXT_ALIGN_LEFT,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER
);

$col_mods_cat = array(
TXT_ALIGN_LEFT|TXT_BOLD,
TXT_ALIGN_CENTER,
TXT_ALIGN_LEFT,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER
);


$pexp_form_num = 0;
$main_form_num = 0;

// общий цикл вывода
foreach( $cat_names as $id_cat => $cat_name )
{
	$cells = array($cat_name,"","","","");
	echo get_table_row($cells, "list", TXT_NONE, "", $col_mods_cat);
	foreach( $subcat_names[$id_cat] as $id_subcat => $subcat_name )
	{
		// +якорь На подкатегорию

		$cells = array(
			"<table class=layout width=100%><tr>".	
			"<td class=layout width=20><div align=center>&equiv;</div></td>".
			"<td class=layout><div align=left><nobr>".$subcat_name."<nobr></div></td>".
			"</tr></table><a name=id_subcat_".$id_subcat.">",
			"",	"", "", "");
		echo get_table_row($cells, "list", TXT_NONE, "", $col_mods);
		$subcat2_count = count($subcat2_names[$id_subcat]);
		$subcat2_num = 0;
		foreach( $subcat2_names[$id_subcat] as $id_subcat2 => $subcat2_name )
		{
			$subcat2_num++;
			$main_form_num++;
			
			if( $subcat2_num == $subcat2_count && !isset($planned_expenses[$id_subcat2]) )
				$columns_modifiers = $col_mods_bottom;
			else
				$columns_modifiers = $col_mods;
				
			$cells = array(
				"<table class=layout width=100%><tr>".	
				"<td class=layout width=20><div align=center>&nbsp;</div></td>".
				"<td class=layout width=20><div align=center>&bull;</div></td>".
				"<td class=layout><div align=left><nobr>".$subcat2_name."<nobr></div></td>".
				"<td class=layout width=1><div id=right>"."<input type=submit value=\"+\" onClick=\"return request_value(document.main_form".$main_form_num.")\">"."</div></td>".
				"</tr></table>",
				"",	"", "", "");
			
//			echo get_table_row($cells, "list", TXT_NONE, "", $columns_modifiers);

			echo "<form name=\"main_form".$main_form_num."\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
			echo "<input type=hidden name=\"id_exp_subcat2\" value=\"".$id_subcat2."\">\r\n";
			echo "<input type=hidden name=\"action\" value=\"add\">\r\n";
			echo "<input type=hidden name=\"value\" value=\"\">";
			echo "<input type=hidden name=\"comment\" value=\"\">";
			echo "<input type=hidden name=\"year\" value=\"".$pexp_year."\">";
			echo "<input type=hidden name=\"month\" value=\"".$pexp_month."\">";
			echo get_table_row($cells, "list", TXT_NONE, "", $columns_modifiers);
			echo "</form>\r\n";

			// вывод запланированных расходов
			if( isset($planned_expenses[$id_subcat2]) )
				$pexp_count = count($planned_expenses[$id_subcat2]);
			else
				$pexp_count = 0; 
			$pexp_num = 0;
			
			if( $pexp_count )
			{
				foreach( $planned_expenses[$id_subcat2] as $id_pexp => $pexp_array )
				{
					$pexp_num++;
					$pexp_form_num++;
									
					if( $pexp_num%2 )
						$row_style_mod = "background-color: ".$even_row_color."; ";
					else	
						$row_style_mod = "background-color: ".$odd_row_color."; ";
	
					if( $subcat2_num == $subcat2_count && $pexp_num == $pexp_count )
						$columns_modifiers = $col_mods_bottom;
					else
						$columns_modifiers = $col_mods;
	
					$pexp_value = $pexp_array[0];		
					$pexp_comment = $pexp_array[1];		
					$cells = array(
						"",
						"<input type=text maxlenth=6 size=6 name=\"value\" value=\"".$pexp_value."\">",
						"<input type=text maxlength=250 size=40 name=\"comment\" value=\"".$pexp_comment."\">",
						"<input type=submit value=\"=\">",
						"<input type=submit value=\"X\" onClick=\"return delete_pexp(document.pexp_form".$pexp_form_num.");\">"
					);
			
					echo "<form name=\"pexp_form".$pexp_form_num."\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
					echo "<input type=hidden name=\"id_pexp\" value=\"".$id_pexp."\">\r\n";
					echo "<input type=hidden name=\"action\" value=\"change\">\r\n";
					echo "<input type=hidden name=\"year\" value=\"".$pexp_year."\">";
					echo "<input type=hidden name=\"month\" value=\"".$pexp_month."\">";
					echo get_table_row($cells, "layout", TXT_NONE, $row_style_mod, $columns_modifiers);
					echo "</form>\r\n";
				
				}	// pexp
			}
		}	// subcats2
	}	// subcats
}	// cats
echo get_table_end();

// 8. Конец скрипта.
include("footer.php");
?>
