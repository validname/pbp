<?
require_once("config.php");

function get_table_start($css_class="")
{
	if( $css_class )
		$out = "<table class=".$css_class.">\r\n";
	else
		$out = "<table>\r\n";
	return $out;
}

function get_table_end()
{
	$out = "</table>\r\n";
	return $out;
}

function get_table_sort_code()
{
	$out  = "<script language=javascript>\r\n";
	$out .= "function set_sort_field(sort_field, sort_dsc)\r\n{\r\n";
	$out .= "	document.main_form.sort_field.value=sort_field;\r\n";
	$out .= "	document.main_form.sort_dsc.value=sort_dsc;\r\n";
	$out .= "	document.main_form.submit();\r\n";
	$out .= "}\r\n</script>\r\n";	
	return $out;
}

function get_table_header($fields, $css_class="", $sort_field="", $sort_dsc=0)
{
	if( $css_class )
		$css_class = " class=".$css_class;
	$out = "<tr".$css_class.">\r\n";
	foreach( $fields as $field_name => $field_text )
	{
		$skip_sorting = is_int($field_name);	// skip sorting if index key is integer (is't useless to use this field names and let us out raw html in field text)
		$out .= "<th".$css_class.">";
		if( !$skip_sorting && $sort_field )
		{
			if( $sort_field==$field_name )
			{ 
				$out .= "<a href=# onClick=\"set_sort_field('".$field_name."', ";
				if( $sort_dsc ) $out .= "0);\">";
				else	$out .= "1);\">";
			}
			else
				$out .= "<a href=# onClick=\"set_sort_field('".$field_name."', 0);\">";
		}	 
		$out .= $field_text;
		if( !$skip_sorting && $sort_field ) 
		{
			if( $sort_field==$field_name )
			{
				if( $sort_dsc )
					$out .= "&nbsp;&#8657;";
				else
					$out .= "&nbsp;&#8659;";
				$out .= "</a>";
			}
			else
				$out .= "</a>";
		}
		$out .= "</th>\r\n";
	}
	$out .= "</tr>"; 
	return $out;
}

function get_table_row($cells, $css_class="", $row_mods=TXT_NONE, $row_style="", $cells_mods=array(), $cells_styles=array(), $colspan_array=array())
{
	if( $css_class )
		$css_class = " class=\"".$css_class."\"";
	if( $row_style )
		$row_style = " style=\"".$row_style."\"";

	$skip_cells = 0;

	$out = "<tr".$css_class.$row_style.">\r\n";
	foreach( $cells as $column => $cell )
	{
		if( $skip_cells>0 )
		{
			$skip_cells--;
			continue;		
		}

		if( isset($colspan_array[$column]) && $colspan_array[$column]>0 )
		{
			$skip_cells =	$colspan_array[$column]-1;
			$colspan = " colspan=".$colspan_array[$column];
		}
		else
			$colspan = "";
	
		$modifier = $row_mods | $cells_mods[$column];

		$style_modifier = "";
		if( $modifier & CELL_LINE_TOP )			$style_modifier .= "border-top: solid #808080 1px; "; 
		if( $modifier & CELL_LINE_BOTTOM )	$style_modifier .= "border-bottom: solid #808080 1px; ";
		if( $modifier & CELL_LINE_LEFT )		$style_modifier .= "border-left: solid #808080 1px; ";
		if( $modifier & CELL_LINE_RIGHT )		$style_modifier .= "border-right: solid #808080 1px; ";

		if( isset($cells_styles[$column]) )
			$style_modifier .= $cells_styles[$column];

		if( $style_modifier )
			$style_modifier = " style=\"".$style_modifier."\"";

		// cell output parts:
		$left = "<td".$css_class.$style_modifier.$colspan.">";
		switch( $modifier & TXT_ALIGN_MASK )
		{
			case TXT_ALIGN_CENTER:	$left .= "<div align=center>"; break; 
			case TXT_ALIGN_LEFT:		$left .= "<div align=left>"; break;
			case TXT_ALIGN_RIGHT:		$left .= "<div align=right>"; break;
			case TXT_ALIGN_JUSTIFY:	$left .= "<div align=justify>"; break;
		}
	
		$center = $cell;
	
		if( $modifier & TXT_ITALIC )	$center = "<i>".$center."</i>";
		if( $modifier & TXT_BOLD )		$center = "<b>".$center."</b>";
		if( $modifier & TXT_UNDRLINE)	$center = "<u>".$center."</u>";
		if( $modifier & TXT_SMALL)		$center = "<font size=-1>".$center."</font>";
		if( $modifier & TXT_BIG)			$center = "<font size=+1>".$center."</font>";
		if( $modifier & TXT_NOBR)			$center = "<nobr>".$center."</nobr>";		

		$right = "";
		if( $modifier & TXT_ALIGN_MASK )	$right = "</div>";
		
		$right .= "</td>\r\n";

		$out .= $left.$center.$right;
	}
	return $out."</tr>\r\n";
}

?>
