<?php
require_once("functions/config.php");
require_once("functions/auth.php");
require_once("functions/func_finance.php");
require_once("functions/func_web.php");
require_once("functions/func_time.php");
require_once("functions/func_dic.php");

$search = web_get_request_value($_REQUEST, "search", 's');

?>
<form name="form" action=<?php echo $_SERVER['PHP_SELF']; ?> method="<?php echo FORM_METHOD; ?>">
<input type=text name=search value="<?php echo $search; ?>">
<input type=submit>
</form>
<?php

$subcats = array();
$subcat_weights = array();
$subcat_rates = array();

if( $search )
{
	$search_words = break_on_words($search);
	$words = count($search_words);

	$word_weights = array();
	$length_all = strlen(implode("", $search_words));
	// веса слов в тексте - чем длиннее слово, тем больше вес (доверие к нему)
	foreach( $search_words as $word_num => $search_word )
		$word_weights[$word_num] = strlen($search_word) / $length_all; 
	
	//searching each word
	foreach( $search_words as $word_num => $search_word )
	{
//		$hash = reduce_text($search_word);	
		$hash = $search_word;
		echo "Hashed text: ".$hash."<br>";

		$query = "select word, id_trans_type, id_subcat2, counter from dictionary where id_trans_type=".TRANS_TYPE_EXP;
		$query .= " and hash like \"".$hash."%\" order by counter desc";
		$res = db_query($query);
		
		$counter_all = 0;
		$founded_words = array();
		while( $temp_array = db_fetch_assoc_array($res) )
		{
			$id_subcat2 = $temp_array['id_subcat2'];
			$founded_word = $temp_array['word'];
			$counter = $temp_array['counter'];

			if( !isset($subcats[$id_subcat2]) || !$subcats[$id_subcat2] )	// кэш подкатегорий
			{
				$query  = "SELECT c.name, sc.name, sc2.name ";
				$query .= "FROM expense_subcats2 AS sc2 ";
				$query .= "INNER JOIN expense_subcats AS sc ON sc.id_exp_subcat = sc2.id_exp_subcat ";
				$query .= "INNER JOIN expense_cats AS c ON c.id_exp_cat = sc.id_exp_cat ";
				$query .= "WHERE sc2.id_exp_subcat2=".$id_subcat2;
				$res2 = db_query($query);
				$temp_array = db_fetch_num_array($res2);
				$subcats[$id_subcat2] = $temp_array[0]."-".$temp_array[1]."-".$temp_array[2];
			}
			
			$counter_all += $counter;
			similar_text( $search_word, $founded_word, $procent);
			$procent = (float)$procent / 100;
			$founded_words[] = array($founded_word, $search_word, $counter, $procent, 0, $id_subcat2);
		}

		foreach( $founded_words as $temp_index => $word_array )
		{
			$id_subcat2 = $word_array[5];
			$founded_words[$temp_index][2] /= $counter_all; 		// нормирование
			$weight_counter = $word_array[2] / $counter_all;
			$weight_similar = $word_array[3];
			$weight = $weight_counter*$weight_similar; 		// суммарный вес найденного слова в выборке
			$founded_words[$temp_index][4] = $weight;

			if( isset( $subcat_weights[$id_subcat2][$word_num] ) )
				$subcat_weights[$id_subcat2][$word_num] = max($subcat_weights[$id_subcat2][$word_num], $weight);
			else
				$subcat_weights[$id_subcat2][$word_num] = $weight;

			$weight_word = $word_weights[$word_num];			// вес слова в поиске
			$weight = $weight * $weight_word;	  
			
			if( isset($subcat_rates[$id_subcat2]) )
				$subcat_rates[$id_subcat2] += $weight;
			else
				$subcat_rates[$id_subcat2] = $weight;
		}

//		echo "<pre>";
//		print_r($founded_words);
//		print_r($subcat_weights);
//		print_r($subcat_rates);
//		echo "</pre>";

		echo "<br>";	
		echo "<table border=1>";
		echo "<tr>\r\n";
		echo "<th>Слово</th>";
		echo "<th>Частота</th>";
		echo "<th>Соотвествие</th>";
		echo "<th>Вес</th>";
		echo "<th>Подкатегория</th>";
		echo "<th></th>";
		echo "</tr>\r\n";
		foreach( $founded_words as $temp_index => $word_array )
		{
			echo "<tr>\r\n";
			{
				echo "<td>".$word_array[0]."</td>\r\n";
				echo "<td>".round($word_array[2], 4)."</td>\r\n";
				echo "<td>".round($word_array[3], 4)."</td>\r\n";
				echo "<td>".round($word_array[4], 4)."</td>\r\n";
				echo "<td>"."[".$word_array[5]."] ".$subcats[$word_array[5]]."</td>\r\n";
			}
			echo "</tr>\r\n";
		}
		echo "</table>";

	}
 
 	arsort($subcat_rates);
 	reset($subcat_rates);
 	$id_matched = key($subcat_rates);
 	echo "Most matched subcat: <b>[".$id_matched."] ".$subcats[$id_matched]."<b>";
 
	echo "<br>";	
	echo "<table border=1>";
	// выводим для всех попавших в кэш подкатегорий
	foreach( $subcat_weights as $id_subcat2 => $arr1 )
	{
		echo "<tr>\r\n";
		echo "<td>"."[".$id_subcat2."] ".$subcats[$id_subcat2]."</td>\r\n";
		{
			for( $word_num=0; $word_num<$words; $word_num++ )
			{
				$weight = $arr1[$word_num];
				
				echo "<td>".round($weight, 2)."</td>\r\n";
	//			echo "<td>".round($weight_subcat, 2)."</td>\r\n";
	
			}
		}
		echo "<td>".round($subcat_rates[$id_subcat2], 2)."</td>\r\n";
		echo "</tr>\r\n";
	}
	echo "</table>";


}
// 8. Конец скрипта.
include("footer.php");
?>
