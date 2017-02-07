<?php
require_once("config.php");
require_once("auth.php");

$consonant_pairs = array();

function init_consonants()
{
	global $consonant_pairs;

	$consonants = array("б","в","г","д","ж","з","к","л","м","н","п","р","с","т","ф","х","ц","ч","ш","щ");
	$count = count($consonants);
	for( $i=0; $i<$count; $i++ )
	{
		for( $j=0; $j<$count; $j++ )
		{
			if( $j==$i ) continue; // no doubles!
			$pair1 = $consonants[$i].$consonants[$j];
			$pair2 = $consonants[$j].$consonants[$i];
			if( !isset($pairs[$pair1]) )
				$pairs[$pair1] = $i*100 + $j;
			if( !isset($pairs[$pair2]) )
				$pairs[$pair2] = $i*100 + $j;
		}	
	}
	$consonant_pairs = $pairs;
}

function reduce_text($string)
{
	global $consonant_pairs;

	$text = strtolower($string);
	
	// убираем двойные буквы и меняем пары согласных на цифровые заменители
	$length = strlen($text);
	$prev_char = '';
	$temp_text = "";
	for( $i=0; $i<$length; $i++ )
	{
		$current_char = $text{$i};
		if( $current_char <> $prev_char )
		{
				if( isset($consonant_pairs[$current_char.$prev_char]) )
				{
					$temp_text = substr($temp_text, 0, -1); // удаляем последний символ (первый из последовательно идущих согласных)
					$temp_text .= $consonant_pairs[$current_char.$prev_char];
				}
				else
					$temp_text .= $current_char; 
		}
		$prev_char = $current_char;
	}
	// меняем согласные на похожие И убираем твердость и мягкость
	$vowels1 = array("a","е","ё","и","й","о","у","ы","ь","ъ","э","ю","я"); 
	$vowels2 = array("a","е","е","и","и","а","у","и","","","е","ю","я"); 
	$text = str_replace($vowels1, $vowels2, $temp_text);
	
	return $text;
}

function break_on_words($text)
{
	if( !$text )
		return false;
//	setlocale(LC_CTYPE, "ru_RU.CP1251"); // for linux!!!!!
	$words = array();
	$text = strtolower($text);
	$text = strtr($text, ".", ",");
	preg_match_all( "/[a-zа-яё]+/", $text, $matches );	// only letters
	foreach( $matches[0] as $temp => $word ) 	{
			if( $word{0} == "," )		// comma at the start
				$word = substr($word, 1);
			if( substr($word, -1) == "," )	// comma at the end
				$word = substr($word, 0, -1);	
			if( strlen($word) < 3  ) // too small for normal word
				continue;

			$words[] = substr($word, 0, 32); //max 32 symbols
	}
	return $words; 
}

init_consonants();

?>
