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
$current_step = 8;
$last_step = get_config_value($id_import_mod, "last_step");
if( $last_step === false )
	{ echo "<hr>Ошибка чтения конфигурационных данных. Экстренный выход."; exit(3); }

if( !$silent_mode) {
	$doc_title = "Шаг ".$current_step.": Перенос переводов между счетами в БД";
	//$doc_onLoad = "set_mode();";
}

// 1. флаги ошибок
$updating_invalid_value = 0;
$updating_error = 0;
$error_text = "";

// 2. подготовка переменных

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$action = web_get_request_value($_REQUEST, "action", 's');
$sort_field = web_get_request_value($_REQUEST, "sort_field", 's');
$sort_dsc = web_get_request_value($_REQUEST, "sort_dsc", 'i');

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

// 2.2 значения по умолчанию для переменных формы
switch( $sort_field )
{
	case "date":			break;
	case "account1":	break;
	case "account2":	break;
	case "value":			break;
	case "comment":		break;
	case "":
	default:	$sort_field =  "date";
						break;
}
if( $sort_dsc>1 )
	$sort_dsc = 1;

// 3. Предварительный этап
$sql_date_format = get_sql_date_format();
$sql_time_format = get_sql_time_format();

// 3.1 запрос счетов из мэппинга
$map_acc_ids = array();
$map_acc_names = array();
$query  = "SELECT id_account, name FROM import_map_acc WHERE id_import_mod=".$id_import_mod." ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса мэппинга счетов. Экстренный выход."; exit(5); }
while( $temp_array = db_fetch_num_array($res) )
{
	$id_account = (int)($temp_array[0]);
	$name = db_strip_slashes($temp_array[1]);
	$map_acc_ids[$name] = $id_account;
	$map_acc_names[$id_account] = $name;
}

// 4. Отработка действий в формой
// Если форма сработала
if( $action )
{
	$updating_invalid_value = 1;
	$error_text = "Неизвестное действие";
	$action = strtolower($action);
//	echo "action: ".$action."<hr>";

	// 4.1 проверка переданных полей формы
	if( $action=='import' )
	{
		foreach( $id_draft_array as $temp => $id_draft )
		{
			// берем данные из import_draft
			$query  = "SELECT id_draft,DATE_FORMAT(date, \"%Y-%m-%d\") as date_f,account,cat,value,comment, change_type, id_transaction FROM import_draft ";
			$query .= "WHERE id_draft=".$id_draft." ORDER BY date";
			$res = db_query($query);
			if( !$res )
				{ echo "<hr>Ошибка запроса импортируемых данных - 2. Экстренный выход."; exit(5); }
	
			$temp_array = db_fetch_assoc_array($res);
			if( !$temp_array )	continue;	// по-хорошему, такого не должно быть, но если кто-то поменял базу или подменял массив id_draft - то запросто
			$id_draft = (int)($temp_array['id_draft']);
			$date = $temp_array['date_f'];
			$account1 = db_strip_slashes($temp_array['account']);
			$account2 = db_strip_slashes($temp_array['cat']);
			$value = (float)($temp_array['value']);
			$comment = db_strip_slashes($temp_array['comment']);
			$id_transaction = (int)($temp_array['id_transaction']);

			if( $id_transaction )
			{
				// сразу запрашиваем данные из transactions по ссылке
				$query  = "SELECT id_transfer FROM transactions WHERE id_transaction=".$id_transaction;
				$res2 = db_query($query);
				if( !$res2 )
					{ echo "<hr>Ошибка запроса данных о транзакциях. Экстренный выход."; exit(5); }
				$data = db_fetch_num_array($res2);
				$id_transfer = (int)($data[0]);
				if( !$id_transfer )
					{ echo "<hr>Ошибка связности в имеющихся перемещениях между усчетами (id_transaction=$id_transaction). Экстренный выход."; exit(5); }
			}
			// обратный мэппинг 
			$id_account1 = $map_acc_ids[$account1];		
			$id_account2 = $map_acc_ids[$account2];		

			switch( $temp_array['change_type'])
			{
				case IMPORT_CHANGE_DEL: // delete from transactions
					$query  = "DELETE FROM transactions WHERE id_transaction=".$id_transaction;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка удаления имеющихся данных-1. Экстренный выход."; exit(5); }
					$query  = "DELETE FROM transactions WHERE id_transaction=".$id_transfer;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка удаления имеющихся данных-2. Экстренный выход."; exit(5); }
					$query  = "DELETE FROM import_draft WHERE id_draft=".$id_draft;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка удаления импортируемых данных - 1. Экстренный выход."; exit(5); }
					break;
				case IMPORT_CHANGE_CHG: // change transactions
					$query  = "UPDATE transactions SET ";
					$query  .= " id_account=".$id_account1.", ";
					$query  .= " date='".$date."', ";
					$query  .= " value='".-$value."', "; // списываем со счета
					$query  .= " comment='".db_add_slashes($comment)."' ";
					$query  .= " WHERE id_transaction=".$id_transaction;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка изменения имеющихся данных-1. Экстренный выход."; exit(5); }
					$query  = "UPDATE transactions SET ";
					$query  .= " id_account=".$id_account2.", ";
					$query  .= " date='".$date."', ";
					$query  .= " value='".$value."', "; // записываем на счет
					$query  .= " comment='".db_add_slashes($comment)."' ";
					$query  .= " WHERE id_transaction=".$id_transfer;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка изменения имеющихся данных-2. Экстренный выход."; exit(5); }
					$query  = "DELETE FROM import_draft WHERE id_draft=".$id_draft;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка удаления импортируемых данных - 2. Экстренный выход."; exit(5); }
					break;
				case IMPORT_CHANGE_ADD:  // add to transactions
				default:
					$query  = "INSERT INTO transactions(id_subcat2, id_account, date, quantity, value, comment) VALUES (";
					$query  .= "0, ".$id_account1.", '".$date."', 0, '".(-$value)."', "; // списываем со счета 
					$query  .= "'".db_add_slashes($comment)."') ";
					$res2 = db_query($query, true);
					if( $res2 === false || $res == 0 )
						{ echo "<hr>Ошибка добавления импортируемых данных - 1. Экстренный выход."; exit(5); }
					$id_transfer1 = $res2; 
					$query  = "INSERT INTO transactions(id_subcat2, id_account, date, quantity, value, comment, id_transfer) VALUES (";
					$query  .= "0, ".$id_account2.", '".$date."', 0, '".$value."', "; // записываем на счет 
					$query  .= "'".db_add_slashes($comment)."', ".$id_transfer1.") ";
					$res2 = db_query($query, true);
					if( $res2 === false || $res == 0 )
						{ echo "<hr>Ошибка добавления импортируемых данных - 2. Экстренный выход."; exit(5); }
					$id_transfer2 = $res2; 
					$query  = "UPDATE transactions SET id_transfer=".$id_transfer2;
					$query  .= " WHERE id_transaction=".$id_transfer1;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка изменения имеющихся данных (линковка перемещения). Экстренный выход."; exit(5); }
					$query  = "DELETE FROM import_draft WHERE id_draft=".$id_draft;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления импортируемых данных - 3. Экстренный выход."; exit(5); }
					break;
			} 			
		}
	}// if( $action=='import' )
} // if( $action )

if( !$silent_mode )
	include("header.php");

if( !$action )
{ //if( !$action )
	// форма грузится первый раз
	if( $last_step>$current_step )
		{ echo "<hr>Вы искусственно вернулись на этот шаг назад после выполнения необратимых шагов. Лучше <a href=\"../step1.php\">начать импорт данных сначала</a>."; exit(6); }	

	if( $last_step<$current_step )
	{
		// ограничение по датам импорта
		echo "1. Удаление лишних (по датам) импортируемых данных.<br>\r\n";

		$start_date = get_config_value($id_import_mod, "start_date");
		$finish_date = get_config_value($id_import_mod, "finish_date");
		
		// удаляем из draft все записи, которые не подходят по дате
		$query = "DELETE FROM import_draft WHERE date<'".convert_date2sql($start_date)."' OR date>'".convert_date2sql($finish_date)."'";
		$res = db_query($query);
		if( !$res )
			{ echo "<hr>Ошибка удаления лишних (по датам) импортируемых данных. Экстренный выход."; exit(5); }

		// составляем массив имеющихся записей о перемещениях
		//id_transaction, id_account1, id_account2, date, value, comment 
		$data_array = array();
		$transfers = array();
		$query  = "SELECT t1.id_transaction, t1.id_account, t1.id_transfer, ";
		$query .= "DATE_FORMAT(t1.date, \"%Y-%m-%d\") as date_f, t1.value, t1.comment FROM transactions AS t1 ";
		$query .= " INNER JOIN  transactions AS t2 ON ";
		$query .= " t2.id_transfer=t1.id_transaction ";
		$query .= " AND t1.id_transfer=t2.id_transaction ";
		$query .= " AND t1.date=t2.date ";
		$query .= " WHERE t1.date>='".convert_date2sql($start_date)."' AND t1.date<='".convert_date2sql($finish_date)."' ";
		$query .= " ORDER BY t1.date, t1.value";
		$res = db_query($query);
		if( !$res )
			{ echo "<hr>Ошибка запроса данных о транзакциях. Экстренный выход."; exit(5); }

		while( $temp_array = db_fetch_assoc_array($res) )
		{
			$id_transaction = (int)($temp_array['id_transaction']);
			$id_account = (int)($temp_array['id_account']);
			$id_transfer = (int)($temp_array['id_transfer']);
			$date = $temp_array['date_f'];
			$value = (float)($temp_array['value']);
			$comment = db_strip_slashes($temp_array['comment']);

			if( isset($transfers[$id_transaction]) )
			{
//				echo "Найдена ссылка на нас, как транфер: ".$id_transaction."<br>";
//				echo "Перенос со счета ".$transfers[$id_transaction][1]." на счет $id_account\r\n";
				$transfer = $transfers[$id_transaction];
				$index = $date."!".$transfer[1]."!".$id_account."!".$value;
					
				$data_array[$index][$transfer[0]] = array(
					'id_transaction' => $transfer[0],
					'id_transfer' => $id_transaction,
					'id_account1' => $transfer[1],
					'id_account2' => $id_account,
					'date' => $transfer[2],
					'value' => -$transfer[3], // value<0
					'comment' => $transfer[4],
				);
				unset($transfers[$id_transaction]);
			}
			else
			{
				$transfers[$id_transfer] = array($id_transaction, $id_account, $date, $value, $comment);
//				echo "Заносим ссылку на транфер ".$id_transfer."<br>";
			}  	
		}

//		echo "<pre>";
//		print_r($data_array);
	
		// составляем массив импортируемых записей 
		$query  = "SELECT id_draft,DATE_FORMAT(date, \"%Y-%m-%d\") as date_f,account,cat,value,comment FROM import_draft ";
		$query .= "WHERE id_trans_type=".TRANS_TYPE_ACC." AND change_type='".IMPORT_CHANGE_ADD."' ORDER BY date";
		$res = db_query($query);
		if( !$res )
			{ echo "<hr>Ошибка запроса импортируемых данных. Экстренный выход."; exit(5); }
	
		echo "<hr>2. Поиск совпадений между импортируемыми данными и уже имеющимися.<br>\r\n";
		flush();
		$import_2delete = array();
		$import_array = array();
		while( $temp_array = db_fetch_assoc_array($res) )
		{
			$id_draft = (int)($temp_array['id_draft']);
			$date = $temp_array['date_f'];
			$account1 = db_strip_slashes($temp_array['account']);
			$account2 = db_strip_slashes($temp_array['cat']);
			$value = (float)($temp_array['value']);
			$comment = db_strip_slashes($temp_array['comment']);
			
			// сравниваем значения
			$id_account1 = $map_acc_ids[$account1];
			$id_account2 = $map_acc_ids[$account2];

			$is_founded = 0;
			$index = $date."!".$id_account1."!".$id_account2."!".$value;

			if( isset($data_array[$index]) )	// данные найдены в обоих таблицах
			{
				foreach($data_array[$index] as $temp => $data)
				{
					// проверяем остальные поля - ищем первое совпадение
					if( $comment == $data['comment'] )
					{
						// полное совпадение
						$import_2delete[] = $id_draft; 
						// сразу удаляем из имеющихся данных
						unset($data_array[$index][$temp]);
						$is_founded = 1;
						break;					
					}
				}
			}
			if( !$is_founded )
			{	// ненайденные в имеющихся
				$import_array[] = array(
				'id_draft' => $id_draft,
				'date' => $date, 
				'id_account1' => $id_account1,
				'id_account2' => $id_account2,
				'value' => $value,
				'comment' => $comment	);
			}
			if( isset($data_array[$index]) && !count($data_array[$index]) )
				unset($data_array[$index]);
		}

//		echo "<pre>";
//		print_r($data_array);
//		print_r($import_array);
//		print_r($import_2delete);
	
		// удаление дубликатов из import_draft
		$count = count($import_2delete);
		if( $count )
			echo "Удалено совпадающих <b>".$count."</b> записей.<br>\r\n";
		else
			echo "Нет совпадающих записей.<br>\r\n";
		flush();
	
		$block = 50; // будем удалять 50 за раз
		$parts = (int)($count/$block);
		$rest = $count%$block;
		if( $rest )
		{
			$parts++;
			$rest = $block - $rest;
			while( $rest )
			{
				$import_2delete[] = 0;
				$rest--;
			}
		}
		for( $i=0; $i<$parts; $i++ )
		{
			$temp = $block;
			$query = "DELETE FROM import_draft WHERE id_draft IN (";
			for( $j=0; $j<$block; $j++ )
			{
				$temp--;
				$query .= $import_2delete[$i*$block+$j];
				if( $temp )
					$query .= ",";
			}
			$query .= ")";
//temp-dps
			$res = db_query($query);
			if( !$res )
				{ echo "<hr>Ошибка удаления совпадающих импортируемых данных. Экстренный выход."; exit(5); }
		}
	
		echo "<hr>3. Поиск измененных данных (одно поле).<br>\r\n";
		flush();
		// теперь $import_array новые или отличающиеся от $data_array данные
		// сравниваем каждый элемент с каждым и ищем изменения - допускаем, что может смениться только одно поле из всех:
		// дата, счет, категория, сумма, количество или комментарий
//		define('CHANGE_D', 1);
//		define('CHANGE_A1', 2);
//		define('CHANGE_A2', 4);
//		define('CHANGE_V', 8);
//		define('CHANGE_C', 16);
		$changed = 0;
	
		foreach( $import_array as $temp => $import_data )
		{
				$id_draft = $import_data['id_draft'];
				$date = $import_data['date']; 
				$id_account1 = $import_data['id_account1'];
				$id_account2 = $import_data['id_account2'];
				$value = $import_data['value'];
				$comment = $import_data['comment'];
		
				foreach( $data_array as $index => $data2 )
				{
					foreach( $data2 as $temp => $data )
					{
						$changes = 0;
						$bit_flag = 0;
						if( $data['date'] != $date )
							{ $changes++; $bit_flag |= CHANGE_D; }
						if( $data['id_account1'] != $id_account1 )
							{ $changes++; $bit_flag |= CHANGE_A1; }
						if( $data['id_account2'] != $id_account2 )
							{ $changes++; $bit_flag |= CHANGE_A2; }
						if( $data['value'] != $value )
							{ $changes++; $bit_flag |= CHANGE_V; }
						if( $data['comment'] != $comment )
							{ $changes++; $bit_flag |= CHANGE_C; }
						if( $bit_flag && $changes <= 1 )
						{
								// нашли только одно отличие, помечаем как измененное
								$id_transaction = $data['id_transaction'];
								$query = "UPDATE import_draft SET id_transaction=".$id_transaction.", change_type='".IMPORT_CHANGE_CHG."' WHERE id_draft=".$id_draft;
//temp-dps
								$res = db_query($query);
								if( !$res )
									{ echo "<hr>Ошибка изменения измененных импортируемых данных. Экстренный выход."; exit(5); }
								unset($data_array[$index][$temp]);
								$changed++;
								break;
						}
					}
					if( !count($data_array[$index]) )
						unset($data_array[$index]);
				}
		}
		if( $changed )
			echo "Найдено <b>".$changed."</b> записей с различающимся одним полем.<br>\r\n";
		else
			echo "Нет измененных записей (либо изменено больше, чем одно поле).<br>\r\n";

//		echo "<pre>";
//		print_r($data_array);
//		print_r($import_array);

		// теперь в $import_array остались только данные для добавления или измененные, а в $data_array - для удаления
		if( !$silent_mode) {
			echo "<hr>4. Поиск удаленных имеющихся данных.<br>\r\n";
			flush();
			// конечно, не поиск, а просто мы их заносим в import_draft и помечаем как "для удаления" 
			foreach( $data_array as $index => $data2 )
			{
				foreach( $data2 as $temp => $data )
				{
					$account1 = $map_acc_names[$data['id_account1']];	// обратный мэппинг - имя счета
					$account2 = $map_acc_names[$data['id_account2']];	// обратный мэппинг - имя счета
		
					$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type, id_transaction) ";
					$query .= " VALUES(";
					$query .= "\"".$data['date']."\", ";
					$query .= "\"".db_add_slashes($account1)."\", ";
					$query .= "\"".db_add_slashes($account2)."\", ";
					$query .= "\"\", ";
					$query .= "\"\", ";
					$query .= "0, ";
					$query .= "\"".$data['value']."\", ";
					$query .= "\"".db_add_slashes($data['comment'])."\", ";
					$query .= TRANS_TYPE_ACC.", ";	// перемещение
					$query .= "\"".IMPORT_CHANGE_DEL."\", ";
					$query .= $data['id_transaction'].")";
	//temp-dps				
					$res = db_query($query);
					if( !$res )
						{ echo "<hr>Ошибка занесения удаляемых данных. Экстренный выход."; exit(5); }
				}
			}
			echo "<hr><br>\r\n";
			flush();
		}
	}
	// сохраняем шаг
//temp-dps
	if( !set_config_value($id_import_mod, "last_step", $current_step) )
		{ echo "<hr>Ошибка занесения конфигурационных данных. Экстренный выход."; exit(3); }
} //if( !$action )


// 6. Заполнение формы
$query_order = "";
switch( $sort_field )
{
	case "date":			$query_order = "date";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", account, cat, value, comment"; break;
	case "account1":		$query_order = "account";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, cat, value, comment";	break;
	case "account2":		$query_order = "cat";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, value, comment";	break;
	case "value":			$query_order = "value";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, cat, comment"; break;
	case "comment":		$query_order = "comment";
	 									if( $sort_dsc )
											$query_order .= " DESC";
										$query_order .= ", date, account, cat, value"; break;
}

$query  = "SELECT id_draft,DATE_FORMAT(date, \"".$sql_date_format."\") as date_f,account,cat,value,comment,change_type,id_transaction FROM import_draft ";
$query .= "WHERE id_trans_type=".TRANS_TYPE_ACC;
if( $query_order )
	$query .= " ORDER BY ".$query_order;
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса импортируемых данных. Экстренный выход."; exit(5); }

if( !$silent_mode) {
	// 6. Вывод формы
	function echo_field($field_name, $field_text, $sort_field="", $sort_dsc="", $onclick_func="")
	{
		echo "<th class=list id=framed>";

		$str = "<a ";

		if( $onclick_func ) {
			$str .= "href=\"\" onClick='".$onclick_func."'";
		} else {
			$str .= "href=".$_SERVER['PHP_SELF']."?sort_field=".$field_name;
			if( $sort_field==$field_name ) {
				if( !$sort_dsc )
					$str .= "&sort_dsc=1";
			}
		}
		$str .= ">";
		echo $str;
		echo $field_text;
		if( $sort_field==$field_name )
		{
			if( $sort_dsc )
				echo "&nbsp;&#8657;";
			else
				echo "&nbsp;&#8659;";
			echo "</a>";
		}
		else
			echo "</a>"; 
		echo "</th>\r\n";
	}
	
        echo "<script lang='javascript'>\n";
        echo "function check_uncheck() {\n";
        echo "          var search_template = '^id_draft_[0-9]+$';\n";
        echo "          var elements = document.forms.form1.elements;\n";
        echo "          var state = true;\n";
        echo "          \n";
        echo "          if ( elements.mass_check_state.value == 0) {\n";
        echo "                  state = false;\n";
        echo "                  elements.mass_check_state.value = 1;\n";
        echo "          } else  {\n";
        echo "                  state = true;\n";
        echo "                  elements.mass_check_state.value = 0;\n";
        echo "          }\n";
        echo "                          \n";
        echo "          for (var i = 0; i < elements.length; i++) {\n";
        echo "                  if ( elements[i].type == 'checkbox' ) {\n";
        echo "                          str = elements[i].name;\n";
        echo "                          if( str.search(search_template) == 0 ) {\n";
        echo "                                  elements[i].checked = state;\n";
        echo "                          }\n";
        echo "                  }\n";
        echo "          }\n";
        echo "}\n";
        echo "</script>\n";

	echo "<div align=center>\r\n";
	echo "<form name=\"form1\" action=\"".$_SERVER['PHP_SELF']."\" method="."POST".">";
	echo "<input type=hidden name=action value=import>";
	echo "<input type=hidden name=mass_check_state value=1>";
	echo "<table class=list>\r\n";
	echo "<tr class=list>\r\n";
	echo_field("date", "Дата", $sort_field, $sort_dsc);
	echo_field("account1", "Со счета", $sort_field, $sort_dsc);
	echo_field("account2", "На счет", $sort_field, $sort_dsc);
	echo_field("value", "Сумма", $sort_field, $sort_dsc);
	echo_field("comment", "Комментарий", $sort_field, $sort_dsc);
	echo_field("mass_check_uncheck", "C", "", $sort_dsc, "check_uncheck(); return false;");
	echo "</tr>\r\n";
}
//echo "</table><pre>";

$id_draft_array_temp = array(); // for silent mode
$num = 0;
while( $temp_array = db_fetch_assoc_array($res) )
{
//	print_r($temp_array);
	$id_draft = (int)($temp_array['id_draft']);
	$id_draft_array_temp[] = $id_draft; 
	$date = $temp_array['date_f'];
	$account1 = db_strip_slashes($temp_array['account']);
	$account2 = db_strip_slashes($temp_array['cat']);
	$value = (float)($temp_array['value']);
	$comment = db_strip_slashes($temp_array['comment']);
	$change_type = db_strip_slashes($temp_array['change_type']);
	$id_transaction = (int)($temp_array['id_transaction']);

	// обратный мэппинг 
	$id_account1 = $map_acc_ids[$account1];		
	$id_account2 = $map_acc_ids[$account2];		

	switch( $change_type )
	{
		case IMPORT_CHANGE_DEL: $row_color = '#f08080'; $check = 0; $changed = 0; break;
		case IMPORT_CHANGE_CHG: $row_color = '#8080f0'; $check = 1; $changed = 1; break;
		case IMPORT_CHANGE_ADD: 
		default:				
										$row_color = '#80f080'; $check = 1; $changed = 0; break;
	}

	if( $changed )
	{
		$query  = "SELECT t1.id_account as id_account1, t2.id_account as id_account2, ";
		$query .= " DATE_FORMAT(t1.date, \"".$sql_date_format."\") as date_f, t1.value, t1.comment FROM transactions AS t1 ";
		$query .= " INNER JOIN  transactions AS t2 ON ";
		$query .= " t2.id_transfer=t1.id_transaction ";
		$query .= " AND t1.id_transfer=t2.id_transaction ";
		$query .= " AND t1.date=t2.date ";
		$query .= " WHERE t1.id_transaction=".$id_transaction;
		$query .= " ORDER BY t1.date, t1.value";
		$res2 = db_query($query);
		if( !$res2 )
			{ echo "<hr>Ошибка запроса данных о транзакциях. Экстренный выход."; exit(5); }
		$data = db_fetch_assoc_array($res2);
		
		$id_account1_new = (int)($data['id_account1']);		
		$id_account2_new = (int)($data['id_account2']);		
		
		if( $data['date_f'] != $date )
			$date = $data['date_f']."<br>&#8658;&nbsp;<b>".$date."</b>";
		if( $id_account1_new != $id_account1 )
			$account1 = $map_acc_names[$id_account1_new]."<br>&#8658;&nbsp;<b>".$account1."</b>";
		if( $id_account2_new != $id_account2 )
			$account2 = $map_acc_names[$id_account2_new]."<br>&#8658;&nbsp;<b>".$account2."</b>";
		if( (0-$data['value']) != $value )
			$value = (0-$data['value'])."<br>&#8658;&nbsp;<b>".$value."</b>";
		if( $data['comment'] != $comment )
			$comment = $data['comment']."<br>&#8658;&nbsp;<b>".$comment."</b>";
	}

	if( !$silent_mode) {
		echo "<tr class=list bgcolor=".$row_color.">\r\n";
		echo "<td class=list id=framed>".$date."</td>\r\n";
		echo "<td class=list id=framed>".$account1."</td>\r\n";
		echo "<td class=list id=framed>".$account2."</td>\r\n";
		echo "<td class=list id=framed><div align=center>".$value."</div></td>\r\n";
		echo "<td class=list id=framed><span id=compact>".$comment."</span></td>\r\n";
		echo "<td class=list id=framed>\r\n";
		echo "<input type=checkbox name=\"id_draft_".$id_draft."\"";
		if( $action )
		{
			if( in_array($id_draft, $id_draft_array) ) echo " checked";
		}
		else
		{
			if( $check ) echo " checked";
		}
		echo ">";
		echo "</td>\r\n";
		echo "</tr>\r\n";
	}
	$num++;
}

if( !$silent_mode) {
	echo "</table><br>\r\n";
	if( $num )
		echo "<input type=submit value=\"Импортировать выделенное\">\r\n";
	echo "</form>\r\n";
}
// 5. Вывод текущего дисбаланса счетов

// 3.2 запрос внутренних счетов
$account_names = array();
$query  = "SELECT id_account, name, start_value, homebuh_start_value FROM accounts WHERE is_debt='0' ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса внутренних счетов. Экстренный выход."; exit(5); }
while( $temp_array = db_fetch_num_array($res) ) {
	$account_names[(int)($temp_array[0])] = array(db_strip_slashes($temp_array[1]), (double)$temp_array[2]);
	$account_start_balances2[(int)($temp_array[0])] = (double)$temp_array[3];
}

// 5.1 запрос баланса счетов ДБ
$account_balances = array();
$query  = "SELECT id_account, balance, start_balance from import_map_acc WHERE id_import_mod=".$id_import_mod;
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса баланса импортируемых счетов. Экстренный выход."; exit(5); }
while( $temp_array = db_fetch_num_array($res) ) {
	$account_balances[(int)$temp_array[0]] = (double)$temp_array[1];
	$account_start_balances[(int)$temp_array[0]] = (double)$temp_array[2];
}
// (end) 5.1 запрос баланса счетов ДБ

// 5.2 запрос баланса счетов PBP по транзакциям
$account_balances2 = array();
$query = "SELECT id_account, sum(value) FROM transactions GROUP BY id_account";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса баланса счетов PBP. Экстренный выход."; exit(5); }
while( $temp_array=db_fetch_num_array($res) )
	$account_balances2[(int)$temp_array[0]] = (double)$temp_array[1];
// (end) 5.2 запрос баланса счетов

if( !$silent_mode) {
	echo "<br>";
	echo "<div align=center>\r\n";
	echo "<h3>Разница в балансах счетов</h3>\r\n";
	echo "<table class=list>\r\n";
	echo "<tr class=list>\r\n";
	echo_field("name", "Счет");
	echo_field("balance1", "Баланс ДБ");
	echo_field("balance2", "Баланс PBP");
	echo_field("difference", "Разница");
	echo "</tr>\r\n";
}
$balanced = true;

foreach($account_names as $id_account => $account_info ) {
	$account_name = $account_info[0];
	$account_start_value = $account_info[1];
	if( isset($account_balances[$id_account]) )
		$balance1 = (float)$account_balances[$id_account];
	else
		$balance1 = 0;
	if( isset($account_start_balances[$id_account]) )
		$start_balance_hb = (float)$account_start_balances[$id_account];
	else
		$start_balance_hb = 0;

	if( isset($account_balances2[$id_account]) ) {
		$balance2 = round((float)$account_balances2[$id_account] + $account_start_value, 2);
		$account_balances2[$id_account] = $balance2;
	}
	else
		$balance2 = 0;
	if( isset($account_start_balances2[$id_account]) )
		$start_balance_pbp = (float)$account_start_balances2[$id_account];
	else
		$start_balance_pbp = 0;

	if( $balance1 <> $balance2 ) {
		$balanced = false;
		$diff = round($balance1 - $balance2, 2);
		if( !$silent_mode) {
			echo "<tr class=list>\r\n";
			echo "<td class=list id=framed><b>".$account_name."<b></td>\r\n";
			echo "<td class=list id=framed><div align=right>".$balance1."</div></td>\r\n";
			echo "<td class=list id=framed><div align=right>".$balance2."</div></td>\r\n";
			echo "<td class=list id=framed><div align=right><font color=red>".$diff."</font></div></td>\r\n";
			echo "</tr>\r\n";
		}
		else{
			if( $diff )
				echo "Дисбаланс для счета '".$account_name."': HB=".$balance1.", PBP=".$balance2.", разница=".$diff."\n";
		}
	}

	// check for changing is start_balanse (HB)
	if( $start_balance_hb <> $start_balance_pbp ) {
		$diff = round($start_balance_hb - $start_balance_pbp, 2);
		if( !$silent_mode) {
			echo "<tr class=list>\r\n";
			echo "<td class=list id=framed><b>".$account_name."<b><br>(стартовый баланс)</td>\r\n";
			echo "<td class=list id=framed><div align=right>".$start_balance_hb."</div></td>\r\n";
			echo "<td class=list id=framed><div align=right>".$start_balance_pbp."</div></td>\r\n";
			echo "<td class=list id=framed><div align=right><font color=red>".$diff."</font></div></td>\r\n";
			echo "</tr>\r\n";
		}
		else{
			if( $diff )
				echo "Изменился стартовый баланс для счета '".$account_name."': HB=".$start_balance_hb.", сохраненный=".$start_balance_pbp.", разница=".$diff."\n";
		}
		// update changes
		$query  = "UPDATE accounts SET ";
		$query  .= " homebuh_start_value='".$start_balance_hb."'";
		$query  .= " WHERE id_account=".$id_account;
		$res2 = db_query($query);
		if( !$res2 )
			{ echo "<hr>Ошибка изменения имеющихся данных-3. Экстренный выход."; exit(5); }
		//echo "\n".$query."\n";
	}
}

if( $silent_mode ){
	if( $balanced ) {
		echo "Разницы в балансах счетов нет.\n";
		$exit_code = 0;
	}
	else
		//$exit_code = 6;	// disbalanced
		echo "Разбалансировка, но пока временно игнорируем.\n";
		$exit_code = 0;
}
else {

	if( $balanced ) {
		echo "<tr class=list>\r\n";
		echo "<td class=list id=framed colspan=4><div align=center>Разницы в балансах счетов нет</div></td>\r\n";
		echo "</tr>\r\n";
	}
	
	echo "</table>\r\n";
	echo "</div>\r\n";

?>
<br>
<table border=0 width=90% align=center>
	<tr>
		<td align=left width=50%>
		</td>
		<td align=right width=50%>
<?php
//if( $last_step == $current_step )
if( 0 ) {
	echo "<form name=\"form2\" action=\"step".($current_step+1).".php\" method=".FORM_METHOD.">";
	echo "<div align=right>\r\n";
	echo "<input type=button value=\"Далее\" onClick=\"window.location='step".($current_step+1).".php#bottom'; return true;\">\r\n";
	echo "</div>\r\n";
	echo "</form>\r\n";
}
else {
	echo "<form name=\"form2\" action=\"step".$current_step.".php\" method=".FORM_METHOD.">";
	echo "<div align=right>\r\n";
//	echo "<input type=button value=\"Закончить\" onClick=\"document.forms.form1.action.value='finish'; document.forms.form1.submit(); return true;\">\r\n";
	echo "</div>\r\n";
	echo "</form>\r\n";
}
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
