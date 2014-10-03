<?php
// --------------------------------- функции работы с матрицами
function tranpose($a)
{
	$n = count($a);
	$m = count($a[0]);
	$b = array();

	for($i=0; $i<$n; $i++)
	{
		for($j=0; $j<$m; $j++)
			$b[$j][$i] = $a[$i][$j];		
	}
	return $b;
}

function multiply($a, $b)
{
	$n1 = count($a);
	$m1 = count($a[0]);
	$n2 = count($b);
	$m2 = count($b[0]);
	if( $m1<>$n2 ) { echo "Размерности умножаемых матриц не совпадают!"; return false; }
	
	$c = array();
	// размерность произведения: n1 x m2 
	for($i=0; $i<$n1; $i++)
	{
		for($j=0; $j<$m2; $j++)
		{
			$c[$i][$j] = 0;
			for($k=0; $k<$n2; $k++)
				$c[$i][$j] += $a[$i][$k]*$b[$k][$j];
		}		
	}
	return $c;
}

function determinant($a)
{
	$n = count($a); // размерность должна совпадать (матрица должна быть квадратой!)
	$l = array();
	$u = array();

	// проверка матрицы на наличие 0
	$non_zero = true;
	for($i=0; $i<$n; $i++)
	{
		for($j=0; $j<$n; $j++)
		{
			if( $a[$i][$j] == 0 )
				$non_zero = false;
		}
	}
	if( !$non_zero )
		{ echo "В матрице есть нули, LU-разложение невозможно.\r\n"; return false; } 

	// находим LU - разложение
	for($i=0; $i<$n; $i++)
	{
		for($j=0; $j<$n; $j++)
		{
			$u[0][$i] = (float) $a[0][$i];
			$l[$i][0] = (float) $a[$i][0] / $u[0][0];
      $sum = 0;
			for($k=0; $k<$i; $k++)
			{
				$sum += $l[$i][$k] * $u[$k][$j];
			}
			$u[$i][$j] = (float) $a[$i][$j] - $sum;
			if($i > $j)
			{
      	$l[$j][$i] = 0;
			}
			else
			{
				$sum = 0;
				for($k=0; $k<$i; $k++)
				{
					$sum += $l[$j][$k] * $u[$k][$i];
				}
				$l[$j][$i] = (float)($a[$j][$i] - $sum) / $u[$i][$i];
			}
		}
	}
	// находим det A = произведение главной диагонали U
	$det = 1;
	for($i=0; $i<$n; $i++)
		$det *= $u[$i][$i];

	return $det;
}

function remove($a, $i_r, $j_r)
{
	$n = count($a);
	$b = array();
	
	//rows
	$i_out = 0;
	for( $i=0; $i<$n; $i++ )
	{
		if( $i == $i_r )	continue;
		
		$m = count($a[$i]);
		$j_out = 0;
		//columns
		for( $j=0; $j<$m; $j++ )
		{
			if( $j == $j_r )	continue;
			
			$b[$i_out][$j_out] = $a[$i][$j];
			$j_out++;
		}
		$i_out++;
	}
	return $b;
}

function invert($a)
{
	$n = count($a);	// матрица должна быть квадратной!
	$inverted = array(); 
	$minus1 = 1;
	
	$det = determinant($a);

	for( $i=0; $i<$n; $i++ )
	{
		$minus2 = $minus1;
		for( $j=0; $j<$n; $j++ )
		{
			$b = remove($a, $i, $j);
			$inverted[$i][$j] = determinant($b) * $minus2 / $det;
			$minus2 *= -1;						 		
		}
		$minus1 *= -1;
	}
	$inverted = tranpose($inverted);
	return $inverted; 
}

// --------------------------------- основная функция полиномной регресии
function PR($x, $y, $p)
{
	$num = min(count($x), count($y)); // размер входной выборки данных
	$xm = array();
	$ym = array();

	// подготовка матриц X и Y
	for( $i=0; $i<$num; $i++ )
	{
		for( $j=0; $j<=$p; $j++ )
			$xm[$i][$j] = pow($x[$i], $j);
		$ym[$i] = array($y[$i]);
	}

	// X`
	$xm_t = tranpose($xm); 
	// M = X`*X
	$m = multiply($xm_t, $xm);
	//print_r($m);
	// M = (X`*X)^-1
	$m = invert($m);
	//print_r($m);
	// M = (X`*X)^-1*X`
	$m = multiply($m, $xm_t);
	//print_r($m);
	// M = (X`*X)^-1*X`*y
	$m = multiply($m, $ym); // вычисленные коэффициенты полинома, матрица размерности p+1 x 1
	
	$a = array();
	for( $i=0; $i<=$p; $i++ )
			$a[$i] = $m[$i][0];
	
	return $a;
}

function estimate_y($a, $x)
{
	$p = count($a)-1; // степень полинома 
	$num = count($x); // размер массива данных	

	// вычисляем новые значения y по найденным коэффицентам полинома
	$y_est = array();  // массив с вычисленными значениями Y 
	for( $i=0; $i<$num; $i++ )
	{
		$y_temp = 0;
		for( $j=0; $j<=$p; $j++ )
			$y_temp += pow($x[$i], $j)*$a[$j];
		$y_est[$i] = $y_temp; 	
	}
	return $y_est;
}

function error_estimate($y, $y_est)
{
	$ssr = 0; // regression sum of squares (SSR)
	$ess = 0; // error sum of squares (ESS)
	$n = count($y);
	$y_mean = array_sum($y) / $n;
	$y_temp = $y;
	sort($y_temp);
	$y_min = $y_temp[0];
	$y_max = $y_temp[$n-1]; 

	for( $i=0; $i<$n; $i++ )
	{
		$ssr += pow(($y_est[$i]-$y_mean), 2);
		$ess += pow(($y[$i]-$y_est[$i]), 2);
	}

	$tss = $ssr + $ess; // total sum of squares (TSS)
	$r2 = $ssr / $tss; // Pearson's co-efficient of regression
	
	return array(	'mean' => $y_mean,
								'min' => $y_min,
								'max' => $y_max,
								'ssr' => $ssr,
								'ess' => $ess,
								'tss' => $tss,
								'r2' => $r2);
}

?>
