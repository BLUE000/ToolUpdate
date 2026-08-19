<?php
declare(strict_types=1);

use ReleaseHub\Config\ConfigLoader;
use ReleaseHub\Log\GeoIPResolver;
use ReleaseHub\Log\LogEngine;

function runLogEngineTests(): void
{
    $testLogDir = TEST_TEMP_STORAGE . '/logs';
    if (is_dir($testLogDir)) {
        foreach (glob($testLogDir . '/*') as $f) {
            @unlink($f);
        }
    }
    $config = new ConfigLoader(TEST_FIXTURES_DIR);
    $resolver = new GeoIPResolver($config->getGeoIpData());
    $engine = new LogEngine($testLogDir, $resolver);

    // UT-03-01: 記録
    $recorded = $engine->record('TrustChain', 'v2.1.0', 'update', '123.45.67.89', 'PHPUnitTest/1.0', 'updater_exe');
    TestAssert::assertTrue($recorded, 'LogEngine: record successfully writes log');

    // 複数件追加
    $engine->record('TrustChain', 'v2.1.0', 'full', '123.45.67.89', 'Browser/1.0', 'browser');
    $engine->record('TrustChain', 'v2.0.0', 'full', '8.8.8.8', 'Browser/1.0', 'browser');
    $engine->record('TwitchFollowerList', 'v1.0.0', 'full', '127.0.0.1', 'Browser/1.0', 'browser');

    // UT-03-02: 累計DL集計
    $totalTc = $engine->getToolTotalDownloads('TrustChain');
    TestAssert::assertEquals(3, $totalTc, 'LogEngine: TrustChain total downloads count');

    // UT-03-03: バージョン別DL集計
    $v210Tc = $engine->getVersionDownloads('TrustChain', 'v2.1.0');
    TestAssert::assertEquals(2, $v210Tc, 'LogEngine: TrustChain v2.1.0 downloads count');

    // UT-03-04: ランキング
    $ranking = $engine->getPopularRanking(5);
    TestAssert::assertTrue(count($ranking) >= 2, 'LogEngine: ranking returns list');
    TestAssert::assertEquals('TrustChain', $ranking[0]['tool_id'], 'LogEngine: rank 1 is TrustChain');

    // UT-03-05: 国別統計
    $stats = $engine->getCountryStatistics();
    TestAssert::assertEquals(4, $stats['total'], 'LogEngine: country stats total');
    TestAssert::assertTrue(count($stats['countries']) >= 1, 'LogEngine: country stats countries list');
}
