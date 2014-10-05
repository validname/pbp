<? 

$CSV_dbs = array();

// just open as file. No fileds must be in the first line!
function open_CSV($CSV_name)
{
	global $CSV_dbs, $path_to_db;

	$CSV_db_array = array('fp'=>false);

	if( !($CSV_db_array['fp'] = @fopen($path_to_db."/".$CSV_name.".csv", "r")) ) {
		echo "Error while opening CSV file ".$CSV_name.PHP_EOL;
		return false;
	}

	$CSV_dbs[$CSV_name] = $CSV_db_array; 
	return true;
}

//no cashing! small speed, but saves memory  
function get_CSV_record($CSV_name)
{
	global $CSV_dbs;

	if( !isset($CSV_dbs[$CSV_name]) )
		return false;
	$CSV_db_array = $CSV_dbs[$CSV_name];

	
	return $data;
}

// just close file
function close_CSV($CSV_name)
{
	global $CSV_dbs;

	if( !isset($CSV_dbs[$CSV_name]) )
		return false;
	$CSV_db_array = $CSV_dbs[$CSV_name];

	fclose($CSV_db_array['fp']);

	unset($CSV_dbs[$CSV_name]);
	return true; 
}
?>
