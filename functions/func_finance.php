<?
// finance functions
require_once("func_db.php");

function count_monthly_expenses_($year, $month)
{
	// get categories
	$query = "SELECT id_exp_cat FROM expense_category";
	$res2 = db_query($query);
	while( $temp_array=db_fetch_num_array($res2) )
		$categories_array[(int)$temp_array[0]] = 0;
	// get old ids
	$query = "SELECT id_exp_cat,id_exp_monthly FROM expenses_monthly WHERE year=".$year." AND month=".$month;
	$res2 = db_query($query);
	while( $temp_array=db_fetch_num_array($res2) )
		$categories_array[(int)$temp_array[0]] = (int)$temp_array[1];
	// get new summary values
	$query_insert = "INSERT INTO expenses_monthly(year, month, id_exp_cat, value) VALUES(".$year.", ".$month.", %d, \"%f\")";
	$query_update = "UPDATE expenses_monthly SET value=\"%f\" WHERE id_exp_monthly=%d";
	$query = "SELECT sum(value),id_exp_cat FROM expenses WHERE year(date)=".$year." AND month(date)=".$month." GROUP BY id_exp_cat";
	$res = db_query($query);
	while( $temp_array=db_fetch_num_array($res) )
	{
		$sum_value = (float)$temp_array[0];
		$id_exp_cat = (int)$temp_array[1];
		if( $categories_array[$id_exp_cat] )
			$query = sprintf($query_update, $sum_value, $categories_array[$id_exp_cat]);
		else
			$query = sprintf($query_insert, $id_exp_cat, $sum_value);
		//echo $query."\r\n";
		db_query($query);
		unset($categories_array[$id_exp_cat]);
	}
	// empty categories
	foreach( $categories_array as $id_exp_cat => $temp )
	{
		if( $categories_array[$id_exp_cat] )
			$query = sprintf($query_update, 0.0, $categories_array[$id_exp_cat]);
		else
			$query = sprintf($query_insert, $id_exp_cat, 0.0);
		//echo $query."\r\n";
		db_query($query);
	}
}

function count_monthly_expenses_all_()
{
	$query = "SELECT year(date) as year, month(date) as month FROM expenses GROUP BY year, month";
	$res = db_query($query);
	while( $temp_array=db_fetch_num_array($res) )
	{
		$year = (int)$temp_array[0];
		$month = (int)$temp_array[1];
		count_monthly_expenses($year, $month);
	}
}

function get_formatted_amount($amount)
{
	return sprintf("<span id=amount>".AMOUNT_FORMAT."</span>",$amount);
}

function mark_rare_expenses($id_exp_subcat)
{
	$start_yearmonth = (int)date('Y')*12 + (int)date('m') - RARE_SEARCH_MONTHS;
	$query  = "SELECT * FROM transactions AS t ";
	$query .= " INNER JOIN expense_subcats2 AS sc2 ON t.id_subcat2=sc2.id_exp_subcat2";
	$query .= " WHERE value<0 and id_transfer=0 ";
	$query .= " AND year(date)*12+month(date) >= ".$start_yearmonth." ";
	$query .= " AND sc2.id_exp_subcat=".$id_exp_subcat." ";
	$res = db_query($query);
	if( !$res )
		{ echo "<hr>Ошибка запроса транзакций."; return false; }
	
	$counts = array();
	$accumulator = 0; 
	while( $result_array = db_fetch_assoc_array($res)) {
//		print_r($result_array);
		$amount = ceil(-(float)$result_array['value']/RARE_SEARCH_PRECISION);
		$id_exp_subcat2 = (int)$result_array['id_exp_subcat2'];
		$subcats2[$id_exp_subcat2] = 1; 
	//	echo $amount."\n";
		if( isset($counts[$amount]) )
		    $counts[$amount]++;
		else
		    $counts[$amount] = 1;
	}
	ksort($counts, SORT_NUMERIC);
	//print_r($counts);
	if( !count($counts) )	// no transactions
		return 0;
	
	$where_in = " IN (";
	$first_subcat = true;
	foreach($subcats2 as $id_exp_subcat2 => $temp) {
		if( !$first_subcat )
			$where_in .= ",";
		$where_in .= $id_exp_subcat2;
		$first_subcat = false;
	}
	$where_in .= ") ";
	
	$counts_all = array_sum($counts); 
	foreach( $counts as $amount => $count) {
		$rate = $count/$counts_all;
	//	echo $amount." - ".$rate."\r\n";
		$accumulator += $rate;
		$amount_theshold = $amount;  
		if( $accumulator>= RARE_SEARCH_TRESHOLD )
			break;	
	}

	$query  = "UPDATE transactions SET is_rare='1' WHERE value<='-".$amount_theshold*RARE_SEARCH_PRECISION."' ";
	$query .= " AND year(date)*12+month(date) >= ".$start_yearmonth." ";
	$query .= " AND id_subcat2 ";
	$query .= $where_in;
	//echo $query; 
	$res = db_query($query);
	if( !$res )
		{ echo "<hr>Ошибка обновления транзакций."; return false; }

	return $amount_theshold*RARE_SEARCH_PRECISION;
}

?>
