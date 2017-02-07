<?php
require_once('../../functions/config.php');
require_once('../../functions/auth.php');
require_once('../../functions/func_db.php');
require_once('../../functions/func_web.php');
require_once("../../functions/func_time.php");
require_once("../functions/import_config.php");

set_time_limit(0);

if(!isset($silent_mode))
	$silent_mode = false;

$module_name = "HomeBuh_paradox";
$id_import_mod = get_module_id($module_name);
$current_step = 5;
$last_step = get_config_value($id_import_mod, "last_step");
if( $last_step === false )
	{ echo "<hr>Ошибка чтения конфигурационных данных. Экстренный выход."; exit(3); }

if( !$silent_mode ) {
	$doc_title = "Шаг ".$current_step.": Соответствие категорий и подкатегорий доходов";
	//$doc_onLoad = "set_mode();";
	include("header.php");
}

// 1. флаги ошибок
$updating_invalid_value = 0;
$updating_error = 0;
$error_text = "";

// 2. подготовка переменных

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$action = web_get_request_value($_REQUEST, "action", 's');
$form_cat_name = web_get_request_value($_REQUEST, "cat_name", 's');
$form_subcat_name = web_get_request_value($_REQUEST, "subcat_name", 's');
$form_id_subcat2 = web_get_request_value($_REQUEST, "id_subcat2", 'i');

// 2.2 значения по умолчанию для переменных формы

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

// 3.1 запрос названий категорий и подкатегорий из импортируемых данных
$imp_subcat_names = array();
$query = "SELECT cat, subcat FROM import_draft WHERE id_trans_type=".TRANS_TYPE_INC." GROUP BY cat, subcat ORDER BY cat, subcat";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса названий импортируемых категорий. Экстренный выход."; exit(5); }
while( $temp_array = db_fetch_num_array($res) )
	$imp_subcat_names[db_strip_slashes($temp_array[0])][] = db_strip_slashes($temp_array[1]);

// 3.2 запрос категорий
$cat_names = array();
$subcat_names = array();
$categories  = array();

$query  = "SELECT c.id_inc_cat, c.name, sc.id_inc_subcat, sc.name, id_inc_subcat2, sc2.name FROM income_subcats2 AS sc2 ";
$query .= "INNER JOIN income_subcats AS sc ON sc.id_inc_subcat=sc2.id_inc_subcat ";
$query .= "INNER JOIN income_cats AS c ON sc.id_inc_cat=c.id_inc_cat ";
$query .= "ORDER BY c.name, sc.name, sc2.name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса внутренних категорий. Экстренный выход."; exit(5); }
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
	$subcat_names[$id_cat] = array(0 => "------ не импортировать ------") + $temp_array;
foreach( $subcat2_names as $id_subcat => $temp_array )
	$subcat_names[$id_subcat] = array(0 => "------ не импортировать ------") + $temp_array;
$cat_names = array(0 => "------ не импортировать ------") + $cat_names;
$categories[0] = array(0, 0);

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
		if( !$form_subcat_name )
		{
			$updating_invalid_value = 1;
			$error_text = "Пустое имя импортируемой подкатегории";
		}
		if( !$form_cat_name )
		{
			$updating_invalid_value = 1;
			$error_text = "Пустое имя импортируемой категории";
		}
		if( !$form_id_subcat2 )
		{
			$updating_invalid_value = 1;
			$error_text = "Пустой идентификатор внутренней под2категории";
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
			$query_where  = " WHERE cat='".db_add_slashes($form_cat_name)."' ";
			$query_where .= " AND subcat='".db_add_slashes($form_subcat_name)."' ";
			$query_where .= " AND id_trans_type=".TRANS_TYPE_INC." AND id_import_mod=".$id_import_mod; 
			// пытаемся прочитать значение  
			$query = "SELECT id_subcat2 FROM import_map_cat ";
			$query .= $query_where;
			$result = db_query($query);
			if( !$result )
			{
				$updating_error = 1;
				echo "<font color=red>Произошла ошибка запроса мэппинга категорий</font><br>";
			}
			else
			{//if( $result )
				if( !$temp_array=db_fetch_num_array($result) ) // пустая выборка
					$inserting = true;
				else
					$inserting = false;
				if( $inserting )
				{
					$db_query  = "INSERT INTO import_map_cat (id_import_mod, id_trans_type, cat, subcat, subcat2, id_subcat2) VALUES (";
					$db_query .= $id_import_mod.", ".TRANS_TYPE_INC.", '".db_add_slashes($form_cat_name)."', ";
					$db_query .= "'".db_add_slashes($form_subcat_name)."', '', ".$form_id_subcat2.") ";
					$result = db_query($db_query, $insert_mode);
					if( !$result )
					{	// ошибка операции
						$updating_error = 1;
						echo "<font color=red>Произошла ошибка занесения мэппинга категорий</font><br>";
					}
				}//if( $inserting )
				else
				{//if( !$inserting )
					$query = "UPDATE import_map_cat SET id_subcat2=".$form_id_subcat2." ";
					$query .= $query_where;
					$result = db_query($query);
					if( !$result )
					{
						$updating_error = 1;
						echo "<font color=red>Произошла ошибка изменения мэппинга категорий</font><br>";
					}
				}//if( !$inserting )				
			}//if( $result )
		}
		if( !$result )
		{	// ошибка операции
				$updating_error = 1;
				echo "<font color=red>Произошла ошибка запроса</font><br>";
		}
	}// if( $updating_invalid_value )
} // if( $action )
else
{ //if( !$action )
	// форма грузится первый раз
	// сохраняем шаг
	if( !set_config_value($id_import_mod, "last_step", $current_step) )
		{ echo "<hr>Ошибка занесения конфигурационных данных. Экстренный выход."; exit(3); }
} //if( !$action )

// 5. Заполнение формы
// 5.1 запрос мэппинга категорий
$map_subcat_ids = array();
$query  = "SELECT id_subcat2, cat, subcat FROM import_map_cat ";
$query .= "WHERE id_import_mod=".$id_import_mod." AND id_trans_type=".TRANS_TYPE_INC." ORDER BY cat, subcat";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса мэппинга категорий. Экстренный выход."; exit(5); }
while( $temp_array = db_fetch_num_array($res) )
{
	$id_subcat2 = (int)($temp_array[0]);
	$cat = db_strip_slashes($temp_array[1]);
	$subcat = db_strip_slashes($temp_array[2]);
	$map_subcat_ids[$cat][$subcat] = $id_subcat2;
}

// 6. Вывод формы
if( !$silent_mode ) {
?>
<script lang=Javascript>
function set_subcat_list(form)
{
	form.id_subcat.length=0;
	form.id_subcat2.length=0;
	switch(form.id_cat.value)
	{
<?php
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
?>
	}
	return true;
}

function set_subcat2_list(form)
{
	form.id_subcat2.length=0;
	switch(form.id_subcat.value)
	{
<?php
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
?>
	}
	return true;
}

</script>

<div align=center>
<table class=list>
<tr class=list>
<th class=list id=framed>Импорт. категория</th>
<th class=list id=framed>Импорт. подкатегория</th>
<th class=list id=framed>Внутр. категория</th>
<th class=list id=framed>Внутр. подкатегория</th>
<th class=list id=framed>Внутр. под2категория</th>
</tr>
<?php
}

$num = 0;
$no_mapping_num = 0;
foreach($imp_subcat_names as $imp_cat_name => $imp_subcat_array )
{
	foreach( $imp_subcat_array as $temp => $imp_subcat_name )
	{
		$num++;
	
		if( isset($map_subcat_ids[$imp_cat_name][$imp_subcat_name]) && $map_subcat_ids[$imp_cat_name][$imp_subcat_name] )
		{
			$id_subcat2 = $map_subcat_ids[$imp_cat_name][$imp_subcat_name];
			$id_cat = $categories[$id_subcat2][0];
			$id_subcat = $categories[$id_subcat2][1];
			$temp_subcat_array = $subcat_names[$id_cat];
			$temp_subcat2_array = $subcat2_names[$id_subcat];			 		
		}
		else
		{
			$id_subcat = 0;
			$id_cat = 0;
			$temp_subcat_array = array();
			$temp_subcat2_array = array();
			$no_mapping_num++; 		
		}
		if( !$silent_mode ) {
			echo "<form name=\"form".$num."\" action=\"".$_SERVER['PHP_SELF']."\" method=".FORM_METHOD.">";
			echo "<input type=hidden name=action value=set_mapping>";
			echo "<input type=hidden name=cat_name value=\"".$imp_cat_name."\">";
			echo "<input type=hidden name=subcat_name value=\"".$imp_subcat_name."\">";
			echo "<tr class=list>\r\n";
			echo "<td class=list>\r\n";
			if( !$id_subcat )	echo "<font color=red>";
			echo $imp_cat_name;
			if( !$id_subcat )	echo "</font>";		
			echo "</td>\r\n";
			echo "<td class=list>\r\n";
			if( !$id_subcat )	echo "<font color=red>";
			echo $imp_subcat_name;
			if( !$id_subcat )	echo "</font>";		
			echo "</td>\r\n";
			echo "<td class=list>\r\n";
			echo web_get_output_webform_selectlist("id_cat", $cat_names, $id_cat, false, "set_subcat_list(document.form".$num.")");	
			echo "</td>\r\n";
			echo "<td class=list>\r\n";
			echo web_get_output_webform_selectlist("id_subcat", $temp_subcat_array, $id_subcat, false, "set_subcat2_list(document.form".$num.")");	
			echo "</td>\r\n";
			echo "<td class=list>\r\n";
			echo web_get_output_webform_selectlist("id_subcat2", $temp_subcat2_array, $id_subcat2);	
			echo "<input type=submit value=\"=\">";
			echo "</td>\r\n";
			echo "</tr>\r\n";
			echo "</form>\r\n";
		}
		else {
			if( !$id_subcat )
				echo "Без мэппинга: подкатегория '".$imp_cat_name."\\".$imp_subcat_name."'\n";
		}
	}
}

if( $silent_mode ) {
	if( $no_mapping_num ) {
		echo "Без мэппинга: ".$no_mapping_num." подкатегорий.";
		exit(6);
	}
}
else {
?>
</table>
<br>
<?php
if( $no_mapping_num )
	echo "<div align=center><font color=red>Без мэппинга: <b>".$no_mapping_num."</b></font></div><br>";
?>
<table border=0 width=90% align=center>
	<tr>
		<td align=left width=50%>
<?php
echo "<form name=\"form\" action=\""."step".($current_step-1).".php"."\" method=".FORM_METHOD.">\r\n";
echo "<div align=left>\r\n";
echo "<input type=submit value=\"Назад\">\r\n";
echo "</div>\r\n";
echo "</form>\r\n";
?>
		</td>
		<td align=right width=50%>
<?php
echo "<form name=\"form\" action=\"step".($current_step+1).".php\" method=".FORM_METHOD.">";
echo "<div align=right>\r\n";
echo "<input type=button value=\"Далее\" onClick=\"window.location='step".($current_step+1).".php#bottom'; return true;\">\r\n";
echo "</div>\r\n";
echo "</form>\r\n";
?>
		</td>
	</tr>
</table>
</div>
<a name=bottom>
<?php
// 7. Конец скрипта.
include("footer.php");
}
?>
