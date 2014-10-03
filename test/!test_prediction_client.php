<?
require_once("functions/config.php");
require_once("functions/auth.php");
require_once("functions/func_finance.php");
require_once("functions/func_web.php");
require_once("functions/func_time.php");
require_once("functions/func_dic.php");

$search = web_get_request_value($_REQUEST, "search", 's');

?>
<script language="Javascript">


function submit_form()
{
	// 1. create an instance
		var xhr; 
    try {  xhr = new ActiveXObject('Msxml2.XMLHTTP');   }
    catch (e) 
    {
        try {   xhr = new ActiveXObject('Microsoft.XMLHTTP');    }
        catch (e2) 
        {
          try {  xhr = new XMLHttpRequest();     }
          catch (e3) {  xhr = false;   }
        }
     }
  // 2. function
	xhr.onreadystatechange = function() { // instructions to process the response };                   
	
	if (xhr.readyState == 4)
	{
		var Result = document.getElementById ('Result');
		if(xhr.status  == 200) {
	  	Result.innerHTML = "Received:"  + xhr.responseText; 
	  	Result.style.color='black';
	  }
	  else {
	  	Result.innerHTML = "Error code " + xhr.status;
	  	Result.style.color='red';
	  }
	} else {
	  // Wait...
	}
}
	// 3. calling server
   xhr.open(GET, "data.txt",  true); 
   xhr.send(null);
	 
	 return false; 
}

var requestObj = null;
 if (window.XMLHttpRequest) {
  //это работает для opera и firefox
  requestObj = new XMLHttpRequest();
 } 
 else 
   if (window.ActiveXObject) {
     // а это проверка internet explorer
     requestObj = new ActiveXObject("Msxml2.XMLHTTP");
     if (!requestObj)
      requestObj = new ActiveXObject("Microsoft.XMLHTTP"); 
   };
 
 function sender (){
  if (! requestObj ) return;
  requestObj.onreadystatechange = function (){
    if (requestObj.readyState == 4 ){
      if (requestObj.status == 200){
        var dv_Result = document.getElementById ('dv_Result');
        var result = requestObj.responseXML.getElementsByTagName('result')[0];
        dv_Result.style.color =result.getElementsByTagName('color')[0].firstChild.nodeValue;
        dv_Result.innerHTML = result.getElementsByTagName('message')[0].firstChild.nodeValue;  
      }
      else  
        alert ('Ошибка, запрос не может быть выполнен, код: ' + requestObj.status); 
    } 
  };
  requestObj.open('POST','ax01.xml',true);
  requestObj.send(); 
 }

</script>

<form name="form" action=<? echo $_SERVER['PHP_SELF']; ?> method="<? echo FORM_METHOD; ?>" onsubmit="submit_form()">
<input type=text name=search value="<? echo $search; ?>">
<input type=submit>
</form>

<div id="Result" style="">Result ...</div>
<?
// 8. Конец скрипта.
include("footer.php");
?>
