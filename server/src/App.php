<?php
declare(strict_types=1);

namespace ReleaseHub;

use ReleaseHub\Config\ConfigLoader;
use ReleaseHub\Git\GitHubClient;
use ReleaseHub\Log\GeoIPResolver;
use ReleaseHub\Log\LogEngine;
use ReleaseHub\Package\ReleaseManager;
use ReleaseHub\Package\ZipPackager;
use ReleaseHub\Template\MarkdownRenderer;
use ReleaseHub\Notifier\FeedGenerator;

class App
{
    private ConfigLoader $config;
    private LogEngine $logEngine;
    private ReleaseManager $releaseManager;
    private MarkdownRenderer $renderer;
    private FeedGenerator $feedGen;
    private \ReleaseHub\Notifier\WebhookNotifier $notifier;
    private string $storageDir;
    private string $baseUrl;

    public function __construct(
        string $baseDir,
        ?string $storageDir = null,
        ?string $configDir = null,
        ?string $templateDir = null
    ) {
        $baseDir = rtrim($baseDir, '/\\');
        $this->storageDir = $storageDir !== null ? rtrim($storageDir, '/\\') : $baseDir . '/storage';
        $configDir = $configDir !== null ? rtrim($configDir, '/\\') : $baseDir . '/config';
        $templateDir = $templateDir !== null ? rtrim($templateDir, '/\\') : $baseDir . '/templates';

        $this->config = new ConfigLoader($configDir);
        $geoResolver = new GeoIPResolver($this->config->getGeoIpData());
        $this->logEngine = new LogEngine($this->storageDir . '/logs', $geoResolver);

        $gitClient = new GitHubClient();
        $packager = new ZipPackager();
        $this->releaseManager = new ReleaseManager($this->storageDir, $gitClient, $packager);
        $this->renderer = new MarkdownRenderer($templateDir);
        $this->feedGen = new FeedGenerator();
        $this->notifier = new \ReleaseHub\Notifier\WebhookNotifier();
        $this->baseUrl = '.';
    }

    public function handleWeb(array $getParams): string
    {
        $page = $getParams['page'] ?? 'tools';
        $toolId = $getParams['id'] ?? ($getParams['tool'] ?? '');
        $action = $getParams['action'] ?? '';

        // 手動同期アクション
        if ($action === 'sync' && $toolId !== '') {
            $toolConfig = $this->config->getTool($toolId);
            if ($toolConfig !== null) {
                $this->releaseManager->checkAndSync($toolConfig, force: true);
            }
            $redirectUrl = sprintf('?page=tool&id=%s', urlencode($toolId));
            header("Location: {$redirectUrl}");
            return '';
        }

        $tools = $this->config->getBranches();

        // ページ別レンダリング
        return match ($page) {
            'tool' => $this->renderToolDetail($toolId),
            'recent' => $this->renderRecent($tools),
            'releases' => $this->renderReleases($tools),
            'readme' => $this->renderReadmePage($toolId, $getParams['lang'] ?? 'ja'),
            default => $this->renderToolsList($tools),
        };
    }

    public function handleApi(array $getParams): array
    {
        $action = $getParams['action'] ?? '';
        $toolId = $getParams['tool'] ?? '';

        if ($action === 'readme') {
            if ($toolId === '') {
                return ['status' => 'error', 'message' => 'Missing tool parameter'];
            }

            $lang = $getParams['lang'] ?? 'ja';
            $readmeData = $this->releaseManager->getReadme($toolId, $lang);
            if ($readmeData === null) {
                http_response_code(404);
                return ['status' => 'error', 'message' => 'README not found for the specified tool/language'];
            }

            $toolConfig = $this->config->getTool($toolId);
            $toolName = $toolConfig['name'] ?? $toolId;
            $html = $this->renderer->markdownToHtml($readmeData['content_markdown']);

            return [
                'status' => 'success',
                'tool_id' => $toolId,
                'tool_name' => $toolName,
                'current_lang' => $readmeData['current_lang'],
                'current_lang_name' => $readmeData['current_lang_name'],
                'filename' => $readmeData['filename'],
                'available_languages' => $readmeData['available_languages'],
                'content_markdown' => $readmeData['content_markdown'],
                'content_html' => $html
            ];
        }

        if ($action === 'check') {
            if ($toolId === '') {
                return ['status' => 'error', 'message' => 'Missing tool parameter'];
            }

            $toolConfig = $this->config->getTool($toolId);
            if ($toolConfig === null) {
                http_response_code(404);
                return ['status' => 'error', 'message' => 'Tool not found'];
            }

            // 同期確認 (TTLチェック)
            $syncResult = $this->releaseManager->checkAndSync($toolConfig, force: false);
            $manifest = $syncResult['manifest'] ?? $this->releaseManager->getManifest($toolId);

            if ($manifest === null || empty($manifest['releases'])) {
                return [
                    'status' => 'success',
                    'tool_id' => $toolId,
                    'current_version' => $getParams['current'] ?? '',
                    'latest_version' => null,
                    'has_update' => false,
                    'message' => 'No releases available'
                ];
            }

            $latestRelease = $manifest['releases'][0];
            $latestVersion = $latestRelease['version'] ?? '';
            $currentVersion = $getParams['current'] ?? '';
            $hasUpdate = ($currentVersion !== '' && $currentVersion !== $latestVersion);

            $updatePkg = $latestRelease['update_package'] ?? null;
            $fullPkg = $latestRelease['full_package'] ?? null;

            return [
                'status' => 'success',
                'tool_id' => $toolId,
                'tool_name' => $manifest['tool_name'] ?? $toolId,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'has_update' => $hasUpdate,
                'release_date' => $latestRelease['release_date'] ?? '',
                'release_notes' => $latestRelease['release_notes'] ?? '',
                'packages' => [
                    'update' => [
                        'available' => $updatePkg !== null,
                        'filename' => $updatePkg['filename'] ?? null,
                        'url' => $updatePkg !== null ? sprintf('api.php?action=download&tool=%s&version=%s&type=update', urlencode($toolId), urlencode($latestVersion)) : null,
                        'size' => $updatePkg['size'] ?? 0,
                        'sha256' => $updatePkg['sha256'] ?? '',
                        'deleted_files' => $updatePkg['deleted_files'] ?? []
                    ],
                    'full' => [
                        'available' => $fullPkg !== null,
                        'filename' => $fullPkg['filename'] ?? null,
                        'url' => $fullPkg !== null ? sprintf('api.php?action=download&tool=%s&version=%s&type=full', urlencode($toolId), urlencode($latestVersion)) : null,
                        'size' => $fullPkg['size'] ?? 0,
                        'sha256' => $fullPkg['sha256'] ?? ''
                    ]
                ]
            ];
        }

        if ($action === 'sync') {
            $token = $getParams['token'] ?? ($_POST['token'] ?? '');
            $expectedToken = $this->config->getAdminSyncToken();

            if ($expectedToken === '' || $token !== $expectedToken) {
                http_response_code(403);
                return ['status' => 'error', 'message' => 'Forbidden: Invalid or missing admin sync token'];
            }

            $tools = $this->config->getBranches();
            $updatedTools = [];
            $allUpToDate = true;

            $globalWebhooks = $this->config->getWebhooks()['global_webhooks'] ?? [];

            foreach ($tools as $tool) {
                $id = $tool['id'] ?? '';
                if ($toolId !== '' && $id !== $toolId) {
                    continue;
                }

                $res = $this->releaseManager->checkAndSync($tool, force: true);
                if ($res['status'] === 'new_release_created') {
                    $allUpToDate = false;
                    $version = $res['version'] ?? '';
                    $manifest = $res['manifest'] ?? null;
                    $releaseEntry = $manifest['releases'][0] ?? [];

                    // Webhook通知送信
                    $this->notifier->notify($tool, $releaseEntry, $globalWebhooks);

                    $updatedTools[] = [
                        'tool_id' => $id,
                        'version' => $version,
                        'status' => 'new_release_created'
                    ];
                }
            }

            if ($allUpToDate) {
                return [
                    'status' => 'up_to_date',
                    'has_changes' => false,
                    'message' => 'All tools are already up to date. No changes made.',
                    'timestamp' => date('c')
                ];
            }

            return [
                'status' => 'updated',
                'has_changes' => true,
                'updated_count' => count($updatedTools),
                'updated_tools' => $updatedTools,
                'message' => 'New releases successfully synchronized and packages created.',
                'timestamp' => date('c')
            ];
        }

        return ['status' => 'error', 'message' => 'Invalid action'];
    }

    public function handleDownload(array $getParams): void
    {
        $toolId = $getParams['tool'] ?? '';
        $version = $getParams['version'] ?? '';
        $type = $getParams['type'] ?? 'full';

        if ($toolId === '' || $version === '') {
            http_response_code(400);
            echo "Bad Request: Missing parameters";
            return;
        }

        $manifest = $this->releaseManager->getManifest($toolId);
        if ($manifest === null || empty($manifest['releases'])) {
            http_response_code(404);
            echo "Not Found: Tool manifest not found";
            return;
        }

        $targetRelease = null;
        foreach ($manifest['releases'] as $rel) {
            if (isset($rel['version']) && $rel['version'] === $version) {
                $targetRelease = $rel;
                break;
            }
        }

        if ($targetRelease === null) {
            http_response_code(404);
            echo "Not Found: Version {$version} not found";
            return;
        }

        $pkg = ($type === 'update') ? ($targetRelease['update_package'] ?? null) : ($targetRelease['full_package'] ?? null);
        if ($pkg === null || empty($pkg['filename'])) {
            http_response_code(404);
            echo "Not Found: Package type {$type} not available";
            return;
        }

        $filename = $pkg['filename'];
        $filePath = sprintf('%s/releases/%s/%s', $this->storageDir, $toolId, $filename);

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo "Not Found: File on server missing";
            return;
        }

        // ダウンロードログの記録 (ストリーム配信前)
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');
        $clientType = str_contains(strtolower($ua), 'updater') ? 'updater_exe' : 'browser';
        $this->logEngine->record($toolId, $version, $type, $ip, $ua, $clientType);

        // ストリーム出力
        $fileSize = (int)filesize($filePath);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: public, max-age=86400');

        // メモリ節約ストリーム出力 (1MBチャンク)
        $fp = @fopen($filePath, 'rb');
        if ($fp !== false) {
            while (!feof($fp)) {
                echo fread($fp, 1048576);
                flush();
            }
            fclose($fp);
        } else {
            readfile($filePath);
        }
        exit;
    }

    public function handleFeed(array $getParams): string
    {
        $type = $getParams['type'] ?? 'rss';
        $toolId = !empty($getParams['tool']) ? (string)$getParams['tool'] : null;

        $tools = $this->config->getBranches();
        $allManifests = [];
        foreach ($tools as $tool) {
            $id = $tool['id'] ?? '';
            if ($id !== '') {
                $manifest = $this->releaseManager->getManifest($id);
                if ($manifest !== null) {
                    $allManifests[] = $manifest;
                }
            }
        }

        if ($type === 'xml') {
            header('Content-Type: application/xml; charset=utf-8');
            $targetManifest = null;
            if ($toolId !== null) {
                foreach ($allManifests as $m) {
                    if ($m['tool_id'] === $toolId) {
                        $targetManifest = $m;
                        break;
                    }
                }
            }
            return $this->feedGen->generateAppcast($targetManifest ?? ($allManifests[0] ?? ['tool_id' => 'all']), $this->baseUrl);
        }

        header('Content-Type: application/rss+xml; charset=utf-8');
        return $this->feedGen->generateRss($allManifests, $toolId, $this->baseUrl);
    }

    private function renderToolsList(array $tools): string
    {
        $cardsHtml = '';
        $allManifests = [];

        foreach ($tools as $tool) {
            $id = $tool['id'] ?? '';
            // バックグラウンドで同期チェック
            $this->releaseManager->checkAndSync($tool, force: false);
            $manifest = $this->releaseManager->getManifest($id);
            if ($manifest !== null) {
                $allManifests[] = $manifest;
            }

            $totalDl = $this->logEngine->getToolTotalDownloads($id);
            $latestVer = $manifest['latest_version'] ?? '未取得';
            $lastReleaseDate = isset($manifest['releases'][0]['release_date'])
                ? date('Y-m-d', strtotime($manifest['releases'][0]['release_date']))
                : '-';

            $cardsHtml .= $this->renderer->renderComponent('tool_card', [
                'TOOL_ID' => htmlspecialchars($id, ENT_QUOTES, 'UTF-8'),
                'TOOL_NAME' => htmlspecialchars($tool['name'] ?? $id, ENT_QUOTES, 'UTF-8'),
                'TOOL_DESC' => htmlspecialchars($tool['description'] ?? '', ENT_QUOTES, 'UTF-8'),
                'LATEST_VERSION' => htmlspecialchars($latestVer, ENT_QUOTES, 'UTF-8'),
                'TOTAL_DOWNLOADS' => number_format($totalDl),
                'RELEASE_DATE' => $lastReleaseDate
            ]);
        }

        // ランキング生成
        $rankingData = $this->logEngine->getPopularRanking(5);
        $rankingHtml = '';
        foreach ($rankingData as $item) {
            $toolConfig = $this->config->getTool($item['tool_id']);
            $rankingHtml .= $this->renderer->renderComponent('ranking_card', [
                'RANK_NUMBER' => (string)$item['rank'],
                'TOOL_ID' => htmlspecialchars($item['tool_id'], ENT_QUOTES, 'UTF-8'),
                'TOOL_NAME' => htmlspecialchars($toolConfig['name'] ?? $item['tool_id'], ENT_QUOTES, 'UTF-8'),
                'TOTAL_DOWNLOADS' => number_format($item['downloads'])
            ]);
        }

        // 国別統計生成
        $countryStats = $this->logEngine->getCountryStatistics(null, 5);
        $countryHtml = '';
        foreach ($countryStats['countries'] as $c) {
            $countryHtml .= $this->renderer->renderComponent('country_stats', [
                'COUNTRY_NAME' => htmlspecialchars($c['country_name'], ENT_QUOTES, 'UTF-8'),
                'COUNTRY_COUNT' => number_format($c['downloads']),
                'COUNTRY_PERCENT' => (string)$c['percentage']
            ]);
        }

        return $this->renderer->render('pages/tools.md', [
            'PAGE_TITLE' => 'ツール一覧 & ランキング',
            'BASE_URL' => $this->baseUrl,
            'GLOBAL_NAV' => $this->renderer->renderComponent('nav', ['ACTIVE_PAGE' => 'tools', 'BASE_URL' => $this->baseUrl]),
            'TOOL_CARDS' => $cardsHtml,
            'RANKING_LIST' => $rankingHtml !== '' ? $rankingHtml : '<p class="empty-text">ログ集計待ち</p>',
            'COUNTRY_STATS' => $countryHtml !== '' ? $countryHtml : '<p class="empty-text">データなし</p>'
        ]);
    }

    private function renderToolDetail(string $toolId): string
    {
        $tool = $this->config->getTool($toolId);
        if ($tool === null) {
            return $this->renderer->render('pages/tools.md', [
                'PAGE_TITLE' => 'ツールが見つかりません',
                'BASE_URL' => $this->baseUrl,
                'GLOBAL_NAV' => $this->renderer->renderComponent('nav', ['ACTIVE_PAGE' => 'tools', 'BASE_URL' => $this->baseUrl]),
                'TOOL_CARDS' => '<p class="error-text">指定されたツールは登録されていません。</p>',
                'RANKING_LIST' => '',
                'COUNTRY_STATS' => ''
            ]);
        }

        $manifest = $this->releaseManager->getManifest($toolId);
        $releasesHtml = '';
        $totalDl = $this->logEngine->getToolTotalDownloads($toolId);

        if ($manifest !== null && !empty($manifest['releases'])) {
            $latestVer = $manifest['releases'][0]['version'] ?? '';
            
            // 最多ダウンロード数バージョンの特定
            $maxDl = 0;
            $mostDownloadedVer = '';
            foreach ($manifest['releases'] as $rel) {
                $v = $rel['version'] ?? '';
                $dl = $this->logEngine->getVersionDownloads($toolId, $v);
                if ($dl > $maxDl) {
                    $maxDl = $dl;
                    $mostDownloadedVer = $v;
                }
            }

            foreach ($manifest['releases'] as $rel) {
                $ver = $rel['version'] ?? '';
                $verDl = $this->logEngine->getVersionDownloads($toolId, $ver);
                $fullPkg = $rel['full_package'] ?? null;
                $updatePkg = $rel['update_package'] ?? null;

                $fullUrl = $fullPkg !== null ? sprintf('api.php?action=download&tool=%s&version=%s&type=full', urlencode($toolId), urlencode($ver)) : '#';
                $updateUrl = $updatePkg !== null ? sprintf('api.php?action=download&tool=%s&version=%s&type=update', urlencode($toolId), urlencode($ver)) : '';

                // 最新・人気バッジの生成
                $badgesHtml = '';
                if ($ver === $latestVer) {
                    $badgesHtml .= '<span class="badge-tag badge-latest">🔥 最新版</span>';
                }
                if ($mostDownloadedVer !== '' && $ver === $mostDownloadedVer) {
                    $badgesHtml .= '<span class="badge-tag badge-popular">👑 人気No.1</span>';
                }

                // リリースノートのレンダリング & READMEリンク自動変換
                $rawNotes = $rel['release_notes'] ?? '';
                if (trim($rawNotes) === '') {
                    $rawNotes = '*リリースノートは記載されていません。*';
                }
                $notesHtml = $this->renderer->markdownToHtml($rawNotes);
                $notesHtml = $this->convertReadmeLinks($notesHtml, $toolId);

                $releasesHtml .= $this->renderer->renderComponent('release_item', [
                    'VERSION' => htmlspecialchars($ver, ENT_QUOTES, 'UTF-8'),
                    'BADGES' => $badgesHtml,
                    'RELEASE_DATE' => htmlspecialchars(date('Y-m-d H:i', strtotime($rel['release_date'] ?? 'now')), ENT_QUOTES, 'UTF-8'),
                    'RELEASE_NOTES' => $notesHtml,
                    'VERSION_DOWNLOADS' => number_format($verDl),
                    'FULL_SIZE' => isset($fullPkg['size']) ? sprintf('%.1f MB', $fullPkg['size'] / 1048576) : '-',
                    'FULL_SHA256' => htmlspecialchars($fullPkg['sha256'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'FULL_URL' => $fullUrl,
                    'UPDATE_SIZE' => isset($updatePkg['size']) ? sprintf('%.1f KB', $updatePkg['size'] / 1024) : '-',
                    'UPDATE_SHA256' => htmlspecialchars($updatePkg['sha256'] ?? '-', ENT_QUOTES, 'UTF-8'),
                    'UPDATE_URL' => $updateUrl,
                    'HAS_UPDATE_PKG' => $updatePkg !== null ? 'true' : 'false'
                ]);
            }
        } else {
            $releasesHtml = '<p class="empty-text">現在利用可能なリリースパッケージはありません。</p>';
        }

        // 多言語READMEの存在確認 & モーダルコンポーネントの準備
        $readmes = $this->releaseManager->getReadmes($toolId);
        $hasReadme = !empty($readmes);
        $readmeBtnHtml = '';
        $modalHtml = '';

        if ($hasReadme) {
            $readmeBtnHtml = sprintf(
                '<button type="button" class="btn-readme open-readme-btn" data-tool="%s" data-lang="ja">📖 ドキュメント・README</button>',
                htmlspecialchars($toolId, ENT_QUOTES, 'UTF-8')
            );

            // 言語選択肢のオプション生成
            $langOptionsHtml = '';
            foreach ($readmes as $l) {
                $langOptionsHtml .= sprintf(
                    '<option value="%s">%s</option>',
                    htmlspecialchars($l['code'], ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8')
                );
            }

            $modalHtml = $this->renderer->renderComponent('readme_modal', [
                'TOOL_ID' => htmlspecialchars($toolId, ENT_QUOTES, 'UTF-8'),
                'TOOL_NAME' => htmlspecialchars($tool['name'] ?? $toolId, ENT_QUOTES, 'UTF-8'),
                'LANG_OPTIONS' => $langOptionsHtml,
                'BASE_URL' => $this->baseUrl
            ]);
        }

        return $this->renderer->render('pages/tool_detail.md', [
            'PAGE_TITLE' => htmlspecialchars($tool['name'] ?? $toolId, ENT_QUOTES, 'UTF-8') . ' - リリース履歴',
            'BASE_URL' => $this->baseUrl,
            'GLOBAL_NAV' => $this->renderer->renderComponent('nav', ['ACTIVE_PAGE' => 'tools', 'BASE_URL' => $this->baseUrl]),
            'TOOL_ID' => htmlspecialchars($toolId, ENT_QUOTES, 'UTF-8'),
            'TOOL_NAME' => htmlspecialchars($tool['name'] ?? $toolId, ENT_QUOTES, 'UTF-8'),
            'TOOL_DESC' => htmlspecialchars($tool['description'] ?? '', ENT_QUOTES, 'UTF-8'),
            'TOTAL_DOWNLOADS' => number_format($totalDl),
            'RELEASES_LIST' => $releasesHtml,
            'README_BUTTON' => $readmeBtnHtml,
            'README_MODAL' => $modalHtml
        ]);
    }

    private function renderReadmePage(string $toolId, string $lang = 'ja'): string
    {
        $tool = $this->config->getTool($toolId);
        if ($tool === null) {
            return $this->renderer->render('pages/tools.md', [
                'PAGE_TITLE' => 'ツールが見つかりません',
                'BASE_URL' => $this->baseUrl,
                'GLOBAL_NAV' => $this->renderer->renderComponent('nav', ['ACTIVE_PAGE' => 'tools', 'BASE_URL' => $this->baseUrl]),
                'TOOL_CARDS' => '<p class="error-text">指定されたツールは登録されていません。</p>',
                'RANKING_LIST' => '',
                'COUNTRY_STATS' => ''
            ]);
        }

        $readmeData = $this->releaseManager->getReadme($toolId, $lang);
        $contentHtml = $readmeData !== null
            ? $this->renderer->markdownToHtml($readmeData['content_markdown'])
            : '<p class="empty-text">ドキュメント (README) はまだ登録されていません。</p>';

        $readmes = $this->releaseManager->getReadmes($toolId);
        $langLinksHtml = '';
        foreach ($readmes as $l) {
            $isCurrent = ($readmeData !== null && $readmeData['current_lang'] === $l['code']);
            $activeClass = $isCurrent ? ' class="lang-tab active"' : ' class="lang-tab"';
            $langLinksHtml .= sprintf(
                '<a href="?page=readme&tool=%s&lang=%s"%s>%s</a>',
                urlencode($toolId),
                urlencode($l['code']),
                $activeClass,
                htmlspecialchars($l['name'], ENT_QUOTES, 'UTF-8')
            );
        }

        return $this->renderer->render('pages/readme.md', [
            'PAGE_TITLE' => htmlspecialchars($tool['name'] ?? $toolId, ENT_QUOTES, 'UTF-8') . ' - ドキュメント (README)',
            'BASE_URL' => $this->baseUrl,
            'GLOBAL_NAV' => $this->renderer->renderComponent('nav', ['ACTIVE_PAGE' => 'tools', 'BASE_URL' => $this->baseUrl]),
            'TOOL_ID' => htmlspecialchars($toolId, ENT_QUOTES, 'UTF-8'),
            'TOOL_NAME' => htmlspecialchars($tool['name'] ?? $toolId, ENT_QUOTES, 'UTF-8'),
            'LANG_TABS' => $langLinksHtml,
            'README_CONTENT' => $contentHtml
        ]);
    }

    private function convertReadmeLinks(string $html, string $toolId): string
    {
        // 既に <a> タグ内のものは二重置換しない
        return preg_replace_callback(
            '/(?<!href=")(?<!">)(README(?:\.([a-zA-Z_\-]+))?\.md|README\b)/u',
            function ($m) use ($toolId) {
                $matched = $m[0];
                $lang = !empty($m[2]) ? strtolower($m[2]) : 'ja';
                return sprintf(
                    '<a href="javascript:void(0)" class="open-readme-link" data-tool="%s" data-lang="%s" title="%s のドキュメントを開く">📖 %s</a>',
                    htmlspecialchars($toolId, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($matched, ENT_QUOTES, 'UTF-8'),
                    htmlspecialchars($matched, ENT_QUOTES, 'UTF-8')
                );
            },
            $html
        );
    }

    private function renderRecent(array $tools): string
    {
        $recentReleases = [];
        foreach ($tools as $tool) {
            $id = $tool['id'] ?? '';
            $manifest = $this->releaseManager->getManifest($id);
            if ($manifest !== null && !empty($manifest['releases'])) {
                foreach ($manifest['releases'] as $rel) {
                    $recentReleases[] = [
                        'tool_id' => $id,
                        'tool_name' => $tool['name'] ?? $id,
                        'version' => $rel['version'] ?? '',
                        'release_date' => $rel['release_date'] ?? date('c'),
                        'release_notes' => $rel['release_notes'] ?? '',
                        'timestamp' => strtotime($rel['release_date'] ?? 'now')
                    ];
                }
            }
        }

        usort($recentReleases, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        $listHtml = '';
        foreach (array_slice($recentReleases, 0, 15) as $item) {
            $notesHtml = $this->renderer->markdownToHtml($item['release_notes']);
            $listHtml .= sprintf(
                '<div class="recent-item">
                    <div class="recent-header">
                        <a href="?page=tool&id=%s" class="tool-title">%s</a>
                        <span class="version-tag">%s</span>
                        <span class="date">%s</span>
                    </div>
                    <div class="notes-content">%s</div>
                </div>',
                urlencode($item['tool_id']),
                htmlspecialchars($item['tool_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($item['version'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars(date('Y-m-d H:i', $item['timestamp']), ENT_QUOTES, 'UTF-8'),
                $notesHtml
            );
        }

        return $this->renderer->render('pages/recent.md', [
            'PAGE_TITLE' => '最近のリリース',
            'BASE_URL' => $this->baseUrl,
            'GLOBAL_NAV' => $this->renderer->renderComponent('nav', ['ACTIVE_PAGE' => 'recent', 'BASE_URL' => $this->baseUrl]),
            'RECENT_LIST' => $listHtml !== '' ? $listHtml : '<p class="empty-text">最近のリリースはありません。</p>'
        ]);
    }

    private function renderReleases(array $tools): string
    {
        $allReleases = [];
        foreach ($tools as $tool) {
            $id = $tool['id'] ?? '';
            $manifest = $this->releaseManager->getManifest($id);
            if ($manifest !== null && !empty($manifest['releases'])) {
                foreach ($manifest['releases'] as $rel) {
                    $allReleases[] = [
                        'tool_id' => $id,
                        'tool_name' => $tool['name'] ?? $id,
                        'version' => $rel['version'] ?? '',
                        'release_date' => $rel['release_date'] ?? date('c'),
                        'release_notes' => $rel['release_notes'] ?? '',
                        'timestamp' => strtotime($rel['release_date'] ?? 'now')
                    ];
                }
            }
        }

        usort($allReleases, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        $timelineHtml = '<ul class="timeline">';
        foreach ($allReleases as $item) {
            $notesHtml = $this->renderer->markdownToHtml($item['release_notes']);
            $timelineHtml .= sprintf(
                '<li>
                    <span class="timeline-date">%s</span>
                    <div class="timeline-content">
                        <h4><a href="?page=tool&id=%s">%s</a> <span class="badge">%s</span></h4>
                        <div class="notes-content">%s</div>
                    </div>
                </li>',
                htmlspecialchars(date('Y-m-d', $item['timestamp']), ENT_QUOTES, 'UTF-8'),
                urlencode($item['tool_id']),
                htmlspecialchars($item['tool_name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($item['version'], ENT_QUOTES, 'UTF-8'),
                $notesHtml
            );
        }
        $timelineHtml .= '</ul>';

        return $this->renderer->render('pages/releases.md', [
            'PAGE_TITLE' => '全ツール リリース年表',
            'BASE_URL' => $this->baseUrl,
            'GLOBAL_NAV' => $this->renderer->renderComponent('nav', ['ACTIVE_PAGE' => 'releases', 'BASE_URL' => $this->baseUrl]),
            'RELEASES_TIMELINE' => count($allReleases) > 0 ? $timelineHtml : '<p class="empty-text">リリース履歴はありません。</p>'
        ]);
    }
}
