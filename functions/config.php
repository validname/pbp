<?
// access to MySQL

require_once("db.config.php");

/* you must define an array with values:
$db_cfg['host']
$db_cfg['db']
$db_cfg['user']
$db_cfg['pass']
*/

// currenly not used. Use your webserver auth instead
//$auth_config['user_name'] = "";
//$auth_config['user_pass'] = "";

// date and time format
define('DATE_FORMAT', "d.m.Y"); // date format
define('TIME_FORMAT', "H:i"); // time format

//number format for amounts
define('AMOUNT_FORMAT', "%.2f");

// form post method
define('FORM_METHOD', "get");

// ------------------------------------ constants

// transaction type for id_trans_type in table import_draft
define("TRANS_TYPE_EXP", 1);	// expenses
define("TRANS_TYPE_INC", 2);	// incomes
define("TRANS_TYPE_ACC", 3);	// account transfers

// type of cnanges (difference) between import_draft and transactions table's rows
define("IMPORT_CHANGE_ADD", 'added'); // row in import_draft is not exist in the transactions 
define("IMPORT_CHANGE_CHG", 'changed'); // row in import_draft has 1 differense from the transactions's row
define("IMPORT_CHANGE_DEL", 'deleted'); // row in transactions is not exist in the import_draft

define("RARE_SEARCH_MONTHS", 12);	//	search and mark rare expenses in the last X months (current month included!) 
define("RARE_SEARCH_PRECISION", 10);	//	search and mark rare expenses in amount with this precision   
define("RARE_SEARCH_TRESHOLD", 0.9);	//	search and mark rare expenses lied above this threshold. < 1  

// ----- text modifiers
define("TXT_NONE", 0);	// no modifiers

// align
define("TXT_ALIGN_CENTER", 1);
define("TXT_ALIGN_LEFT", 2);
define("TXT_ALIGN_RIGHT", 3);
define("TXT_ALIGN_JUSTIFY", 4);

// bit modifiers
define("TXT_ALIGN_MASK", 7);
define("TXT_ITALIC", 8);
define("TXT_BOLD", 16);
define("TXT_UNDRLINE", 32);
define("TXT_SMALL", 64);
define("TXT_BIG", 128);
define("TXT_NOBR", 256);

define("CELL_LINE_TOP", 512);	// table cell with top line 
define("CELL_LINE_BOTTOM", 1024);	// table cell with bottom line
define("CELL_LINE_LEFT", 2048);	// table cell with left line
define("CELL_LINE_RIGHT", 4096);	// table cell with right line

?>
