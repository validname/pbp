<?
require_once('functions/config.php');
require_once('functions/auth.php');
require_once('functions/func_db.php');
require_once('functions/func_web.php');
require_once("functions/func_time.php");
require_once("functions/func_table.php");
require_once("functions/func_profiler.php");
require_once("functions/func_finance.php");

$doc_title = "Редкие расходы";
//$doc_onLoad = "set_mode();";
include("header.php");

	$query  = "SELECT id_exp_subcat, sc.name, c.id_exp_cat, c.name as cat_name FROM expense_subcats AS sc ";
	$query .= "	INNER JOIN expense_cats AS c ON c.id_exp_cat=sc.id_exp_cat ";
	$query .= "	ORDER BY c.name, sc.name";
	$res = db_query($query);
//	echo $query;
	if( !$res )
		{ echo "<hr>Ошибка запроса подкатегорий расходов."; exit; }
	
	while( $result_array = db_fetch_assoc_array($res)) {
//		print_r($result_array);
		$id_exp_cat = (int)$result_array['id_exp_cat'];
		$cat_name = db_strip_slashes($result_array['cat_name']);
		$id_exp_subcat = (int)$result_array['id_exp_subcat'];
		$subcat_name = db_strip_slashes($result_array['name']);
		
		//search and mark
		$amount_treshold = mark_rare_expenses($id_exp_subcat);
		echo "[".$id_exp_cat."] ".$cat_name." \ [".$id_exp_subcat."] ".$subcat_name." : ";
		if( is_bool($amount_treshold) && !$amount_treshold)
			echo "<font color=red>Ошибка!</font>";
		else
			echo $amount_treshold." р.";
		echo "<br>";
	}

?>
