<?
require_once("../../functions/config.php");
require_once("../../functions/auth.php");

// !!!!!!!!!!!! 
ini_set("display_errors", "on");


echo "<html>\n";
echo "<head>\n";
echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf8\">\n";
echo "<LINK rel=\"stylesheet\" type=\"text/css\" href=\"default.css"."\">\n";
if( isset($doc_title) )
	echo "<title>".$doc_title."</title>\n";
echo "</head>\n";
echo "<body";
if( isset($doc_onLoad) )
	echo " onLoad=\"".$doc_onLoad."\"";
echo ">\n";

?>
