<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$uid  = api_guard('simulate');
$data = requestData();
$price = parseAmount($data['price'] ?? null);
if ($price === null) jsonResponse(['error' => 'Enter the price as a number above 0.'], 422);

$typical = typicalMonthlySavings($uid);
$saved = totalSaved($uid);
$r = FinanceEngine::afford($price, $typical['monthly'], $saved);
jsonResponse(['success' => true, 'price' => $price, 'saved' => $saved, 'months_used' => $typical['months_used']] + $r);