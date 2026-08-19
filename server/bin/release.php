<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use ReleaseHub\Config\ConfigLoader;
use ReleaseHub\Git\GitHubClient;
use ReleaseHub\Package\ReleaseManager;
use ReleaseHub\Package\ZipPackager;

$baseDir = dirname(__DIR__);
$config = new ConfigLoader($baseDir . '/config');
$gitClient = new GitHubClient();
$packager = new ZipPackager();
$manager = new ReleaseManager($baseDir . '/storage', $gitClient, $packager);

$tools = $config->getBranches();
echo "========================================\n";
echo " ReleaseHub CLI Sync Engine\n";
echo "========================================\n";

if (empty($tools)) {
    echo "No tools configured in config/branches.json\n";
    exit(0);
}

foreach ($tools as $tool) {
    $id = $tool['id'] ?? '';
    echo "Syncing tool: {$id} ... ";
    $result = $manager->checkAndSync($tool, force: true);
    echo "Result: {$result['status']}\n";
}

echo "All tools synced successfully.\n";
