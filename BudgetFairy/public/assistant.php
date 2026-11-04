<?php
$pageTitle = 'Fairy Assistant';
require_once __DIR__ . '/../includes/bootstrap.php';
$uid = require_login();
$pdo = db();


$st = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type='income' THEN amount END),0) i, COALESCE(SUM(CASE WHEN type='expense' THEN amount END),0) x
  FROM transactions WHERE user_id=? AND transaction_date >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
  AND transaction_date < DATE_ADD(DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01'), INTERVAL 1 MONTH)");
$st->execute([$uid]); $m = $st->fetch();
$income = (float)$m['i']; $expenses = (float)$m['x'];
$usingSample = ($income == 0 && $expenses == 0);
if ($usingSample) { $income = 650; $expenses = 480; }
$st = $pdo->prepare('SELECT id, title, target_amount, saved_amount FROM savings_goals WHERE user_id=? ORDER BY created_at DESC, id DESC');
$st->execute([$uid]); $goals = $st->fetchAll();
$firstGoal = $goals[0] ?? null;
$prefTarget = parseAmount($_GET['target'] ?? null);
if ($prefTarget !== null) $firstGoal = ['id' => 0, 'target_amount' => $prefTarget, 'saved_amount' => totalSaved($uid)];

require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">A LITTLE CLARITY GOES A LONG WAY</span><h1>Meet your <span class="text-gradient">Fairy Assistant.</span></h1><p>Compare your current plan with an adjusted one and see what changes.</p></div></section>
<section class="container pb-5"><div class="row g-4">
  <div class="col-lg-7"><div class="simulator-card">
    <div class="d-flex align-items-center gap-3 mb-3"><div class="fairy-avatar"><img src="assets/img/fairy.svg" alt=""></div><div><h3 class="mb-0">What-if simulator</h3><div class="small text-muted"><?= $usingSample ? 'Using sample numbers — add transactions to use your own.' : 'Prefilled from this month’s transactions.' ?></div></div></div>
    <form id="simForm" class="row g-3">
     <div class="col-12"><label class="form-label" for="simGoal">Goal</label>
       <select id="simGoal" class="form-select"><option value="">Custom amount</option>
       <?php foreach ($goals as $g): ?><option value="<?= (int)$g['id'] ?>" data-target="<?= e($g['target_amount']) ?>" data-saved="<?= e($g['saved_amount']) ?>" <?= $firstGoal && $prefTarget === null && $g['id']==$firstGoal['id'] ? 'selected' : '' ?>><?= e($g['title']) ?></option><?php endforeach; ?></select></div>
     <div class="col-sm-6"><label class="form-label" for="simIncome">Monthly income (€)</label><input id="simIncome" class="form-control" type="number" min="0" step="10" value="<?= e($income) ?>"></div>
     <div class="col-sm-6"><label class="form-label" for="simExpenses">Monthly expenses (€)</label><input id="simExpenses" class="form-control" type="number" min="0" step="10" value="<?= e($expenses) ?>"></div>
     <div class="col-sm-6"><label class="form-label" for="simSaved">Current savings (€)</label><input id="simSaved" class="form-control" type="number" min="0" step="10" value="<?= e($firstGoal['saved_amount'] ?? 200) ?>"></div>
     <div class="col-sm-6"><label class="form-label" for="simTarget">Purchase / goal (€)</label><input id="simTarget" class="form-control" type="number" min="0" step="10" value="<?= e($firstGoal['target_amount'] ?? 700) ?>"></div>
     <div class="col-12"><label for="simMonths" class="form-label d-flex justify-content-between"><span>Time available</span><strong><span id="monthsLabel">3</span> months</strong></label><input id="simMonths" class="form-range" type="range" min="1" max="24" value="3"></div>
     <div class="col-12"><hr class="my-1"><div class="small fw-bold text-uppercase text-muted">Adjusted plan — what if I…</div></div>
     <div class="col-12"><label for="simCut" class="form-label d-flex justify-content-between"><span>Reduce monthly expenses</span><strong>€<span id="cutLabel">0</span></strong></label><input id="simCut" class="form-range" type="range" min="0" max="300" step="5" value="0"></div>
     <div class="col-12"><label for="simExtra" class="form-label d-flex justify-content-between"><span>Earn extra per month</span><strong>€<span id="extraLabel">0</span></strong></label><input id="simExtra" class="form-range" type="range" min="0" max="300" step="5" value="0"></div>
     <div class="col-12"><label for="simMoreMonths" class="form-label d-flex justify-content-between"><span>Wait longer</span><strong>+<span id="moreMonthsLabel">0</span> months</strong></label><input id="simMoreMonths" class="form-range" type="range" min="0" max="12" value="0"></div>
    </form>
    <div class="row g-3 mt-1">
     <div class="col-6"><div class="result-tile"><div class="small text-muted fw-bold">ORIGINAL PLAN</div><div class="small text-muted mt-2">Monthly savings</div><div class="result-number" id="origMonthly">—</div><div class="small text-muted mt-2">Projected savings</div><div class="result-number" id="origProjected">—</div><div class="small mt-2" id="origGoal"></div></div></div>
     <div class="col-6"><div class="result-tile result-adjusted"><div class="small fw-bold" style="color:var(--purple)">ADJUSTED PLAN</div><div class="small text-muted mt-2">Monthly savings</div><div class="result-number" id="adjMonthly">—</div><div class="small text-muted mt-2">Projected savings</div><div class="result-number" id="adjProjected">—</div><div class="small mt-2" id="adjGoal"></div></div></div>
     <div class="col-12"><div id="goalStatus" class="goal-status" aria-live="polite">Calculating…</div></div>
    </div>
    <div class="chart-wrap mt-4"><canvas id="projectionChart" aria-label="Original versus adjusted projected savings"></canvas></div>
  </div></div>
  <div class="col-lg-5"><div class="chat-card">
    <div class="chat-header"><div class="fairy-avatar small-avatar"><img src="assets/img/fairy.svg" alt=""></div><div><strong>Fia</strong><div class="small text-white-50">Your friendly fairy guide</div></div><span class="online-dot ms-auto"></span></div>
    <div id="chatMessages" class="chat-messages"><div class="chat-bubble bot-bubble">Hi, I’m Fia! ✨ Move the sliders, then ask me to explain the difference between your two plans, or ask for a saving tip.</div>
     <div class="suggestion-list"><button type="button" class="suggestion-chip">Can I afford this goal?</button><button type="button" class="suggestion-chip">Is the adjusted plan worth it?</button><button type="button" class="suggestion-chip">Give me a saving tip</button><button type="button" class="suggestion-chip">How can I save faster?</button></div></div>
    <form id="chatForm" class="chat-input-area"><input id="chatInput" class="form-control" maxlength="700" placeholder="Ask your fairy something..." required><button class="btn btn-primary" aria-label="Send message">➤</button></form>
    <div class="px-3 pb-3 small text-muted">Numbers are calculated by BudgetFairy; the assistant only explains them. Estimates are educational.</div>
  </div></div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
