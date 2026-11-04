<?php
$pageTitle = 'Savings goals';
require_once __DIR__ . '/../includes/bootstrap.php';
$uid = require_login();
$st = db()->prepare('SELECT * FROM savings_goals WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$st->execute([$uid]); $goals = $st->fetchAll();
$goalColors = ['#7254a8', '#e8789a', '#3fb8a5', '#f2b84b', '#6aa9e9'];
require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">LITTLE STEPS, BIG DREAMS</span><h1>Your savings, <span class="text-gradient">your goals.</span></h1><p>Give every euro a purpose. Create a goal and see how small monthly contributions add up.</p></div></section>
<section class="container section-space"><div class="row g-4">
  <div class="col-lg-5"><div class="panel-card" id="goalCard">
   <h3 data-form-title data-add-title="Create a savings goal" data-edit-title="Edit savings goal">Create a savings goal</h3><p class="text-muted small">What are you saving for?</p>
   <form id="goalForm" class="row g-3" data-api="../api/goals.php" data-reload>
    <input type="hidden" name="id" value="">
    <div class="col-12"><label class="form-label">Goal name</label><input class="form-control" name="title" maxlength="100" required placeholder="e.g. New laptop"></div>
    <div class="col-6"><label class="form-label">Target (€)</label><input class="form-control" name="target_amount" type="number" min="0.01" step="0.01" required></div>
    <div class="col-6"><label class="form-label">Already saved (€)</label><input class="form-control" name="saved_amount" type="number" min="0" step="0.01" value="0" required></div>
    <div class="col-12"><label class="form-label">Target date</label><input class="form-control" name="target_date" type="date"></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit" data-submit-label data-add-label="Create my goal" data-edit-label="Update goal">Create my goal</button><button class="btn btn-outline-secondary d-none" type="button" data-cancel>Cancel</button></div>
    <div class="col-12"><div class="form-message small" aria-live="polite"></div></div>
   </form>
  </div></div>
  <div class="col-lg-7"><div class="panel-card h-100"><h3>Your goals</h3><p class="text-muted small">Progress is based on the amounts you enter.</p>
  <?php if (!$goals): ?><div class="empty-state"><div class="empty-icon">✧</div><p class="fw-semibold">Your first goal starts here</p><p class="small text-muted">Create a savings goal using the form.</p></div>
  <?php else: ?><div class="row g-3"><?php foreach ($goals as $gi => $goal):
     $pct = min(100, (int)round(((float)$goal['saved_amount']/(float)$goal['target_amount'])*100));
     $remaining = max(0, (float)$goal['target_amount']-(float)$goal['saved_amount']);
     $payload = ['id'=>(int)$goal['id'],'title'=>$goal['title'],'target_amount'=>(float)$goal['target_amount'],'saved_amount'=>(float)$goal['saved_amount'],'target_date'=>$goal['target_date'] ?? '']; ?>
   <div class="col-12"><div class="goal-card"><div class="d-flex justify-content-between align-items-start"><div><span class="goal-symbol">✦</span><h5 class="mt-2 mb-1"><?= e($goal['title']) ?></h5><div class="small text-muted"><?= $goal['target_date'] ? 'Target: '.e($goal['target_date']) : 'No deadline set' ?></div></div><span class="goal-percent"><?= $pct ?>%</span></div>
     <div class="progress bf-progress my-3"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $goalColors[$gi % 5] ?>"></div></div>
     <div class="d-flex justify-content-between small mb-3"><span><?= money((float)$goal['saved_amount']) ?> saved</span><strong><?= money($remaining) ?> to go</strong></div>
     <div class="d-flex flex-wrap gap-2"><?php if ($remaining > 0): ?><button class="btn btn-primary-soft btn-sm" data-contribute='<?= e(json_encode(['id'=>(int)$goal['id'],'title'=>$goal['title']])) ?>'>+ Add money</button><?php else: ?><span class="goal-done">🎉 Goal reached!</span><?php endif; ?><button class="btn btn-outline-secondary btn-sm" data-edit='<?= e(json_encode($payload)) ?>' data-target="#goalCard">Edit</button>
       <button class="btn btn-outline-danger btn-sm" data-delete-url="../api/goals.php" data-id="<?= (int)$goal['id'] ?>" data-confirm="Delete the goal “<?= e($goal['title']) ?>”?">Delete</button></div>
   </div></div>
  <?php endforeach; ?></div><?php endif; ?>
  </div></div>
</div></section>
<div class="modal fade" id="contributeModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content modal-soft">
  <div class="modal-header"><h5 class="modal-title">Add money ✨</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><p class="small text-muted mb-2" id="contributeTitle"></p>
    <form id="contributeForm" class="row g-2" data-api="../api/goals.php" data-action="contribute" data-reload>
      <input type="hidden" name="id" value="">
      <div class="col-12"><input class="form-control" name="amount" type="number" min="0.01" step="0.01" placeholder="Amount in €" required aria-label="Amount"></div>
      <div class="col-12 d-flex gap-2"><button type="button" class="btn btn-outline-secondary btn-sm" data-quick="10">+€10</button><button type="button" class="btn btn-outline-secondary btn-sm" data-quick="20">+€20</button><button type="button" class="btn btn-outline-secondary btn-sm" data-quick="50">+€50</button></div>
      <div class="col-12"><button class="btn btn-primary w-100" type="submit">Add to goal</button><div class="form-message small mt-2" aria-live="polite"></div></div>
    </form></div></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
