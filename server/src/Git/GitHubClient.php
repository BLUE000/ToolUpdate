<?php
declare(strict_types=1);

namespace ReleaseHub\Git;

class GitHubClient
{
    private ?string $token;
    private int $rateLimitRemaining = 60;
    private int $rateLimitResetTime = 0;

    public function __construct(?string $token = null)
    {
        $this->token = $token !== null && trim($token) !== '' ? trim($token) : null;
    }

    public function getLatestRelease(string $repoUrl): ?array
    {
        $repoPath = $this->parseRepoPath($repoUrl);
        if ($repoPath === null) {
            return null;
        }

        return $this->request("/repos/{$repoPath}/releases/latest");
    }

    public function getReleases(string $repoUrl): array
    {
        $repoPath = $this->parseRepoPath($repoUrl);
        if ($repoPath === null) {
            return [];
        }

        $result = $this->request("/repos/{$repoPath}/releases");
        return is_array($result) ? $result : [];
    }

    public function getTags(string $repoUrl): array
    {
        $repoPath = $this->parseRepoPath($repoUrl);
        if ($repoPath === null) {
            return [];
        }

        $result = $this->request("/repos/{$repoPath}/tags");
        return is_array($result) ? $result : [];
    }

    public function downloadAsset(string $assetUrl, string $savePath): bool
    {
        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $fp = @fopen($savePath, 'w+');
        if ($fp === false) {
            return false;
        }

        $ch = curl_init($assetUrl);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ReleaseHub-Engine/1.0');

        $headers = ['Accept: application/octet-stream'];
        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode >= 400) {
            @unlink($savePath);
            return false;
        }

        return true;
    }

    public function getReadmeFiles(string $repoUrl, string $branch = 'master'): array
    {
        $repoPath = $this->parseRepoPath($repoUrl);
        if ($repoPath === null) {
            return [];
        }

        $knownLanguages = [
            'ja' => ['name' => '🇯🇵 日本語', 'filename' => 'README.ja.md'],
            'en' => ['name' => '🇺🇸 English', 'filename' => 'README.en.md'],
            'de' => ['name' => '🇩🇪 Deutsch', 'filename' => 'README.de.md'],
            'fr' => ['name' => '🇫🇷 Français', 'filename' => 'README.fr.md'],
            'pt' => ['name' => '🇵🇹 Português', 'filename' => 'README.pt.md'],
            'ru' => ['name' => '🇷🇺 Русский', 'filename' => 'README.ru.md'],
            'zh' => ['name' => '🇨🇳 简体中文', 'filename' => 'README.zh.md'],
            'ko' => ['name' => '🇰🇷 한국어', 'filename' => 'README.ko.md'],
            'es' => ['name' => '🇪🇸 Español', 'filename' => 'README.es.md'],
            'default' => ['name' => '🌐 Default (README.md)', 'filename' => 'README.md']
        ];

        $results = [];

        // 1. Contents APIからファイル一覧取得を試行
        $contents = $this->request("/repos/{$repoPath}/contents?ref={$branch}");
        if (is_array($contents)) {
            foreach ($contents as $file) {
                $name = $file['name'] ?? '';
                if (!is_string($name) || !str_starts_with(strtoupper($name), 'README')) {
                    continue;
                }

                // 言語判定
                $langCode = 'default';
                $langLabel = '🌐 Default';
                if (preg_match('/^README\.([a-zA-Z_\-]+)\.md$/i', $name, $m)) {
                    $code = strtolower($m[1]);
                    $langCode = $code;
                    $langLabel = $knownLanguages[$code]['name'] ?? strtoupper($code);
                } elseif (strcasecmp($name, 'README.md') === 0 || strcasecmp($name, 'README') === 0) {
                    $langCode = 'default';
                    $langLabel = '🌐 Default (README.md)';
                }

                $downloadUrl = $file['download_url'] ?? null;
                $rawContent = '';
                if (is_string($downloadUrl) && $downloadUrl !== '') {
                    $rawContent = (string)@file_get_contents($downloadUrl);
                }

                if ($rawContent !== '') {
                    $results[] = [
                        'code' => $langCode,
                        'name' => $langLabel,
                        'filename' => $name,
                        'content' => $rawContent
                    ];
                }
            }
        }

        // 2. Contents APIが取れなかった場合やレートリミット時のRawフォールバック
        if (empty($results)) {
            $candidates = [
                'ja' => 'README.ja.md',
                'default' => 'README.md',
                'en' => 'README.en.md',
                'de' => 'README.de.md',
                'fr' => 'README.fr.md',
                'pt' => 'README.pt.md',
                'ru' => 'README.ru.md'
            ];

            foreach ($candidates as $code => $filename) {
                $rawUrl = "https://raw.githubusercontent.com/{$repoPath}/{$branch}/{$filename}";
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'user_agent' => 'ReleaseHub-Engine/1.0'
                    ]
                ]);
                $content = @file_get_contents($rawUrl, false, $ctx);
                if ($content !== false && trim($content) !== '') {
                    $results[] = [
                        'code' => $code,
                        'name' => $knownLanguages[$code]['name'] ?? strtoupper($code),
                        'filename' => $filename,
                        'content' => $content
                    ];
                }
            }
        }

        return $results;
    }

    public function isRateLimited(): bool
    {
        if ($this->rateLimitRemaining <= 0) {
            return time() < $this->rateLimitResetTime;
        }
        return false;
    }

    public function getRateLimitResetTime(): int
    {
        return $this->rateLimitResetTime;
    }

    public function parseRepoPath(string $repoUrl): ?string
    {
        $repoUrl = trim($repoUrl);
        if (preg_match('#github\.com[:/]([a-zA-Z0-9_\-\.]+)/([a-zA-Z0-9_\-\.]+?)(?:\.git)?$#i', $repoUrl, $m)) {
            return $m[1] . '/' . $m[2];
        }
        return null;
    }

    private function request(string $endpoint): ?array
    {
        if ($this->isRateLimited()) {
            return null;
        }

        $url = 'https://api.github.com' . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Accept: application/vnd.github.v3+json',
            'User-Agent: ReleaseHub-Engine/1.0'
        ];

        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);
            return null;
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerStr = substr((string)$response, 0, $headerSize);
        $bodyStr = substr((string)$response, $headerSize);
        curl_close($ch);

        // レートリミットヘッダ解析
        if (preg_match('/x-ratelimit-remaining:\s*(\d+)/i', $headerStr, $m)) {
            $this->rateLimitRemaining = (int)$m[1];
        }
        if (preg_match('/x-ratelimit-reset:\s*(\d+)/i', $headerStr, $m)) {
            $this->rateLimitResetTime = (int)$m[1];
        }

        if ($httpCode >= 400) {
            return null;
        }

        $data = json_decode($bodyStr, true);
        return is_array($data) ? $data : null;
    }
}
