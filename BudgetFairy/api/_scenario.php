<?php
declare(strict_types=1);

function scenarioFromRequest(array $d): array {
    $num = fn($k, $max) => min($max, max(0, (float)($d[$k] ?? 0)));
    $base = [
        'income'   => $num('income', 1e9),
        'expenses' => $num('expenses', 1e9),
        'saved'    => $num('saved', 1e9),
        'target'   => $num('target', 1e9),
        'months'   => max(1, min(FinanceEngine::MAX_MONTHS, (int)($d['months'] ?? 1))),
    ];
    $adj = [
        'expense_cut'  => $num('expense_cut', 1e9),
        'extra_income' => $num('extra_income', 1e9),
        'extra_months' => max(0, min(FinanceEngine::MAX_MONTHS, (int)($d['extra_months'] ?? 0))),
    ];
    return FinanceEngine::compare($base, $adj);
}
