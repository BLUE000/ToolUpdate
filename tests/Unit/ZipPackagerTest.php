<?php
declare(strict_types=1);

use ReleaseHub\Package\ZipPackager;

function runZipPackagerTests(): void
{
    $packager = new ZipPackager();

    $workDir = TEST_TEMP_STORAGE . '/zip_test';
    @mkdir($workDir . '/v1', 0755, true);
    @mkdir($workDir . '/v2', 0755, true);

    file_put_contents($workDir . '/v1/file_a.txt', 'Version 1 Content A');
    file_put_contents($workDir . '/v1/file_b.txt', 'Version 1 Content B (to be deleted)');

    file_put_contents($workDir . '/v2/file_a.txt', 'Version 2 Content A (Modified)');
    file_put_contents($workDir . '/v2/file_c.txt', 'Version 2 Content C (New)');

    // UT-04-02: 全体ZIP作成
    $fullZipPath = $workDir . '/v1_full.zip';
    $fullRes = $packager->createFullZip($workDir . '/v1', $fullZipPath);
    TestAssert::assertTrue($fullRes, 'ZipPackager: createFullZip creates zip');
    TestAssert::assertTrue(file_exists($fullZipPath), 'ZipPackager: full zip exists');

    // UT-04-01: SHA256計算
    $sha = $packager->calculateSha256($fullZipPath);
    TestAssert::assertEquals(64, strlen($sha), 'ZipPackager: SHA256 length is 64 hex characters');

    // UT-04-03 & 04: 差分ZIP & 削除ファイル
    $diffZipPath = $workDir . '/update_v2.zip';
    $diffRes = $packager->createDiffZip($workDir . '/v1', $workDir . '/v2', $diffZipPath);

    TestAssert::assertTrue($diffRes['success'], 'ZipPackager: createDiffZip success');
    TestAssert::assertEquals(2, $diffRes['file_count'], 'ZipPackager: 2 files modified/added');
    TestAssert::assertEquals(['file_b.txt'], $diffRes['deleted_files'], 'ZipPackager: file_b.txt in deleted list');
    TestAssert::assertTrue(file_exists($diffZipPath), 'ZipPackager: update zip exists');
}
