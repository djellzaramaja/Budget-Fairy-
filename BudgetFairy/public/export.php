<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$uid = require_login();
$month = monthRange($_GET['month'] ?? null) ? $_GET['month'] : '';
$sql = 'SELECT transaction_date, type, amount, description, category FROM transactions WHERE user_id = ?';
$params = [$uid];
if ($month) { [$f, $t] = monthRange($month); $sql .= ' AND transaction_date >= ? AND transaction_date < ?'; array_push($params, $f, $t); }
$st = db()->prepare($sql . ' ORDER BY transaction_date DESC, id DESC');
$st->execute($params);


$safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : $v;

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="budgetfairy-' . ($month ?: 'all') . '.csv"');
header('Cache-Control: no-store');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows € and accents correctly
fputcsv($out, ['Date', 'Type', 'Amount (EUR)', 'Description', 'Category'], ',', '"', '');
foreach ($st as $r) fputcsv($out, [$r['transaction_date'], $r['type'], $r['amount'], $safe($r['description']), $safe($r['category'])], ',', '"', '');
fclose($out);