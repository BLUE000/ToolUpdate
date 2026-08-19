<?php
declare(strict_types=1);

namespace ReleaseHub\Package;

use ZipArchive;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ZipPackager
{
    public function calculateSha256(string $filePath): string
    {
        if (!file_exists($filePath)) {
            return '';
        }
        $hash = hash_file('sha256', $filePath);
        return $hash !== false ? $hash : '';
    }

    public function extractZip(string $zipPath, string $extractTo): bool
    {
        if (!file_exists($zipPath)) {
            return false;
        }

        if (!is_dir($extractTo)) {
            @mkdir($extractTo, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $result = $zip->extractTo($extractTo);
        $zip->close();
        return $result;
    }

    public function createDiffZip(
        string $prevExtractDir,
        string $newExtractDir,
        string $outputZipPath,
        array $excludeList = []
    ): array {
        $prevFiles = $this->getFileList($prevExtractDir, $excludeList);
        $newFiles = $this->getFileList($newExtractDir, $excludeList);

        $addedOrModified = [];
        $deletedFiles = [];

        // 新規追加 & 変更されたファイル抽出
        foreach ($newFiles as $relPath => $fullPath) {
            if (!isset($prevFiles[$relPath])) {
                $addedOrModified[$relPath] = $fullPath;
            } else {
                $prevHash = hash_file('md5', $prevFiles[$relPath]);
                $newHash = hash_file('md5', $fullPath);
                if ($prevHash !== $newHash) {
                    $addedOrModified[$relPath] = $fullPath;
                }
            }
        }

        // 削除されたファイル抽出
        foreach ($prevFiles as $relPath => $fullPath) {
            if (!isset($newFiles[$relPath])) {
                $deletedFiles[] = $relPath;
            }
        }

        // 差分ZIP作成
        $outDir = dirname($outputZipPath);
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return [
                'success' => false,
                'deleted_files' => $deletedFiles,
                'file_count' => 0
            ];
        }

        foreach ($addedOrModified as $relPath => $fullPath) {
            $zip->addFile($fullPath, $relPath);
        }

        // 削除ファイルリストをdelete_list.jsonとして同梱
        if (!empty($deletedFiles)) {
            $zip->addFromString('delete_list.json', json_encode($deletedFiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        $zip->close();

        return [
            'success' => true,
            'deleted_files' => $deletedFiles,
            'file_count' => count($addedOrModified)
        ];
    }

    public function createFullZip(string $sourceDir, string $outputZipPath, array $excludeList = []): bool
    {
        if (!is_dir($sourceDir)) {
            return false;
        }

        $files = $this->getFileList($sourceDir, $excludeList);
        if (empty($files)) {
            return false;
        }

        $outDir = dirname($outputZipPath);
        if (!is_dir($outDir)) {
            @mkdir($outDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        foreach ($files as $relPath => $fullPath) {
            $zip->addFile($fullPath, $relPath);
        }

        return $zip->close();
    }

    private function getFileList(string $dir, array $excludeList = []): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $dirLength = strlen(rtrim($dir, '/\\')) + 1;

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $fullPath = (string)$item->getPathname();
                $relPath = str_replace('\\', '/', substr($fullPath, $dirLength));

                // 除外判定
                $excluded = false;
                foreach ($excludeList as $pattern) {
                    $pattern = trim($pattern);
                    if ($pattern === '') {
                        continue;
                    }
                    if (str_starts_with($relPath, $pattern . '/') || $relPath === $pattern || fnmatch($pattern, $relPath)) {
                        $excluded = true;
                        break;
                    }
                }

                if (!$excluded) {
                    $files[$relPath] = $fullPath;
                }
            }
        }

        return $files;
    }
}
