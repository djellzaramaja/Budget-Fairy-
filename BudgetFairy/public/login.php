<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (current_user()) redirect('index.php');

$error = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_form();
    $email = mb_strtolower(cleanText($_POST['email'] ?? '', 190));
    $password = (string)($_POST['password'] ?? '');

    if (!rate_limit_hit('login', client_ip() . '|' . $email)) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        $st = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
        $st->execute([$email]);
        $row = $st->fetch();
        
        $hash = $row['password_hash'] ?? '$2y$10$Pe6/Ni8ecn8ec.n.kSZAROAPvm4jHXyL.qOvBehb3i/bq3FOsaUK.';
        if (strlen($password) <= 1024 && password_verify($password, $hash) && $row) {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $row['id']]);
            }
            login_user((int)$row['id']);
            redirect('index.php');
        }
        $error = 'Incorrect email or password.';
    }
}
$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">WELCOME BACK</span><h1>Log in to your <span class="text-gradient">BudgetFairy.</span></h1></div></section>
<section class="container section-space"><div class="row"><div class="col-md-8 col-lg-5">
  <div class="panel-card">
    <?php if ($error): ?><div class="alert alert-danger py-2 small" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="row g-3" autocomplete="on">
      <?= csrf_field() ?>
      <div class="col-12"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="190" required autofocus value="<?= e($email) ?>"></div>
      <div class="col-12"><label class="form-label" for="password">Password</label><input class="form-control" id="password" name="password" type="password" required autocomplete="current-password"></div>
      <div class="col-12"><button class="btn btn-primary w-100" type="submit">Log in</button></div>
      <div class="col-12 small text-muted">New here? <a class="text-link" href="register.php">Create an account</a></div>
    </form>
  </div>
</div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
