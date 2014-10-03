<?php
// функции оценки задержек
require_once("config.php");

$time_counter = get_microtime();

function get_microtime()
{
 return explode(" ", microtime());
}

function get_elapsed_microtime()
{
 global $time_counter; 

 $time_counter2 = get_microtime();
 $elapsed = (float)($time_counter2[1]-$time_counter[1])+($time_counter2[0]-$time_counter[0]);
 $time_counter = $time_counter2; 
 return $elapsed; 
}

?>
