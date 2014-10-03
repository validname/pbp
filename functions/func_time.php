<?php
// функции работы со временем
require_once("config.php");

/* 'date' format:
* time
g - 12-hour format of an hour without leading zeros (1 through 12)
G - 24-hour format of an hour without leading zeros (0 through 23)
h - 12-hour format of an hour with leading zeros (01 through 12)
H - 24-hour format of an hour with leading zeros (00 through 23)
A - Uppercase Ante meridiem and Post meridiem (AM or PM)
i - Minutes with leading zeros (00 to 59)
* date
d - Day of the month, 2 digits with leading zeros (01 to 31)
j - Day of the month without leading zeros (1 to 31)
m - Numeric representation of a month, with leading zeros (01 through 12)
n - Numeric representation of a month, without leading zeros (1 through 12)
Y - A full numeric representation of a year, 4 digits (Examples: 1999 or 2003)
y - A two digit representation of a year (Examples: 99 or 03)
*/

// date-формат вывода даты
function get_php_date_format()
	{ return DATE_FORMAT; }

// date-формат вывода времени
function get_php_time_format()
	{  return TIME_FORMAT; }

// SQL-формат вывода даты
function get_sql_date_format()
{
	$format = DATE_FORMAT;
	
	$length = strlen($format);
	$out_format = "";
	for( $i=0; $i<$length; $i++ )
	{
		$char = $format{$i};
		switch( $char )
		{
			case 'd':	$out_format .= "%d";
						break;
			case 'j':	$out_format .= "%e";
						break;
			case 'm':	$out_format .= "%m";
						break;
			case 'n':	$out_format .= "%c";
						break;
			case 'Y':	$out_format .= "%Y";
						break;
			case 'y':	$out_format .= "%y";
						break;
			default:
						$out_format .= $char;
		}
	}
	return $out_format;
}

// SQL-формат вывода времени
function get_sql_time_format()
{
	$format = TIME_FORMAT;
	
	$length = strlen($format);
	$out_format = "";
	for( $i=0; $i<$length; $i++ )
	{
		$char = $format{$i};
		switch( $char )
		{
			case 'g':	$out_format .= "%l";
						break;
			case 'G':	$out_format .= "%k";
						break;
			case 'h':	$out_format .= "%h";
						break;
			case 'H':	$out_format .= "%H";
						break;
			case 'A':	$out_format .= "%p";
						break;
			case 'i':	$out_format .= "%i";
						break;
			default:
						$out_format .= $char;
		}
	}
	return $out_format;
}

function convert_time2sql($time_string)
{
	$format = TIME_FORMAT;
	
	$format_array = array('\\','.','[',']','+','*','^','|','(',')','$','-','{','}','?'); // key symbols
	$temp_array = array('\\\\','\\.','\\[','\\]','\\+','\\*','\\^','\\|','\\(','\\)','\\$','\\-','\\{','\\}','\\?');
	$preg_format = str_replace($format_array, $temp_array, $format);
	$format_array = array('g','G','h','H','i');
	$preg_format = str_replace($format_array, "([0-9]+)", $preg_format);
	$preg_format = str_replace('A', "(am|pm)", $preg_format);
	$preg_format = "/^".$preg_format."$/i";
//	echo "format: ".$preg_format."<br>";
	if( !preg_match($preg_format, $time_string, $matches) )
		return false;

	$length = strlen($format);
	$t_count = count($matches);
	$is_pm = false;
	$hour_24 = false;
	$hour = 0;
	$min = 0;
	$t_i = 1;
	for( $f_i=0; $f_i<$length; $f_i++ )
	{
		$t_char = $format{$f_i};
//		echo "t_char: ".$t_char."<br>";
		if( $t_i == $t_count )
			break;	// no more numbers in given time string
		$number = $matches[$t_i];
//		echo "number: ".$number."<br>";
		switch( $t_char )
		{
			case 'g':	
			case 'h':	$hour_24 = false;
						$hour = (int)$number;
						$t_i++;
						break;
			case 'G':	
			case 'H':	$hour_24 = true;
						$hour = (int)$number;
						$t_i++;
						break;
			case 'A':	if( strtolower($number) == 'pm' )
							$is_pm = true;
						$t_i++;
						break;
			case 'i':	$min = (int)$number;
						$t_i++;
						break;
			default:
		}
	}
	// check values
	if( $hour > 23 )
		return false;
	if( $min > 59 )
		return false;
		
	if( !$hour_24 && $is_pm && $hour>0 && $hour<=12 )
	{
		$hour += 12;
		if( $hour > 23 ) // next day !!!!!!!!!!!!!!!
			$hour -= 24;
	}
	return sprintf("%02d:%02d:00", $hour, $min);
}

function convert_date2sql($date_string)
{
	$format = DATE_FORMAT;

	$format_array = array('\\','.','[',']','+','*','^','|','(',')','$','-','{','}','?'); // key symbols
	$temp_array = array('\\\\','\\.','\\[','\\]','\\+','\\*','\\^','\\|','\\(','\\)','\\$','\\-','\\{','\\}','\\?');
	$preg_format = str_replace($format_array, $temp_array, $format);
	$format_array = array('d','j','m','n','Y','y');
	$preg_format = str_replace($format_array, "([0-9]+)", $preg_format);
	$preg_format = "/^".$preg_format."$/i";
	if( !preg_match($preg_format, $date_string, $matches) )
		return false;

	$length = strlen($format);
	$t_count = count($matches);
	$day = 0;
	$month = 0;
	$year = 0;
	$t_i = 1;
	for( $f_i=0; $f_i<$length; $f_i++ )
	{
		$t_char = $format{$f_i};
		if( $t_i == $t_count )
			break;	// no more numbers in given time string
		$number = $matches[$t_i];
		switch( $t_char )
		{
			case 'd':
			case 'j':	$day = (int)$number;
						$t_i++;
						break;
			case 'm':
			case 'n':	$month = (int)$number;
						$t_i++;
						break;
			case 'Y':	
			case 'y':	$year = (int)$number;
						$t_i++;
						break;
			default:
		}
	}
	// check values
	if( !$day || $day > 31 )
		return false;
	if( !$month || $month > 12 )
		return false;
	if( !$year )
		return false;

	if( $year < 100 )
	{
		if( $year > 69 ) // from 70 to 99
			$year += 1900; // from 1970 to 1999
		else // from 0 to 69
			$year +=2000; // from 2000 to 2069
	}
	return sprintf("%04d-%02d-%02d", $year, $month, $day);
}

function get_month_array()
{
	return array(1=>"Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь");
}

function get_day_array()
{
 $days = array();
 for( $i=1; $i<=31; $i++ )
 	  $days[$i] = $i;
 return $days;
}

function get_year_array()
{
 $current_year = (int)(date("Y"));
 $years = array();
 for( $i=$current_year-10; $i<=$current_year+10; $i++ )
 	  $years[$i] = $i;
 return $years;
}

?>
