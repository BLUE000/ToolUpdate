<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Unit/ConfigLoaderTest.php';
require_once __DIR__ . '/Unit/GeoIPResolverTest.php';
require_once __DIR__ . '/Unit/LogEngineTest.php';
require_once __DIR__ . '/Unit/ZipPackagerTest.php';
require_once __DIR__ . '/Unit/MarkdownRendererTest.php';
require_once __DIR__ . '/Unit/GitHubClientTest.php';
require_once __DIR__ . '/Unit/ReleaseManagerTest.php';
require_once __DIR__ . '/Integration/ApiRoutesTest.php';
require_once __DIR__ . '/Integration/WebPortalRoutesTest.php';
require_once __DIR__ . '/Integration/FeedRoutesTest.php';
require_once __DIR__ . '/Integration/ConcurrencyAndFallbackTest.php';

$startTime = microtime(true);
$logBuffer = [];

$logBuffer[] = "========================================================";
$logBuffer[] = " ReleaseHub Automated Test Suite Execution";
$logBuffer[] = " Date: " . date('Y-m-d H:i:s');
$logBuffer[] = " PHP: " . PHP_VERSION . " (" . PHP_OS . ")";
$logBuffer[] = "========================================================\n";

TestAssert::reset();

// 各テストスイート実行
$suites = [
    'Unit: ConfigLoader' => 'runConfigLoaderTests',
    'Unit: GeoIPResolver' => 'runGeoIPResolverTests',
    'Unit: LogEngine' => 'runLogEngineTests',
    'Unit: ZipPackager' => 'runZipPackagerTests',
    'Unit: MarkdownRenderer' => 'runMarkdownRendererTests',
    'Unit: GitHubClient' => 'runGitHubClientTests',
    'Unit: ReleaseManager' => 'runReleaseManagerTests',
    'Integration: ApiRoutes' => 'runApiRoutesIntegrationTests',
    'Integration: WebPortalRoutes' => 'runWebPortalRoutesIntegrationTests',
    'Integration: FeedRoutes' => 'runFeedRoutesIntegrationTests',
    'Integration: ConcurrencyAndFallback' => 'runConcurrencyAndFallbackIntegrationTests',
];

foreach ($suites as $name => $fn) {
    $beforeFail = TestAssert::$failed;
    $suiteStart = microtime(true);
    try {
        $fn();
        $suiteElapsed = round((microtime(true) - $suiteStart) * 1000, 2);
        if (TestAssert::$failed === $beforeFail) {
            $logBuffer[] = sprintf("[PASS] %s (%s ms)", $name, $suiteElapsed);
        } else {
            $logBuffer[] = sprintf("[FAIL] %s (%s ms)", $name, $suiteElapsed);
        }
    } catch (\Throwable $e) {
        TestAssert::$failed++;
        TestAssert::$failures[] = "Exception in [{$name}]: " . $e->getMessage() . "\n" . $e->getTraceAsString();
        $logBuffer[] = sprintf("[ERROR] %s: %s", $name, $e->getMessage());
    }
}

$elapsedTime = round(microtime(true) - $startTime, 4);
$totalTests = TestAssert::$passed + TestAssert::$failed;

$ngList = empty(TestAssert::$failures) ? 'なし' : implode("\n  - ", TestAssert::$failures);

$summary = "\n========================================================\n";
$summary .= " テスト実行結果サマリ\n";
$summary .= "========================================================\n";
$summary .= sprintf("実行件数 %d件(OK: %d件/NG: %d件)\n", $totalTests, TestAssert::$passed, TestAssert::$failed);
$summary .= "NGテスト項目番号： " . (empty(TestAssert::$failures) ? "なし\n" : "\n  - " . $ngList . "\n");
$summary .= sprintf("テスト実行時間：%s秒\n", number_format($elapsedTime, 3));
$summary .= "========================================================\n";

$fullLog = implode("\n", $logBuffer) . "\n" . $summary;

// ログファイル保存
$logFileName = sprintf('test_run_%s.log', date('Y-m-d_H-i-s'));
$logPath = TEST_LOGS_DIR . '/' . $logFileName;
@file_put_contents($logPath, $fullLog);

// 標準出力へのサマリ出力
echo $summary;
echo "詳細ログ保管先: {$logPath}\n";

exit(TestAssert::$failed === 0 ? 0 : 1);
