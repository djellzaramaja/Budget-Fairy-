<?php
$pageTitle = 'Your financial dashboard';
require_once __DIR__ . '/../includes/bootstrap.php';
$uid = require_login();
$pdo = db();
 
processRecurring($uid);
[$from, $to] = currentMonthRange();
$st = $pdo->prepare("SELECT
    COALESCE(SUM(CASE WHEN type='income'  THEN amount END),0) AS income,
    COALESCE(SUM(CASE WHEN type='expense' THEN amount END),0) AS expenses
  FROM transactions WHERE user_id = ? AND transaction_date >= ? AND transaction_date < ?");
$st->execute([$uid, $from, $to]);
$row = $st->fetch();
$summary = ['income' => (float)$row['income'], 'expenses' => (float)$row['expenses']];
$summary['savings'] = $summary['income'] - $summary['expenses'];
 
$st = $pdo->prepare('SELECT * FROM savings_goals WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 3');
$st->execute([$uid]); $goals = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY transaction_date DESC, id DESC LIMIT 5');
$st->execute([$uid]); $recent = $st->fetchAll();
 
$cats = categoryTotals($uid, $from, $to);
$history = monthlyHistory($uid, 6);
$st = $pdo->prepare('SELECT (SELECT COUNT(*) FROM transactions WHERE user_id=?) + (SELECT COUNT(*) FROM savings_goals WHERE user_id=?)');
$st->execute([$uid, $uid]);
$isNew = ((int)$st->fetchColumn() === 0);
$goalColors = ['#7254a8', '#e8789a', '#3fb8a5', '#f2b84b', '#6aa9e9'];
 
require __DIR__ . '/../includes/header.php';
?>
<section class="hero-section">
 <div class="container"><div class="row align-items-center g-4">
   <div class="col-lg-7">
    <span class="eyebrow">YOUR MONEY, YOUR FUTURE</span>
    <h1>Make room for the things <span class="text-gradient">that matter.</span></h1>
    <p class="hero-copy">Hi <?= e($user['name']) ?>! Explore what could happen before you make your next money move.</p>
    <a class="btn btn-primary btn-lg" href="assistant.php">Explore a what-if scenario <span aria-hidden="true">→</span></a>
   </div>
   <div class="col-lg-5"><div class="fairy-hero-card">
      <div class="sparkle sparkle-one">✦</div><div class="fairy-orb"><img src="assets/img/fairy.svg" alt="Fia the fairy"></div>
      <div class="text-center"><h5 class="mb-1">Meet Fia, your money guide</h5><p class="text-muted small mb-0">Small changes can create a brighter future.</p></div>
   </div></div>
 </div></div>
</section>
<?php if ($isNew): ?>
<section class="container pt-4"><div class="welcome-card">
  <div class="welcome-fairy"><img src="assets/img/fairy.svg" alt=""></div>
  <div><h3 class="mb-1">Welcome, <?= e($user['name']) ?>! ✨ Let’s get you started</h3>
  <p class="text-muted mb-3">Three little steps and your dashboard comes alive:</p>
  <ol class="welcome-steps mb-3"><li>Add your income (for example, your salary)</li><li>Add a few expenses</li><li>Create a savings goal and try the what-if simulator</li></ol>
  <button class="btn btn-primary btn-sm me-2" data-tx-add>+ Add my first transaction</button><a class="btn btn-primary-soft btn-sm" href="savings.php">Create a goal</a></div>
</div></section>
<?php endif; ?>
<section class="container section-space">
 <div class="section-heading d-flex flex-wrap justify-content-between align-items-end gap-2">
  <div><span class="eyebrow">YOUR OVERVIEW</span><h2>Your current account</h2><p class="text-muted mb-0">A snapshot of this month's activity.</p></div>
  <a href="savings.php" class="text-link">Manage savings goals →</a>
 </div>
 <div class="row g-3 mt-2">
  <div class="col-md-4"><div class="metric-card metric-income"><div class="metric-label">Income this month</div><div class="metric-value"><?= money($summary['income']) ?></div><div class="metric-foot">Money coming in</div><span class="metric-icon">↗</span></div></div>
  <div class="col-md-4"><div class="metric-card metric-expense"><div class="metric-label">Expenses this month</div><div class="metric-value"><?= money($summary['expenses']) ?></div><div class="metric-foot">Money going out</div><span class="metric-icon">↘</span></div></div>
  <div class="col-md-4"><div class="metric-card metric-highlight"><div class="metric-label">Monthly difference</div><div class="metric-value"><?= money($summary['savings']) ?></div><div class="metric-foot">Income minus expenses</div><span class="metric-icon">✧</span></div></div>
 </div>
</section>
<section class="container pb-4"><div class="row g-4">
  <div class="col-lg-5"><div class="panel-card h-100 afford-card">
    <h3>Can I afford this? <span aria-hidden="true">🛍️</span></h3>
    <p class="text-muted small">Type a price and Fia checks it against what you’ve saved and what you usually save each month.</p>
    <form id="affordForm" class="d-flex gap-2"><input class="form-control" id="affordPrice" type="number" min="0.01" step="0.01" placeholder="Price in €" required aria-label="Price"><button class="btn btn-primary" type="submit">Check</button></form>
    <div id="affordResult" class="afford-result d-none" aria-live="polite"></div>
  </div></div>
  <div class="col-lg-7"><div class="panel-card h-100">
    <h3>Where your money went</h3><p class="text-muted small">Expenses by category this month.</p>
    <?php if (!$cats): ?><div class="empty-state py-4"><div class="empty-icon">◔</div><p class="small text-muted mb-0">Add some expenses to see your spending breakdown.</p></div>
    <?php else: ?><div class="chart-wrap chart-wrap-sm"><canvas id="categoryChart" data-chart="<?= e(json_encode(['labels' => array_column($cats, 'category'), 'values' => array_column($cats, 'total')])) ?>" aria-label="Spending by category"></canvas></div><?php endif; ?>
  </div></div>
</div></section>
<section class="container pb-4"><div class="panel-card">
  <h3>Last 6 months</h3><p class="text-muted small">Income versus expenses, month by month.</p>
  <div class="chart-wrap"><canvas id="historyChart" data-chart="<?= e(json_encode($history)) ?>" aria-label="Income and expenses over six months"></canvas></div>
</div></section>
<section class="container pb-5"><div class="row g-4">
  <div class="col-lg-7"><div class="panel-card h-100">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h3>Recent activity</h3><p class="text-muted small mb-0">Your latest transactions</p></div>
      <div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="transactions.php">View all</a><button class="btn btn-outline-secondary btn-sm" data-tx-add>+ Add transaction</button></div></div>
    <?php if (!$recent): ?><div class="empty-state"><div class="empty-icon">↗</div><p class="mb-1 fw-semibold">Your activity will appear here</p><p class="small text-muted">Add your first income or expense to get started.</p></div>
    <?php else: ?><div class="table-responsive"><table class="table align-middle bf-table"><thead><tr><th>Description</th><th>Date</th><th class="text-end">Amount</th></tr></thead><tbody>
    <?php foreach ($recent as $item): ?><tr><td><strong><?= e($item['description']) ?></strong><div class="small text-muted"><?= e($item['category']) ?></div></td><td><?= e($item['transaction_date']) ?></td><td class="text-end <?= $item['type']==='income'?'amount-plus':'amount-minus' ?>"><?= $item['type']==='income'?'+':'−' ?><?= money((float)$item['amount']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  </div></div>
  <div class="col-lg-5"><div class="panel-card h-100">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h3>Savings goals</h3><p class="text-muted small mb-0">Keep your eye on what matters.</p></div><a class="text-link small" href="savings.php">View all →</a></div>
    <?php if (!$goals): ?><div class="empty-state"><div class="empty-icon">✧</div><p class="fw-semibold mb-1">Give your savings a purpose</p><p class="small text-muted">Create a goal for a trip, laptop, or emergency fund.</p><a href="savings.php" class="btn btn-primary-soft btn-sm">Create a goal</a></div>
    <?php else: foreach ($goals as $gi => $goal): $pct = min(100, (int)round(((float)$goal['saved_amount']/(float)$goal['target_amount'])*100)); ?>
    <div class="goal-mini mb-4"><div class="d-flex justify-content-between"><strong><?= e($goal['title']) ?></strong><span class="small text-muted"><?= $pct ?>%</span></div><div class="progress bf-progress my-2"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $goalColors[$gi % 5] ?>"></div></div><div class="small text-muted"><?= money((float)$goal['saved_amount']) ?> of <?= money((float)$goal['target_amount']) ?></div></div>
    <?php endforeach; endif; ?>
  </div></div>
</div></section>
<?php require __DIR__ . '/../includes/transaction_modal.php'; require __DIR__ . '/../includes/footer.php'; ?>
 