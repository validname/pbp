<?
require_once("functions/config.php");
require_once("functions/auth.php");
require_once("functions/func_db.php");
require_once("functions/func_web.php");

$doc_title = "График расходов";
//$doc_onLoad = "set_mode();";
include("header.php");
//print_header_menu();

// параметры формы
$form_months = web_get_request_value($_REQUEST, "months", 'i');
$form_id_exp_cat = web_get_request_value($_REQUEST, "id_exp_cat", 'i');
$form_id_exp_subcat = web_get_request_value($_REQUEST, "id_exp_subcat", 'i');
$wo_last_month = web_get_checkbox_value($_REQUEST, "wo_last_month");
$rare = web_get_request_value($_REQUEST, "rare", 'i');
$width = web_get_request_value($_REQUEST, "width", 'i');
$height = web_get_request_value($_REQUEST, "height", 'i');

// параметры выборки
if( !$form_months )
	$form_months = 6;	// for 5-degree polynom

if( $rare<=0 || $rare>3 )
	$rare = 3; //rare doesn't matters

// предварительный запрос количества всех месяцев расходов
$query  = "SELECT year(date), month(date) ";
$query .= "FROM transactions AS t ";
$query .= "WHERE id_transfer=0 AND value<0 ";
$query .= "GROUP BY year(date), month(date)";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса количества месяцев. Экстренный выход."; exit; }
$months_all = db_num_rows($res);

// предварительный запрос категорий и подкатегорий 
$query  = "SELECT c.id_exp_cat, c.name as c_name, sc.id_exp_subcat, sc.name AS sc_name ";
$query .= "FROM expense_subcats AS sc ";
$query .= "INNER JOIN expense_cats AS c ON c.id_exp_cat=sc.id_exp_cat ";
$query .= "ORDER BY c.name, sc.name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса категорий. Экстренный выход."; exit; }
while( $temp_array=db_fetch_assoc_array($res) )
{
	$id_exp_cat = (int)$temp_array['id_exp_cat'];
	$id_exp_subcat = (int)$temp_array['id_exp_subcat'];
	$category_names[$id_exp_cat] = db_strip_slashes($temp_array['c_name']);
	$subcategory_names[$id_exp_cat][$id_exp_subcat] = db_strip_slashes($temp_array['sc_name']);
}

reset($category_names);
if( !$form_id_exp_cat )
	$form_id_exp_cat = key($category_names); // первый ключ = первый по счету id_exp_cat

$current_subcats = array("-----");
if( isset($subcategory_names[$form_id_exp_cat]) )
	$current_subcats += $subcategory_names[$form_id_exp_cat];

// Javascript
echo "<script language=Javascript>\r\n";
echo "function on_change_cat(id_exp_cat)\r\n";
echo "{\r\n";
echo "	document.main_form.id_exp_subcat.options.length = 1;\r\n";
echo "	document.main_form.id_exp_subcat.options[0].value = 0;\r\n";
echo "	document.main_form.id_exp_subcat.options[0].text = \"-----\";\r\n";
echo "	switch(document.main_form.id_exp_cat.value)\r\n";
echo "	{\r\n";
foreach( $category_names as $id_exp_cat => $cat_name )
{
	echo "		case '".$id_exp_cat."':\r\n";
	$subcat_num = count($subcategory_names[$id_exp_cat]);
	if( $subcat_num < 2 )
	{
		echo "						document.main_form.id_exp_subcat.disabled = true;\r\n";
		echo "												break;\r\n";
	}
	else
	{
		echo "						document.main_form.id_exp_subcat.disabled = false;\r\n";
		echo "						document.main_form.id_exp_subcat.options.length = ".$subcat_num.";\r\n";
		$num = 0;						
		foreach( $subcategory_names[$id_exp_cat] as $id_exp_subcat => $subcat_name )
		{
			$num++;
			echo "						document.main_form.id_exp_subcat.options[".$num."].value = ".$id_exp_subcat.";\r\n";
			echo "						document.main_form.id_exp_subcat.options[".$num."].text = \"".$subcat_name."\";\r\n";
		}
		echo "												break;\r\n";
	}
	
}
echo "	}\r\n";
echo "	return true;\r\n";
echo "}\r\n";
echo "</script>\r\n";
?>
<script language=Javascript>
function add_month(value)
{
	min = 2;
	max = document.main_form.months_all.value;
	months = document.main_form.months.value -0 + value;
	if( months < min )
		months = min;
	if( months > max )
		months = max;
	document.main_form.months.value = months;
}

function months_key()
{
	KeyID = event.keyCode;
	switch(KeyID)
	{
		case 38: 	// Arrow Up
							add_month(1);
							break;
		case 40: 	// Arrow Down
							add_month(-1);
							break;
	}
}
</script>
<?
// вывод формы
?>
<form name="main_form" action=<? echo $_SERVER['PHP_SELF']; ?> method="<? echo FORM_METHOD; ?>">
<table class=layout>
<tr class=layout><td class=layout id=center>
<?	
	echo "<input type=hidden name=months_all value=\"".$months_all."\">\r\n";
	echo "<label for=id_exp_cat>Категория: </label>\r\n";
	echo web_get_output_webform_selectlist("id_exp_cat", $category_names, $form_id_exp_cat, false, "on_change_cat();");
	echo "<label for=id_exp_subcat>Подкатегория: </label>\r\n";
	echo web_get_output_webform_selectlist("id_exp_subcat", $current_subcats, $form_id_exp_subcat);
?>
</td></tr>
<tr class=layout><td class=layout id=center>
<?
	echo "<label for=months>Выборка за месяцев: </label>\r\n";
	echo "<input type=text id=months maxlength=4 size=4 name=months value=\"".$form_months."\" onKeyDown=\"months_key();\">\r\n";
?>
<input type=button value="-" onClick="add_month(-1);">
<input type=button value="+" onClick="add_month(1);">
<input type=button value="все" onClick="document.main_form.months.value=document.main_form.months_all.value;">
<?
	echo web_get_output_webform_checkbox("wo_last_month", "", $wo_last_month, false);
	echo "<label for=wo_last_month>кроме последнего</label>\r\n";	
	//echo web_get_output_webform_checkbox("wo_rare", "", $wo_rare, false);
	//echo "<label for=wo_rare>без редких</label>\r\n";
	//echo "<label for=rare>Нетипичные расходы:</label> \r\n";	
	echo web_get_output_webform_radiobtn("rare", array(1=>"нетипичные",2=>"типичные",3=>"все"), $rare, false);
?>
</td></tr>
<tr class=layout><td class=layout id=center>
<?
?>
<input type=submit value="Выбрать">
</td></tr>
<tr class=layout><td class=layout>&nbsp;</td></tr>
<tr class=layout><td class=layout>
<?
// вывод графика
$width = 800;
$height = 500;
$wo_last_month? $wolm="on" : $wolm="off";
echo "<img width=".$width." height=".$height." src=\"expenses_graph1.php";
echo "?months=".$form_months."&id_exp_cat=".$form_id_exp_cat."&id_exp_subcat=".$form_id_exp_subcat;
echo "&width=".$width."&height=".$height."&wo_last_month=".$wolm."&rare=".$rare."\">";
?>
</td>
</tr></table>
</form>
<? include("footer.php"); ?>
