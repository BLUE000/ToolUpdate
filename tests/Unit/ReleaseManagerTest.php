<?php
declare(strict_types=1);

use ReleaseHub\Git\GitHubClient;
use ReleaseHub\Package\ReleaseManager;
use ReleaseHub\Package\ZipPackager;

function runReleaseManagerTests(): void
{
    $tempStorage = TEST_TEMP_STORAGE . '/rm_test';
    $client = new GitHubClient();
    $packager = new ZipPackager();
    $manager = new ReleaseManager($tempStorage, $client, $packager);

    // UT-07-01: manifestの保存と取得
    $manifestData = [
        'tool_id' => 'TrustChain',
        'tool_name' => 'TrustChain Authenticator',
        'latest_version' => 'v2.1.0',
        'last_synced_at' => date('c'),
        'releases' => [
            [
                'version' => 'v2.1.0',
                'release_date' => date('c'),
                'release_notes' => 'Test Release 2.1.0',
                'full_package' => [
                    'filename' => 'TrustChain_v2.1.0_full.zip',
                    'size' => 1024,
                    'sha256' => 'dummyhash123'
                ]
            ]
        ]
    ];

    $saveRes = $manager->saveManifest('TrustChain', $manifestData);
    TestAssert::assertTrue($saveRes, 'ReleaseManager: saveManifest returns true');

    $loaded = $manager->getManifest('TrustChain');
    TestAssert::assertNotNull($loaded, 'ReleaseManager: getManifest loads manifest');
    TestAssert::assertEquals('v2.1.0', $loaded['latest_version'] ?? '', 'ReleaseManager: latest_version matches');

    // UT-07-02: TTLキャッシュ判定
    $syncCached = $manager->checkAndSync(['id' => 'TrustChain', 'repository' => 'https://github.com/BLUE000/TrustChain.git'], force: false);
    TestAssert::assertEquals('cached', $syncCached['status'], 'ReleaseManager: recent sync returns cached status');

    // UT-07-04: 多言語READMEのキャッシュと取得
    $readmeDir = $tempStorage . '/readmes/TrustChain';
    @mkdir($readmeDir, 0755, true);
    file_put_contents($readmeDir . '/README.ja.md', "# 日本語ドキュメント\nこれはテストです。");
    file_put_contents($readmeDir . '/README.md', "# English Documentation\nThis is a test.");
    $meta = [
        'tool_id' => 'TrustChain',
        'last_synced_at' => date('c'),
        'languages' => [
            ['code' => 'ja', 'name' => '🇯🇵 日本語', 'filename' => 'README.ja.md'],
            ['code' => 'default', 'name' => '🌐 Default (README.md)', 'filename' => 'README.md']
        ]
    ];
    file_put_contents($readmeDir . '/readmes.json', json_encode($meta));

    $readmes = $manager->getReadmes('TrustChain');
    TestAssert::assertEquals(2, count($readmes), 'ReleaseManager: getReadmes returns 2 languages');

    $readmeJa = $manager->getReadme('TrustChain', 'ja');
    TestAssert::assertNotNull($readmeJa, 'ReleaseManager: getReadme(ja) returns data');
    TestAssert::assertStringContains('日本語ドキュメント', $readmeJa['content_markdown'] ?? '', 'ReleaseManager: Japanese content loaded');

    $readmeEn = $manager->getReadme('TrustChain', 'default');
    TestAssert::assertNotNull($readmeEn, 'ReleaseManager: getReadme(default) returns data');
    TestAssert::assertStringContains('English Documentation', $readmeEn['content_markdown'] ?? '', 'ReleaseManager: English content loaded');
}
