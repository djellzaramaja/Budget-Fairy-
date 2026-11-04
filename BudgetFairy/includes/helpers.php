<?php
declare(strict_types=1);

function config(string $key, mixed $default = null): mixed {
    $v = $GLOBALS['bf_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($v) || !array_key_exists($part, $v)) return $default;
        $v = $v[$part];
    }
    return $v;
}

function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requestData(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

function cleanText(mixed $value, int $max = 120): string {
    $s = is_scalar($value) ? (string)$value : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';  // strip control chars
    return mb_substr(trim($s), 0, $max);
}

function money(float $amount): string {
    return ($amount < 0 ? '−' : '') . '€' . number_format(abs($amount), 2);
}

function validDate(mixed $d): bool {
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}


function parseAmount(mixed $v, bool $allowZero = false): ?float {
    if (is_string($v)) $v = trim($v);
    if ($v === '' || $v === null || is_bool($v) || !is_numeric($v)) return null;
    $f = (float)$v;
    if (!is_finite($f) || $f > 9999999999.99) return null;
    if ($f < 0 || (!$allowZero && $f <= 0)) return null;
    return round($f, 2);
}

function redirect(string $to): never { header('Location: ' . $to); exit; }

function recurringDueDates(string $start, int $dom, ?string $last, string $today, int $cap = 36): array {
    $dom = max(1, min(28, $dom));
    $at  = fn(DateTimeImmutable $d): DateTimeImmutable => $d->setDate((int)$d->format('Y'), (int)$d->format('n'), $dom);
    $next = fn(DateTimeImmutable $d): DateTimeImmutable => $at($d->modify('first day of next month'));
    $todayD = new DateTimeImmutable($today);
    if ($last === null) {
        $s = new DateTimeImmutable($start);
        $d = $at($s);
        if ($d < $s) $d = $next($d);
    } else {
        $d = $next(new DateTimeImmutable($last));
    }
    $out = [];
    while ($d <= $todayD && count($out) < $cap) { $out[] = $d->format('Y-m-d'); $d = $next($d); }
    return $out;
}

function monthRange(?string $ym): ?array {
    if (!is_string($ym) || !preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $ym)) return null;
    $start = new DateTimeImmutable($ym . '-01');
    return [$start->format('Y-m-d'), $start->modify('first day of next month')->format('Y-m-d')];
}
