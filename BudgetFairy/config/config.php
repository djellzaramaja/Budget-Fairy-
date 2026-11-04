<?php

$cfg = [
    'env'     => getenv('BF_ENV') ?: 'development',         
    'db' => [
        'host' => getenv('BF_DB_HOST') ?: '127.0.0.1',
        'port' => getenv('BF_DB_PORT') ?: '3306',
        'name' => getenv('BF_DB_NAME') ?: 'budgetfairy',
        'user' => getenv('BF_DB_USER') ?: 'root',
        'pass' => getenv('BF_DB_PASS') ?: '',
    ],
    'session_lifetime' => 60 * 60 * 2,                        
    'rate_limits' => [                                        
        'login'     => [5, 900],
        'register'  => [5, 3600],
        'api_write' => [60, 60],
        'assistant' => [10, 60],
        'simulate'  => [120, 60],
    ],
];
$local = __DIR__ . '/local.php';
if (is_file($local)) $cfg = array_replace_recursive($cfg, require $local);
return $cfg;
