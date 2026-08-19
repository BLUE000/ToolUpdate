<?php
declare(strict_types=1);

namespace ReleaseHub\Package;

use ReleaseHub\Git\GitHubClient;

class ReleaseManager
{
    private string $storageDir;
    private GitHubClient $gitClient;
    private ZipPackager $packager;
    private int $ttlSeconds;

    public function __construct(
        string $storageDir,
        GitHubClient $gitClient,
        ZipPackager $packager,
        int $ttlSeconds = 900
    ) {
        $this->storageDir = rtrim($storageDir, '/\\');
        $this->gitClient = $gitClient;
        $this->packager = $packager;
        $this->ttlSeconds = $ttlSeconds;

        $releasesDir = $this->storageDir . '/releases';
        if (!is_dir($releasesDir)) {
            @mkdir($releasesDir, 0755, true);
        }
        $locksDir = $this->storageDir . '/locks';
        if (!is_dir($locksDir)) {
            @mkdir($locksDir, 0755, true);
        }
        $tmpDir = $this->storageDir . '/tmp';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }
    }

    public function getManifest(string $toolId): ?array
    {
        $manifestPath = $this->getManifestPath($toolId);
        if (!file_exists($manifestPath)) {
            return null;
        }

        $content = file_get_contents($manifestPath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    public function saveManifest(string $toolId, array $manifest): bool
    {
        $dir = $this->storageDir . '/releases/' . $toolId;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $manifestPath = $this->getManifestPath($toolId);
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        return @file_put_contents($manifestPath, $json) !== false;
    }

    public function checkAndSync(array $toolConfig, bool $force = false): array
    {
        $toolId = $toolConfig['id'] ?? '';
        if ($toolId === '') {
            return ['status' => 'error', 'message' => 'Invalid tool configuration'];
        }

        $manifest = $this->getManifest($toolId);

        // 1. TTLキャッシュチェック (強制同期でない場合)
        if (!$force && $manifest !== null && isset($manifest['last_synced_at'])) {
            $lastSynced = strtotime($manifest['last_synced_at']);
            if ($lastSynced !== false && (time() - $lastSynced) < $this->ttlSeconds) {
                return ['status' => 'cached', 'manifest' => $manifest];
            }
        }

        // 2. 排他ロック取得
        $lockHandle = $this->acquireLock();
        if ($lockHandle === null) {
            // 他プロセスが同期中のため既存キャッシュ返却
            return ['status' => 'busy', 'manifest' => $manifest];
        }

        try {
            $repoUrl = $toolConfig['repository'] ?? '';
            $latestRelease = $this->gitClient->getLatestRelease($repoUrl);

            if ($latestRelease === null) {
                // APIレートリミットまたは取得失敗時: 既存manifestを維持
                if ($manifest !== null) {
                    $manifest['last_synced_at'] = date('c');
                    $this->saveManifest($toolId, $manifest);
                }
                return ['status' => 'rate_limited_or_error', 'manifest' => $manifest];
            }

            $tagName = $latestRelease['tag_name'] ?? $latestRelease['name'] ?? '';
            if ($tagName === '') {
                return ['status' => 'error', 'message' => 'No tag found in release', 'manifest' => $manifest];
            }

            // 多言語READMEの同期
            $this->syncReadmes($toolConfig);

            // 新リリースかどうかの判定
            $currentLatest = $manifest['latest_version'] ?? '';
            if ($manifest !== null && $currentLatest === $tagName) {
                // 既に最新の場合でも、もし既存のリリースノートが空で今回GitHub側で記載があれば更新
                $latestBody = trim($latestRelease['body'] ?? '');
                if ($latestBody !== '' && !empty($manifest['releases']) && $manifest['releases'][0]['release_notes'] === '*リリースノートは記載されていません。*') {
                    $manifest['releases'][0]['release_notes'] = $latestBody;
                }
                $manifest['last_synced_at'] = date('c');
                $this->saveManifest($toolId, $manifest);
                return ['status' => 'up_to_date', 'manifest' => $manifest];
            }

            // 新規バージョンのパッケージ処理
            $toolDir = $this->storageDir . '/releases/' . $toolId;
            if (!is_dir($toolDir)) {
                @mkdir($toolDir, 0755, true);
            }

            $fullZipFilename = sprintf('%s_%s_full.zip', $toolId, $tagName);
            $fullZipPath = $toolDir . '/' . $fullZipFilename;

            // GitHub Release アセットZIPの探索
            $assetZipUrl = null;
            if (isset($latestRelease['assets']) && is_array($latestRelease['assets'])) {
                foreach ($latestRelease['assets'] as $asset) {
                    $name = $asset['name'] ?? '';
                    if (str_ends_with(strtolower($name), '.zip') && !str_contains(strtolower($name), 'update')) {
                        $assetZipUrl = $asset['browser_download_url'] ?? null;
                        break;
                    }
                }
            }

            // フルZIPの取得
            if ($assetZipUrl !== null) {
                $this->gitClient->downloadAsset($assetZipUrl, $fullZipPath);
            } elseif (isset($latestRelease['zipball_url'])) {
                $this->gitClient->downloadAsset($latestRelease['zipball_url'], $fullZipPath);
            }

            $fullSize = file_exists($fullZipPath) ? (int)filesize($fullZipPath) : 0;
            $fullSha = $this->packager->calculateSha256($fullZipPath);

            // 差分ZIPの自動生成 (前回バージョンが存在する場合)
            $updatePackage = null;
            if ($manifest !== null && !empty($manifest['releases']) && file_exists($fullZipPath)) {
                $prevRelease = $manifest['releases'][0];
                $prevVersion = $prevRelease['version'] ?? '';
                $prevFullFilename = $prevRelease['full_package']['filename'] ?? '';
                $prevFullPath = $toolDir . '/' . $prevFullFilename;

                if ($prevVersion !== '' && file_exists($prevFullPath)) {
                    $prevExtractDir = $this->storageDir . '/tmp/' . $toolId . '_' . $prevVersion;
                    $newExtractDir = $this->storageDir . '/tmp/' . $toolId . '_' . $tagName;

                    $this->packager->extractZip($prevFullPath, $prevExtractDir);
                    $this->packager->extractZip($fullZipPath, $newExtractDir);

                    $updateZipFilename = sprintf('%s_%s_update_from_%s.zip', $toolId, $tagName, $prevVersion);
                    $updateZipPath = $toolDir . '/' . $updateZipFilename;

                    $diffResult = $this->packager->createDiffZip(
                        $prevExtractDir,
                        $newExtractDir,
                        $updateZipPath,
                        $toolConfig['exclude'] ?? []
                    );

                    if ($diffResult['success']) {
                        $updateSize = (int)filesize($updateZipPath);
                        $updateSha = $this->packager->calculateSha256($updateZipPath);
                        $updatePackage = [
                            'filename' => $updateZipFilename,
                            'size' => $updateSize,
                            'sha256' => $updateSha,
                            'downloads' => 0,
                            'deleted_files' => $diffResult['deleted_files'] ?? []
                        ];
                    }

                    // 一時ディレクトリの削除
                    $this->removeDirectory($prevExtractDir);
                    $this->removeDirectory($newExtractDir);
                }
            }

            // 新リリースエントリの作成
            $releaseNotes = trim($latestRelease['body'] ?? '');
            if ($releaseNotes === '') {
                $releaseNotes = '*リリースノートは記載されていません。*';
            }

            $newReleaseEntry = [
                'version' => $tagName,
                'prev_version' => $currentLatest !== '' ? $currentLatest : null,
                'release_date' => $latestRelease['published_at'] ?? date('c'),
                'commit_hash' => substr((string)($latestRelease['target_commitish'] ?? ''), 0, 7),
                'release_notes' => $releaseNotes,
                'full_package' => [
                    'filename' => $fullZipFilename,
                    'size' => $fullSize,
                    'sha256' => $fullSha,
                    'downloads' => 0
                ],
                'update_package' => $updatePackage
            ];

            $releases = $manifest !== null && isset($manifest['releases']) ? $manifest['releases'] : [];
            array_unshift($releases, $newReleaseEntry);

            $newManifest = [
                'tool_id' => $toolId,
                'tool_name' => $toolConfig['name'] ?? $toolId,
                'latest_version' => $tagName,
                'total_downloads' => $manifest['total_downloads'] ?? 0,
                'last_synced_at' => date('c'),
                'releases' => $releases
            ];

            $this->saveManifest($toolId, $newManifest);

            return [
                'status' => 'new_release_created',
                'tool_id' => $toolId,
                'version' => $tagName,
                'manifest' => $newManifest
            ];
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    public function syncReadmes(array $toolConfig): array
    {
        $toolId = $toolConfig['id'] ?? '';
        $repoUrl = $toolConfig['repository'] ?? '';
        $branch = $toolConfig['branch'] ?? 'master';
        if ($toolId === '' || $repoUrl === '') {
            return [];
        }

        $readmeDir = $this->storageDir . '/readmes/' . $toolId;
        if (!is_dir($readmeDir)) {
            @mkdir($readmeDir, 0755, true);
        }

        $files = $this->gitClient->getReadmeFiles($repoUrl, $branch);
        if (empty($files)) {
            return [];
        }

        $languages = [];
        foreach ($files as $file) {
            $code = $file['code'] ?? 'default';
            $filename = $file['filename'] ?? 'README.md';
            $content = $file['content'] ?? '';

            file_put_contents($readmeDir . '/' . $filename, $content);
            $languages[] = [
                'code' => $code,
                'name' => $file['name'] ?? $code,
                'filename' => $filename
            ];
        }

        $meta = [
            'tool_id' => $toolId,
            'last_synced_at' => date('c'),
            'languages' => $languages
        ];
        file_put_contents($readmeDir . '/readmes.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $languages;
    }

    public function getReadmes(string $toolId): array
    {
        $metaPath = $this->storageDir . '/readmes/' . $toolId . '/readmes.json';
        if (!file_exists($metaPath)) {
            return [];
        }
        $data = json_decode((string)file_get_contents($metaPath), true);
        return is_array($data) && isset($data['languages']) && is_array($data['languages'])
            ? $data['languages']
            : [];
    }

    public function getReadme(string $toolId, string $lang = 'ja'): ?array
    {
        $languages = $this->getReadmes($toolId);
        if (empty($languages)) {
            return null;
        }

        // 言語のマッチング探索
        $selected = null;
        foreach ($languages as $l) {
            if (isset($l['code']) && strtolower($l['code']) === strtolower($lang)) {
                $selected = $l;
                break;
            }
        }

        // 指定言語がない場合: 日本語(ja) ➔ default ➔ en ➔ 最初の言語
        if ($selected === null) {
            foreach (['ja', 'default', 'en'] as $fallbackCode) {
                foreach ($languages as $l) {
                    if (isset($l['code']) && strtolower($l['code']) === $fallbackCode) {
                        $selected = $l;
                        break 2;
                    }
                }
            }
        }
        if ($selected === null) {
            $selected = $languages[0];
        }

        $filename = $selected['filename'] ?? 'README.md';
        $filePath = $this->storageDir . '/readmes/' . $toolId . '/' . $filename;
        if (!file_exists($filePath)) {
            return null;
        }

        $content = (string)file_get_contents($filePath);
        return [
            'tool_id' => $toolId,
            'current_lang' => $selected['code'] ?? 'default',
            'current_lang_name' => $selected['name'] ?? 'Default',
            'filename' => $filename,
            'available_languages' => $languages,
            'content_markdown' => $content
        ];
    }

    private function getManifestPath(string $toolId): string
    {
        return sprintf('%s/releases/%s/manifest.json', $this->storageDir, $toolId);
    }

    private function acquireLock(): mixed
    {
        $lockFile = $this->storageDir . '/locks/sync.lock';
        $fp = @fopen($lockFile, 'w+');
        if ($fp === false) {
            return null;
        }

        if (flock($fp, LOCK_EX | LOCK_NB)) {
            return $fp;
        }

        fclose($fp);
        return null;
    }

    private function releaseLock(mixed $lockHandle): void
    {
        if (is_resource($lockHandle)) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff((array)scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
