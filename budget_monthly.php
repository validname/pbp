<?php
require_once("functions/config.php");
require_once("functions/auth.php");
require_once("functions/func_finance.php");
require_once("functions/func_web.php");
require_once("functions/func_time.php");
require_once("functions/func_table.php");

$doc_title = "Месячный бюджет";
//$doc_onLoad = "set_mode();";
//include("header.php");
//print_header_menu();

// 1. флаги ошибок
$critical_error = 0;
$updating_invalid_value = 0;
$updating_error = 0;

$error_text = "";
$error_in_id_owner = 0;

// 2. подготовка переменных
$month_current = (int)date('m');
$month_default = $month_current;
$year_default = (int)date('Y');
$day_today = (int)date('d');
$day_last = date("d", mktime(0,0,0,$month_default+1, 0, $year_default)); // последнее число этого месяца
$month_elapsed_procent = round($day_today/$day_last*100, 2);

$good_color = "#c0f0c0";	// green
$bad_color = "#f0c0c0";		// red
$warn_color = "#d0b000";		// orange

$even_row_color = "#ffffff";
$odd_row_color = "#e0e0e0";

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$action = web_get_request_value($_REQUEST, "action", 's');
$year = web_get_request_value($_REQUEST, "year", 'i');
$month = web_get_request_value($_REQUEST, "month", 'i');
$budget_id_exp_subcat = web_get_request_value($_REQUEST, "id_exp_subcat", 'i');
$budget_value = web_get_request_value($_REQUEST, "budget_value", 'f');	//float

// 2.2 значения по умолчанию для переменных формы

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

// 3.1 предзапрос общего спиcка категорий и подкатегорий
$category_names = array();
$subcategory_names = array();
$budgets = array();
$expenses = array();
$category_accounts = array();
$id_exp_cat_prev = 0;
$query  = "SELECT sc.id_exp_cat, c.name AS c_name, sc.id_exp_subcat, sc.name AS sc_name, c.id_account ";
$query .= "FROM expense_subcats AS sc ";
$query .= "INNER JOIN expense_cats AS c ON c.id_exp_cat = sc.id_exp_cat ";
$query .= "ORDER BY c.name, sc_name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса категорий. Экстренный выход."; exit; }
while( $temp_array=db_fetch_assoc_array($res) )
{
	$id_exp_cat = (int)$temp_array['id_exp_cat'];
	$id_exp_subcat = (int)$temp_array['id_exp_subcat'];
	$id_account = (int)$temp_array['id_account'];
	$subcategory_names[$id_exp_subcat] = db_strip_slashes($temp_array['sc_name']);
	$budgets[$id_exp_cat][$id_exp_subcat] = 0.0;
	$expenses[$id_exp_cat][$id_exp_subcat] = 0.0;
	$category_names[$id_exp_cat] = db_strip_slashes($temp_array['c_name']);
	if( $id_account )
		$category_accounts[$id_exp_cat] = $id_account;
}
// (end) 3.1 предзапрос categories

// 3.2 анализ прикрепленных счетов
$attached_acc_array = array();
$rest_acc_flag = false;
$temp_array = array_count_values($category_accounts);
arsort($temp_array);
foreach($temp_array as $id_account => $temp) {
	$attached_acc_array[] = $id_account;
	if( $temp == 1 ) {
		if( !$rest_acc_flag )
			$rest_acc_flag = true;
		else {
			array_pop($attached_acc_array);
			array_pop($attached_acc_array);
			$attached_acc_array[] = 0;	// last attached account = "rest accounts"
			break;
		}
	}
}
$attached_acc_count = count($attached_acc_array);
// (end)3.2 анализ прикрепленных счетов

// 3.3 запрос счетов
$query = "SELECT id_account, name, start_value, comment FROM accounts ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса счетов. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$accounts[(int)$temp_array[0]] = array(db_strip_slashes($temp_array[1]), (double)$temp_array[2], db_strip_slashes($temp_array[3]));
// (end) 3.3 запрос счетов
$accounts[0] = array("остальные", 0.0, "");

// 3.4 запрос баланса счетов по транзакциям
$query = "SELECT id_account, sum(value) FROM transactions GROUP BY id_account";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса счетов. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$account_balances[(int)$temp_array[0]] = (double)$temp_array[1];
// (end) 3.4 запрос баланса счетов

// 3.5 запрос сбережений
$query = "SELECT id_account, sum(value) FROM savings GROUP BY id_account";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса сбережений. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$savings[(int)$temp_array[0]] = (double)$temp_array[1];
// (end) 3.5 запрос сбережений

// 3.6 генерация массива названий месяцев - перенес в начало
$monthes = array_merge(array(0 => ""), get_month_array());
unset($monthes[0]);

// 4. Отработка действий в формой
// Если форма сработала
if( $action )
{
	$action = strtolower($action);
//	echo "action: ".$action."<hr>";

	// 4.1 проверка переданных полей формы
  if( !$year )
  {
    $updating_invalid_value = 1;
		$error_text = "пустое поле года";
		$year = $year_default;
	}
  if( !$month )
  {
    $updating_invalid_value = 1;
		$error_text = "пустое поле месяца";
		$month = $month_default;
	}

	if( $action=='set_budget_value' )
	{
		if( !$budget_id_exp_subcat )
		{
			$updating_invalid_value = 1;
			$error_text = "пустое поле подкатегории расхода";
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
		if( $action=='set_budget_value' )
		{
			$query_where = " WHERE year=".$year." AND month=".$month." AND id_exp_subcat=".$budget_id_exp_subcat; 
			// пытаемся прочитать значение  
			$query = "SELECT value FROM budget_monthly ";
			$query .= $query_where; 
			$result = db_query($query);
			if( !$result )
			{
				$updating_error = 1;
				echo "<font color=red>Произошла ошибка запроса запланированных расходов</font><br>";
			}
			else
			{//if( $result )
				$need2update = false;
				$inserting = true;
				if( !$temp_array=db_fetch_num_array($result) )
					$need2update = true;
				else
				{
					$inserting = false;
					if( $temp_array[0] != $budget_value )
						$need2update = true;
				}
				if( $need2update )
				{
					if( $inserting )
					{
						$db_query  = "INSERT INTO budget_monthly (year, month, id_exp_subcat, value) VALUES (";
						$db_query .= $year.", ".$month.", ".$budget_id_exp_subcat.", '".$budget_value."') ";
						$result = db_query($db_query);
						if( !$result )
						{	// ошибка операции
							$updating_error = 1;
							echo "<font color=red>Произошла ошибка занесения запланированных расходов</font><br>";
						}
					}//if( $inserting )
					else
					{//if( !$inserting )
						$query = "UPDATE budget_monthly SET value='".$budget_value."' ";
						$query .= $query_where;
						$result = db_query($query);
						if( !$result )
						{
							$updating_error = 1;
							echo "<font color=red>Произошла ошибка изменения запланированных расходов</font><br>";
						}
					}//if( !$inserting )				
				}//if( $need2update )
			}//if( $result )
		}//if( $action=='set_budget_value' )
	}// if( $updating_invalid_value )
} // if( $action )
else
{ // значения по умолчанию
  if( !$year )
  	$year = $year_default;
  if( !$month )
  	$month = $month_default;
}

// заголовок
$doc_title = "Месячный бюджет - ".$monthes[$month]." ".$year;
include("header.php");

// 5. Заполнение формы
// 5.1 запрос бюджета по категориям и подкатегориям
$query  = "SELECT c.id_exp_cat, bm.id_exp_subcat, value FROM budget_monthly AS bm ";
$query  .= "INNER JOIN expense_subcats AS sc ON sc.id_exp_subcat=bm.id_exp_subcat ";
$query  .= "INNER JOIN expense_cats AS c ON c.id_exp_cat=sc.id_exp_cat ";
$query  .= "WHERE year=".$year." AND month=".$month;
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса бюджета. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$budgets[(int)$temp_array[0]][(int)$temp_array[1]] = (double)$temp_array[2];
// (end) 5.1 запрос бюджета

// 5.2 запрос реальных затрат - по категориям и подкатегориям
$query  = "SELECT c.id_exp_cat, sc.id_exp_subcat, sum(value) ";
$query .= "FROM transactions AS t ";
$query .= "INNER JOIN expense_subcats2 AS sc2 ON sc2.id_exp_subcat2 = t.id_subcat2 ";
$query .= "INNER JOIN expense_subcats AS sc ON sc.id_exp_subcat = sc2.id_exp_subcat ";
$query .= "INNER JOIN expense_cats AS c ON c.id_exp_cat = sc.id_exp_cat ";
$query .= "WHERE id_transfer=0 AND value<0 ";
$query .= "AND year(date)=".$year." AND month(date)=".$month." ";
$query .= "GROUP BY c.id_exp_cat, sc.id_exp_subcat ";
$query .= "ORDER BY c.name, sc.name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка подсчета расходов. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$expenses[(int)($temp_array[0])][(int)($temp_array[1])] = (double)($temp_array[2]);
// (end) 5.2 запрос реальных затрат

// 5.3 запрос запланированных покупок
$query  = "SELECT sc.id_exp_cat, sc.id_exp_subcat, value, comment FROM planned_expenses AS pe ";
$query  .= "INNER JOIN expense_subcats2 AS sc2 ON sc2.id_exp_subcat2=pe.id_exp_subcat2 ";
$query  .= "INNER JOIN expense_subcats AS sc ON sc.id_exp_subcat=sc2.id_exp_subcat ";
$query  .= "WHERE year=".$year." AND month=".$month;
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса запланированных покупок. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
{
	$planned_expenses[(int)$temp_array[0]][(int)$temp_array[1]][] = array((double)$temp_array[2], db_strip_slashes($temp_array[3]));
	if( isset($planned_expenses_sum_cat[(int)$temp_array[0]]) )
		$planned_expenses_sum_cat[(int)$temp_array[0]] += (double)$temp_array[2];
	else
		$planned_expenses_sum_cat[(int)$temp_array[0]] = (double)$temp_array[2];
	if( isset($planned_expenses_sum_subcat[(int)$temp_array[1]]) )
		$planned_expenses_sum_subcat[(int)$temp_array[1]] += (double)$temp_array[2];	
	else
		$planned_expenses_sum_subcat[(int)$temp_array[1]] = (double)$temp_array[2];
}
// (end) 5.3 запрос запланированных покупок

// 6. Вывод формы
$month_prev = $month - 1;
$year_prev = $year; 
if( $month_prev==0 )
	{	$month_prev = 12; $year_prev--;	} 

$month_next = $month + 1;
$year_next = $year; 
if( $month_next==13 )
	{	$month_next = 1; $year_next++;	} 

?>

<form name="form" action=<?php echo $_SERVER['PHP_SELF']; ?> method="<?php echo FORM_METHOD; ?>">
<table class=layout><tr class=layout>
<td class=layout>
<?php
	echo "<a href=\"".$_SERVER['PHP_SELF']."?action=change_month&year=".$year_prev."&month=".$month_prev."\">пред.</a>&nbsp;\r\n";
	echo "<input type=text maxlength=4 size=4 name=year value=\"".$year."\">";
	echo web_get_output_webform_selectlist("month", $monthes, $month);
?>
<input type=hidden name=action value="change_month">
<input type=submit value="Выбрать">
<?php
	echo "<a href=\"".$_SERVER['PHP_SELF']."?action=change_month&year=".$year_next."&month=".$month_next."\">след.</a>&nbsp;\r\n";
?>
</td>
</tr></table>
</form>

<table class=list>
<tr class=list>
<th class=list id=framed rowspan=2>#</th><th class=list id=framed rowspan=2 colspan=1>Категория</th><th class=list id=framed colspan=<?php echo 5+$attached_acc_count; ?>>Расходы</th><th class=list id=framed rowspan=2>.</th>
</tr>
<tr class=list>
<th class=list id=framed>План.</th><th class=list id=framed>Факт.</th><th class=list id=framed>%</th>
<th class=list id=framed>остаток</th>
<?php foreach($attached_acc_array as $temp => $id_account) { ?>
<th class=list id=framed>остаток на счете<br><?php echo "\"".$accounts[$id_account][0]."\""; ?><br>(без накоплений)</th>
<?php } ?>
<th class=list id=framed>+/-</th>
</tr>
<?php

$col_mods_cats_wo_sub = array(
TXT_ALIGN_RIGHT|CELL_LINE_BOTTOM, 
TXT_ALIGN_LEFT|TXT_BOLD|CELL_LINE_BOTTOM,
TXT_ALIGN_RIGHT|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM
);

$col_mods_cats_w_sub = array(
TXT_ALIGN_RIGHT, 
TXT_ALIGN_LEFT|TXT_BOLD,
TXT_ALIGN_RIGHT,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER
);

$col_mods_subcat_last = array(
CELL_LINE_BOTTOM,
TXT_ALIGN_RIGHT|CELL_LINE_BOTTOM,
TXT_ALIGN_RIGHT|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM
);

$col_mods_subcat = array(
TXT_NONE, 
TXT_ALIGN_RIGHT,
TXT_ALIGN_RIGHT,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER,
TXT_NONE,
TXT_ALIGN_CENTER
);

$col_mods_pexp_last = array(
CELL_LINE_BOTTOM,
CELL_LINE_BOTTOM,
TXT_ALIGN_CENTER|CELL_LINE_BOTTOM,
TXT_ALIGN_LEFT|CELL_LINE_BOTTOM,
CELL_LINE_BOTTOM,
CELL_LINE_BOTTOM,
CELL_LINE_BOTTOM,
CELL_LINE_BOTTOM
);

$col_mods_pexp = array(
TXT_NONE, 
TXT_NONE, 
TXT_ALIGN_CENTER, 
TXT_ALIGN_LEFT, 
TXT_NONE, 
TXT_NONE,
TXT_NONE,
TXT_NONE, 
);

function expand_array($array, $key, $value, $count) {
	// insert $value element between ($key-1) and oldkey key $count times
	// $count=0 means unchanged array
	// works only with numeric arrays!
	$length = count($array);
	// right tail
	for($temp=$length-1; $temp>=$key; $temp--) {
			$array[$temp+$count] = $array[$temp];
	}
	// filling
	for($temp=$key+$count-1; $temp>=$key; $temp--) {
			$array[$temp] = $value;
	}
	ksort($array);
	return $array;
}

$col_mods_cats_wo_sub = expand_array($col_mods_cats_wo_sub, 6, TXT_ALIGN_CENTER|CELL_LINE_BOTTOM, $attached_acc_count);
$col_mods_cats_w_sub = expand_array($col_mods_cats_w_sub, 6, TXT_ALIGN_CENTER, $attached_acc_count);
$col_mods_subcat_last = expand_array($col_mods_subcat_last, 6, CELL_LINE_BOTTOM, $attached_acc_count);
$col_mods_subcat = expand_array($col_mods_subcat, 6, TXT_NONE, $attached_acc_count);
$col_mods_pexp_last = expand_array($col_mods_pexp_last, 6, CELL_LINE_BOTTOM, $attached_acc_count);
$col_mods_pexp = expand_array($col_mods_pexp, 6, TXT_NONE, $attached_acc_count);

// вывод категорий
$cat_num = 0;
$expenses_total = 0;
$diff_total = 0;
$budget_total = 0;
$rest_acc_total_balance = 0;
$diff_round_total = 0;
$diff_error_total = 0;
$diff_add_total = 0;

// ------------------------------------- Вывод
// общий цикл вывода - по категориям
foreach( $expenses as $id_exp_cat => $subcat_expenses )
{
	$cat_num++;
	
	reset($subcat_expenses);
	$subcat_count = count($subcat_expenses);

	// выводим категорию в любом случае
	$category_name = $category_names[$id_exp_cat];

	$expense_value = -array_sum($subcat_expenses);
	$expenses_total += $expense_value;
	$budget_value = array_sum($budgets[$id_exp_cat]);
	$budget_total += $budget_value;
	$diff = round($budget_value - $expense_value, 2);

	if( $budget_value )
	{
		$procent = round($expense_value/$budget_value*100, 2);
		$procent_string = $procent."%";
	}
	else
	{
		$procent = 0.0;
		$procent_string = "";
	}			

	if( isset($category_accounts[$id_exp_cat]) && $category_accounts[$id_exp_cat] )
	{
		$id_account = $category_accounts[$id_exp_cat];
		$temp_array = $accounts[$id_account];
		//$account_name = $temp_array[0];
		//$account_comment = $temp_array[2];
		$account_start_balance = $temp_array[1];

		if( isset($account_balances[$id_account]) )
			$account_balance = $account_balances[$id_account];
		else
			$account_balance = 0;
		$account_balance += $account_start_balance;

		if( isset($savings[$id_account]) && $savings[$id_account] )
		{
			$account_balance -= $savings[$id_account];
			$account_balance_text = $account_balance." <i><a href=savings.php#id_account_".$id_account.">[".$savings[$id_account]."</a>]</i>"; 
		}
		else
			$account_balance_text = $account_balance;

		if( !in_array($id_account, $attached_acc_array) )
			$rest_acc_total_balance += $account_balance;
	}
	else
	{
		$account_balance = 0;
		$account_balance_text = "&nbsp;";
	}

	// выравнивание остатков счетов - рекомендации
	$diff_round = ceil($diff/10) * 10;
	if( $diff < 0 )
		$diff_add = -$account_balance;
	else
		$diff_add = $diff_round - $account_balance;
	$diff_add_total += $diff_add;
	
	if( $diff_add < 0 )
		$diff_add = "взять (-) ".(-$diff_add);
	else
	{
		if( !$diff_add )
			$diff_add = "";
		else
			$diff_add = "доб. (+) ".$diff_add;
	}

	if( $subcat_count > 1 ) // есть листы под2категорий
	{
		$budget_field_mod = " disabled";
		$edit_btn = "";
	}
	else
	{
		$budget_field_mod = "";
		$edit_btn = "<input type=submit value=\"=\">";		
	}

	if( isset($planned_expenses_sum_cat[$id_exp_cat]) && $planned_expenses_sum_cat[$id_exp_cat]>0 )
		$cat_pexp_exists = true;
	else
		$cat_pexp_exists = false;	 

	if( $subcat_count > 1 || $cat_pexp_exists )
		$columns_modifiers = $col_mods_cats_w_sub;
	else
		$columns_modifiers = $col_mods_cats_wo_sub;

	$cells = array(
		$cat_num.".",
		$category_name,
		"<input ".$budget_field_mod." type=text maxlenth=6 size=6 name=budget_value value=\"".$budget_value."\">",
		"<a href=\"transactions.php?trans_exp=on&first_year=".$year."&first_month=".$month."&id_cat=".$id_exp_cat."\">".get_formatted_amount($expense_value)."</a>",
		$procent_string,
		get_formatted_amount($diff));

	$account_balance_added = false;
	foreach( $attached_acc_array as $temp2 => $temp) {
		if( $id_account == $temp ) {
			$cells[] = $account_balance_text;
			$account_balance_added = true;
		}
		elseif( !$temp && !$account_balance_added )
			$cells[] = $account_balance_text;
		else
			$cells[] = "&nbsp;";
	}
		
	$cells[] = $diff_add;
	$cells[] = $edit_btn;

	$columns_styles = array();

	if( $cat_pexp_exists && $planned_expenses_sum_cat[$id_exp_cat] > $budget_value )
		$columns_styles[2] = "background-color:".$warn_color."; ";	// warning

	if( $month == $month_current )
	{
		if( $procent < $month_elapsed_procent )
			$columns_styles[4] = "background-color:".$good_color."; ";	// good
		else
			$columns_styles[4] = "background-color:".$bad_color."; ";	// bad
	}
	else
	{
		if( $procent <= 100 )
			$columns_styles[4] = "background-color:".$good_color."; ";	// good
		else
			$columns_styles[4] = "background-color:".$bad_color."; ";	// bad
	}
	
	if( $diff >= 0 )
		$columns_styles[5] = "background-color:".$good_color."; ";	// good
	else
		$columns_styles[5] = "background-color:".$bad_color."; ";	// bad

	echo "<form name=\"form\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
	echo "<input type=hidden name=\"year\" value=\"".$year."\">\r\n";
	echo "<input type=hidden name=\"month\" value=\"".$month."\">\r\n";
	echo "<input type=hidden name=\"id_exp_subcat\" value=\"".key($subcat_expenses)."\">\r\n";
	echo "<input type=hidden name=action value=\"set_budget_value\">\r\n";
	echo get_table_row($cells, "list", TXT_NONE, "", $columns_modifiers, $columns_styles);
	echo "</form>\r\n";

	// работа с подкатегориями
	$subcat_num = 0;	
	foreach( $subcat_expenses as $id_exp_subcat => $subcat_expense )
	{
		$subcat_num++;

		if( isset($planned_expenses[$id_exp_cat][$id_exp_subcat]) && count($planned_expenses[$id_exp_cat][$id_exp_subcat]) )
			$subcat_pexp_exists = true;
		else
			$subcat_pexp_exists = false;

		if( $subcat_count > 1 )
		{
			$subcat_name = $subcategory_names[$id_exp_subcat];
			$subcat_expense = -$subcat_expense;
			$subcat_budget = $budgets[$id_exp_cat][$id_exp_subcat];
			$diff = round($subcat_budget - $subcat_expense, 2);
	
			if( $subcat_budget )
			{
				$procent = round($subcat_expense/$subcat_budget*100, 2);
				$procent_string = $procent."%";
			}
			else
			{
				$procent = 0.0;
				$procent_string = "";
			}			
	
			if( $diff >= 0 )
				$cell_color = $good_color;	// good
			else
				$cell_color = $bad_color;	// bad
	
			if( $subcat_num%2 )
				$row_color = "background-color:".$even_row_color.";";
			else
				$row_color = "background-color:".$odd_row_color.";";

			if( $subcat_num == $subcat_count && !$subcat_pexp_exists )
				$columns_modifiers = $col_mods_subcat_last;
			else
				$columns_modifiers = $col_mods_subcat;

			$cells = array(
				"&nbsp;",
				$subcat_name,
				"<input type=text maxlenth=6 size=6 name=budget_value value=\"".$subcat_budget."\">",
				"<a href=\"transactions.php?trans_exp=on&first_year=".$year."&first_month=".$month."&id_cat=".$id_exp_cat."&id_subcat=".$id_exp_subcat."\">".get_formatted_amount($subcat_expense)."</a>",
				$procent_string,
				get_formatted_amount($diff));
			for($temp=0; $temp<$attached_acc_count;$temp++)
				$cells[] = "&nbsp;";
			$cells[] = "&nbsp;";
			$cells[] = "<input type=submit value=\"=\">";

			$columns_styles = array();
		
			if( $subcat_pexp_exists && $planned_expenses_sum_subcat[$id_exp_subcat] > $subcat_budget )
				$columns_styles[2] = "background-color:".$warn_color."; ";	// warning

			if( $month == $month_current )
			{
				if( $procent < $month_elapsed_procent )
					$columns_styles[4] = "background-color:".$good_color."; ";	// good
				else
					$columns_styles[4] = "background-color:".$bad_color."; ";	// bad
			}
			else
			{
				if( $procent <= 100 )
					$columns_styles[4] = "background-color:".$good_color."; ";	// good
				else
					$columns_styles[4] = "background-color:".$bad_color."; ";	// bad
			}
			
			if( $diff >= 0 )
				$columns_styles[5] = "background-color:".$good_color."; ";	// good
			else
				$columns_styles[5] = "background-color:".$bad_color."; ";	// bad
	
			echo "<form name=\"form\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
			echo "<input type=hidden name=\"year\" value=\"".$year."\">\r\n";
			echo "<input type=hidden name=\"month\" value=\"".$month."\">\r\n";
			echo "<input type=hidden name=\"id_exp_subcat\" value=\"".$id_exp_subcat."\">\r\n";
			echo "<input type=hidden name=action value=\"set_budget_value\">\r\n";
			echo get_table_row($cells, "list", TXT_NONE, $row_color, $columns_modifiers, $columns_styles);
			echo "</form>\r\n";
		}

		// работа с запланированными покупками
		if( $subcat_pexp_exists )
		{
			$pexp_count = count($planned_expenses[$id_exp_cat][$id_exp_subcat]);
			$pexp_num = 0;		
			foreach( $planned_expenses[$id_exp_cat][$id_exp_subcat] as $temp => $pexp_array )
			{
				$pexp_num++;
				$pexp_value = $pexp_array[0];
				$pexp_comment = $pexp_array[1];

				if( $pexp_num == $pexp_count && $subcat_num == $subcat_count )
					$columns_modifiers = $col_mods_pexp_last;
				else
					$columns_modifiers = $col_mods_pexp;
	
				$cells = array(
					"&nbsp;",
					"&nbsp;",
					"<a href=planned_expenses.php?year=".$year."&month=".$month."#id_subcat_".$id_exp_subcat.">".$pexp_value."</a>",
					$pexp_comment."</a>",
					"",
					"");
				for($temp=0; $temp<$attached_acc_count;$temp++)
					$cells[] = "&nbsp;";
				$cells[] = "&nbsp;";
				$cells[] = "&nbsp;";
				echo get_table_row($cells, "list", TXT_NONE, $row_color, $columns_modifiers, array(), array(3=>5));
			}
		} 

	} // foreach( $subcat_expenses as $id_exp_subcat => $subcat_expense )
} // foreach( $expenses as $id_exp_cat => $subcat_expenses )

// totals
	
$diff_total = $budget_total - $expenses_total;

if( $budget_total )
{
	$procent = round($expenses_total/$budget_total*100, 2);
	$procent_string = $procent."%";
}
else
{
	$procent = 0.0;
	$procent_string = "";
}

if( $month == $month_current )
{
	if( $procent < $month_elapsed_procent )
		$cell_color = $good_color;	// good
	else
		$cell_color = $bad_color;	// bad
}
else
{
	if( $procent <= 100 )
		$cell_color = $good_color;	// good
	else
		$cell_color = $bad_color;	// bad
}

if( $diff_total >= 0 )
	$cell_color2 = $good_color;	// good
else
	$cell_color2 = $bad_color;	// bad

if( $diff_add_total <= 0 )
	$diff_add_total = "&nbsp;";
else
	$diff_add_total = "(не хватает) ".$diff_add_total;
 
echo "<tr class=list>\r\n";
echo "<th class=list colspan=10></th>\r\n";
echo "</tr>\r\n";
//get_formatted_amount(

$columns_modifiers = array(
TXT_NONE, 
TXT_ALIGN_RIGHT, 
TXT_ALIGN_CENTER|CELL_LINE_LEFT|CELL_LINE_TOP|CELL_LINE_RIGHT, 
TXT_ALIGN_CENTER|CELL_LINE_TOP|CELL_LINE_RIGHT, 
TXT_ALIGN_CENTER|CELL_LINE_TOP|CELL_LINE_RIGHT, 
TXT_ALIGN_CENTER|CELL_LINE_TOP|CELL_LINE_RIGHT, 
TXT_ALIGN_CENTER|CELL_LINE_TOP|CELL_LINE_RIGHT,
TXT_NONE 
);

$columns_modifiers = expand_array($columns_modifiers, 6, TXT_ALIGN_CENTER|CELL_LINE_TOP|CELL_LINE_RIGHT, $attached_acc_count);

$cells = array(
	"&nbsp;",
	"<b>Итого</b>",
	$budget_total,
	$expenses_total,
	$procent_string,
	$diff_total);
for($temp=1; $temp<$attached_acc_count;$temp++)
	$cells[] = "&nbsp;";
$cells[] = $rest_acc_total_balance;
$cells[] = $diff_add_total;
$cells[] = "&nbsp;";

$columns_styles = array();
$columns_styles[4] = "background-color:".$cell_color.";";
$columns_styles[5] = "background-color:".$cell_color2.";";

echo get_table_row($cells, "list", TXT_NONE, "", $columns_modifiers, $columns_styles);

echo get_table_end();

// день месяца
if( $month == $month_current )
{
	echo "<div align=center>\r\n";
	echo "Текущий месяц закончен на <b>".$month_elapsed_procent."%</b>\r\n";
	echo "</div>\r\n";
}

// 7. Вывод статистики на прошлый месяц
?>
<hr width=50%>
<table class=list>
<tr class=list id=bottom_line><th class=list colspan=2>Данные прошлого месяца (<b><?php echo $monthes[$month_prev]; ?></b>)</th></tr>
<?php
// 7.1 запрос бюджета
$query = "SELECT sum(value) FROM budget_monthly WHERE year=".$year_prev." AND month=".$month_prev;
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса бюджета за прошлый месяц. Экстренный выход."; exit; }
$temp_array = db_fetch_num_array($res);
$budget_prev = (float)$temp_array[0];
echo "<tr class=list id=bottom_line><td class=list>Планируемые расходы: </td><td class=list><div id=center>".$budget_prev."</div></td></tr>\r\n";
// (end) 7.1 запрос бюджета

// 7.2 запрос реальных затрат
$query  = "SELECT sum(value) FROM transactions ";
$query .= "WHERE id_transfer=0 AND value<0 ";
$query .= "AND year(date)=".$year_prev." AND month(date)=".$month_prev;
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка подсчета расходов за прошлый месяц. Экстренный выход."; exit; }
$temp_array = db_fetch_num_array($res);
$expenses_prev = -(float)$temp_array[0];
echo "<tr class=list id=bottom_line><td class=list>Реальные расходы</td><td class=list><div id=center>".$expenses_prev."</div></td></tr>\r\n";
// (end) 7.2 запрос реальных затрат

// 7.4 остаток
$diff_prev = $budget_prev - $expenses_prev;
echo "<tr class=list id=bottom_line><td class=list>Остаток</td><td class=list><div id=center>".$diff_prev."</div></td></tr>\r\n";

// 7.4 запрос доходов
$query  = "SELECT sum(value) FROM transactions ";
$query .= "WHERE id_transfer=0 AND value>0 ";
$query .= "AND year(date)=".$year_prev." AND month(date)=".$month_prev;
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка подсчета доходов за прошлый месяц. Экстренный выход."; exit; }
$temp_array = db_fetch_num_array($res);
$incomes_prev = (float)$temp_array[0];
echo "<tr class=list id=bottom_line><td class=list>Доходы</td><td class=list><div id=center>".$incomes_prev."</div></td></tr>\r\n";
// (end) 7.4 запрос доходов

// 7.5 можно потратить в этом месяце
if( $diff_prev > 0 )
	$incomes_to_outlay = $incomes_prev;
else
{
	if( !$budget_prev )	// нет бюджета в прошлом месяце - считаем, что потратили сколько нужно
		$incomes_to_outlay = $incomes_prev;
	else
		$incomes_to_outlay = $incomes_prev + $diff_prev;
}
$incomes_to_outlay = (floor($incomes_to_outlay/500)*500);
echo "<tr class=list id=bottom_line><td class=list>Рекомендуемая сумма затрат на текущий месяц</td><td class=list><div id=center>".$incomes_to_outlay."</td></tr>\r\n";

$incomes_diff = $incomes_to_outlay - $budget_total;
echo "<tr class=list id=bottom_line><td class=list>Остаток от рекомендуемой суммы затрат</td><td class=list><div id=center>".$incomes_diff."</div></td></tr>\r\n";

?>
</table>
<?php
// 8. Конец скрипта.
include("footer.php");
?>
