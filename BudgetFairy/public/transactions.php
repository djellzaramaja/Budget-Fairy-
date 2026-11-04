<?php
$pageTitle = 'Transactions';
require_once __DIR__ . '/../includes/bootstrap.php';
$uid = require_login();
$pdo = db();
processRecurring($uid);


$month = monthRange($_GET['month'] ?? null) ? $_GET['month'] : '';
$where = 'user_id = ?'; $params = [$uid];
if ($month) { [$f, $t] = monthRange($month); $where .= ' AND transaction_date >= ? AND transaction_date < ?'; array_push($params, $f, $t); }

$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$st = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE $where"); $st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$st = $pdo->prepare("SELECT * FROM transactions WHERE $where ORDER BY transaction_date DESC, id DESC LIMIT ? OFFSET ?");
foreach ($params as $i => $v) $st->bindValue($i + 1, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
$st->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$st->bindValue(count($params) + 2, ($page - 1) * $perPage, PDO::PARAM_INT);
$st->execute(); $items = $st->fetchAll();

$st = $pdo->prepare("SELECT DISTINCT DATE_FORMAT(transaction_date,'%Y-%m') ym FROM transactions WHERE user_id = ? ORDER BY ym DESC");
$st->execute([$uid]); $months = $st->fetchAll(PDO::FETCH_COLUMN);
$st = $pdo->prepare('SELECT * FROM recurring_transactions WHERE user_id = ? ORDER BY day_of_month, id'); $st->execute([$uid]); $recurring = $st->fetchAll();
$qs = fn(array $extra) => '?' . http_build_query(array_filter(['month' => $month] + $extra));

require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">EVERY EURO, ACCOUNTED FOR</span><h1>Your <span class="text-gradient">transactions.</span></h1><p>Add, edit or remove income and expenses. Only you can see them.</p></div></section>
<section class="container section-space"><div class="panel-card">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0"><?= $total ?> transaction<?= $total === 1 ? '' : 's' ?></h3>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <form method="get" class="m-0"><select class="form-select form-select-sm" name="month" data-autosubmit aria-label="Filter by month">
        <option value="">All months</option>
        <?php foreach ($months as $ym): ?><option value="<?= e($ym) ?>" <?= $ym === $month ? 'selected' : '' ?>><?= e((new DateTimeImmutable($ym . '-01'))->format('F Y')) ?></option><?php endforeach; ?>
      </select></form>
      <a class="btn btn-outline-secondary btn-sm" href="export.php<?= $month ? '?month=' . e($month) : '' ?>">⬇ Export CSV</a>
      <button class="btn btn-primary btn-sm" data-tx-add>+ Add transaction</button>
    </div>
  </div>
  <?php if (!$items): ?><div class="empty-state"><div class="empty-icon">↗</div><p class="fw-semibold">No transactions <?= $month ? 'in this month' : 'yet' ?></p><p class="small text-muted">Click “Add transaction” to record one.</p></div>
  <?php else: ?><div class="table-responsive"><table class="table align-middle bf-table"><thead><tr><th>Description</th><th>Date</th><th class="text-end">Amount</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php foreach ($items as $t): $payload = ['id'=>(int)$t['id'],'type'=>$t['type'],'amount'=>(float)$t['amount'],'description'=>$t['description'],'category'=>$t['category'],'transaction_date'=>$t['transaction_date']]; ?>
    <tr><td><strong><?= e($t['description']) ?></strong><div class="small"><span class="cat-pill"><?= e($t['category']) ?></span></div></td><td><?= e($t['transaction_date']) ?></td>
      <td class="text-end <?= $t['type']==='income'?'amount-plus':'amount-minus' ?>"><?= $t['type']==='income'?'+':'−' ?><?= money((float)$t['amount']) ?></td>
      <td class="text-end text-nowrap"><button class="btn btn-outline-secondary btn-sm" data-edit='<?= e(json_encode($payload)) ?>' data-target="#transactionModal">Edit</button>
        <button class="btn btn-outline-danger btn-sm" data-delete-url="../api/transactions.php" data-id="<?= (int)$t['id'] ?>" data-confirm="Delete “<?= e($t['description']) ?>”?">Delete</button></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <?php if ($pages > 1): ?><nav class="d-flex justify-content-between align-items-center mt-2"><span class="small text-muted">Page <?= $page ?> of <?= $pages ?></span><div class="d-flex gap-2">
    <?php if ($page > 1): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $qs(['page' => $page-1]) ?>">← Newer</a><?php endif; ?>
    <?php if ($page < $pages): ?><a class="btn btn-outline-secondary btn-sm" href="<?= $qs(['page' => $page+1]) ?>">Older →</a><?php endif; ?></div></nav><?php endif; ?>
  <?php endif; ?>
</div></section>

<section class="container pb-5"><div class="row g-4">
  <div class="col-lg-7"><div class="panel-card h-100">
    <h3>Recurring (every month) 🔁</h3><p class="text-muted small">Rent, salary, subscriptions — add them once and BudgetFairy records them automatically on the day.</p>
    <?php if (!$recurring): ?><p class="small text-muted mb-0">Nothing recurring yet. Use the form to add one.</p><?php else: ?>
    <ul class="list-unstyled mb-0"><?php foreach ($recurring as $r): ?>
      <li class="d-flex justify-content-between align-items-center py-2 border-bottom"><div><strong><?= e($r['description']) ?></strong><div class="small text-muted"><?= e($r['category']) ?> · day <?= (int)$r['day_of_month'] ?> of each month</div></div>
      <div class="text-nowrap"><span class="<?= $r['type']==='income'?'amount-plus':'amount-minus' ?> me-2"><?= $r['type']==='income'?'+':'−' ?><?= money((float)$r['amount']) ?></span>
      <button class="btn btn-outline-danger btn-sm" data-delete-url="../api/recurring.php" data-id="<?= (int)$r['id'] ?>" data-confirm="Stop the recurring item “<?= e($r['description']) ?>”? Past transactions are kept.">Remove</button></div></li>
    <?php endforeach; ?></ul><?php endif; ?>
  </div></div>
  <div class="col-lg-5"><div class="panel-card h-100"><h3>Add recurring item</h3>
    <form class="row g-2 mt-1" data-api="../api/recurring.php" data-action="create" data-reload>
      <div class="col-6"><select class="form-select" name="type" aria-label="Type"><option value="expense">Expense</option><option value="income">Income</option></select></div>
      <div class="col-6"><input class="form-control" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount €" required></div>
      <div class="col-12"><input class="form-control" name="description" maxlength="120" placeholder="e.g. Rent" required></div>
      <div class="col-6"><input class="form-control" name="category" maxlength="60" placeholder="Category" required></div>
      <div class="col-6"><input class="form-control" name="day_of_month" type="number" min="1" max="28" placeholder="Day (1-28)" required></div>
      <div class="col-12"><label class="form-label small mb-1">Starting from</label><input class="form-control" name="start_date" type="date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="col-12"><button class="btn btn-primary w-100" type="submit">Add recurring item</button><div class="form-message small mt-2" aria-live="polite"></div></div>
    </form></div></div>
</div></section>
<?php require __DIR__ . '/../includes/transaction_modal.php'; require __DIR__ . '/../includes/footer.php'; ?>
