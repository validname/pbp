<?php
require_once('../../functions/config.php');
require_once('../../functions/auth.php');
require_once('../../functions/func_db.php');
require_once('../../functions/func_web.php');
require_once("../../functions/func_time.php");
require_once("../functions/import_config.php");
require_once("../functions/px_wrapper.php");

set_time_limit(0);

$nl = "\n";
$exit_code = 0;

echo date("Y-m-d H:i").$nl;

$module_name = "HomeBuh_paradox";
$id_import_mod = get_module_id($module_name);
set_config_value($id_import_mod, "last_step", 1);

$silent_import_enabled = get_config_value($id_import_mod, "silent_import_enabled");
if( $silent_import_enabled === false || $silent_import_enabled !== "1" ) {
	echo "Автоматическая загрузка запрещена настройками.\n";
	//set_config_value($id_import_mod, "silent_import_enabled", 0);
	exit(1);
}

$px_dbs = array();
$path_to_db = "/mnt/media2/work/finance/Homebuh4/Base/";


$last_modified = filemtime($path_to_db);
$db_last_modified = get_config_value($id_import_mod, "db_last_modified");
if( $db_last_modified === false )
	$need_to_load = true;
else {
			if( $last_modified <> $db_last_modified ) 
				$need_to_load = true;
			else
				$need_to_load = false;
}

//---------------------------------------------------------------------------------------------------------	
//!!!!
//$need_to_load = true;

if( $need_to_load ) { 
	$result = set_config_value($id_import_mod, "db_last_modified", $last_modified);
	if( !$result ) {
		echo "Ошибка записи параметра модуля 'db_last_modified'.";
		exit(3);
	}

	db_query("DELETE FROM import_draft");

	$silent_mode = true;

//	moved to step2, may be deleted
/*
	$lockfile = $path_to_db."/../file.lck";
	$fp = @fopen($lockfile, 'r');
	if( $fp === false ) {
		$fp = fopen($lockfile, 'w');
		fputs($fp, "silent");
		fclose($fp);
	}
	else
		fclose($fp);
*/

//---------------------------------------------------------------------------------------------------------	
	include("step2.php");
	$_REQUEST['start_date'] = $start_date;
	$_REQUEST['finish_date'] = $finish_date;
	$_REQUEST['action'] = "set_date";
	include("step2.php");
	$_REQUEST['action'] = "";
//---------------------------------------------------------------------------------------------------------	
	echo "<hr>7. Проверка мэппинга счетов".$nl;
	include("step3.php");
//---------------------------------------------------------------------------------------------------------	
	echo "<hr>8. Проверка мэппинга категорий расходов".$nl;
	include("step4.php");
//---------------------------------------------------------------------------------------------------------	
	echo "<hr>9. Проверка мэппинга категорий доходов".$nl;
	include("step5.php");
//---------------------------------------------------------------------------------------------------------	
	echo "<hr>10. Перенос расходов в БД".$nl;
	$_REQUEST = array();
	include("step6.php");
	for( $i=1; $i<=10; $i++ ) {
		if( !count($id_draft_array_temp) ) break;
		echo "(цикл ".$i.")  Вызов импорта расходов".$nl;
		$_REQUEST = array();
		$_REQUEST['action'] = 'import';
		foreach ( $id_draft_array_temp as $temp => $id )
			$_REQUEST['id_draft_'.$id] = 'on';
//		print_r($_REQUEST);
		include("step6.php");		
	}
//---------------------------------------------------------------------------------------------------------	
	echo "<hr>11. Перенос доходов в БД".$nl;	
	$_REQUEST = array();
	include("step7.php");
	for( $i=1; $i<=10; $i++ ) {
		if( !count($id_draft_array_temp) ) break;
		echo "(цикл ".$i.")  Вызов импорта доходов".$nl;
		$_REQUEST = array();
		$_REQUEST['action'] = 'import';
		foreach ( $id_draft_array_temp as $temp => $id )
			$_REQUEST['id_draft_'.$id] = 'on';
//		print_r($_REQUEST);
		include("step7.php");		
	}
//---------------------------------------------------------------------------------------------------------	
	echo "<hr>12. Перенос перемещений между счетами в БД".$nl;	
	$_REQUEST = array();
	include("step8.php");
	for( $i=1; $i<=10; $i++ ) {
		if( !count($id_draft_array_temp) ) break;
		echo "(цикл ".$i.")  Вызов импорта перемещений".$nl;
		$_REQUEST = array();
		$_REQUEST['action'] = 'import';
		foreach ( $id_draft_array_temp as $temp => $id )
			$_REQUEST['id_draft_'.$id] = 'on';
//		print_r($_REQUEST);
		include("step8.php");		
	}
//---------------------------------------------------------------------------------------------------------	
	// moved to last step8, but...
	// for every case, force delete lock file
	$lockfile = $path_to_db."/../file.lck";
	unlink($lockfile);
	if( $exit_code )
		echo "<hr>Ошибка ! --------------------------------------------------------".$nl;
}
else
{
	echo "Загрузка не требуется.".$nl;
}
if( !$exit_code ) {
	echo "<hr>Нормальное завершение --------------------------------------------------------".$nl;
}
else
		exit($exit_code);
?>
