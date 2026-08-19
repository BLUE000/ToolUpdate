<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use ReleaseHub\App;

$app = new App(__DIR__ . '/..');
echo $app->handleFeed($_GET);
