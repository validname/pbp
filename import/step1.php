<?php
require_once('../functions/config.php');
require_once('../functions/auth.php');
require_once('../functions/func_db.php');

set_time_limit(0);

db_query("DELETE FROM import_draft");

$id_import_mod = 2;
$query = "DELETE FROM import_config WHERE id_import_mod=".$id_import_mod." AND name='last_step'"; 
db_query($query);

header("Location: homebuh/step2.php");
?>
