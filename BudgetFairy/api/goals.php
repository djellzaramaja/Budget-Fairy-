<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$uid  = api_guard();
$data = requestData();
$action = $data['action'] ?? '';
$pdo = db();

function goalFields(array $d): array {
    $title  = cleanText($d['title'] ?? '', 100);
    $target = parseAmount($d['target_amount'] ?? null);
    $saved  = parseAmount($d['saved_amount'] ?? 0, true);
    $date   = $d['target_date'] ?? null;
    if ($date === '') $date = null;
    if ($title === '' || $target === null || $saved === null || $saved > $target) {
        jsonResponse(['error' => 'Enter a valid goal. Saved amount cannot exceed the target.'], 422);
    }
    if ($date !== null && !validDate($date)) jsonResponse(['error' => 'Invalid target date.'], 422);
    return [$title, $target, $saved, $date];
}
function goalId(array $d): int {
    $id = filter_var($d['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) jsonResponse(['error' => 'Invalid goal.'], 422);
    return $id;
}

switch ($action) {
    case 'create':
        [$title, $target, $saved, $date] = goalFields($data);
        $pdo->prepare('INSERT INTO savings_goals (user_id, title, target_amount, saved_amount, target_date) VALUES (?,?,?,?,?)')
            ->execute([$uid, $title, $target, $saved, $date]);
        jsonResponse(['success' => true, 'message' => 'Savings goal created.']);

    case 'update':
        $id = goalId($data);
        [$title, $target, $saved, $date] = goalFields($data);
        $st = $pdo->prepare('UPDATE savings_goals SET title=?, target_amount=?, saved_amount=?, target_date=? WHERE id=? AND user_id=?');
        $st->execute([$title, $target, $saved, $date, $id, $uid]);
        if ($st->rowCount() === 0) jsonResponse(['error' => 'Goal not found.'], 404);
        jsonResponse(['success' => true, 'message' => 'Goal updated.']);

    case 'contribute':
        $id = goalId($data);
        $amount = parseAmount($data['amount'] ?? null);
        if ($amount === null) jsonResponse(['error' => 'Enter an amount above 0.'], 422);
        // LEAST() caps the saved amount at the target so a goal can never go over 100%.
        $st = $pdo->prepare('UPDATE savings_goals SET saved_amount = LEAST(target_amount, saved_amount + ?) WHERE id = ? AND user_id = ?');
        $st->execute([$amount, $id, $uid]);
        if ($st->rowCount() === 0) jsonResponse(['error' => 'Goal not found.'], 404);
        jsonResponse(['success' => true, 'message' => 'Added to your goal!']);

    case 'delete':
        $id = goalId($data);
        $st = $pdo->prepare('DELETE FROM savings_goals WHERE id=? AND user_id=?');
        $st->execute([$id, $uid]);
        if ($st->rowCount() === 0) jsonResponse(['error' => 'Goal not found.'], 404);
        jsonResponse(['success' => true, 'message' => 'Goal deleted.']);

    default:
        jsonResponse(['error' => 'Unknown action.'], 400);
}
