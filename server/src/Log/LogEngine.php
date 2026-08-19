<?php
declare(strict_types=1);

namespace ReleaseHub\Log;

class LogEngine
{
    private string $logDir;
    private GeoIPResolver $geoResolver;

    public function __construct(string $logDir, GeoIPResolver $geoResolver)
    {
        $this->logDir = rtrim($logDir, '/\\');
        $this->geoResolver = $geoResolver;
        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
    }

    public function record(
        string $toolId,
        string $version,
        string $packageType,
        string $ip,
        string $userAgent,
        string $clientType = 'browser'
    ): bool {
        $ip = trim($ip);
        if ($ip === '') {
            $ip = '127.0.0.1';
        }

        // 逆引きホスト名（フェイルセーフ）
        $hostName = $ip;
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $resolved = @gethostbyaddr($ip);
            if ($resolved !== false && $resolved !== '') {
                $hostName = $resolved;
            }
        }

        // 国名特定
        $geo = $this->geoResolver->resolve($ip);

        $record = [
            'timestamp' => date('c'),
            'tool_id' => $toolId,
            'version' => $version,
            'package_type' => $packageType,
            'ip_address' => $ip,
            'host_name' => $hostName,
            'country_code' => $geo['country_code'],
            'country_name' => $geo['country_name'],
            'user_agent' => $userAgent,
            'client_type' => $clientType
        ];

        $jsonLine = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        $fileName = sprintf('download_logs_%s.jsonl', date('Y-m-d'));
        $filePath = $this->logDir . '/' . $fileName;

        try {
            $result = @file_put_contents($filePath, $jsonLine, FILE_APPEND | LOCK_EX);
            return $result !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function getToolTotalDownloads(string $toolId): int
    {
        $count = 0;
        $files = $this->getLogFiles();
        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if (is_array($data) && isset($data['tool_id']) && $data['tool_id'] === $toolId) {
                    $count++;
                }
            }
        }
        return $count;
    }

    public function getVersionDownloads(string $toolId, string $version): int
    {
        $count = 0;
        $files = $this->getLogFiles();
        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if (
                    is_array($data)
                    && isset($data['tool_id'], $data['version'])
                    && $data['tool_id'] === $toolId
                    && $data['version'] === $version
                ) {
                    $count++;
                }
            }
        }
        return $count;
    }

    public function getPopularRanking(int $limit = 10, ?string $period = 'all'): array
    {
        $toolCounts = [];
        $files = $this->getLogFiles($period);
        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if (is_array($data) && isset($data['tool_id']) && $data['tool_id'] !== '') {
                    $id = $data['tool_id'];
                    $toolCounts[$id] = ($toolCounts[$id] ?? 0) + 1;
                }
            }
        }

        arsort($toolCounts);

        $ranking = [];
        $rank = 1;
        foreach ($toolCounts as $toolId => $downloads) {
            $ranking[] = [
                'rank' => $rank++,
                'tool_id' => $toolId,
                'downloads' => $downloads
            ];
            if (count($ranking) >= $limit) {
                break;
            }
        }

        return $ranking;
    }

    public function getCountryStatistics(?string $toolId = null, int $limit = 10): array
    {
        $countryCounts = [];
        $total = 0;
        $files = $this->getLogFiles();
        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if (!is_array($data)) {
                    continue;
                }
                if ($toolId !== null && (!isset($data['tool_id']) || $data['tool_id'] !== $toolId)) {
                    continue;
                }
                $country = $data['country_name'] ?? 'Unknown';
                $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
                $total++;
            }
        }

        arsort($countryCounts);

        $stats = [];
        foreach ($countryCounts as $country => $count) {
            $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0.0;
            $stats[] = [
                'country_name' => $country,
                'downloads' => $count,
                'percentage' => $percentage
            ];
            if (count($stats) >= $limit) {
                break;
            }
        }

        return [
            'total' => $total,
            'countries' => $stats
        ];
    }

    public function getRecentDownloads(int $limit = 20): array
    {
        $records = [];
        $files = $this->getLogFiles();
        // 最新ログファイルから逆順に走査
        $files = array_reverse($files);

        foreach ($files as $file) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            $lines = array_reverse($lines);
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if (is_array($data)) {
                    $records[] = $data;
                    if (count($records) >= $limit) {
                        return $records;
                    }
                }
            }
        }

        return $records;
    }

    private function getLogFiles(?string $period = 'all'): array
    {
        if (!is_dir($this->logDir)) {
            return [];
        }

        $pattern = $this->logDir . '/download_logs_*.jsonl';
        $files = glob($pattern);
        if ($files === false) {
            return [];
        }

        sort($files);

        if ($period === 'today') {
            $todayFile = sprintf('%s/download_logs_%s.jsonl', $this->logDir, date('Y-m-d'));
            return in_array($todayFile, $files, true) ? [$todayFile] : [];
        }

        if ($period === 'week') {
            $weekAgo = date('Y-m-d', strtotime('-7 days'));
            return array_filter($files, function ($file) use ($weekAgo) {
                preg_match('/download_logs_(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m);
                return isset($m[1]) && $m[1] >= $weekAgo;
            });
        }

        if ($period === 'month') {
            $monthAgo = date('Y-m-d', strtotime('-30 days'));
            return array_filter($files, function ($file) use ($monthAgo) {
                preg_match('/download_logs_(\d{4}-\d{2}-\d{2})\.jsonl$/', $file, $m);
                return isset($m[1]) && $m[1] >= $monthAgo;
            });
        }

        return $files;
    }
}
