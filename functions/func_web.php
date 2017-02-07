<?php
require_once("config.php");

function web_get_request_value($array, $name, $format)
{
	global $html_chars_trans_table;
	
	if( !isset($array[$name]) ) return false;

	if( $array[$name] == "" && $format != 's' ) // empty string in value
		return false;	//	error!

	$array[$name] = trim($array[$name]);
	switch( $format )
	{
		case 'i':	// integer
							$new_value = (int)($array[$name]);
							if( (string)($new_value) != $array[$name] )
								return false;
							break;
		case 'b':	// bool
							$new_value = (int)($array[$name]);
							if( (string)($new_value) != $array[$name] )
								return false;
							if( $new_value > 1 )
								$new_value = 1;
							break;
		case 's':	// string
							$new_value = (string)$array[$name];
							break;
		case 'f':	// float, double
							preg_match("/([0-9]*)[.,]?([0-9]*)/", $array[$name], $matches);
							$new_value = (float)( $matches[1].".".$matches[2] );
							break;
		default:	$new_value = ($array[$name]);
				break;

	}
	return $new_value;
}

function web_get_checkbox_value($array, $name)
{
	if( !isset($array[$name]) ) return 0;
	if( strtolower($array[$name]) == "on" )
		return 1;
	else
		return 0;
}

function web_get_output_webform_checkbox($name, $text, $current_value, $disabled=false, $onClick="")
{
	$string = "";

	$string .= "<input type=checkbox name=\"".$name."\" ";
	if( $disabled != false ) $string .= " class=disabled_field";
	if( $onClick != "" ) $string .= " onClick=\"".$onClick."\"";
	if( $current_value ) $string .= " checked";
	$string .= " id=\"".$name."\">";
	if( $text )
		$string .= "<label for=\"".$name."\">".$text."</label>\n";
	return $string;
}

function web_get_webform_bool_values()
{
	return array( 1 => "да", 0 => "нет");
}

function web_get_output_webform_radiobtn($radiobtn_name, $values_array, $current_value, $disabled=false, $onClick="")
{ // array index - radio button values, array values - radio button text
	$string = "";
	$values = count($values_array);
	for( $i=0; $i<$values; $i++ )
	{
		$radiobtn_value = key($values_array);
		$radiobtn_text = current($values_array);

		if( gettype($current_value) == gettype($radiobtn_value) && $current_value == $radiobtn_value )
			$is_equal = true;
		else
			$is_equal = false;
		
		$string .= "<input type=radio name=\"".$radiobtn_name."\" ";
		if( $disabled != false ) $string .= " class=disabled_field";
		if( $onClick != "" ) $string .= " onClick=\"".$onClick."\"";
		$string .= " value=\"".$radiobtn_value."\"";
		if( $is_equal ) $string .= " checked";
		$string .= " id=\"".$radiobtn_name."_val".$i."\">";
		if( $is_equal ) $string .= "<b>";
		$string .= "<label for=\"".$radiobtn_name."_val".$i."\">".$radiobtn_text."</label>";
		if( $is_equal ) $string .= "</b>";
		$string .= "\n";

		next($values_array);
	}
	return $string;
}

function web_get_output_webform_selectlist($select_name, $values_array, $current_value, $disabled=false, $onChange="")
{ // array index - select option values, array values - select option text
	$values = count($values_array);
	$string = "<select name=\"".$select_name."\"";
	if( $disabled != false ) $string .= " class=disabled_field";
	if( $onChange != "" ) $string .= " onChange=\"".$onChange."\"";
	$string .= " id=\"".$select_name."\">\r\n";
	for( $i=0; $i<$values; $i++ )
	{
		$option_value = key($values_array);
		$option_text = current($values_array);

		if( gettype($current_value) == gettype($option_value) && $current_value == $option_value )
			$is_equal = true;
		else
			$is_equal = false;

		$string .= "<option value=\"".$option_value."\"";
		if( $is_equal ) $string .= " selected";
		$string .= ">";
		$string .= $option_text."</option>";
		$string .= "\r\n";

		next($values_array);
	}
	$string .= "</select>\r\n";
	return $string;
}

?>
