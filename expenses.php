<?php
require_once('functions/config.php');
require_once('functions/auth.php');
require_once('functions/func_db.php');
require_once('functions/func_web.php');
require_once("functions/func_time.php");
require_once("functions/func_table.php");
require_once("functions/func_profiler.php");

$doc_title = "Добавление расходов";
//$doc_onLoad = "set_mode();";
include("header.php");
?>
 <!-- calendar stylesheet -->
  <link rel="stylesheet" type="text/css" media="all" href="jscalendar-1.0/calendar-win2k-cold-1.css" title="win2k-cold-1" />

  <!-- main calendar program -->
  <script type="text/javascript" src="jscalendar-1.0/calendar.js"></script>

  <!-- language for the calendar -->
  <script type="text/javascript" src="jscalendar-1.0/lang/calendar-en.js"></script>

  <!-- the following script defines the Calendar.setup helper function, which makes
       adding a calendar a matter of 1 or 2 lines of code. -->
  <script type="text/javascript" src="jscalendar-1.0/calendar-setup.js"></script>

<?php

// 1. флаги ошибок
$updating_invalid_value = 0;
$updating_error = 0;
$error_text = "";

// 2. подготовка переменных

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$f_action = web_get_request_value($_REQUEST, "action", 's');

$f_id_transaction = web_get_request_value($_REQUEST, "id_transaction", 'i');
$f_day = web_get_request_value($_REQUEST, "day", 'i');
$f_month = web_get_request_value($_REQUEST, "month", 'i');
$f_year = web_get_request_value($_REQUEST, "year", 'i');
$f_id_subcat2 = web_get_request_value($_REQUEST, "id_subcat2", 'i');
$f_id_account = web_get_request_value($_REQUEST, "id_account", 'i');
$f_quantity = web_get_request_value($_REQUEST, "quantity", 'i');
$f_value = web_get_request_value($_REQUEST, "value", 'f');
$f_comment = web_get_request_value($_REQUEST, "comment", 's');

$f_first_day = web_get_request_value($_REQUEST, "first_day", 'i');
$f_first_month = web_get_request_value($_REQUEST, "first_month", 'i');
$f_first_year = web_get_request_value($_REQUEST, "first_year", 'i');
$f_last_day = web_get_request_value($_REQUEST, "last_day", 'i');
$f_last_month = web_get_request_value($_REQUEST, "last_month", 'i');
$f_last_year = web_get_request_value($_REQUEST, "last_year", 'i');
//$filter_id_account_on = web_get_request_value($_REQUEST, "id_account_on", 'i');

// 2.2 значения по умолчанию для переменных формы

if( !$f_day )
	$f_day = (int)(date("d"));
if( !$f_month || $f_month > 12 )
	$f_month = (int)(date("m"));
if( !$f_year )
	$f_year = (int)(date("Y"));
$temp = date("d", mktime(0,0,0,$f_month+1, 0, $f_year)); // последнее число месяца
if( $f_day > $temp )
	$f_day = (int)($temp);	// последнее число

if( !$f_first_day )
	$f_first_day = (int)(date("d"));
if( !$f_first_month || $f_first_month > 12 )
	$f_first_month = (int)(date("m"));
if( !$f_first_year )
	$f_first_year = (int)(date("Y"));
$temp = date("d", mktime(0,0,0,$f_first_month+1, 0, $f_first_year)); // последнее число месяца
if( $f_first_day > $temp )
	$f_first_day = (int)($temp);	// последнее число

if( !$f_last_day )
	 $f_last_day = $f_first_day;
if( !$f_last_month || !$f_last_month > 12 )
	$f_last_month = $f_first_month; // месяц тот же, что и начальный 
if( !$f_last_year )
	$f_last_year = $f_first_year; // год тот же, что и начальный
	
if( $f_last_year <= $f_first_year )
{
	$f_last_year = $f_first_year;
	if( $f_last_month <= $f_first_month )
	{
		$f_last_month = $f_first_month;
		if( $f_last_day < $f_first_day )
			$f_last_day = $f_first_day;
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
$query  = "SELECT id_account, name FROM accounts ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса счетов. Экстренный выход."; exit; }
while( $temp_array = db_fetch_num_array($res) )
	$accounts[(int)($temp_array[0])] = db_strip_slashes($temp_array[1]);
$accounts = array(0 =>$no_choice_string ) + $accounts;


// 3.3 запрос категорий и подкатегорий 
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

// Javascript
echo "<script language=Javascript>\r\n";
echo "function set_subcat2(id_subcat2)\r\n";
echo "{\r\n";
echo "	var form = document.main_form\r\n";
echo "	switch(id_subcat2)\r\n";
echo "	{\r\n";
foreach( $categories as $id_subcat2 => $temp_array)
{
	echo "		case '".$id_subcat2."':\r\n";
	echo "			select_option(form.id_cat, ".$temp_array[0].");\r\n";
	echo "			set_subcat_list(form);\r\n";
	echo "			select_option(form.id_subcat, ".$temp_array[1].");\r\n";
	echo "			set_subcat2_list(form);\r\n";
	echo "			select_option(form.id_subcat2, ".$id_subcat2.");\r\n";
	echo "			break;\r\n";
}
echo "	}\r\n";
echo "}\r\n";
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
echo "	var length = form.id_subcat.length;\r\n";
echo "	if( length == 2 ) { // no-choice + single subcat\r\n";
echo "		for(var i = 0; i < length; i++) {\r\n";
echo "			var e = form.id_subcat.options[i];\r\n";
echo "			if( e.value )\r\n";
echo "				e.selected = true;\r\n";
echo "		}\r\n";
echo "	set_subcat2_list(form);\r\n";
echo "	}\r\n";
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
echo "	var length = form.id_subcat2.length;\r\n";
echo "	if( length == 2 ) { // no-choice + single subcat2\r\n";
echo "		for(var i = 0; i < length; i++) {\r\n";
echo "			var e = form.id_subcat2.options[i];\r\n";
echo "			if( e.value )\r\n";
echo "				e.selected = true;\r\n";
echo "		}\r\n";
echo "	}\r\n";
echo "	return true;\r\n";
echo "}\r\n";
echo "</script>\r\n";

//!dps! move to actioned part!
if( $f_id_subcat2 ) {
    list($f_id_cat, $f_id_subcat) = $categories[$f_id_subcat2];
}
else {
    $f_id_cat = $f_id_subcat = 0;
}

// 4. Отработка действий в формой
// Если форма сработала
if( $f_action )
{
/*
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
*/
} // if( $action )
else
{ //if( !$action )
	// форма грузится первый раз
} //if( !$action )

$query_date_from = $f_first_year."-".$f_first_month."-".$f_first_day;
$query_date_to = $f_last_year."-".$f_last_month."-".$f_last_day;
//!!!!!!!!!!!!!!!!
$query_date_from = "2009-03-20";
$query_date_to = "2009-03-20";
$query = "";
$query .= "(SELECT t.id_transaction, t.id_subcat2, t.id_account, t.id_transfer, DATE_FORMAT(t.date, \"".$sql_date_format."\") as date_f, ";
$query .= " t.date, t.quantity, t.value, t.comment, t.is_rare, "; 
$query .= " a.name as account, exp_c.name as cat, exp_sc.name as subcat, exp_sc2.name as subcat2, ".TRANS_TYPE_EXP." as trans_type ";
$query .= " FROM transactions AS t ";
$query .= " INNER JOIN accounts AS a ON a.id_account=t.id_account ";
$query .= " INNER JOIN expense_subcats2 AS exp_sc2 ON exp_sc2.id_exp_subcat2=t.id_subcat2 ";
$query .= " INNER JOIN expense_subcats AS exp_sc ON exp_sc.id_exp_subcat=exp_sc2.id_exp_subcat ";
$query .= " INNER JOIN expense_cats AS exp_c ON exp_c.id_exp_cat=exp_sc.id_exp_cat ";
$query .= " WHERE t.date>='".$query_date_from."' AND t.date<='".$query_date_to."' ";
/*if( $filter_id_account1 )
	$query .= " AND a.id_account=".$filter_id_account1." ";
if( $filter_id_cat )
	$query .= " AND exp_c.id_exp_cat=".$filter_id_cat." ";
if( $filter_id_subcat )
	$query .= " AND exp_sc.id_exp_subcat=".$filter_id_subcat." ";
if( $filter_id_subcat2 )
	$query .= " AND exp_sc2.id_exp_subcat2=".$filter_id_subcat2." ";
*/
$query .= " AND value<0 AND id_transfer=0 ) ";	// расходы
$query .= " ORDER BY t.date";

//echo $query;

//get_elapsed_microtime();

$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса транзакций. Экстренный выход."; exit; }
//$time1 = get_elapsed_microtime();
//echo "Времени на запрос: ".$time1."\r\n";

?>
<div align=center>
<!-- form name=\"main_form\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD." -->
<form name="main_form" action="<?php echo $_SERVER['PHP_SELF']?>" method="GET">
<input type=hidden name=action value=add>
<!-- input type=hidden name=sort_field value=".$sort_field.-->
<!-- input type=hidden name=sort_dsc value=".$sort_dsc."-->
<table class=layout border=1>
	<tr class=layout>
		<td class=layout>
			<table class=form>
			<tr class=form>
				<td class=form>Дата</td>
				<td class=form>
<?php
echo web_get_output_webform_selectlist("day", $days, $f_day);
echo web_get_output_webform_selectlist("month", $monthes, $f_month);
echo web_get_output_webform_selectlist("year", $years, $f_year);
?>
				<input type="hidden" name="date" id="date1_field" value="<?php echo $f_year."-".$f_month."-".$f_day; ?>" /><button type="reset" id="date1_button">...</button>
				<span style="background-color: #ff8; cursor: default;" id="date1"></span>

<script type="text/javascript">
	function search_event(event, result) {
		var field = document.getElementById ('result_text');
		switch( event ) {
			case 1:	// search started
					field.innerHTML = 'Поиск...';
					field.style.color = '#c0c000';
					break;
			case 2:	// search finished with error
					field.innerHTML = 'Ошибка, код: ' + result;
					field.style.color = '#c00000';
					break;
			case 3:	// search succesfully finished, not found
					field.innerHTML = 'Не найдено';
					field.style.color = '#c0c0c0';
					break;
			case 4:	// search succesfully finished, found
					field.innerHTML = 'Найдена под2категория: ' + result;
					field.style.color = '#00c000';
					break;
		}
	}

	function search_comment(search_field)	{
		/*
			0	The request is not initialized
			1	The request has been set up
			2	The request has been sent
			3	The request is in process
			4	The request is complete
		 */
		if( !search_field.value.length ) return;
		
		var requestObj = null;
		if (window.XMLHttpRequest) {
		//это работает для opera и firefox
		requestObj = new XMLHttpRequest();
		}
		else
			if (window.ActiveXObject) {
			// а это проверка internet explorer
			requestObj = new ActiveXObject("Msxml2.XMLHTTP");
			if (!requestObj)
			requestObj = new ActiveXObject("Microsoft.XMLHTTP");
		};
		if (! requestObj ) return;

		requestObj.onreadystatechange = function ()
		{
			if (requestObj.readyState == 4) {
				if (requestObj.status == 200) {
					if( requestObj.responseText ) {
						search_event(4, requestObj.responseText);
						set_subcat2(requestObj.responseText);
					}
					else {
						search_event(3, requestObj.responseText);
					}
				}
				else
					search_event(2, requestObj.status);
			} else {
				if (requestObj.readyState == 2)
					search_event(result_field, 1);
				if (requestObj.readyState == 3)
					search_event(result_field, 1);

			}
		};
		var URLvars = encodeURIComponent(search_field.value);
//		requestObj.open('POST','!test_prediction_server.php',true);
		requestObj.open('GET','!test_prediction_server.php?search=' + URLvars,true);
		requestObj.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		requestObj.send('search=' + URLvars);
	}

	function select_option(element, value) {
		var length = element.length;
		for(var i = 0; i < length; i++) {
			var e = element.options[i];
			if( e.value == value )
				e.selected = true;
			else
				e.selected = false;
		}
	}

	function set_date(datetime)	{
		var date = datetime.date;
		var el = document.getElementById("day");
		el.value = date.getDate();      // integer, 1..31
		el = document.getElementById("month");
		select_option(el, date.getMonth() + 1);
		el = document.getElementById("year");
		select_option(el, date.getFullYear());
		return true;
	}

    Calendar.setup({
        inputField     :    "date1_field",      // id of the input field
        ifFormat       :    "%Y-%m-%d",       // format of the input field
        button         :    "date1_button",  // trigger for the calendar (button ID)
		//displayArea    :    "date1",       // ID of the span where the date is to be shown
		//daFormat       :    "%d.%m.%Y (%A)",// format of the displayed date
		firstDay		: 1,	//which day is to be displayed as the first day of week. Possible values are 0 to 6; 0 means Sunday
		weekNumbers		: false,
		showOthers : true,
		onUpdate: set_date

    });
</script>
				</td>
			</tr>
			</table>
		</td>
	</tr>
	<tr class=layout>
		<td class=layout>
			<table class=form>
			<tr class=form>
				<td class=form>Комментарий<br>
					<textarea name="comment" cols=40 rows=2 onChange="search_comment(this)">
					</textarea><br>
					<span id="result_text">&nbsp;<span>
				</td>
			</tr>
			</table>
		</td>
	</tr>

<tr class=layout>
		<td class=layout>
			<table class=form>
			<tr class=form>
				<td class=form>
					Сумма <input type="text" maxlength="10" size="10" name="value" value="100.92">
					Кол-во <input type="text" maxlength="2" size="2" name="quantity" value="2">
				</td>
			</tr>
			</table>
		</td>
	</tr>

	<tr class=layout>
		<td class=layout>
			<table class=form>
			<tr class=form>
				<td class=form>Категория<br>
<?php echo web_get_output_webform_selectlist("id_cat", $cat_names, $f_id_cat, false, "set_subcat_list(document.main_form)"); ?>
				</td>
			</tr>
			<tr class=form>
				<td class=form>Подкатегория<br>
<?php
if( isset($subcat_names[$f_id_cat][$f_id_subcat]) )
    $temp_array = $subcat_names[$f_id_cat];
else
    $temp_array = array($f_id_subcat => $no_choice_string);
echo web_get_output_webform_selectlist("id_subcat", $temp_array, $f_id_subcat, false, "set_subcat2_list(document.main_form)");	
?>
</td>
			</tr>
			<tr class=form>
				<td class=form>Под2категория<br>
<?php
if( isset($subcat2_names[$f_id_subcat][$f_id_subcat2]) )
    $temp_array = $subcat2_names[$f_id_subcat];
else
    $temp_array = array($f_id_subcat2 => $no_choice_string);
echo web_get_output_webform_selectlist("id_subcat2", $temp_array, $f_id_subcat2);	
?>
</td>
			</tr>
			</table>	
		</td>
	</tr>

	<tr class=layout>
		<td class=layout>
			<table class=form>
			<tr class=form>
				<td class=form>Счет<br>
<?php echo web_get_output_webform_selectlist("id_account",$accounts, $f_id_account, false); ?>
				</td>
			</tr>
			</table>
		</td>
	</tr>

	<tr class=layout>
		<td class=layout>
			<div align=center><br>
			<input type=submit value="Добавить">
			</div>
		</td>
	</tr>
</table>
</form>
</div>
<?php

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
"tttt"=> "");
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
//echo get_table_header($fields, "grid", $sort_field, $sort_dsc);
echo get_table_header($fields, "grid");

$num = 1;
$total_expenses = 0;
$total_incomes = 0;
$total_acc_trans = 0;
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
	else // acc
	{
		//$value = $value;
	//	if( $id_account == $filter_id_account1 )
//			$value = -$value;

		$total_acc_trans += $value;
		$quantity = "";
		$row_color = '#b0d0e0';
	}
	
	$temp = "<input type=checkbox name=\"id_transaction_".$id_transaction."\"";
	if( $is_rare ) 
		$temp .= " checked";
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

$row_color = '#f0f0f0';
$cells = array(
//	"",	
"","","", "","","",$total_expenses,"Расходы","");
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
