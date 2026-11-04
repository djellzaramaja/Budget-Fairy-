<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (current_user()) redirect('index.php');

$errors = []; $name = ''; $email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_form();
    $name = cleanText($_POST['name'] ?? '', 80);
    $email = mb_strtolower(cleanText($_POST['email'] ?? '', 190));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    if (!rate_limit_hit('register', client_ip())) {
        $errors[] = 'Too many sign-ups from this network. Please try again later.';
    } else {
        if ($name === '') $errors[] = 'Please enter your name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (strlen($password) < 10) $errors[] = 'Password must be at least 10 characters.';
        if (strlen($password) > 72) $errors[] = 'Password must be 72 characters or fewer.';   // bcrypt limit
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    }
    if (!$errors) {
        try {
            db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?,?,?)')
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            login_user((int)db()->lastInsertId());
            redirect('index.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') $errors[] = 'An account with this email already exists. Try logging in.';
            else { error_log($e->getMessage()); $errors[] = 'Could not create your account. Please try again.'; }
        }
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/../includes/header.php';
?>
<section class="page-hero"><div class="container"><span class="eyebrow">LITTLE STEPS, BIG DREAMS</span><h1>Create your <span class="text-gradient">account.</span></h1><p>Your budget stays private to you.</p></div></section>
<section class="container section-space"><div class="row"><div class="col-md-8 col-lg-5">
  <div class="panel-card">
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2 small" role="alert"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="row g-3">
      <?= csrf_field() ?>
      <div class="col-12"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" maxlength="80" required value="<?= e($name) ?>" autocomplete="name"></div>
      <div class="col-12"><label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" maxlength="190" required value="<?= e($email) ?>" autocomplete="email"></div>
      <div class="col-12"><label class="form-label" for="password">Password <span class="text-muted fw-normal">(10+ characters)</span></label><input class="form-control" id="password" name="password" type="password" minlength="10" maxlength="72" required autocomplete="new-password"></div>
      <div class="col-12"><label class="form-label" for="confirm">Confirm password</label><input class="form-control" id="confirm" name="confirm" type="password" minlength="10" maxlength="72" required autocomplete="new-password"></div>
      <div class="col-12"><button class="btn btn-primary w-100" type="submit">Create account</button></div>
      <div class="col-12 small text-muted">Already registered? <a class="text-link" href="login.php">Log in</a></div>
    </form>
  </div>
</div></div></section>
<?php require __DIR__ . '/../includes/footer.php'; ?>