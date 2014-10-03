<?
require_once('../../functions/config.php');
require_once('../../functions/auth.php');
require_once('../../functions/func_db.php');
require_once('../../functions/func_web.php');
require_once("../../functions/func_time.php");

### changeflags
define('CHANGE_D', 1);
define('CHANGE_A', 2);
define('CHANGE_A1', 2);
define('CHANGE_A2', 4);
define('CHANGE_S', 8);
define('CHANGE_V', 16);
define('CHANGE_C', 32);
define('CHANGE_Q', 64);

function get_module_id($module_name)
{
	$query = "SELECT id_import_mod FROM import_modules WHERE name=\"".$module_name."\"";
	$result = db_query($query);
	if( !$result )
		return false;
	else
	{
		$temp_array = db_fetch_num_array($result);
		return (int)($temp_array[0]);
	}
}

function get_config_value($id_import_mod, $name)
{
	$query = "SELECT value FROM import_config WHERE id_import_mod=".$id_import_mod." AND name='".db_add_slashes($name)."'"; 
	$result = db_query($query);
	if( !$result )
		return false;
	else
	{
		if( !$temp_array = db_fetch_num_array($result) )
			return false;
		else
			return db_strip_slashes($temp_array[0]);
	}
}

function set_config_value($id_import_mod, $name, $value)
{
	$query_where = " WHERE id_import_mod=".$id_import_mod." AND name='".db_add_slashes($name)."'"; 
	// пытаемся прочитать значение  
	$query = "SELECT value FROM import_config ";
	$query .= $query_where; 
	$result = db_query($query);
	if( !$result )
		return false;
	else
	{//if( $result )
		$need2update = false;
		$inserting = true;
		if( !$temp_array = db_fetch_num_array($result) )
			$need2update = true;
		else
		{
			$inserting = false;
			if( $temp_array[0] != $value )
				$need2update = true;
		}
		if( $need2update )
		{
			if( $inserting )
			{
				$db_query  = "INSERT INTO import_config (id_import_mod, name, value) VALUES (";
				$db_query .= $id_import_mod.", '".db_add_slashes($name)."', '".db_add_slashes($value)."') ";
				$result = db_query($db_query);
				if( !$result )
					return false;
			}//if( $inserting )
			else
			{//if( !$inserting )
				$query = "UPDATE import_config SET value='".db_add_slashes($value)."' ";
				$query .= $query_where;
				$result = db_query($query);
				if( !$result )
					return false;
			}//if( !$inserting )				
		}//if( $need2update )
		return true;
	}//if( $result )
}


?>
