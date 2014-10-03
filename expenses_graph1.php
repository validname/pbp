<?php
require_once("functions/config.php");
require_once("functions/auth.php");
require_once("functions/func_db.php");
//require_once("functions/func_finance.php");
require_once("functions/func_web.php");
//require_once("functions/func_time.php");
require_once("functions/func_matrix.php");
include ("jpgraph/jpgraph.php");
include ("jpgraph/jpgraph_line.php");

// 1. флаги ошибок
$updating_invalid_value = 0;
$updating_error = 0;
$error_text = "";

// 2. подготовка переменных

// 2.1 переменные переданных из формы - обрезка, приведение к формату
$months = web_get_request_value($_REQUEST, "months", 'i');
$id_exp_cat = web_get_request_value($_REQUEST, "id_exp_cat", 'i');
$id_exp_subcat = web_get_request_value($_REQUEST, "id_exp_subcat", 'i');
$wo_last_month = web_get_checkbox_value($_REQUEST, "wo_last_month");
$rare = web_get_request_value($_REQUEST, "rare", 'i');
$width = web_get_request_value($_REQUEST, "width", 'i');
$height = web_get_request_value($_REQUEST, "height", 'i');

if( !$months )
	$months = 6;	// for 5-degree polynom
if( !$width )
	$width = 600;
if( !$height )
	$height = 400;
if( !$id_exp_cat )
{
	echo "Ошибка передачи параметров, отсутствует категория расходов.";
	exit;
}

if( $rare<=0 || $rare>3 )
	$rare = 3; //rare doesn't matters
$rare--;

// параметра графика (размер - задан выше)
$margin_left = 40;  
$margin_right = 120;
$margin_top = 20;
$margin_bottom = 60;

// 3. запросы к БД
$current_month = (int)date('m');
$current_year = (int)date('Y');
$current_yearmonth = $current_year*12 + $current_month;
$start_yearmonth = $current_yearmonth - $months + 1 - $wo_last_month;
$finish_yearmonth = $current_yearmonth - $wo_last_month;

/*
$start_month = $current_month - $months + 1 - $wo_last_month; // with current
$start_year = $current_year;
while( $start_month <= 0 )
{
	$start_month += 12;
	$start_year--;
}
*/
// запрос раcходов помесячно
$query  = "SELECT year(date), month(date), sum(value) ";
$query .= "FROM transactions AS t ";
$query .= "INNER JOIN expense_subcats2 AS sc2 ON sc2.id_exp_subcat2=t.id_subcat2 ";
$query .= "INNER JOIN expense_subcats AS sc ON sc.id_exp_subcat=sc2.id_exp_subcat ";
$query .= "WHERE id_transfer=0 AND value<0 ";
if( $rare<=1 )
	$query .= "AND is_rare='".$rare."' ";
$query .= "AND year(date)*12+month(date) >= ".$start_yearmonth." ";
$query .= "AND year(date)*12+month(date) <= ".$finish_yearmonth." ";
$query .= "AND sc.id_exp_cat=".$id_exp_cat." ";
if( $id_exp_subcat )
	$query .= "AND sc.id_exp_subcat=".$id_exp_subcat." ";
$query .= "GROUP BY year(date), month(date)";

$res = db_query($query);
if( !$res )
	{ echo "Ошибка подсчета месячных расходов. Экстренный выход."; exit; }
$expenses = array();
$labels = array();	
while( $temp_array=db_fetch_num_array($res) )
{
	$expenses[] = -$temp_array[2];
//	$labels[] = round(-$temp_array[2])." = ".$temp_array[0].".".sprintf("%02d", $temp_array[1]);
	$labels[] = $temp_array[0].".".sprintf("%02d", $temp_array[1]);
}

$labels[] = "+1";

// массивы с точками
//$y = array(2033.93,3129.64,1000.30,3959.92,2493.66,2348.59,1734.77,1223.80,2072.84,1768.11,2714.78,2582.24,1372.39,2795.43,2476.45,3214.19,2186.19,1923.06,3050.19,1627.26,3778.02,3126.59,2152.54);
$y = $expenses; 

$n = count($y); // длина выборки
if( $n < 2 ) // не хватит даже на полином 1-ой степени (линию)
	{ echo "Длина выборки из БД = ".$n." - слишком мало для графика."; exit; }

$x = array();
for( $i=0; $i<=$n; $i++ )
	$x[$i] = $i;

// узнаем коэффиенты полинома
$a1 = PR($x, $y, 1);
if( $n>2 )
	$a2 = PR($x, $y, 2);
if( $n>3 )
	$a3 = PR($x, $y, 3);
if( $n>4 )
	$a4 = PR($x, $y, 4);
if( $n>5 )
	$a5 = PR($x, $y, 5);

// вычисляем новые значения y по найденным коэффицентам полинома
$y_est1 = estimate_y($a1, $x);
if( $n>2 )
	$y_est2 = estimate_y($a2, $x);
if( $n>3 )
	$y_est3 = estimate_y($a3, $x);
if( $n>4 )
	$y_est4 = estimate_y($a4, $x);
if( $n>5 )
	$y_est5 = estimate_y($a5, $x);

// оцениваем погрешности на самом простом полиноме 1-ой степени 
$est_errors = error_estimate($y, $y_est1);

// среднее значение для графика нам интересно не как среднеарифметическое по выборке,
// а как среднеарифметическое от количества месяцев в запросе - его и считаем
$est_errors['mean'] = array_sum($y) / $months;
$max_y_value = $est_errors['max'];
$min_y_value = $est_errors['min'];

if( $max_y_value < $est_errors['mean'] )
	$max_y_value = $est_errors['mean'];
if( $min_y_value > $est_errors['mean'] )
	$min_y_value = $est_errors['mean'];

// выбираем масштаб для графика (чтобы хвосты полиномов не сбивали авто-масштаб)
$y_scope = ($max_y_value - $min_y_value)*1.05; // размах графика значений + 5%
$y_ticks = ($height-$margin_top-$margin_bottom) / 20; // допустим, 5 - минимальный шаг тика
$y_scope_site = $y_scope / $y_ticks; // сколько значений попадает на один тик
$y_scope_site_round = ceil($y_scope_site/10)*10;
if( $y_scope >= 800 )
	$y_scope_site_round = ceil($y_scope_site/100)*100;
if( $y_scope >= 8000 )
	$y_scope_site_round = ceil($y_scope_site/1000)*1000;
if( $y_scope >= 80000 )
	$y_scope_site_round = ceil($y_scope_site/10000)*10000;
$y_min_round = floor($min_y_value/$y_scope_site_round)*$y_scope_site_round; 
$y_max_round = ceil($max_y_value/$y_scope_site_round)*$y_scope_site_round;

// подбираем частоту текстовых меток на оси Y
$y_tick_interval = 1;


/*echo $ticks;
echo "<br>";
echo $scope_site_round;
echo "<br>";
echo $est_errors['max'];
echo "<br>";
echo $max_round; */

// подбираем частоту текстовых меток на оси X
$x_tick_interval = 1;

// графический вывод
// Create the graph. These two calls are always required 
$graph = new Graph($width, $height,"auto");     
$graph->img->SetMargin($margin_left, $margin_right, $margin_top, $margin_bottom);

// ось Y
$graph->SetScale("textlin",$y_min_round,$y_max_round); 
$graph->ygrid->Show(true,true);
$graph->ygrid->SetFill(true,'#EFEFEF@0.5','#BBCCFF@0.5');
$graph->yaxis->SetTextLabelInterval($y_tick_interval);
// ось X
$graph->xaxis->SetTickLabels($labels);
$graph->xaxis->SetTextLabelInterval($x_tick_interval);
$graph->xaxis->SetLabelAngle(90);

// Create the linear plot 
$lineplot =new LinePlot($y); 
$lineplot1 =new LinePlot($y_est1); 
if( $n>2 )
	$lineplot2 =new LinePlot($y_est2); 
if( $n>3 )
	$lineplot3 =new LinePlot($y_est3); 
if( $n>4 )
	$lineplot4 =new LinePlot($y_est4); 
if( $n>5 )
	$lineplot5 =new LinePlot($y_est5); 
$lineplot_m = new PlotLine(HORIZONTAL, $est_errors['mean']);

$graph->Add($lineplot);
$graph->Add($lineplot_m);
$graph->Add($lineplot1);
if( $n>2 )
	$graph->Add($lineplot2);
if( $n>3 )
	$graph->Add($lineplot3);
if( $n>4 )
	$graph->Add($lineplot4);
if( $n>5 )
	$graph->Add($lineplot5);

$lineplot->SetColor("black");
$lineplot_m->SetColor("black");
$lineplot1->SetColor("#f00000");
if( $n>2 )
	$lineplot2->SetColor("#00f000");
if( $n>3 )
	$lineplot3->SetColor("#0000f0");
if( $n>4 )
	$lineplot4->SetColor("#f000f0");
if( $n>5 )
	$lineplot5->SetColor("#00f0f0");

$lineplot->SetLegend("data");
$lineplot_m->SetLegend("M (".round($est_errors['mean']).")");
$lineplot1->SetLegend("p1 (".round($y_est1[$n]).")");
if( $n>2 )
	$lineplot2->SetLegend("p2 (".round($y_est2[$n]).")");
if( $n>3 )
	$lineplot3->SetLegend("p3 (".round($y_est3[$n]).")");
if( $n>4 )
	$lineplot4->SetLegend("p4 (".round($y_est4[$n]).")");
if( $n>5 )
	$lineplot5->SetLegend("p5 (".round($y_est5[$n]).")");

$lineplot->value->Show();
$lineplot->value ->SetColor("darkred"); 
//$lineplot ->value->SetFont(FF_FONT1); 
$lineplot ->value->SetFormat("%d");
$lineplot->mark->SetType(MARK_SQUARE);
$graph ->legend->Pos(0.01,0.5,"right" ,"center");

// Display the graph 
$graph->Stroke();

?>
