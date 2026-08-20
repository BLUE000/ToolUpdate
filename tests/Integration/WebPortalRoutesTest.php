<?php
declare(strict_types=1);

use ReleaseHub\App;

function runWebPortalRoutesIntegrationTests(): void
{
    $tempStorage = TEST_TEMP_STORAGE . '/integration_web';
    $app = new App(
        baseDir: __DIR__ . '/../../server',
        storageDir: $tempStorage,
        configDir: TEST_FIXTURES_DIR,
        templateDir: __DIR__ . '/../../server/templates'
    );

    // IT-07: TOP/一覧画面
    $htmlIndex = $app->handleWeb([]);
    TestAssert::assertStringContains('Release<span class="highlight">Hub</span>', $htmlIndex, 'Web IT-07: Logo present');
    TestAssert::assertStringContains('登録ツール一覧', $htmlIndex, 'Web IT-07: Tools list heading present');
    TestAssert::assertStringContains('現在 <strong>', $htmlIndex, 'Web IT-07: Tools count present');
    TestAssert::assertStringContains('TrustChain Authenticator', $htmlIndex, 'Web IT-07: Tool listed');
    TestAssert::assertStringContains('人気ツールランキング', $htmlIndex, 'Web IT-07: Ranking panel present');

    // IT-08: ツール詳細画面
    $htmlDetail = $app->handleWeb(['page' => 'tool', 'id' => 'TrustChain']);
    TestAssert::assertStringContains('TrustChain Authenticator - リリース履歴', $htmlDetail, 'Web IT-08: Tool detail title');
    TestAssert::assertStringContains('RSSフィード', $htmlDetail, 'Web IT-08: RSS feed button present');
    TestAssert::assertStringContains('🔥 最新版', $htmlDetail, 'Web IT-08: Latest badge rendered');

    // IT-09: 最近のリリース
    $htmlRecent = $app->handleWeb(['page' => 'recent']);
    TestAssert::assertStringContains('最近のリリース', $htmlRecent, 'Web IT-09: Recent page title');

    // IT-10: リリース年表
    $htmlReleases = $app->handleWeb(['page' => 'releases']);
    TestAssert::assertStringContains('全ツール リリース年表', $htmlReleases, 'Web IT-10: Releases page title');

    // IT-11: 不正なページパラメータフォールバック
    $htmlFallback = $app->handleWeb(['page' => 'unknown_page_xyz']);
    TestAssert::assertStringContains('登録ツール一覧', $htmlFallback, 'Web IT-11: Fallbacks to tools list');

    // IT-21: README単体ページ
    $readmeDir = $tempStorage . '/readmes/TrustChain';
    @mkdir($readmeDir, 0755, true);
    file_put_contents($readmeDir . '/README.ja.md', "# 日本語単体ドキュメント\n単体ページ検証");
    $meta = [
        'tool_id' => 'TrustChain',
        'last_synced_at' => date('c'),
        'languages' => [
            ['code' => 'ja', 'name' => '🇯🇵 日本語', 'filename' => 'README.ja.md']
        ]
    ];
    file_put_contents($readmeDir . '/readmes.json', json_encode($meta));

    $htmlReadme = $app->handleWeb(['page' => 'readme', 'tool' => 'TrustChain', 'lang' => 'ja']);
    TestAssert::assertStringContains('TrustChain Authenticator - ドキュメント', $htmlReadme, 'Web IT-21: Readme standalone page title');
    TestAssert::assertStringContains('<h1>日本語単体ドキュメント</h1>', $htmlReadme, 'Web IT-21: Readme content rendered');

    // IT-22: ツール詳細画面 READMEボタン & モーダル描画
    $htmlDetailWithReadme = $app->handleWeb(['page' => 'tool', 'id' => 'TrustChain']);
    TestAssert::assertStringContains('📖 ドキュメント・README', $htmlDetailWithReadme, 'Web IT-22: README button present');
    TestAssert::assertStringContains('readmeModal', $htmlDetailWithReadme, 'Web IT-22: README modal present');
}
