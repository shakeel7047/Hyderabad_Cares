<?php
/**
 * FixMyHyderabad — shared configuration and helpers.
 * XAMPP defaults: user "root", empty password, host 127.0.0.1.
 * Change these values on Hostinger.
 */

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'fixmyhyderabad';
const DB_USER = 'root';
const DB_PASS = '';

const UPLOAD_DIR = __DIR__ . '/uploads';
const UPLOAD_URL = 'uploads';
const MAX_PHOTO_BYTES = 5 * 1024 * 1024; // 5 MB
const MAX_REPORTS_PER_10_MIN = 5;

const ALLOWED_CATEGORIES = [
    'roads',
    'lights',
    'water',
    'sewage',
    'garbage',
    'parks',
    'safety',
    'other',
];

const ALLOWED_AREAS = [
    'Latifabad',
    'Qasimabad',
    'Saddar',
    'Hirabad',
    'Shahi Bazaar',
    'Paretabad',
    'Tando Yousuf',
    'Bhitai Nagar',
    'Citizen Colony',
    'Gulistan-e-Sajjad',
    'Auto Bhan Road',
    'Wadhu Wah',
    'SITE Area',
    'Jamshoro Road',
];

const ALLOWED_STATUSES = ['Reported', 'Verified', 'Progress', 'Resolved'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function json_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400): void
{
    json_out(['ok' => false, 'error' => $message], $status);
}

function clean_text(?string $value, int $maxLength): string
{
    $value = trim((string) $value);
    // Strip control characters, keep normal punctuation and newlines.
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $maxLength);
}

function client_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $ip . '|hyderabad-cares');
}
