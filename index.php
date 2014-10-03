<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf8">
</head>
<?
require_once("functions/config.php");
require_once("functions/auth.php");
include("header.php");
?>
<hr>
<h3>Счета</h3>
<a href="accounts.php">Остатки на счетах</a><br>
<a href="savings.php">Накопления</a><br>
<a href="budget_monthly.php">Месячный бюджет</a><br>
<hr>
<h3>Расходы</h3>
<a href="expenses.php">!Ввод расходов</a><br>
<a href="#expenses_recompense.php">!Компенсация расходов</a><br>
<a href="planned_expenses.php">Запланированные расходы</a><br>
<hr>
<h3>Доходы</h3>
<a href="#incomes.php">!Ввод доходов</a><br>
<a href="#incomes_accumulate.php">!Перенос доходов</a><br>
<hr>
<h3>Отчеты и графики</h3>
<a href="transactions.php">Транзакции</a><br>
<a href="debt_transactions.php">Долговые перемещения</a><br>
<a href="expenses_graphic.php">Графики расходов</a><br>
<hr>
<h3>Справочники</h3>
<a href="expenses_dir.php">!Справочник категорий расходов</a><br>
<hr>
<h3>Специальное</h3>
<a href="import/step1.php">Импорт данных из HomeBuh (ручной)</a><br>
<a href="import/homebuh_paradox/import_silent.php">Импорт данных из HomeBuh (автоматический)</a><br>
<a href="mark_rare_expenses.php">Найти и пометить нетипичные расходы (за последние <? echo RARE_SEARCH_MONTHS; ?> месяцев)</a><br>
<a href="#"></a><br>
