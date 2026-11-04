<?php
// CLI only:  php database/seed.php
// Creates a demo user with sample data. LOCAL/DEMO USE ONLY.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only.');
require __DIR__ . '/../includes/bootstrap.php';
$pdo = db();
$email = 'demo@budgetfairy.test';
$pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
$pdo->prepare('INSERT INTO users (name,email,password_hash) VALUES (?,?,?)')
    ->execute(['Demo Student', $email, password_hash('DemoPass123!', PASSWORD_DEFAULT)]);
$uid = (int)$pdo->lastInsertId();
$tx = $pdo->prepare('INSERT INTO transactions (user_id,type,amount,description,category,transaction_date) VALUES (?,?,?,?,?,CURDATE())');
foreach ([['income',650,'Part-time job','Salary'],['expense',180,'Rent contribution','Housing'],['expense',120,'Groceries','Food'],['expense',65,'Transport','Transport'],['expense',45,'Coffee and snacks','Food']] as $r)
    $tx->execute([$uid,...$r]);
$g = $pdo->prepare('INSERT INTO savings_goals (user_id,title,target_amount,saved_amount,target_date) VALUES (?,?,?,?,DATE_ADD(CURDATE(), INTERVAL ? MONTH))');
$g->execute([$uid,'New laptop',700,200,3]);
$g->execute([$uid,'Weekend trip',350,120,5]);
echo "Demo user ready: $email / DemoPass123!\n";
