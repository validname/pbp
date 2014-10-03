<?
require_once("functions/config.php");
require_once("functions/auth.php");
require_once("functions/func_finance.php");
require_once("functions/func_web.php");
require_once("functions/func_time.php");
require_once("functions/func_table.php");

$doc_title = "Остатки на счетах";
//$doc_onLoad = "set_mode();";
include("header.php");
//print_header_menu();

// 1. флаги ошибок

// 2. подготовка переменных

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$action = web_get_request_value($_REQUEST, "action", 's');

// 2.2 значения по умолчанию для переменных формы

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

// 3.1 предзапрос categories
$categories_array = array();
$cat_accounts_array = array();
$query = "SELECT id_exp_cat, name, id_account FROM expense_cats";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса категорий. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) ) {
	$id_exp_cat = (int)$temp_array[0];
	$categories_array[$id_exp_cat] = db_strip_slashes($temp_array[1]);
	$cat_accounts_array[(int)$temp_array[2]][] = $id_exp_cat;
}
// (end) 3.1 предзапрос categories

// 3.2 запрос списков счетов
$account_list_array = array();
$query = "SELECT id_acc_list, name FROM account_lists ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса списков счетов. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$account_list_array[(int)$temp_array[0]] = db_strip_slashes($temp_array[1]);

// 3.3 запрос счетов
$debt_account_id = 0;
$debt_account_name = "";
$debt_account_balance = 0;

$accounts_array = array();
$account_ids_array = array();
$query = "SELECT id_account, name, start_value, comment, is_debt, id_acc_list FROM accounts ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса счетов. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
{
	$accounts_array[(int)$temp_array[5]][(int)$temp_array[0]] = array(db_strip_slashes($temp_array[1]), (double)$temp_array[2], db_strip_slashes($temp_array[3]), (int)$temp_array[5]);
	$account_ids_array[] = (int)$temp_array[0];
	if( (int)$temp_array[4]==1 )
	{
		$debt_account_id = (int)$temp_array[0];
		$debt_account_name = db_strip_slashes($temp_array[1]);
	}
}
// (end) 3.3 запрос счетов

// 3.4 запрос баланса счетов
$query = "SELECT id_account, sum(value) FROM transactions GROUP BY id_account";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса счетов. Экстренный выход."; exit; }
while( $temp_array=db_fetch_num_array($res) )
	$account_balance_array[(int)$temp_array[0]] = (double)$temp_array[1];

if( $debt_account_id )
{
		if( isset($account_balance_array[$debt_account_id]) )
			$debt_account_balance = $account_balance_array[$debt_account_id];
}

foreach( $account_ids_array as $temp => $id_account )
{
	if( !isset($account_balance_array[$id_account]) )
		$account_balance_array[$id_account] = 0;
}
// (end) 3.4 запрос баланса счетов

// 4. Отработка действий в формой
// Если форма сработала
if( $action )
{
	$action = strtolower($action);
//	echo "action: ".$action."<hr>";

	// 4.1 проверка переданных полей формы
	if( $action=='set_budget_value' )
	{
	}
	// 4.2 работа с БД, если поля переданы нормально
	if( $updating_invalid_value )
	{
	}
	else
	{// if( !$errors )
	}
	if( !$updating_invalid_value )
	{
	// 4.2.2 - нет ошибок, заносим в БД
	}// if( !$errors )
} // if( $action )
else
{ // значения по умолчанию
}

// 5. Заполнение формы


// 6. Вывод формы
?>
<script language=Javascript>

function reset_checkboxes()
{
	var f = document.tb_form;
	var all_true = true;
	var all_false = true;
	var set = false;

	// check states
	for(var i = 0; i < f.length; i++)
	{
		var e = f.elements[i];
    if( e.type == "checkbox" )
    {
			if( e.checked == true )
				all_false = false;
			else
				all_true = false;
		}
  }

  if( all_false == true )	// set checkboxes if empty
		set = true;
	else
		set = false; 

	//setting  
	for(var i = 0; i < f.length; i++)
	{
		var e = f.elements[i];
    if( e.type == "checkbox" )
			e.checked = set;
  }
  document.th_form.elements[0].checked = set;
  calc_sum();
}

function calc_sum()
{
	var f = document.tb_form;
	var sum = 0.0; 
	for(var i = 0; i < f.length; i++)
	{
		var e = f.elements[i];
    if( e.type == "checkbox" && e.checked == true )
			sum += parseFloat(e.value)*100;
  }
  //округление
  f.sum.value = sum/100;
}

</script>
<?

$checkbox = "<input type=checkbox name=\"reset\" onclick='reset_checkboxes();'>";

echo get_table_start("list");
echo "<form name=th_form>";
echo get_table_header(array($checkbox, "Счёт", "Остаток", "Связанная категория", "Комментарий"), "grid");
echo "</form>"; 
echo "<form name=tb_form>";
// вывод
$col_mods = array(
TXT_ALIGN_CENTER,
TXT_ALIGN_RIGHT,
TXT_ALIGN_RIGHT,
TXT_ALIGN_LEFT,
TXT_ALIGN_LEFT,
);

$col_list_mods = array(
TXT_ALIGN_CENTER,
TXT_ALIGN_LEFT|TXT_BOLD,
TXT_ALIGN_LEFT|TXT_BOLD,
TXT_ALIGN_LEFT,
TXT_ALIGN_CENTER,
);

$col_total_mods = array(
TXT_ALIGN_CENTER,
TXT_ALIGN_RIGHT|TXT_BOLD|TXT_BIG,
TXT_ALIGN_CENTER|TXT_BOLD,
TXT_ALIGN_LEFT,
TXT_ALIGN_CENTER,
);


$num = 0;
$total_balance = 0;

foreach( $accounts_array as $id_acc_list => $accounts_temp_array )
{
	$acc_list_name = $account_list_array[$id_acc_list];

	$acc_list_balance = 0;
	foreach( $accounts_temp_array as $id_account => $temp_array )
	{
		$account_balance = $account_balance_array[$id_account];
		$account_balance = round($account_balance + $temp_array[1], 2);	// start balance ("offset")
		$account_balance_array[$id_account] = $account_balance;  
		$acc_list_balance += $account_balance;
		$total_balance += $account_balance; 
	}
	$cells = array("", $acc_list_name,get_formatted_amount($acc_list_balance),	"", "");
	echo get_table_row($cells, "grid", TXT_NONE, "", $col_list_mods);				
	
	foreach( $accounts_temp_array as $id_account => $temp_array )
	{
		$num++;
	
		$name = $temp_array[0];
		$account_balance = $account_balance_array[$id_account];
	
		$category_name = "";
		if( isset($cat_accounts_array[$id_account])) {
			foreach( $cat_accounts_array[$id_account] as $temp => $id_exp_cat ) {
				if( $category_name )
					$category_name .= "<br>";
				$category_name .= $categories_array[$id_exp_cat];
			}
		}
		$comment = $temp_array[2];
		if( !$comment )
			$comment = "&nbsp;"; 
		
		$checkbox = "<input type=checkbox name=".$id_account." value=\"".$account_balance."\" onclick='calc_sum();' ".(($id_account<>$debt_account_id)? "checked": "").">";
		$cells = array($checkbox, $name, get_formatted_amount($account_balance),	$category_name, $comment);
		echo get_table_row($cells, "grid", TXT_NONE, "", $col_mods);				
		
	} //foreach( $accounts_array as $id_account => $temp_array )
} // foreach( $account_list_array as $id_acc_list => $temp_array )

$cells = array("", "Итого",get_formatted_amount($total_balance), "", "");
echo get_table_row($cells, "grid", TXT_NONE, "", $col_total_mods);				

$cells = array("", "Сумма", "<input type=text maxlength=10 size=10 name=sum value=\"".($total_balance-$debt_account_balance)."\">", "", "");
echo get_table_row($cells, "list", TXT_NONE, "", $col_list_mods);				

echo get_table_end(); 
echo "</form>";

// 7. Конец скрипта.
include("footer.php");
?>
