<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$uid  = api_guard();
$data = requestData();
$action = $data['action'] ?? '';
$pdo = db();


function transactionFields(array $d): array {
    $type = $d['type'] ?? '';
    $amount = parseAmount($d['amount'] ?? null);
    $desc = cleanText($d['description'] ?? '', 120);
    $cat  = cleanText($d['category'] ?? '', 60);
    $date = $d['transaction_date'] ?? '';
    if (!in_array($type, ['income', 'expense'], true) || $amount === null || $desc === '' || $cat === '' || !validDate($date)) {
        jsonResponse(['error' => 'Please enter valid transaction details (amount above 0, real date, description and category).'], 422);
    }
    return [$type, $amount, $desc, $cat, $date];
}
function idOf(array $d): int {
    $id = filter_var($d['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) jsonResponse(['error' => 'Invalid transaction.'], 422);
    return $id;
}

switch ($action) {
    case 'create':
        [$type, $amount, $desc, $cat, $date] = transactionFields($data);
        $pdo->prepare('INSERT INTO transactions (user_id, type, amount, description, category, transaction_date) VALUES (?,?,?,?,?,?)')
            ->execute([$uid, $type, $amount, $desc, $cat, $date]);
        jsonResponse(['success' => true, 'message' => 'Transaction saved.']);

    case 'update':
        $id = idOf($data);
        [$type, $amount, $desc, $cat, $date] = transactionFields($data);
        $st = $pdo->prepare('UPDATE transactions SET type=?, amount=?, description=?, category=?, transaction_date=? WHERE id=? AND user_id=?');
        $st->execute([$type, $amount, $desc, $cat, $date, $id, $uid]);
        if ($st->rowCount() === 0) jsonResponse(['error' => 'Transaction not found.'], 404);
        jsonResponse(['success' => true, 'message' => 'Transaction updated.']);

    case 'delete':
        $id = idOf($data);
        $st = $pdo->prepare('DELETE FROM transactions WHERE id=? AND user_id=?');
        $st->execute([$id, $uid]);
        if ($st->rowCount() === 0) jsonResponse(['error' => 'Transaction not found.'], 404);
        jsonResponse(['success' => true, 'message' => 'Transaction deleted.']);

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
