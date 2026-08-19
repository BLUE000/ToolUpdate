<?php
declare(strict_types=1);

// ReleaseHub Test Bootstrap
require_once __DIR__ . '/../server/src/autoload.php';

// テスト用定数定義
define('TEST_ROOT', __DIR__);
define('TEST_FIXTURES_DIR', __DIR__ . '/fixtures');
define('TEST_TEMP_STORAGE', __DIR__ . '/temp_storage');
define('TEST_LOGS_DIR', __DIR__ . '/logs');

// テスト用一時ディレクトリの初期化
if (!is_dir(TEST_TEMP_STORAGE)) {
    @mkdir(TEST_TEMP_STORAGE, 0755, true);
}
if (!is_dir(TEST_LOGS_DIR)) {
    @mkdir(TEST_LOGS_DIR, 0755, true);
}

// 簡易アサーション用ヘルパークラス
class TestAssert
{
    public static int $passed = 0;
    public static int $failed = 0;
    public static array $failures = [];

    public static function assertTrue(bool $condition, string $message = ''): void
    {
        if ($condition) {
            self::$passed++;
        } else {
            self::$failed++;
            self::$failures[] = $message ?: 'Expected true but got false';
        }
    }

    public static function assertFalse(bool $condition, string $message = ''): void
    {
        self::assertTrue(!$condition, $message ?: 'Expected false but got true');
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            self::$passed++;
        } else {
            self::$failed++;
            $expectedStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
            $actualStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
            self::$failures[] = ($message ? $message . ' - ' : '') . "Expected [{$expectedStr}] but got [{$actualStr}]";
        }
    }

    public static function assertNotNull(mixed $actual, string $message = ''): void
    {
        self::assertTrue($actual !== null, $message ?: 'Expected non-null value');
    }

    public static function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        self::assertTrue(str_contains($haystack, $needle), $message ?: "String does not contain [{$needle}]");
    }

    public static function reset(): void
    {
        self::$passed = 0;
        self::$failed = 0;
        self::$failures = [];
    }
}
