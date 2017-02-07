<?php
require_once('../../functions/config.php');
require_once('../../functions/auth.php');
require_once('../../functions/func_db.php');
require_once('../../functions/func_web.php');
require_once("../../functions/func_time.php");
require_once("../../functions/func_finance.php");
require_once("../functions/import_config.php");

set_time_limit(0);

if(!isset($silent_mode))
	$silent_mode = false;

$module_name = "HomeBuh_paradox";
$id_import_mod = get_module_id($module_name);
$current_step = 6;
$last_step = get_config_value($id_import_mod, "last_step");
if( $last_step === false )
	{ echo "<hr>Ошибка чтения конфигурационных данных. Экстренный выход."; exit(3); }

if( !$silent_mode ) {
	$doc_title = "Шаг ".$current_step.": Перенос расходов в БД";
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

// 3.2 запрос названий категорий и подкатегорий из мэппинга
$map_subcat2_ids = array();
$map_subcat_names = array();
$query  = "SELECT id_subcat2, cat, subcat, subcat2 FROM import_map_cat ";
$query .= "WHERE id_import_mod=".$id_import_mod." AND id_trans_type=".TRANS_TYPE_EXP." ORDER BY cat, subcat, subcat2";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса мэппинга категорий. Экстренный выход."; exit(5); }
while( $temp_array = db_fetch_num_array($res) )
{
	$id_subcat2 = (int)($temp_array[0]);
	$cat = db_strip_slashes($temp_array[1]);
	$subcat = db_strip_slashes($temp_array[2]);
	$subcat2 = db_strip_slashes($temp_array[3]);
	$map_subcat2_ids[$cat][$subcat][$subcat2] = $id_subcat2;
	$map_subcat_names[$id_subcat2] = array($cat, $subcat, $subcat2);
}

// 3.3 запрос внутренних категорий
$cat_names = array();
$subcat_names = array();
$categories  = array();

$query  = "SELECT c.id_exp_cat, c.name, sc.id_exp_subcat, sc.name, id_exp_subcat2, sc2.name FROM expense_subcats2 AS sc2 ";
$query .= "INNER JOIN expense_subcats AS sc ON sc.id_exp_subcat=sc2.id_exp_subcat ";
$query .= "INNER JOIN expense_cats AS c ON sc.id_exp_cat=c.id_exp_cat ";
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
	$subcat_names[$id_subcat] = db_strip_slashes($temp_array[3]);
	$subcat2_names[$id_subcat2] = db_strip_slashes($temp_array[5]);
	$categories[$id_subcat2] = array($id_cat, $id_subcat); 
}

// 3.4 запрос внутренних счетов
$account_names = array();
$query  = "SELECT id_account, name FROM accounts ORDER BY name";
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса внутренних счетов. Экстренный выход."; exit(5); }
while( $temp_array = db_fetch_num_array($res) )
	$account_names[(int)($temp_array[0])] = db_strip_slashes($temp_array[1]);

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
		$imported_subcats = array();
		foreach( $id_draft_array as $temp => $id_draft )
		{
			// берем данные из import_draft
			$query  = "SELECT id_draft,DATE_FORMAT(date, \"%Y-%m-%d\") as date_f,account,cat,subcat,subcat2,quantity,value,comment, change_type, id_transaction FROM import_draft ";
			$query .= "WHERE id_draft=".$id_draft." ORDER BY date";
			$res = db_query($query);
			if( !$res )
				{ echo "<hr>Ошибка запроса импортируемых данных - 2. Экстренный выход."; exit(5); }
	
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
			
			list($id_cat, $id_subcat) = $categories[$id_subcat2];
			$imported_subcats[] = $id_subcat;

			switch( $temp_array['change_type'])
			{
				case IMPORT_CHANGE_DEL: // delete from transactions
					$query  = "DELETE FROM transactions WHERE id_transaction=".$id_transaction;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления имеющихся данных. Экстренный выход."; exit(5); }
					$query  = "DELETE FROM import_draft WHERE id_draft=".$id_draft;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления импортируемых данных - 1. Экстренный выход."; exit(5); }
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
						{ echo "<hr>Ошибка запроса удаления имеющихся данных. Экстренный выход."; exit(5); }
					$query  = "DELETE FROM import_draft WHERE id_draft=".$id_draft;
					$res2 = db_query($query);
					if( !$res2 )
						{ echo "<hr>Ошибка запроса удаления импортируемых данных - 2. Экстренный выход."; exit(5); }
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
						{ echo "<hr>Ошибка запроса удаления импортируемых данных - 3. Экстренный выход."; exit(5); }
					break;
			} 			
		}
		
		// mark rare expenses
//		print_r($imported_subcats);
		foreach( $imported_subcats as $temp => $id_subcat ) {
			$amount_treshold = mark_rare_expenses($id_subcat);
			if( is_bool($amount_treshold) && !$amount_treshold )
				echo "<font color=red>Ошибка на поиске нетипичных расходов на подкатегории с id=".$id_subcat."</font>";
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

		// составляем массив имеющихся записей о расходах 
		$data_array = array();
		$query  = "SELECT id_transaction,id_subcat2,id_account,DATE_FORMAT(date, \"%Y-%m-%d\") as date_f,quantity,value,comment FROM transactions ";
		$query .= "WHERE value<0 AND id_transfer=0 AND date>='".convert_date2sql($start_date)."' AND date<='".convert_date2sql($finish_date)."' ORDER BY date";
		$res = db_query($query);
		if( !$res )
			{ echo "<hr>Ошибка запроса данных о транзакциях. Экстренный выход."; exit(5); }
		while( $temp_array = db_fetch_assoc_array($res) )
		{
			$id_transaction = (int)($temp_array['id_transaction']);
			$id_subcat2 = (int)($temp_array['id_subcat2']);
			$id_account = (int)($temp_array['id_account']);
			$date = $temp_array['date_f'];
			$quantity = (int)($temp_array['quantity']);
			$value = -(float)($temp_array['value']);
			$comment = db_strip_slashes($temp_array['comment']);
			$index = $date."!".$id_account."!".$id_subcat2."!".$value;
			$data_array[$index][$id_transaction] = array(
				'id_transaction' => $id_transaction,
				'date' => $date, 
				'id_subcat2' => $id_subcat2,
				'id_account' => $id_account,
				'quantity' => $quantity,
				'value' => $value,
				'comment' => $comment	);
		}
		
//		echo "<pre>";
//		print_r($data_array);
	
		// составляем массив импортируемых записей 
		$query  = "SELECT id_draft,DATE_FORMAT(date, \"%Y-%m-%d\") as date_f,account,cat,subcat,subcat2,quantity,value,comment FROM import_draft ";
		$query .= "WHERE id_trans_type=".TRANS_TYPE_EXP." AND change_type='".IMPORT_CHANGE_ADD."' ORDER BY date";
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
			$account = db_strip_slashes($temp_array['account']);
			$cat = db_strip_slashes($temp_array['cat']);
			$subcat = db_strip_slashes($temp_array['subcat']);
			$subcat2 = db_strip_slashes($temp_array['subcat2']);
			$quantity = (int)($temp_array['quantity']);
			$value = (float)($temp_array['value']);
			$comment = db_strip_slashes($temp_array['comment']);
			
			// сравниваем значения
			$id_account = $map_acc_ids[$account];
			$id_subcat2 = $map_subcat2_ids[$cat][$subcat][$subcat2];
	
			$is_founded = 0;
			$index = $date."!".$id_account."!".$id_subcat2."!".$value;
			if( isset($data_array[$index]) )	// данные найдены в обоих таблицах
			{
				foreach($data_array[$index] as $temp => $data)
				{
					// проверяем остальные поля - ищем первое совпадение
					if( $quantity == $data['quantity'] && $comment == $data['comment'] )
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
				'id_subcat2' => $id_subcat2,
				'id_account' => $id_account,
				'quantity' => $quantity,
				'value' => $value,
				'comment' => $comment	);
			}
			if( isset($data_array[$index]) && !count($data_array[$index]) )
				unset($data_array[$index]);
		}

//		echo "<pre>";
//		print_r($data_array);
//		print_r($import_array);
	
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
//		define('CHANGE_A', 2);
//		define('CHANGE_S', 4);
//		define('CHANGE_V', 8);
//		define('CHANGE_Q', 16);
//		define('CHANGE_C', 32);
		$changed = 0;
	
		foreach( $import_array as $temp => $import_data )
		{
				$id_draft = $import_data['id_draft'];
				$date = $import_data['date']; 
				$id_subcat2 = $import_data['id_subcat2'];
				$id_account = $import_data['id_account'];
				$quantity = $import_data['quantity'];
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
						if( $data['id_account'] != $id_account )
							{ $changes++; $bit_flag |= CHANGE_A; }
						if( $data['id_subcat2'] != $id_subcat2 )
							{ $changes++; $bit_flag |= CHANGE_S; }
						if( $data['value'] != $value )
							{ $changes++; $bit_flag |= CHANGE_V; }
						if( $data['quantity'] != $quantity )
							{ $changes++; $bit_flag |= CHANGE_Q; }
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
		if(!$silent_mode ) {// в автоматическом режиме данные из БД не удаляются, так что их и заносить/помечать смысла нет
			echo "<hr>4. Поиск удаленных имеющихся данных.<br>\r\n";
			flush();
			// конечно, не поиск, а просто мы их заносим в import_draft и помечаем как "для удаления" 
			foreach( $data_array as $index => $data2 )
			{
				foreach( $data2 as $temp => $data )
				{
					$id_subcat2 = $data['id_subcat2'];
					$account = $map_acc_names[$data['id_account']];	// обратный мэппинг - имя счет
					$cat = $map_subcat_names[$id_subcat2][0];	// обратный мэппинг - имя категории
					$subcat = $map_subcat_names[$id_subcat2][1];	// обратный мэппинг - имя подкатегории
					$subcat2 = $map_subcat_names[$id_subcat2][2];	// обратный мэппинг - имя подкатегории
		
					$query  = "INSERT INTO import_draft(date, account, cat,	subcat, subcat2, quantity, value, comment, id_trans_type, change_type, id_transaction) ";
					$query .= " VALUES(";
					$query .= "\"".$data['date']."\", ";
					$query .= "\"".db_add_slashes($account)."\", ";
					$query .= "\"".db_add_slashes($cat)."\", ";
					$query .= "\"".db_add_slashes($subcat)."\", ";
					$query .= "\"\", ";
					$query .= "".$data['quantity'].", ";
					$query .= "\"".$data['value']."\", ";
					$query .= "\"".db_add_slashes($data['comment'])."\", ";
					$query .= TRANS_TYPE_EXP.", ";	// расходы
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
	if( !set_config_value($id_import_mod, "last_step", $current_step) )
		{ echo "<hr>Ошибка занесения конфигурационных данных. Экстренный выход."; exit(3); }
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

$query  = "SELECT id_draft,DATE_FORMAT(date, \"".$sql_date_format."\") as date_f,account,cat,subcat,subcat2,quantity,value,comment,change_type,id_transaction FROM import_draft ";
$query .= "WHERE id_trans_type=".TRANS_TYPE_EXP;
if( $query_order )
	$query .= " ORDER BY ".$query_order;
$res = db_query($query);
if( !$res )
	{ echo "<hr>Ошибка запроса импортируемых данных. Экстренный выход."; exit(5); }

// 6. Вывод формы
if( !$silent_mode ) {
	function echo_field($field_name, $field_text, $sort_field, $sort_dsc, $onclick_func="")
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
	echo "		var search_template = '^id_draft_[0-9]+$';\n";
	echo "		var elements = document.forms.form1.elements;\n";
	echo "		var state = true;\n";
	echo "		\n";
	echo "		if ( elements.mass_check_state.value == 0) {\n";
	echo "			state = false;\n";
	echo "			elements.mass_check_state.value = 1;\n";
	echo "		} else  {\n";
	echo "			state = true;\n";
	echo "			elements.mass_check_state.value = 0;\n";
	echo "		}\n";
	echo "				\n";
	echo "		for (var i = 0; i < elements.length; i++) {\n";
	echo "			if ( elements[i].type == 'checkbox' ) {\n";
	echo "				str = elements[i].name;\n";
	echo "				if( str.search(search_template) == 0 ) {\n";
	echo "					elements[i].checked = state;\n";
	echo "				}\n";
	echo "			}\n";
	echo "		}\n";
	echo "}\n";
	echo "</script>\n";

	echo "<div align=center>\r\n";
	echo "<form name=\"form1\" action=\"".$_SERVER['PHP_SELF']."\" method="."POST".">";
	echo "<input type=hidden name=action value=import>";
	echo "<input type=hidden name=mass_check_state value=1>";
	echo "<table class=list>\r\n";
	echo "<tr class=list>\r\n";
	echo_field("date", "Дата", $sort_field, $sort_dsc);
	echo_field("account", "Счет", $sort_field, $sort_dsc);
	echo_field("cat", "Категория", $sort_field, $sort_dsc);
	echo_field("subcat", "Подкатегория", $sort_field, $sort_dsc);
	echo_field("subcat2", "Под2категория", $sort_field, $sort_dsc);
	echo_field("quantity", "Кол.", $sort_field, $sort_dsc);
	echo_field("value", "Сумма", $sort_field, $sort_dsc);
	echo_field("comment", "Комментарий", $sort_field, $sort_dsc);
	echo_field("mass_check_uncheck", "C", "", $sort_dsc, "check_uncheck(); return false;");
	echo "</tr>\r\n";
}
//echo "</table><pre>";

$id_draft_array_temp =array(); // for silent mode 
$num = 0;
while( $temp_array = db_fetch_assoc_array($res) )
{
//	print_r($temp_array);
	$id_draft = (int)($temp_array['id_draft']);
	$id_draft_array_temp[] = $id_draft; 
	$date = $temp_array['date_f'];
	$account = db_strip_slashes($temp_array['account']);
	$cat = db_strip_slashes($temp_array['cat']);
	$subcat = db_strip_slashes($temp_array['subcat']);
	$subcat2 = db_strip_slashes($temp_array['subcat2']);
	$quantity = (int)($temp_array['quantity']);
	$value = (float)($temp_array['value']);
	$comment = db_strip_slashes($temp_array['comment']);
	$change_type = db_strip_slashes($temp_array['change_type']);
	$id_transaction = (int)($temp_array['id_transaction']);

	// мэппинг 
	$id_account = $map_acc_ids[$account];		
	$id_subcat2 = $map_subcat2_ids[$cat][$subcat][$subcat2];

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
		$query  = "SELECT id_subcat2,id_account,DATE_FORMAT(date, \"".$sql_date_format."\") as date_f,quantity,value,comment FROM transactions ";
		$query .= "WHERE id_transaction=".$id_transaction;
		$res2 = db_query($query);
		if( !$res2 )
			{ echo "<hr>Ошибка запроса данных о транзакциях. Экстренный выход."; exit(5); }
		$data = db_fetch_assoc_array($res2);
		
		$id_account_new = (int)($data['id_account']);		
		$id_subcat2_new = (int)($data['id_subcat2']);
		
		if( $data['date_f'] != $date )
			$date = $data['date_f']."<br>&#8658;&nbsp;<b>".$date."</b>";
		if( $id_account_new != $id_account )
			$account = $map_acc_names[$id_account_new]."<br>&#8658;&nbsp;<b>".$account."</b>";
		if( $id_subcat2_new != $id_subcat2 )
		{
			if( $cat <> $map_subcat_names[$id_subcat2_new][0] )
				$cat = $map_subcat_names[$id_subcat2_new][0]."<br>&#8658;&nbsp;<b>".$cat."</b>";
			if( $subcat <> $map_subcat_names[$id_subcat2_new][1] )
				$subcat = $map_subcat_names[$id_subcat2_new][1]."<br>&#8658;&nbsp;<b>".$subcat."</b>";
			if( $subcat2 <> $map_subcat_names[$id_subcat2_new][2] )
				$subcat2 = $map_subcat_names[$id_subcat2_new][2]."<br>&#8658;&nbsp;<b>".$subcat2."</b>";
		}
		if( (0-$data['value']) != $value )
			$value = (0-$data['value'])."<br>&#8658;&nbsp;<b>".$value."</b>";
		if( $data['quantity'] != $quantity )
			$quantity = $data['quantity']."<br>&#8658;&nbsp;<b>".$quantity."</b>";
		if( $data['comment'] != $comment )
			$comment = $data['comment']."<br>&#8658;&nbsp;<b>".$comment."</b>";
	}

	if( !$silent_mode ) {
		echo "<tr class=list bgcolor=".$row_color.">\r\n";
		echo "<td class=list id=framed>".$date."</td>\r\n";
		echo "<td class=list id=framed>".$account."</td>\r\n";
		echo "<td class=list id=framed>".$cat."</td>\r\n";
		echo "<td class=list id=framed>".$subcat."</td>\r\n";
		echo "<td class=list id=framed>".$subcat2."</td>\r\n";
		echo "<td class=list id=framed><div align=center>".$quantity."</div></td>\r\n";
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

if( !$silent_mode ) {
echo "</table><br>\r\n";
echo "<input type=submit value=\"Импортировать выделенное\">\r\n";
echo "</form>\r\n";
?>
<br>
<table border=0 width=90% align=center>
	<tr>
		<td align=left width=50%>
		</td>
		<td align=right width=50%>
<?php
if( $last_step == $current_step || !$num )
{
	echo "<form name=\"form2\" action=\"step".($current_step+1).".php\" method=".FORM_METHOD.">";
	echo "<div align=right>\r\n";
	echo "<input type=button value=\"Далее\" onClick=\"window.location='step".($current_step+1).".php#bottom'; return true;\">\r\n";
	echo "</div>\r\n";
	echo "</form>\r\n";
}
?>
		</td>
	</tr>
</table></div>
<a name=bottom>
<?php
// 7. Конец скрипта.
include("footer.php");
}
?>
