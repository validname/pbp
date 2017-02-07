<?php
require_once("functions/config.php");
require_once("functions/auth.php");

// !!!!!!!!!!!! 
ini_set("display_errors", "on");


echo "<html>\n";
echo "<head>\n";
echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf8\">\n";
echo "<LINK rel=\"stylesheet\" type=\"text/css\" href=\"styles/"."default.css"."\">\n";
if( isset($doc_title) )
	echo "<title>".$doc_title."</title>\n";
echo "</head>\n";
echo "<body";
if( isset($doc_onLoad) )
	echo " onLoad=\"".$doc_onLoad."\"";
echo ">\n";

function print_header_menu()
{
	echo "<div id=center>\n";
	echo "<table class=main_menu>\n";
	echo "<tr>\n";
	echo "<td class=main_menu><a target=_top href=\"request_list.php\">".$messages[111]."</a></td>\n";
	echo "<td class=main_menu><a target=_top href=\"journey_list.php\">".$messages[112]."</a></td>\n";
	echo "<td class=main_menu><a target=_top href=\"inventory_menu.php\">".$messages[113]."</a></td>\n";
	echo "<td class=main_menu><a target=_top href=\"reports_menu.php\">".$messages[116]."</a></td>\n";
	echo "<td class=main_menu><a target=_top href=\"db_menu.php\">".$messages[115]."</a></td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</div>\n";
//	echo "<div id=center><hr width=50%></div>\n";
}

?>
