<?php

function px_open_db($px_db)
{
	global $px_dbs, $path_to_db;

	$px_db_array = array('name'=> $px_db, 'fp'=>false, 'records'=>0, 'index'=>0, 'date_fields'=>array());
	$px_db_array['name'] = $px_db;
		
	$result = array('status' => 0, 'text' =>"", 'data' => array());
	$date_fields = array();

	if(!$px_res = px_new()) {
		echo "Error while creating new db object";
	  return false;
	}
	if( !($px_db_array['fp'] = @fopen($path_to_db."/".$px_db.".db", "r")) ) {
		if( !($px_db_array['fp'] = @fopen($path_to_db."/".$px_db.".DB", "r")) ) {
			echo "Error while opening db file";
		  return false;
		}
	}
	if( !px_open_fp($px_res, $px_db_array['fp']) ) {
		echo "Error while opening db file as db";
	  return false;
	}
	
	$schema = px_get_schema($px_res);
	foreach( $schema as $field_name => $field_info) {
		if( $field_info['type'] == PX_FIELD_DATE )
			$px_db_array['date_fields'][] = $field_name; 
		if( $field_info['type'] == PX_FIELD_ALPHA )
			$px_db_array['string_fields'][] = $field_name; 
	}

	$px_db_array['records'] = px_numrecords($px_res);
	$px_dbs[$px_res] = $px_db_array; 
	return $px_res;	
}

function px_select_from_db($px_res)	//no cashing! small speed, but saves memory  
{
	global $px_dbs;
	
	if( !isset($px_dbs[$px_res]) )
		return false;
	$px_db_array = $px_dbs[$px_res];

	if( !$px_db_array['records'] )
		return false;
	if( $px_db_array['index'] == $px_db_array['records'] )
		return false;

	$data = px_retrieve_record($px_res, $px_db_array['index']);
//	iconv_set_encoding('input_encoding', 'UTF-8');
//	iconv_set_encoding('output_encoding', 'UTF-8');
//	iconv_set_encoding('internal_encoding', 'UTF-8');
	foreach( $px_db_array['string_fields'] as $temp => $field_name )
		$data[$field_name] = iconv('CP1251', 'UTF-8', $data[$field_name]);
	foreach( $px_db_array['date_fields'] as $temp => $field_name )
		$data[$field_name] = px_date2string($px_res, $data[$field_name], "Y-m-d 00:00:0");
	$px_db_array['index']++;
	$px_dbs[$px_res] = $px_db_array; 
	return $data;
}

function px_reset_index($px_res)
{
	global $px_dbs;
	
	if( !isset($px_dbs[$px_res]) )
		return false;
	$px_db_array = $px_dbs[$px_res];

	if( !$px_db_array['records'] )
		return false;

	$px_db_array['index'] = 0;
	$px_dbs[$px_res] = $px_db_array; 
	return true;
}


function px_close_db($px_res)
{
	global $px_dbs;
	
	if( !isset($px_dbs[$px_res]) )
		return false;
	$px_db_array = $px_dbs[$px_res];
	
	px_close($px_res);
	px_delete($px_res);
	fclose($px_db_array['fp']);
	
	unset($px_db_array);
	unset($px_dbs[$px_res]);
	return true; 
}
?>
