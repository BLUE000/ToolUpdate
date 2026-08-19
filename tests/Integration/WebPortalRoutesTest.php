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
}
