<?php
require_once('functions/config.php');
require_once('functions/auth.php');
require_once('functions/func_db.php');
require_once('functions/func_web.php');
require_once("functions/func_time.php");
require_once("functions/func_table.php");
require_once("functions/func_profiler.php");

$doc_title = "Долговые перемещения";
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

// 2.2 значения по умолчанию для переменных формы
switch( $sort_field )
{
	case "date":			break;
	case "account":		break;
	case "account2":				break;
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
$query  = "SELECT id_account, name, is_debt FROM accounts ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса счетов. Экстренный выход."; exit; }
while( $temp_array = db_fetch_num_array($res) )
{
	$accounts[(int)($temp_array[0])] = db_strip_slashes($temp_array[1]);
	if( (int)($temp_array[2]) == 1 )
		$id_account_debt = (int)($temp_array[0]);
}
$accounts = array(0 =>$no_choice_string ) + $accounts;

if( !$id_account_debt )
	{ echo "<hr>Долговой счёт не обнаружен. Экстренный выход."; exit; }
	
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
										$query_order .= ", account, account2, value, comment"; break;
	case "account":		$query_order = "account";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account2, value, comment";	break;
	case "account2":				$query_order = "account2";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, value, comment"; break;
	case "value":			$query_order = "value";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, account2, comment"; break;
	case "comment":		$query_order = "comment";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, account2, value"; break;
}

$query_date_from = $first_year."-".$first_month."-".$first_day;
$query_date_to = $last_year."-".$last_month."-".$last_day;
$query = "";
$query .= "SELECT t1.id_transaction, t1.id_account, t2.id_account as id_account2, t1.id_transfer, DATE_FORMAT(t1.date, \"".$sql_date_format."\") as date_f, ";
$query .= " t1.date, t2.value, t1.comment, t1.is_rare, "; 
$query .= " a1.name as account, a2.name as account2 ";
$query .= " FROM transactions AS t1 ";
$query .= " INNER JOIN transactions AS t2 ON t1.id_transfer=t2.id_transaction ";
$query .= " INNER JOIN accounts AS a1 ON a1.id_account=t1.id_account ";
$query .= " INNER JOIN accounts AS a2 ON a2.id_account=t2.id_account ";
$query .= " WHERE t1.date>='".$query_date_from."' AND t1.date<='".$query_date_to."' ";
$query .= " AND (t1.id_account=".$id_account_debt." ";
$query .= " OR t2.id_account=".$id_account_debt.") ";
$query .= " AND t1.value<0 AND t1.id_transfer!=0  ";	// переводы между счетами

if( $query && $query_order )
	$query .= " ORDER BY ".$query_order;

//echo $query."<hr>";

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
echo "			<div align=center><br>\r\n";
echo "			<input type=submit value=\"Обновить\">\r\n";
echo "			</div>\r\n";
echo "		</td>\r\n";
echo "	</tr>\r\n";
echo "</table>\r\n";
echo "</form>\r\n";

$fields = array(
//"#"=>"#",
"date"=>"Дата", 
"account"=>"Счет",
"account2"=>"Счет-получатель", 
"value"=>"Сумма", 
"comment"=>"Комментарий", 
"tttt"=> "");
$columns_modifiers = array(
//TXT_ALIGN_CENTER|TXT_NOBR,
TXT_ALIGN_CENTER|TXT_NOBR, 
TXT_ALIGN_LEFT|TXT_NOBR,
TXT_ALIGN_LEFT|TXT_NOBR,
TXT_ALIGN_CENTER,
TXT_ALIGN_LEFT|TXT_SMALL,
TXT_ALIGN_CENTER
);

echo get_table_sort_code();
echo get_table_start("grid");
echo get_table_header($fields, "grid", $sort_field, $sort_dsc);

$num = 1;
$total_expenses = 0;
$total_incomes = 0;
$total_balance = 0;
while( $temp_array = db_fetch_assoc_array($res) )
{
//	print_r($temp_array);
	$id_transaction = (int)($temp_array['id_transaction']);
	$id_account = (int)($temp_array['id_account']);
	$id_account2 = (int)($temp_array['id_account2']);
	$id_transfer = (int)($temp_array['id_transfer']);
	$date = $temp_array['date_f'];
	$value = (float)($temp_array['value']);
	$comment = db_strip_slashes($temp_array['comment']);
	$is_rare = (int)($temp_array['is_rare']);
	$account = db_strip_slashes($temp_array['account']);
	$account2 = db_strip_slashes($temp_array['account2']);	
	
	if( $id_account2 == $id_account_debt ) // дали в долг
	{
	 	$total_balance += (-$value); 
		$value = "".(-$value);
		$row_color = '#f0b0b0';
	}
	else // acc
	{
		$total_balance += $value;
		$quantity = "";
		$row_color = '#b0e0b0';
	}
	
	$temp = "<input type=checkbox name=\"id_transaction_".$id_transaction."\"";
	if( $is_rare ) 
		$temp .= " checked";
	$temp .= ">";

	$cells = array(
//	$num,	
	$date,
	$account,
	$account2,	
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
0,0,0,TXT_ALIGN_CENTER|TXT_BOLD,TXT_ALIGN_LEFT|TXT_BOLD,0);
$row_color = '#e0f0b0';
$cells = array(
//	"",	
"","","",$total_balance,"Баланс","");
echo get_table_row($cells, "grid", TXT_NONE, "background-color:".$row_color.";", $columns_modifiers);
echo get_table_end();
echo "<br>\r\n";
echo "</div>\r\n";
//$time2 = get_elapsed_microtime();
//echo "Времени на вывод: ".$time2."\r\n";
//echo "Общее время: ".($time2+$time1)."\r\n";

// 7. Конец скрипта.
include("footer.php");
?>
