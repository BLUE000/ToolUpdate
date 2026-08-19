<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use ReleaseHub\App;

$app = new App(__DIR__ . '/..');

$action = $_GET['action'] ?? '';
if ($action === 'download') {
    $app->handleDownload($_GET);
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($app->handleApi($_GET), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
