<?
require_once('functions/config.php');
require_once('functions/auth.php');
require_once('functions/func_db.php');
require_once('functions/func_web.php');
require_once("functions/func_time.php");
require_once("functions/func_table.php");
require_once("functions/func_profiler.php");

$doc_title = "Транзакции";
//$doc_onLoad = "set_mode();";
include("header.php");

// 1. флаги ошибок
$updating_invalid_value = 0;
$updating_error = 0;
$error_text = "";

// 2. подготовка переменных

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$action = web_get_request_value($_REQUEST, "action", 's');

$sort_field = web_get_request_value($_REQUEST, "sort_field", 's');
$sort_dsc = web_get_request_value($_REQUEST, "sort_dsc", 'b');
$first_day = web_get_request_value($_REQUEST, "first_day", 'i');
$first_month = web_get_request_value($_REQUEST, "first_month", 'i');
$first_year = web_get_request_value($_REQUEST, "first_year", 'i');
$last_day = web_get_request_value($_REQUEST, "last_day", 'i');
$last_month = web_get_request_value($_REQUEST, "last_month", 'i');
$last_year = web_get_request_value($_REQUEST, "last_year", 'i');
$trans_exp = web_get_checkbox_value($_REQUEST, "trans_exp");
$trans_inc = web_get_checkbox_value($_REQUEST, "trans_inc");
$trans_acc = web_get_checkbox_value($_REQUEST, "trans_acc");
$filter_id_account1 = web_get_request_value($_REQUEST, "id_account1", 'i');
$filter_id_account2 = web_get_request_value($_REQUEST, "id_account2", 'i');
$filter_id_cat = web_get_request_value($_REQUEST, "id_cat", 'i');
$filter_id_subcat = web_get_request_value($_REQUEST, "id_subcat", 'i');
$filter_id_subcat2 = web_get_request_value($_REQUEST, "id_subcat2", 'i');

/*
$form_var_array = $_REQUEST;
unset($form_var_array['action']);

// ищем	переданные id
$id_draft_array = array();
foreach( $form_var_array as $key => $value )
{
	if( strtolower($value) != "on" ) continue;
	
	if( preg_match("/^id_draft_([0-9]+)$/", $key, $matches) )
		$id_draft_array[] = $matches[1]; 	
}
*/

// 2.2 значения по умолчанию для переменных формы
switch( $sort_field )
{
	case "date":			break;
	case "account":		break;
	case "cat":				break;
	case "subcat":		break;
	case "subcat2":		break;
	case "quantity":	break;
	case "value":			break;
	case "comment":		break;
	case "":
	default:	$sort_field =  "date";
						break;
}

if( !$first_month || $first_month > 12 )
	$first_month = (int)(date("m"));
if( !$first_year )
	$first_year = (int)(date("Y"));
if( !$first_day )
	$first_day = 1;	// первое число
$temp = date("d", mktime(0,0,0,$first_month+1, 0, $first_year)); // последнее число месяца
if( $first_day > $temp )
	$first_day = (int)($temp);	// последнее число
if( !$last_month )
	$last_month = $first_month; // месяц тот же, что и начальный 
if( !$last_year )
	$last_year = $first_year; // год тот же, что и начальный
if( !$last_day )
	$last_day = (int)(date("d",mktime(0,0,0,$last_month+1, 0, $last_year))); // последнее число
	
if( $last_year <= $first_year )
{
	$last_year = $first_year;
	if( $last_month <= $first_month )
	{
		$last_month = $first_month;
		if( $last_day < $first_day )
			$last_day = $first_day;
	}
}

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

$no_choice_string = "-------------------------------------------------------------";

// 3.1 массивы дат
$days = get_day_array();
$days[0] = "";
$monthes = get_month_array();
$monthes[0] = "";
$years = get_year_array();
$years[0] = "";

// 3.2 запрос счетов
$accounts = array();
if( $trans_exp || $trans_inc || $trans_acc )
{
	$query  = "SELECT id_account, name FROM accounts ORDER BY name";
	$res = db_query($query);
	if( !$res )
		{ echo "<hr>Ошибка запроса счетов. Экстренный выход."; exit; }
	while( $temp_array = db_fetch_num_array($res) )
		$accounts[(int)($temp_array[0])] = db_strip_slashes($temp_array[1]);
	$accounts = array(0 =>$no_choice_string ) + $accounts;
}

// 3.3 запрос категорий и подкатегорий 
$cat_names = array();
$subcat_names = array();
$subcat2_names = array();
$categories  = array();

if( $trans_exp xor $trans_inc )
{
 	if( $trans_exp )
	{
		$query  = "SELECT c.id_exp_cat, c.name, sc.id_exp_subcat, sc.name, id_exp_subcat2, sc2.name FROM expense_subcats2 AS sc2 ";
		$query .= "INNER JOIN expense_subcats AS sc ON sc.id_exp_subcat=sc2.id_exp_subcat ";
		$query .= "INNER JOIN expense_cats AS c ON sc.id_exp_cat=c.id_exp_cat ";
		$query .= "ORDER BY c.name, sc.name, sc2.name";
	}
	else
	{
		$query  = "SELECT c.id_inc_cat, c.name, sc.id_inc_subcat, sc.name, id_inc_subcat2, sc2.name FROM income_subcats2 AS sc2 ";
		$query .= "INNER JOIN income_subcats AS sc ON sc.id_inc_subcat=sc2.id_inc_subcat ";
		$query .= "INNER JOIN income_cats AS c ON sc.id_inc_cat=c.id_inc_cat ";
		$query .= "ORDER BY c.name, sc.name, sc2.name";
	}	
	$res = db_query($query);
	if( !$res )
		{ echo "<hr>Ошибка запроса внутренних категорий. Экстренный выход."; exit; }
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
	foreach( $subcat_names as $id_cat => $temp_array )
		$subcat_names[$id_cat] = array(0 => $no_choice_string) + $temp_array;
	foreach( $subcat2_names as $id_subcat => $temp_array )
		$subcat2_names[$id_subcat] = array(0 => $no_choice_string) + $temp_array;
	$cat_names = array(0 => $no_choice_string) + $cat_names;
	$categories[0] = array(0, 0);
}
// Javascript
echo "<script language=Javascript>\r\n";
echo "function set_subcat_list(form)\r\n";
echo "{\r\n";
echo "	form.id_subcat.length=1;\r\n";
echo "	form.id_subcat.options[0].value=0;\r\n";
echo "	form.id_subcat.options[0].text=\"".$no_choice_string."\";\r\n";
echo "	form.id_subcat2.length=1;\r\n";
echo "	form.id_subcat2.options[0].value=0;\r\n";
echo "	form.id_subcat2.options[0].text=\"".$no_choice_string."\";\r\n";
echo "	switch(form.id_cat.value)\r\n";
echo "	{\r\n\r\n";
foreach( $subcat_names as $id_cat => $temp_array)
{
	echo "		case '".$id_cat."':\r\n";
	echo "			form.id_subcat.length=".count($temp_array).";\r\n";
	
	$num = 0;
	foreach( $temp_array as $id_subcat => $subcat_name )
	{
		echo "			form.id_subcat.options[".$num."].value=".$id_subcat.";\r\n";
		echo "			form.id_subcat.options[".$num."].text=\"".$subcat_name."\";\r\n";
		$num++;
	}
	echo "			break;\r\n";
}
echo "\r\n	}\r\n";
echo "	return true;\r\n";
echo "}\r\n\r\n";
echo "function set_subcat2_list(form)\r\n";
echo "{\r\n";
echo "	form.id_subcat2.length=0;\r\n";
echo "	switch(form.id_subcat.value)\r\n";
echo "	{\r\n";
foreach( $subcat2_names as $id_subcat => $temp_array)
{
	echo "		case '".$id_subcat."':\r\n";
	echo "			form.id_subcat2.length=".count($temp_array).";\r\n";
	
	$num = 0;
	foreach( $temp_array as $id_subcat2 => $subcat2_name )
	{
		echo "			form.id_subcat2.options[".$num."].value=".$id_subcat2.";\r\n";
		echo "			form.id_subcat2.options[".$num."].text=\"".$subcat2_name."\";\r\n";
		$num++;
	}
	echo "			break;\r\n";
}
echo "	}\r\n";
echo "	return true;\r\n";
echo "}\r\n";
echo "</script>\r\n";

// 4. Отработка действий в формой
// Если форма сработала
if( $action )
{
	$updating_invalid_value = 1;
	$error_text = "Неизвестное действие";
	$action = strtolower($action);
//	echo "action: ".$action."<hr>";

	// 4.1 проверка переданных полей формы
	if( $action=='import111' )
	{
		foreach( $id_draft_array as $temp => $id_draft )
		{
			// берем данные из import_draft
			$query  = "SELECT id_draft,DATE_FORMAT(date, \"%Y-%m-%d\") as date_f,account,cat,subcat,subcat2,quantity,value,comment, change_type, id_transaction FROM import_draft ";
			$query .= "WHERE id_draft=".$id_draft." ORDER BY date";
			$res = db_query($query);
			if( !$res )
				{ echo "<hr>Ошибка запроса импортируемых данных - 2. Экстренный выход."; exit; }
	
			$temp_array = db_fetch_assoc_array($res);
			if( !$temp_array )	continue;	// по-хорошему, такого не должно быть, но если кто-то поменял базу или подменял массив id_draft - то запросто
			$id_draft = (int)($temp_array['id_draft']);
			$date = $temp_array['date_f'];
			$account = db_strip_slashes($temp_array['account']);
			$cat = db_strip_slashes($temp_array['cat']);
			$subcat = db_strip_slashes($temp_array['subcat']);
			$subcat2 = db_strip_slashes($temp_array['subcat2']);
			$quantity = (int)($temp_array['quantity']);
			$value = (float)($temp_array['value']);
			$comment = db_strip_slashes($temp_array['comment']);
			$id_transaction = (int)($temp_array['id_transaction']);

			// мэппинг		
			$id_account = $map_acc_ids[$account];
			$id_subcat2 = $map_subcat2_ids[$cat][$subcat][$subcat2];

			switch( $temp_array['change_type'])
			{
				case IMPORT_CHANGE_DEL: // delete from transactions
					$query  = "DELETE FROM transactions WHERE id_transaction=".$id_transaction;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления имеющихся данных. Экстренный выход."; exit; }
					$query  = "DELETE FROM import_draft WHERE id_draft=".$id_draft;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления импортируемых данных - 1. Экстренный выход."; exit; }
					break;
				case IMPORT_CHANGE_CHG: // change transactions
					$query  = "UPDATE transactions SET ";
					$query  .= " id_subcat2=".$id_subcat2.", ";
					$query  .= " id_account=".$id_account.", ";
					$query  .= " date='".$date."', ";
					$query  .= " quantity=".$quantity.", ";
					$query  .= " value='".(-$value)."', "; // minus for expenses
					$query  .= " comment='".db_add_slashes($comment)."' ";
					$query  .= " WHERE id_transaction=".$id_transaction;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления имеющихся данных. Экстренный выход."; exit; }
					$query  = "DELETE FROM import_draft WHERE id_draft=".$id_draft;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления импортируемых данных - 2. Экстренный выход."; exit; }
					break;
				case IMPORT_CHANGE_ADD:  // add to transactions
				default:
					$query  = "INSERT INTO transactions(id_subcat2, id_account, date, quantity, value, comment) VALUES (";
					$query  .= $id_subcat2.", ".$id_account.", '".$date."', ".$quantity.", '".(-$value)."', "; // minus for expenses
					$query  .= "'".db_add_slashes($comment)."') ";
					$res2 = db_query($query);
					$query  = "DELETE FROM import_draft WHERE id_draft=".$id_draft;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления импортируемых данных - 3. Экстренный выход."; exit; }
					break;
			} 			
		}
	}// if( $action=='import' )
} // if( $action )
else
{ //if( !$action )
	// форма грузится первый раз
} //if( !$action )

// 5. Заполнение формы
$query_order = "";
switch( $sort_field )
{
	case "date":			$query_order = "date";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", account, cat, subcat, subcat2, quantity, value, comment"; break;
	case "account":		$query_order = "account";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, cat, subcat, subcat2, quantity, value, comment";	break;
	case "cat":				$query_order = "cat";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, subcat, subcat2, quantity, value, comment"; break;
	case "subcat":		$query_order = "subcat";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, cat, subcat2, quantity, value, comment"; break;
	case "subcat2":		$query_order = "subcat2";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, cat, subcat, quantity, value, comment"; break;
	case "quantity":	$query_order = "quantity";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, cat, subcat, subcat2, value, comment"; break;
	case "value":			$query_order = "value";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, cat, subcat, subcat2, quantity, comment"; break;
	case "comment":		$query_order = "comment";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, cat, subcat, subcat2, quantity, value"; break;
}

$query_date_from = $first_year."-".$first_month."-".$first_day;
$query_date_to = $last_year."-".$last_month."-".$last_day;
$query = "";
if( $trans_exp )
{
	$query .= "(SELECT t.id_transaction, t.id_subcat2, t.id_account, t.id_transfer, DATE_FORMAT(t.date, \"".$sql_date_format."\") as date_f, ";
	$query .= " t.date, t.quantity, t.value, t.comment, t.is_rare, "; 
	$query .= " a.name as account, exp_c.name as cat, exp_sc.name as subcat, exp_sc2.name as subcat2, ".TRANS_TYPE_EXP." as trans_type ";
	$query .= " FROM transactions AS t ";
	$query .= " INNER JOIN accounts AS a ON a.id_account=t.id_account ";
	$query .= " INNER JOIN expense_subcats2 AS exp_sc2 ON exp_sc2.id_exp_subcat2=t.id_subcat2 ";
	$query .= " INNER JOIN expense_subcats AS exp_sc ON exp_sc.id_exp_subcat=exp_sc2.id_exp_subcat ";
	$query .= " INNER JOIN expense_cats AS exp_c ON exp_c.id_exp_cat=exp_sc.id_exp_cat ";
	$query .= " WHERE t.date>='".$query_date_from."' AND t.date<='".$query_date_to."' ";
	if( $filter_id_account1 )
		$query .= " AND a.id_account=".$filter_id_account1." ";
	if( $filter_id_cat )
		$query .= " AND exp_c.id_exp_cat=".$filter_id_cat." ";
	if( $filter_id_subcat )
		$query .= " AND exp_sc.id_exp_subcat=".$filter_id_subcat." ";
	if( $filter_id_subcat2 )
		$query .= " AND exp_sc2.id_exp_subcat2=".$filter_id_subcat2." ";
	$query .= " AND value<0 AND id_transfer=0 ) ";	// расходы
}
if( $trans_exp && $trans_inc )
		$query .= " UNION ";
if( $trans_inc )
{
	$query .= " (SELECT t.id_transaction, t.id_subcat2, t.id_account, t.id_transfer, DATE_FORMAT(t.date, \"".$sql_date_format."\") as date_f, ";
	$query .= " t.date, t.quantity, t.value, t.comment, t.is_rare, "; 
	$query .= " a.name as account, inc_c.name as cat, inc_sc.name as subcat, inc_sc2.name as subcat2, ".TRANS_TYPE_INC." as trans_type ";
	$query .= " FROM transactions AS t ";
	$query .= " INNER JOIN accounts AS a ON a.id_account=t.id_account ";
	$query .= " INNER JOIN income_subcats2 AS inc_sc2 ON inc_sc2.id_inc_subcat2=t.id_subcat2 ";
	$query .= " INNER JOIN income_subcats AS inc_sc ON inc_sc.id_inc_subcat=inc_sc2.id_inc_subcat ";
	$query .= " INNER JOIN income_cats AS inc_c ON inc_c.id_inc_cat=inc_sc.id_inc_cat ";
	$query .= " WHERE t.date>='".$query_date_from."' AND t.date<='".$query_date_to."' "; 
	if( $filter_id_account1 )
		$query .= " AND a.id_account=".$filter_id_account1." ";
	if( $filter_id_cat )
		$query .= " AND inc_c.id_inc_cat=".$filter_id_cat." ";
	if( $filter_id_subcat )
		$query .= " AND inc_sc.id_inc_subcat=".$filter_id_subcat." ";
	if( $filter_id_subcat2 )
		$query .= " AND inc_sc2.id_inc_subcat2=".$filter_id_subcat2." ";
	$query .= " AND value>0 AND id_transfer=0 ) ";	// доходы
}
if( ($trans_exp || $trans_inc) && $trans_acc )
		$query .= " UNION ";
if( $trans_acc )
{
	$query .= "(SELECT t1.id_transaction, t2.id_account as id_subcat2, t1.id_account, t1.id_transfer, DATE_FORMAT(t1.date, \"".$sql_date_format."\") as date_f, ";
	$query .= " t1.date, '' as quantity, t2.value, t1.comment, t1.is_rare, "; 
	$query .= " a1.name as account, a2.name as cat, '' as subcat, '' as subcat2, ".TRANS_TYPE_ACC." as trans_type ";
	$query .= " FROM transactions AS t1 ";
	$query .= " INNER JOIN transactions AS t2 ON t1.id_transfer=t2.id_transaction ";
	$query .= " INNER JOIN accounts AS a1 ON a1.id_account=t1.id_account ";
	$query .= " INNER JOIN accounts AS a2 ON a2.id_account=t2.id_account ";
	$query .= " WHERE t1.date>='".$query_date_from."' AND t1.date<='".$query_date_to."' ";
	if( $trans_exp || $trans_inc )
	{
		if( $filter_id_account1 )
			$query .= " AND (t1.id_account=".$filter_id_account1." OR t2.id_account=".$filter_id_account1.") ";
	}
	else
	{
		if( $filter_id_account1 )
			$query .= " AND t1.id_account=".$filter_id_account1." ";
		if( $filter_id_account2 )
			$query .= " AND t2.id_account=".$filter_id_account2." ";
	}
	$query .= " AND t1.value<0 AND t1.id_transfer!=0 ) ";	// переводы между счетами
}

//echo $query;

if( $query && $query_order )
	$query .= " ORDER BY ".$query_order;

if( !$query )
		$query = "select * from accounts where id_account=0";  // !!! это просто заглушка, чтобы вернуть пустой результат

//get_elapsed_microtime();

$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса транзакций. Экстренный выход."; exit; }
//$time1 = get_elapsed_microtime();
//echo "Времени на запрос: ".$time1."\r\n";

echo "<div align=center>\r\n";
//echo "<form name=\"main_form\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">\r\n";
echo "<form name=\"main_form\" action=\"".$_SERVER['PHP_SELF']."\" method="."GET".">\r\n";
echo "<input type=hidden name=action value=import>\r\n";
echo "<input type=hidden name=sort_field value=".$sort_field.">\r\n";
echo "<input type=hidden name=sort_dsc value=".$sort_dsc.">\r\n";
echo "<table class=layout>\r\n";
echo "	<tr class=layout>\r\n";
echo "		<td class=layout>\r\n";
echo "			<table class=form>\r\n";
echo "			<tr class=form>\r\n";
echo "				<td class=form>с даты\r\n</td>";
echo "				<td class=form>\r\n";
echo web_get_output_webform_selectlist("first_day", $days, $first_day);
echo web_get_output_webform_selectlist("first_month", $monthes, $first_month);
echo web_get_output_webform_selectlist("first_year", $years, $first_year);
echo "				</td>\r\n";
echo "			</tr>\r\n";
echo "			<tr class=form>\r\n";
echo "				<td class=form>по дату\r\n</td>";
echo "				<td class=form>\r\n";
echo web_get_output_webform_selectlist("last_day", $days, $last_day);
echo web_get_output_webform_selectlist("last_month", $monthes, $last_month);
echo web_get_output_webform_selectlist("last_year", $years, $last_year);
echo "				</td>\r\n";
echo "			</tr>\r\n";
echo "			</table>\r\n";
echo "		</td>\r\n";
echo "	</tr>\r\n";
echo "	<tr class=layout>\r\n";
echo "		<td class=layout>\r\n";
echo "			<table class=form>\r\n";
echo "			<tr class=form>\r\n";
echo "				<td class=form>\r\n";
echo web_get_output_webform_checkbox("trans_exp", "Расходы", $trans_exp)."<br>";
echo web_get_output_webform_checkbox("trans_inc", "Доходы", $trans_inc)."<br>";
echo web_get_output_webform_checkbox("trans_acc", "Перемещения", $trans_acc)."<br>";
echo "				</td>\r\n";
echo "			</tr>\r\n";
echo "			</table>\r\n";
echo "		</td>\r\n";
echo "	</tr>\r\n";
if( $trans_exp xor $trans_inc )
{
 	if( $trans_exp )
 		$trans_string = "расходов";
	else
 		$trans_string = "доходов";
	
	echo "	<tr class=layout>\r\n";
	echo "		<td class=layout>\r\n";
	echo "			<table class=form>\r\n";
	echo "			<tr class=form>\r\n";
	echo "				<td class=form>\r\n";
	echo "Категория ".$trans_string;
	echo "				</td>\r\n";
	echo "				<td class=form>\r\n";
	echo web_get_output_webform_selectlist("id_cat", $cat_names, $filter_id_cat, false, "set_subcat_list(document.main_form)");
	echo "				</td>\r\n";
	echo "			</tr>\r\n";
	echo "			<tr class=form>\r\n";
	echo "				<td class=form>\r\n";
	echo "Подкатегория ".$trans_string;
	echo "				</td>\r\n";
	echo "				<td class=form>\r\n";
	if( isset($subcat_names[$filter_id_cat][$filter_id_subcat]) )
		$temp_array = $subcat_names[$filter_id_cat];
	else
		$temp_array = array($filter_id_subcat => $no_choice_string);
	echo web_get_output_webform_selectlist("id_subcat", $temp_array, $filter_id_subcat, false, "set_subcat2_list(document.main_form)");	
	echo "				</td>\r\n";
	echo "			</tr>\r\n";
	echo "			<tr class=form>\r\n";
	echo "				<td class=form>\r\n";
	echo "Под2категория ".$trans_string;
	echo "				</td>\r\n";
	echo "				<td class=form>\r\n";
	if( isset($subcat2_names[$filter_id_subcat][$filter_id_subcat2]) )
		$temp_array = $subcat2_names[$filter_id_subcat];
	else
		$temp_array = array($filter_id_subcat2 => $no_choice_string);
	echo web_get_output_webform_selectlist("id_subcat2", $temp_array, $filter_id_subcat2);	
	echo "				</td>\r\n";
	echo "			</tr>\r\n";
	echo "			</table>\r\n";	
	echo "		</td>\r\n";
	echo "	</tr>\r\n";
}
if( $trans_exp || $trans_inc || $trans_acc )
{
	echo "	<tr class=layout>\r\n";
	echo "		<td class=layout>\r\n";
	echo "			<table class=form>\r\n";
	echo "			<tr class=form>\r\n";
	echo "				<td class=form>\r\n";
	if( $trans_exp || $trans_inc )
		echo "Операции со счётом: ";
	else
		echo "Со счета: ";
	echo "				</td>\r\n";
	echo "				<td class=form>\r\n";
	echo web_get_output_webform_selectlist("id_account1", $accounts, $filter_id_account1);	
	echo "				</td>\r\n";
	echo "			</tr>\r\n";
	if( !$trans_exp && !$trans_inc )
	{
		echo "			<tr class=form>\r\n";
		echo "				<td class=form>\r\n";
		echo "На счёт: ";
		echo "				</td>\r\n";
		echo "				<td class=form>\r\n";
		echo web_get_output_webform_selectlist("id_account2", $accounts, $filter_id_account2);	
		echo "				</td>\r\n";
		echo "			</tr>\r\n";
	}
	else
		echo "<input type=hidden name=\"id_account2\" value=\"".$filter_id_account2."\">";
	echo "			</table>\r\n";
	echo "		</td>\r\n";
	echo "	</tr>\r\n";
}
else
	echo "<input type=hidden name=\"id_account1\" value=\"".$filter_id_account1."\">";

echo "	<tr class=layout>\r\n";
echo "		<td class=layout>\r\n";
echo "			<div align=center><br>\r\n";
echo "			<input type=submit value=\"Обновить\">\r\n";
echo "			</div>\r\n";
echo "		</td>\r\n";
echo "	</tr>\r\n";
echo "</table>\r\n";
echo "</form>\r\n";

?>
<script language=Javascript>

function reset_checkboxes()
{
	var f = document.trx_form;
	var all_true = true;
	var all_false = true;
	var set = false;

	// check states
	for(var i = 0; i < f.length; i++) {
		var e = f.elements[i];
		if( e.type == "checkbox" ) {
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
	var f = document.trx_form;
	var sum = 0.0; 
	for(var i = 0; i < f.length; i++)
	{
		var e = f.elements[i];
    if( e.type == "checkbox" && e.checked == true )
			sum += parseFloat(e.value)*100;
  }
  //округление
  f.checked_sum.value = sum/100;
}

</script>
<?php

echo "<form name=\"th_form\" action=\"\" method="."GET".">\r\n";

$checkbox = "<input type=checkbox name=\"reset\" onclick='reset_checkboxes();'>";

$fields = array(
//"#"=>"#",
"date"=>"Дата", 
"account"=>"Счет", 
"cat"=>"Категория", 
"subcat"=>"Подкатегория", 
"subcat2"=>"Под2категория", 
"quantity"=>"Кол.",
"value"=>"Сумма", 
"comment"=>"Комментарий", 
0=> $checkbox);
$columns_modifiers = array(
//TXT_ALIGN_CENTER|TXT_NOBR,
TXT_ALIGN_CENTER|TXT_NOBR, 
TXT_ALIGN_LEFT|TXT_NOBR,
TXT_ALIGN_LEFT|TXT_NOBR,
TXT_ALIGN_LEFT|TXT_NOBR,
TXT_ALIGN_LEFT|TXT_NOBR,
TXT_ALIGN_CENTER,
TXT_ALIGN_CENTER,
TXT_ALIGN_LEFT|TXT_SMALL,
TXT_ALIGN_CENTER
);

echo get_table_sort_code();
echo get_table_start("grid");
echo get_table_header($fields, "grid", $sort_field, $sort_dsc);

echo "</form>\r\n";
echo "<form name=\"trx_form\" action=\"\" method="."GET".">\r\n";

$num = 1;
$total_expenses = 0;
$total_incomes = 0;
$total_acc_trans = 0;
$checked_sum = 0;
while( $temp_array = db_fetch_assoc_array($res) )
{
//	print_r($temp_array);
	$id_transaction = (int)($temp_array['id_transaction']);
	$id_subcat2 = (int)($temp_array['id_subcat2']);
	$id_account = (int)($temp_array['id_account']);
	$id_transfer = (int)($temp_array['id_transfer']);
	$date = $temp_array['date_f'];
	$quantity = (int)($temp_array['quantity']);
	$value = (float)($temp_array['value']);
	$comment = db_strip_slashes($temp_array['comment']);
	$is_rare = (int)($temp_array['is_rare']);
	$trans_type = (int)($temp_array['trans_type']);	
	$account = db_strip_slashes($temp_array['account']);
	$cat = db_strip_slashes($temp_array['cat']);
	$subcat = db_strip_slashes($temp_array['subcat']);
	$subcat2 = db_strip_slashes($temp_array['subcat2']);
	
	if( $trans_type == TRANS_TYPE_EXP )
	{
	 	$total_expenses += (-$value); 
		$value = "".(-$value);
		$row_color = '#f0f0f0';
	}
	elseif( $trans_type == TRANS_TYPE_INC )
	{
	 	$total_incomes += $value; 
		$value = "+".$value;
		$row_color = '#e0f0b0';
	}
	else // account transfer
	{
		//$value = $value;
	//	if( $id_account == $filter_id_account1 )
//			$value = -$value;

		$total_acc_trans += $value;
		$quantity = "";
		$row_color = '#b0d0e0';
	}
	
	$temp = "<input type=checkbox name=\"id_transaction_".$id_transaction."\" value=\"".$value."\" onclick='calc_sum();'";
	if( $is_rare ) {
		$temp .= " checked";
		$checked_sum += $value;
	}
	$temp .= ">";

	$cells = array(
//	$num,	
	$date,
	$account,
	$cat,
	$subcat,
	$subcat2,
	$quantity,
	$value,
	$comment,
	$temp
	);
	echo get_table_row($cells, "grid", TXT_NONE, "background-color:".$row_color.";", $columns_modifiers);
	$num++;
}

// итоги
$columns_modifiers = array(
//0,
0,0,0,0,0,0,TXT_ALIGN_CENTER|TXT_BOLD,TXT_ALIGN_LEFT|TXT_BOLD,0);
if( $trans_inc )
{	
	$row_color = '#e0f0b0';
	$cells = array(
	//	"",	
	"","","", "","","",$total_incomes,"Доходы","");
	echo get_table_row($cells, "grid", TXT_NONE, "background-color:".$row_color.";", $columns_modifiers);
}
if( $trans_exp )
{
	$row_color = '#f0f0f0';
	$cells = array(
	//	"",	
	"","","", "","","",$total_expenses,"Расходы","");
	echo get_table_row($cells, "grid", TXT_NONE, "background-color:".$row_color.";", $columns_modifiers);
}
if( $trans_acc )
{
	$row_color = '#f0f0f0';
	$cells = array(
	//	"",	
	"","","", "","","",$total_acc_trans,"Перемещения","");
	echo get_table_row($cells, "grid", TXT_NONE, "background-color:".$row_color.";", $columns_modifiers);
}

// checked sum
$cells = array("","","", "","","","<input type='text' size=6 name='checked_sum' value='".$checked_sum."'>","Отмечено","");
echo get_table_row($cells, "grid", TXT_NONE, "", $columns_modifiers);

echo get_table_end();
echo "</form>\n";
echo "<br>\r\n";
echo "</div>\r\n";
//$time2 = get_elapsed_microtime();
//echo "Времени на вывод: ".$time2."\r\n";
//echo "Общее время: ".($time2+$time1)."\r\n";

// 7. Конец скрипта.
include("footer.php");
?>
