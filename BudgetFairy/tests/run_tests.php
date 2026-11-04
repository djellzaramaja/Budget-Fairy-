<?php

declare(strict_types=1);
define('BF_ROOT', dirname(__DIR__));
require BF_ROOT . '/includes/FinanceEngine.php';
require BF_ROOT . '/includes/helpers.php';

$pass = 0; $fail = 0;
function check(string $name, mixed $actual, mixed $expected): void {
    global $pass, $fail;
    if ($actual === $expected) { $pass++; echo "  ok   $name\n"; return; }
    $fail++; echo "  FAIL $name\n       expected " . var_export($expected, true) . "\n       got      " . var_export($actual, true) . "\n";
}

echo "FinanceEngine::project\n";
$r = FinanceEngine::project(['income'=>650,'expenses'=>480,'saved'=>200,'target'=>700,'months'=>3]);
check('monthly savings', $r['monthly_savings'], 170.0);
check('projected after 3 months', $r['projected_savings'], 710.0);
check('on track', $r['status'], 'on_track');
check('surplus', $r['surplus'], 10.0);
check('months to goal (ceil 500/170)', $r['months_to_goal'], 3);
check('series length = months+1', count($r['series']), 4);

$r = FinanceEngine::project(['income'=>650,'expenses'=>480,'saved'=>200,'target'=>1000,'months'=>3]);
check('short of goal', $r['status'], 'short');
check('shortfall', $r['shortfall'], 290.0);
check('required monthly (ceil 800/3 = 266.67)', $r['required_monthly'], 266.67);

echo "Edge cases\n";
$r = FinanceEngine::project(['income'=>400,'expenses'=>500,'saved'=>100,'target'=>300,'months'=>3]);
check('deficit status', $r['status'], 'deficit');
check('negative monthly', $r['monthly_savings'], -100.0);
check('never reaches goal', $r['months_to_goal'], null);
check('savings run out in month 2 (100 -> 0 -> -100)', $r['runs_out_month'], 2);
check('projected can go negative (debt shown honestly)', $r['projected_savings'], -200.0);

$r = FinanceEngine::project(['income'=>500,'expenses'=>500,'saved'=>0,'target'=>100,'months'=>6]);
check('zero monthly savings', $r['monthly_savings'], 0.0);
check('zero monthly: never reaches goal', $r['months_to_goal'], null);
check('zero monthly: status short (not deficit)', $r['status'], 'short');

$r = FinanceEngine::project(['income'=>100,'expenses'=>100,'saved'=>900,'target'=>500,'months'=>2]);
check('already reached: months_to_goal 0', $r['months_to_goal'], 0);

$r = FinanceEngine::project(['income'=>500,'expenses'=>300,'saved'=>0,'target'=>0,'months'=>3]);
check('no goal (target 0)', $r['status'], 'no_goal');
check('no goal: shortfall 0', $r['shortfall'], 0.0);
check('no goal: months_to_goal null', $r['months_to_goal'], null);

check('months clamped low', FinanceEngine::project(['months'=>0])['months'], 1);
check('months clamped high', FinanceEngine::project(['months'=>9999])['months'], FinanceEngine::MAX_MONTHS);
check('negative inputs become 0', FinanceEngine::project(['income'=>-50,'expenses'=>-10,'saved'=>-5,'target'=>-1,'months'=>2])['projected_savings'], 0.0);
check('garbage strings become 0', FinanceEngine::project(['income'=>'abc','expenses'=>null,'months'=>'x'])['monthly_savings'], 0.0);
check('NaN/INF become 0', FinanceEngine::project(['income'=>NAN,'expenses'=>INF])['monthly_savings'], 0.0);
check('cut cannot exceed expenses (100 income, 50 spend, cut 200 => 100)', FinanceEngine::project(['income'=>100,'expenses'=>50,'cut'=>200,'months'=>1])['monthly_savings'], 100.0);
check('float drift avoided (0.1+0.2)', FinanceEngine::project(['income'=>0.30,'expenses'=>0.10,'months'=>1])['monthly_savings'], 0.2);
check('huge numbers stay finite', is_finite(FinanceEngine::project(['income'=>1e308,'months'=>120])['projected_savings']), true);

echo "FinanceEngine::compare\n";
$base = ['income'=>650,'expenses'=>480,'saved'=>200,'target'=>1000,'months'=>3];
$c = FinanceEngine::compare($base, ['expense_cut'=>50, 'extra_income'=>0, 'extra_months'=>0]);
check('cut improves monthly by 50', $c['delta']['monthly_savings'], 50.0);
check('cut improves projection by 150', $c['delta']['projected_savings'], 150.0);
check('shortfall reduced by 150', $c['delta']['shortfall'], 150.0);
check('original untouched by adjustment', $c['original']['monthly_savings'], 170.0);
$c = FinanceEngine::compare($base, ['expense_cut'=>0, 'extra_income'=>0, 'extra_months'=>3]);
check('extra months extends horizon', $c['adjusted']['months'], 6);
check('goal reached sooner = 0 when only time changes', $c['delta']['months_to_goal'], 0);
$c = FinanceEngine::compare($base, ['expense_cut'=>100]);
check('months saved: 800/170=5 vs 800/270=3', $c['delta']['months_to_goal'], 2);
$c = FinanceEngine::compare(['income'=>400,'expenses'=>500,'saved'=>0,'target'=>100,'months'=>3], ['expense_cut'=>200]);
check('rescue plan: deficit -> reaches goal', [$c['original']['status'], $c['adjusted']['status']], ['deficit','on_track']);
check('months_to_goal delta null when original never reaches', $c['delta']['months_to_goal'], null);
$c = FinanceEngine::compare($base, ['expense_cut'=>-50, 'extra_income'=>-20, 'extra_months'=>-5]);
check('negative adjustments ignored', $c['delta']['monthly_savings'], 0.0);

echo "Validation helpers\n";
check('parseAmount ok', parseAmount('12.345'), 12.35);
check('parseAmount rejects zero', parseAmount('0'), null);
check('parseAmount allows zero when asked', parseAmount('0', true), 0.0);
check('parseAmount rejects negative', parseAmount(-5), null);
check('parseAmount rejects text', parseAmount('12abc'), null);
check('parseAmount rejects INF', parseAmount(INF), null);
check('parseAmount rejects too large', parseAmount(1e12), null);
check('parseAmount rejects bool', parseAmount(true), null);
check('validDate ok', validDate('2026-02-28'), true);
check('validDate rejects 2026-02-30', validDate('2026-02-30'), false);
check('validDate rejects junk', validDate('tomorrow'), false);
check('cleanText trims + limits', cleanText("  hello world  ", 5), 'hello');
check('cleanText strips control chars', cleanText("a\x00b\x07c"), 'abc');
check('cleanText non-scalar', cleanText(['x']), '');
check('e() escapes HTML', e('<script>"x"</script>'), '&lt;script&gt;&quot;x&quot;&lt;/script&gt;');


echo "FinanceEngine::afford\n";
$a = FinanceEngine::afford(300, 100, 50);
check('afford: wait 3 months (250/100)', [$a['status'], $a['months']], ['wait', 3]);
$a = FinanceEngine::afford(300, 100, 500);
check('afford: already can, leftover 200', [$a['status'], $a['leftover']], ['now', 200.0]);
check('afford: exactly enough = now', FinanceEngine::afford(100, 0, 100)['status'], 'now');
check('afford: no savings = never', FinanceEngine::afford(300, 0, 50)['status'], 'never');
check('afford: negative savings = never', FinanceEngine::afford(300, -40, 50)['status'], 'never');
check('afford: shortfall reported', FinanceEngine::afford(300, -40, 50)['shortfall'], 250.0);
check('afford: zero price invalid', FinanceEngine::afford(0, 100, 50)['status'], 'invalid');
check('afford: garbage price invalid', FinanceEngine::afford('abc', 100, 50)['status'], 'invalid');
check('afford: 1 cent/month is too long', FinanceEngine::afford(5000, 0.01, 0)['status'], 'too_long');
check('afford: NaN savings treated as 0', FinanceEngine::afford(100, NAN, 0)['status'], 'never');

echo "Recurring + month helpers\n";
check('recurring: 3 due dates', recurringDueDates('2026-01-15', 15, null, '2026-03-20'), ['2026-01-15','2026-02-15','2026-03-15']);
check('recurring: start after day -> next month', recurringDueDates('2026-01-20', 15, null, '2026-02-28'), ['2026-02-15']);
check('recurring: nothing new after last', recurringDueDates('2026-01-15', 15, '2026-02-15', '2026-02-20'), []);
check('recurring: continues after last', recurringDueDates('2026-01-15', 15, '2026-02-15', '2026-04-16'), ['2026-03-15','2026-04-15']);
check('recurring: not due yet', recurringDueDates('2026-05-10', 10, null, '2026-05-09'), []);
check('recurring: day 31 clamps to 28', recurringDueDates('2026-01-01', 31, null, '2026-02-28'), ['2026-01-28','2026-02-28']);
check('recurring: year rollover', recurringDueDates('2026-12-05', 5, null, '2027-01-06'), ['2026-12-05','2027-01-05']);
check('recurring: cap respected', count(recurringDueDates('2000-01-01', 1, null, '2026-01-01', 12)), 12);
check('monthRange ok', monthRange('2026-12'), ['2026-12-01', '2027-01-01']);
check('monthRange rejects 2026-13', monthRange('2026-13'), null);
check('monthRange rejects junk', monthRange("2026-1'; DROP"), null);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
