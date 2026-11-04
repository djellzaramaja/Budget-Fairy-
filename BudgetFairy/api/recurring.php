<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$uid  = api_guard();
$data = requestData();
$pdo  = db();

switch ($data['action'] ?? '') {
    case 'create':
        $type = $data['type'] ?? '';
        $amount = parseAmount($data['amount'] ?? null);
        $desc = cleanText($data['description'] ?? '', 120);
        $cat  = cleanText($data['category'] ?? '', 60);
        $dom  = filter_var($data['day_of_month'] ?? null, FILTER_VALIDATE_INT);
        $start = $data['start_date'] ?? '';
        if (!in_array($type, ['income', 'expense'], true) || $amount === null || $desc === '' || $cat === '' ||
            $dom === false || $dom < 1 || $dom > 28 || !validDate($start)) {
            jsonResponse(['error' => 'Enter valid details. Day of month must be between 1 and 28.'], 422);
        }
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM recurring_transactions WHERE user_id = ?');
        $cnt->execute([$uid]);
        if ((int)$cnt->fetchColumn() >= 30) jsonResponse(['error' => 'You can have up to 30 recurring items.'], 422);
        $pdo->prepare('INSERT INTO recurring_transactions (user_id, type, amount, description, category, day_of_month, start_date) VALUES (?,?,?,?,?,?,?)')
            ->execute([$uid, $type, $amount, $desc, $cat, $dom, $start]);
        processRecurring($uid);   // create any already-due transactions straight away
        jsonResponse(['success' => true, 'message' => 'Recurring item added.']);

    case 'delete':
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) jsonResponse(['error' => 'Invalid item.'], 422);
        $st = $pdo->prepare('DELETE FROM recurring_transactions WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        if ($st->rowCount() === 0) jsonResponse(['error' => 'Item not found.'], 404);
        jsonResponse(['success' => true, 'message' => 'Recurring item removed. Past transactions are kept.']);

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
