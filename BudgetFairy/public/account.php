<?php
$pageTitle = 'Your account';
require_once __DIR__ . '/../includes/bootstrap.php';
$uid = require_login();
$user = current_user();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_form();
    $password = (string)($_POST['password'] ?? '');
    if (!rate_limit_hit('login', client_ip() . '|delete|' . $uid)) {
        $error = 'Too many attempts. Please wait and try again.';
    } else {
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?'); $st->execute([$uid]);
        $hash = (string)$st->fetchColumn();
        if (strlen($password) <= 1024 && $hash !== '' && password_verify($password, $hash) && ($_POST['confirm'] ?? '') === 'DELETE') {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);   // cascades to all their data
            logout_user();
            redirect('login.php');
        }
        $error = 'Wrong password, or you did not type DELETE.';
    }
}
$st = db()->prepare('SELECT (SELECT COUNT(*) FROM transactions WHERE user_id=?) t, (SELECT COUNT(*) FROM savings_goals WHERE user_id=?) g');
$st->execute([$uid, $uid]); $counts = $st->fetch();
require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">YOUR PRIVACY</span><h1>Your <span class="text-gradient">account.</span></h1></div></section>
<section class="container section-space"><div class="row g-4">
  <div class="col-lg-5"><div class="panel-card"><h3>Profile</h3>
    <p class="mb-1"><strong><?= e($user['name']) ?></strong></p><p class="text-muted"><?= e($user['email']) ?></p>
    <p class="small text-muted mb-3">You have <?= (int)$counts['t'] ?> transactions and <?= (int)$counts['g'] ?> goals stored.</p>
    <a class="btn btn-outline-secondary btn-sm" href="export.php">⬇ Download my transactions (CSV)</a></div></div>
  <div class="col-lg-7"><div class="panel-card danger-zone"><h3>Delete my account</h3>
    <p class="small text-muted">This permanently deletes your account, transactions, goals and recurring items. It cannot be undone.</p>
    <?php if ($error): ?><div class="alert alert-danger py-2 small" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="row g-2"><?= csrf_field() ?>
      <div class="col-md-6"><input class="form-control" type="password" name="password" placeholder="Your password" required autocomplete="current-password"></div>
      <div class="col-md-6"><input class="form-control" name="confirm" placeholder='Type DELETE to confirm' required autocomplete="off"></div>
      <div class="col-12"><button class="btn btn-danger" type="submit">Delete everything</button></div>
    </form></div></div>
</div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
