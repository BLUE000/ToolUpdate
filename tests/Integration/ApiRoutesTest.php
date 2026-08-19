<?php
declare(strict_types=1);

use ReleaseHub\App;

function runApiRoutesIntegrationTests(): void
{
    $tempStorage = TEST_TEMP_STORAGE . '/integration';
    @mkdir($tempStorage . '/releases/TrustChain', 0755, true);

    // ダミーmanifest配置
    $manifestData = [
        'tool_id' => 'TrustChain',
        'tool_name' => 'TrustChain Authenticator',
        'latest_version' => 'v2.1.0',
        'last_synced_at' => date('c'),
        'releases' => [
            [
                'version' => 'v2.1.0',
                'release_date' => date('c'),
                'release_notes' => 'Integration Test Notes',
                'full_package' => [
                    'filename' => 'TrustChain_v2.1.0_full.zip',
                    'size' => 2048,
                    'sha256' => 'aabbccddeeff'
                ],
                'update_package' => [
                    'filename' => 'TrustChain_v2.1.0_update.zip',
                    'size' => 512,
                    'sha256' => '112233445566',
                    'deleted_files' => []
                ]
            ]
        ]
    ];
    file_put_contents($tempStorage . '/releases/TrustChain/manifest.json', json_encode($manifestData));
    file_put_contents($tempStorage . '/releases/TrustChain/TrustChain_v2.1.0_full.zip', 'dummy zip data');

    $app = new App(
        baseDir: __DIR__ . '/../../server',
        storageDir: $tempStorage,
        configDir: TEST_FIXTURES_DIR,
        templateDir: __DIR__ . '/../../server/templates'
    );

    // IT-01: 更新あり check
    $apiRes1 = $app->handleApi(['action' => 'check', 'tool' => 'TrustChain', 'current' => 'v2.0.0']);
    TestAssert::assertEquals('success', $apiRes1['status'] ?? '', 'API IT-01: status is success');
    TestAssert::assertTrue($apiRes1['has_update'] ?? false, 'API IT-01: has_update is true');
    TestAssert::assertEquals('v2.1.0', $apiRes1['latest_version'] ?? '', 'API IT-01: latest_version is v2.1.0');
    TestAssert::assertNotNull($apiRes1['packages']['full']['url'] ?? null, 'API IT-01: full package url present');

    // IT-02: 最新状態 check
    $apiRes2 = $app->handleApi(['action' => 'check', 'tool' => 'TrustChain', 'current' => 'v2.1.0']);
    TestAssert::assertFalse($apiRes2['has_update'] ?? true, 'API IT-02: has_update is false when current');

    // IT-03: 存在しないツール
    $apiRes3 = $app->handleApi(['action' => 'check', 'tool' => 'InvalidTool', 'current' => 'v1.0.0']);
    TestAssert::assertEquals('error', $apiRes3['status'] ?? '', 'API IT-03: status error for invalid tool');

    // IT-04: 管理者同期 API (認証エラー)
    $apiResAuthErr = $app->handleApi(['action' => 'sync', 'token' => 'wrong-token']);
    TestAssert::assertEquals('error', $apiResAuthErr['status'] ?? '', 'API IT-04: Auth error on invalid token');

    // IT-05: 管理者同期 API (正常系: 変化なしスキップ)
    $apiResSyncOk = $app->handleApi(['action' => 'sync', 'token' => 'test-admin-secret-token']);
    TestAssert::assertTrue(in_array($apiResSyncOk['status'] ?? '', ['up_to_date', 'updated']), 'API IT-05: Sync returns up_to_date or updated');
    TestAssert::assertNotNull($apiResSyncOk['message'] ?? null, 'API IT-05: Message present');

    // IT-20: 多言語README API (action=readme)
    $readmeDir = $tempStorage . '/readmes/TrustChain';
    @mkdir($readmeDir, 0755, true);
    file_put_contents($readmeDir . '/README.ja.md', "# 日本語タイトル\nテスト説明文です。");
    $meta = [
        'tool_id' => 'TrustChain',
        'last_synced_at' => date('c'),
        'languages' => [
            ['code' => 'ja', 'name' => '🇯🇵 日本語', 'filename' => 'README.ja.md']
        ]
    ];
    file_put_contents($readmeDir . '/readmes.json', json_encode($meta));

    $apiReadme = $app->handleApi(['action' => 'readme', 'tool' => 'TrustChain', 'lang' => 'ja']);
    TestAssert::assertEquals('success', $apiReadme['status'] ?? '', 'API IT-20: README status is success');
    TestAssert::assertStringContains('<h1>日本語タイトル</h1>', $apiReadme['content_html'] ?? '', 'API IT-20: README content_html rendered');
    TestAssert::assertEquals('ja', $apiReadme['current_lang'] ?? '', 'API IT-20: current_lang is ja');
}
