<?php
declare(strict_types=1);


function currentMonthRange(): array {
    $s = new DateTimeImmutable('first day of this month');
    return [$s->format('Y-m-d'), $s->modify('first day of next month')->format('Y-m-d')];
}

function categoryTotals(int $uid, string $from, string $to, int $limit = 8): array {
    $st = db()->prepare("SELECT category, SUM(amount) AS total FROM transactions
        WHERE user_id = ? AND type = 'expense' AND transaction_date >= ? AND transaction_date < ?
        GROUP BY category ORDER BY total DESC LIMIT " . (int)$limit);
    $st->execute([$uid, $from, $to]);
    return array_map(fn($r) => ['category' => $r['category'], 'total' => (float)$r['total']], $st->fetchAll());
}


function monthlyHistory(int $uid, int $n = 6): array {
    $first = (new DateTimeImmutable('first day of this month'))->modify('-' . ($n - 1) . ' months');
    $st = db()->prepare("SELECT DATE_FORMAT(transaction_date,'%Y-%m') ym,
        SUM(CASE WHEN type='income' THEN amount ELSE 0 END) i, SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) x
        FROM transactions WHERE user_id = ? AND transaction_date >= ? GROUP BY ym");
    $st->execute([$uid, $first->format('Y-m-d')]);
    $by = [];
    foreach ($st->fetchAll() as $r) $by[$r['ym']] = [(float)$r['i'], (float)$r['x']];
    $labels = $inc = $exp = [];
    for ($k = 0; $k < $n; $k++) {
        $m = $first->modify("+$k months");
        $labels[] = $m->format('M');
        $inc[] = $by[$m->format('Y-m')][0] ?? 0.0;
        $exp[] = $by[$m->format('Y-m')][1] ?? 0.0;
    }
    return ['labels' => $labels, 'income' => $inc, 'expenses' => $exp];
}


function typicalMonthlySavings(int $uid): array {
    $h = monthlyHistory($uid, 3);
    $diffs = [];
    foreach ($h['income'] as $k => $i) if ($i > 0 || $h['expenses'][$k] > 0) $diffs[] = $i - $h['expenses'][$k];
    return ['monthly' => $diffs ? array_sum($diffs) / count($diffs) : 0.0, 'months_used' => count($diffs)];
}

function totalSaved(int $uid): float {
    $st = db()->prepare('SELECT COALESCE(SUM(saved_amount),0) FROM savings_goals WHERE user_id = ?');
    $st->execute([$uid]);
    return (float)$st->fetchColumn();
}


function processRecurring(int $uid): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT * FROM recurring_transactions WHERE user_id = ?');
    $st->execute([$uid]);
    $today = date('Y-m-d');
    foreach ($st->fetchAll() as $r) {
        $prev = $r['last_generated'];
        foreach (recurringDueDates($r['start_date'], (int)$r['day_of_month'], $prev, $today) as $date) {
            // Claim this date first; if another request already did, rowCount is 0 and we skip (no duplicates).
            $claim = $pdo->prepare('UPDATE recurring_transactions SET last_generated = ? WHERE id = ? AND user_id = ? AND last_generated <=> ?');
            $claim->execute([$date, $r['id'], $uid, $prev]);
            if ($claim->rowCount() === 1) {
                $pdo->prepare('INSERT INTO transactions (user_id, type, amount, description, category, transaction_date) VALUES (?,?,?,?,?,?)')
                    ->execute([$uid, $r['type'], $r['amount'], $r['description'], $r['category'], $date]);
            }
            $prev = $date;
        }
    }
}
