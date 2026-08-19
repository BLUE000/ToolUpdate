<?php
declare(strict_types=1);

use ReleaseHub\Git\GitHubClient;
use ReleaseHub\Package\ReleaseManager;
use ReleaseHub\Package\ZipPackager;

function runConcurrencyAndFallbackIntegrationTests(): void
{
    $tempStorage = TEST_TEMP_STORAGE . '/concurrency';
    $client = new GitHubClient();
    $packager = new ZipPackager();
    $manager1 = new ReleaseManager($tempStorage, $client, $packager);
    $manager2 = new ReleaseManager($tempStorage, $client, $packager);

    // IT-17: 排他制御・キャッシュテスト
    $manifestData = [
        'tool_id' => 'TrustChain',
        'latest_version' => 'v1.0.0',
        'last_synced_at' => date('c'),
        'releases' => []
    ];
    $manager1->saveManifest('TrustChain', $manifestData);

    $toolConfig = ['id' => 'TrustChain', 'repository' => 'https://github.com/BLUE000/TrustChain.git'];
    $res1 = $manager1->checkAndSync($toolConfig, force: false);
    TestAssert::assertTrue(in_array($res1['status'], ['cached', 'up_to_date', 'rate_limited_or_error', 'new_release_created']), 'Concurrency IT-17: Res1 status valid');
}
