<?
require_once("config.php");

function sent_auth_header()
{
	header('WWW-Authenticate: Basic realm=Personal Budget Planner');
	header('HTTP/1.0 401 Unauthorized');
}

/*if( php_sapi_name() !== 'cli' ) {
	if ( !isset($_SERVER['PHP_AUTH_USER']) )
	{
		sent_auth_header();
		echo "Вам нужно авторизироваться.";
		exit;
	}
	if( $_SERVER['PHP_AUTH_USER'] != $auth_config['user_name'] || $_SERVER['PHP_AUTH_PW'] != $auth_config['user_pass'] )
	{
		sent_auth_header();
		echo "Неверная пара логин/пароль!";
		exit;
	}
}*/
?>
