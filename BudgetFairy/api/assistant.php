<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_scenario.php';

$uid  = api_guard('assistant');
$data = requestData();
$message = cleanText($data['message'] ?? '', 700);
if ($message === '') jsonResponse(['error' => 'Please enter a question.'], 422);


$cmp = scenarioFromRequest($data);
$o = $cmp['original']; $a = $cmp['adjusted'];

[$from, $to] = currentMonthRange();
$top = array_slice(categoryTotals($uid, $from, $to), 0, 3);
$reply = AiClient::explain($message, $cmp, ['this_month_top_spending' => $top]);
if ($reply !== null) jsonResponse(['reply' => $reply, 'mode' => 'ai']);


$text = match ($a['status']) {
    'deficit'  => "Your expenses are higher than your income by " . money(abs($a['monthly_savings'])) . " a month in this plan, so savings would shrink. Try lowering expenses or raising income first.",
    'no_goal'  => "You would save about " . money($a['monthly_savings']) . " a month, reaching " . money($a['projected_savings']) . " in {$a['months']} months. Set a goal amount to see if you're on track.",
    'on_track' => "Good news! You could have " . money($a['projected_savings']) . " after {$a['months']} months, enough for your goal.",
    default    => "You'd be about " . money($a['shortfall']) . " short after {$a['months']} months. Saving around " . money($a['required_monthly']) . " a month would close the gap.",
};
if ($top && preg_match('/tip|save|saving|cut|faster|reduce|spend/i', $message)) {
    $totalSpent = array_sum(array_column(categoryTotals($uid, $from, $to, 50), 'total'));
    $t = $top[0];
    $pct = $totalSpent > 0 ? round($t['total'] / $totalSpent * 100) : 0;
    $text = "Your biggest spending category this month is {$t['category']}: " . money($t['total']) . " ({$pct}% of your expenses). "
          . "Trimming just 10% there would free about " . money($t['total'] * 0.10) . " a month, so try a small, realistic cut there first.";
}
if ($cmp['delta']['projected_savings'] != 0.0) {
    $text .= " Compared with your original plan, the adjusted version changes projected savings by " . money($cmp['delta']['projected_savings']) . ".";
}
jsonResponse(['reply' => $text . " (Estimates based on the figures you entered.)", 'mode' => 'demo']);
