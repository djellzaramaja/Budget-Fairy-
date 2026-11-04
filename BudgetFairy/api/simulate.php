<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/_scenario.php';

api_guard('simulate');
jsonResponse(['success' => true] + scenarioFromRequest(requestData()));
