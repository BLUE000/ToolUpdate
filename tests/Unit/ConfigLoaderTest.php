<?php
declare(strict_types=1);

use ReleaseHub\Config\ConfigLoader;

function runConfigLoaderTests(): void
{
    $config = new ConfigLoader(TEST_FIXTURES_DIR);

    // UT-01-01: 正常系 branches.json
    $branches = $config->getBranches();
    TestAssert::assertTrue(count($branches) >= 2, 'ConfigLoader: getBranches returns tools array');

    // UT-01-02: 正常系 getTool
    $tool = $config->getTool('TrustChain');
    TestAssert::assertNotNull($tool, 'ConfigLoader: getTool returns TrustChain');
    TestAssert::assertEquals('TrustChain Authenticator', $tool['name'] ?? '', 'ConfigLoader: Tool name matches');

    // UT-01-03: 異常系 存在しないツール
    $notExist = $config->getTool('NotExistTool');
    TestAssert::assertTrue($notExist === null, 'ConfigLoader: Non-existent tool returns null');

    // UT-01-05: バリデーション
    TestAssert::assertTrue($config->validateToolConfig($tool), 'ConfigLoader: validateToolConfig is valid');
    TestAssert::assertFalse($config->validateToolConfig(['id' => 'x']), 'ConfigLoader: validateToolConfig detects missing fields');

    // UT-01-04: 不正ディレクトリ / 不在
    $emptyConfig = new ConfigLoader(TEST_TEMP_STORAGE . '/empty_config');
    TestAssert::assertEquals([], $emptyConfig->getBranches(), 'ConfigLoader: Empty dir returns empty array');
}
