<?php
require_once __DIR__ . '/bootstrap.php';
$pageTitle = $pageTitle ?? 'BudgetFairy';
$currentPage = basename($_SERVER['PHP_SELF']);
$user = current_user();
$navLink = fn(string $file, string $label) =>
    '<li class="nav-item"><a class="nav-link ' . ($currentPage === $file ? 'active' : '') . '" href="' . $file . '">' . $label . '</a></li>';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="BudgetFairy helps you explore your financial future before making a money decision.">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title><?= e($pageTitle) ?> | BudgetFairy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg bf-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $user ? 'index.php' : 'login.php' ?>">
      <span class="brand-mark">✦</span><span>Budget<span class="brand-accent">Fairy</span></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <?php if ($user): ?>
          <?= $navLink('index.php', 'Dashboard') ?>
          <?= $navLink('transactions.php', 'Transactions') ?>
          <?= $navLink('savings.php', 'Savings') ?>
          <?= $navLink('assistant.php', 'Fairy Assistant') ?>
          <?= $navLink('about.php', 'About') ?>
          <li class="nav-item ms-lg-2 d-flex align-items-center gap-2">
            <a class="small text-muted text-decoration-none" href="account.php" title="Your account"><?= e($user['name']) ?></a>
            <form method="post" action="logout.php" class="m-0"><?= csrf_field() ?><button class="btn btn-primary-soft btn-sm px-3" type="submit">Log out</button></form>
          </li>
        <?php else: ?>
          <?= $navLink('about.php', 'About') ?>
          <?= $navLink('login.php', 'Log in') ?>
          <li class="nav-item ms-lg-2"><a class="btn btn-primary-soft btn-sm px-3" href="register.php">Sign up <span aria-hidden="true">→</span></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main>
