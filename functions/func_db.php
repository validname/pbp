<?
// функции работы с БД MySQL

require_once("config.php");

// открывает соединение к базе
function db_open()
{
	global $db_cfg;

	$link = @mysql_connect($db_cfg['host'], $db_cfg['user'], $db_cfg['pass']);
	if( $link !== false )
	{
		mysql_select_db($db_cfg['db'], $link);
		if( @mysql_select_db($db_cfg['db'], $link) )
		{
			$db_cfg['link'] = $link;
			$result = true;
		}
		else
		{
			echo "Не существует нужной БД, либо она неправильно прописана в конфигурационных файлах. Ошибка MySQL: ".mysql_error();
			$result = false;
		}
		$result |= @mysql_query("SET NAMES utf8");
//		$result |= @mysql_query("SET character_set_client=utf8");
//		$result |= @mysql_query("SET character_set_connection=utf8");
//		$result |= @mysql_query("SET character_set_database=utf8");
//		$result |= @mysql_query("SET character_set_results=utf8");
		if( $result == false )
			echo "Не удалось поставить кодировку обмена. Ошибка MySQL: ".mysql_error();
		return $result;
	}
	else
	{
		echo "Нет соединения с БД MySQL, проверьте, что она запущена. Ошибка MySQL: ".mysql_error();
		return false;
	}
}

// запрос к БД, использует заранее открытое соединение
function db_query($query, $insert_mode = false)
{
	global $db_cfg;

	if( !(isset($db_cfg['link']) && $db_cfg['link']) )
	{
		if( !db_open() )
			return false;
	}
	$link = $db_cfg['link'];
	$result = @mysql_query($query, $link);
	if( $result === false )	// error
	{
		$query = addslashes($query);
		echo "На запросе (".$query.") произошла ошибка: ".mysql_error();
	}
	else
	{
		if( $insert_mode === true )
			$result = @mysql_insert_id($link);
	}
	return $result;
}

// проверяет результат запроса: выполнен ли он и есть ли результат в выборке
function db_check_select_result($query_result)
{
	if( $query_result == false )
		return false;

	if( !db_num_rows($query_result) )
		return false;

	return true;
}

// возвращает количество строк в результате выполнения запроса выборки (SELECT)
function db_num_rows($query_result)
{
	if( $query_result )
		return @mysql_num_rows($query_result);
	else
		return 0;
}

// возвращает ассоциативный массив из строки результата запроса
function db_fetch_assoc_array($query_result)
{
	if( $query_result )
		return @mysql_fetch_assoc($query_result);
	else
		return false;
}

// возвращает числовой массив из строки результата запроса
function db_fetch_num_array($query_result)
{
	if( $query_result )
		return @mysql_fetch_row($query_result);
	else
		return false;
}

// возвращает содержимое указанной ячейки для запроса
function db_get_cell($query_result, $row, $field=0)
{
	if( $query_result )
		return @mysql_result($query_result, $row, $field);
	else
		return false;
}

// добавляет '\' перед '\'
function db_add_slashes($string)
{
	global $html_chars_untrans_table;
	
	$string = str_replace("\\", "\\\\", $string);
	$string = addslashes($string);
	return $string;
}

// just wrapper
function db_strip_slashes($string)
{
	global $html_chars_trans_table;
	
	$string = stripslashes($string); 
	return $string;
}

?>
