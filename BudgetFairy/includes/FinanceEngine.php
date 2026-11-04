<?php
declare(strict_types=1);

final class FinanceEngine
{
    public const MAX_MONTHS = 120;

    public static function toCents(mixed $v): int {
        if (!is_numeric($v)) return 0;
        $f = (float)$v;
        if (!is_finite($f) || $f < 0) return 0;
        return (int)round(min($f, 9999999999.99) * 100);
    }

    
    public static function normalize(array $in): array {
        $expenses = self::toCents($in['expenses'] ?? 0);
        $months = (int)($in['months'] ?? 1);
        return [
            'income'   => self::toCents($in['income'] ?? 0),
            'expenses' => $expenses,
            'cut'      => min(self::toCents($in['cut'] ?? 0), $expenses),   // can't cut more than you spend
            'saved'    => self::toCents($in['saved'] ?? 0),
            'target'   => self::toCents($in['target'] ?? 0),
            'months'   => max(1, min(self::MAX_MONTHS, $months)),
        ];
    }

    
    public static function project(array $in): array {
        $p = self::normalize($in);
        $monthly = $p['income'] - ($p['expenses'] - $p['cut']);

        $series = [];
        for ($i = 0; $i <= $p['months']; $i++) $series[] = $p['saved'] + $monthly * $i;
        $projected = $series[$p['months']];

        $hasGoal = $p['target'] > 0;
        $shortfall = $hasGoal ? max(0, $p['target'] - $projected) : 0;
        $surplus   = $hasGoal ? max(0, $projected - $p['target']) : 0;

        $monthsToGoal = null;
        if ($hasGoal) {
            if ($p['saved'] >= $p['target']) $monthsToGoal = 0;
            elseif ($monthly > 0) $monthsToGoal = intdiv($p['target'] - $p['saved'] + $monthly - 1, $monthly); // ceil
        }

        $runsOut = null;
        foreach ($series as $i => $bal) if ($bal < 0) { $runsOut = $i; break; }

        $requiredMonthly = ($hasGoal && $p['target'] > $p['saved'])
            ? intdiv($p['target'] - $p['saved'] + $p['months'] - 1, $p['months']) : 0;

        if ($monthly < 0)                         $status = 'deficit';
        elseif (!$hasGoal)                        $status = 'no_goal';
        elseif ($projected >= $p['target'])       $status = 'on_track';
        else                                      $status = 'short';

        $c = fn(int $x): float => $x / 100;
        return [
            'status' => $status,
            'months' => $p['months'],
            'monthly_savings' => $c($monthly),
            'projected_savings' => $c($projected),
            'shortfall' => $c($shortfall),
            'surplus' => $c($surplus),
            'months_to_goal' => $monthsToGoal,
            'runs_out_month' => $runsOut,
            'required_monthly' => $c($requiredMonthly),
            'series' => array_map($c, $series),
        ];
    }

    
    public static function adjust(array $base, array $adj): array {
        $b = self::normalize($base);
        $out = $base;
        $out['income']   = ($b['income'] + self::toCents($adj['extra_income'] ?? 0)) / 100;
        $out['cut']      = (self::toCents($adj['expense_cut'] ?? 0)) / 100;
        $out['months']   = $b['months'] + max(0, (int)($adj['extra_months'] ?? 0));
        return $out;
    }

    
    public static function compare(array $base, array $adj): array {
        $o = self::project($base);
        $a = self::project(self::adjust($base, $adj));
        $mtg = ($o['months_to_goal'] !== null && $a['months_to_goal'] !== null)
            ? $o['months_to_goal'] - $a['months_to_goal'] : null;
        return [
            'original' => $o,
            'adjusted' => $a,
            'delta' => [
                'monthly_savings'   => round($a['monthly_savings'] - $o['monthly_savings'], 2),
                'projected_savings' => round($a['projected_savings'] - $o['projected_savings'], 2),
                'shortfall'         => round($o['shortfall'] - $a['shortfall'], 2),   // positive = shortfall reduced
                'months_to_goal'    => $mtg,                                          // positive = reaches goal sooner
            ],
        ];
    }

    
    public static function afford(mixed $price, mixed $monthlySavings, mixed $saved, int $maxMonths = 120): array {
        $p = self::toCents($price);
        $s = self::toCents($saved);
        $m = (is_numeric($monthlySavings) && is_finite((float)$monthlySavings)) ? (int)round((float)$monthlySavings * 100) : 0;
        $out = ['status' => 'invalid', 'months' => null, 'leftover' => 0.0, 'shortfall' => 0.0, 'monthly_savings' => $m / 100.0];
        if ($p <= 0) return $out;
        if ($s >= $p) return ['status' => 'now', 'months' => 0, 'leftover' => ($s - $p) / 100.0, 'shortfall' => 0.0] + $out;
        $out['shortfall'] = ($p - $s) / 100.0;
        if ($m <= 0) return ['status' => 'never'] + $out;
        $months = intdiv($p - $s + $m - 1, $m);
        return ['status' => $months > $maxMonths ? 'too_long' : 'wait', 'months' => $months] + $out;
    }
}
