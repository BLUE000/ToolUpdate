# ソフトウェア更新・フルパッケージ管理システム（ReleaseHub） 詳細設計書

- **文書バージョン**: 1.0.0
- **作成日**: 2026-08-20
- **ステータス**: 詳細設計完了 / ユーザーレビュー待ち
- **対応基本設計書**: [basic_design.md](file:///d:/prog/PHP/ReleaseHub/doc/basic_design.md)
- **対応単体テスト仕様書**: [unit_test_specification.md](file:///d:/prog/PHP/ReleaseHub/doc/unit_test_specification.md)

---

## 1. 共通アーキテクチャ・コーディング規約

- **PHPバージョン**: PHP 8.1.0 以上（厳格な型付け `declare(strict_types=1);` を全ファイル先頭に明記）
- **名前空間ルート**: `ReleaseHub\`（PSR-4に完全準拠、ディレクトリ `server/src/`）
- **エラー・例外ハンドリング**:
  - 設定エラー、ファイルIOエラー、Git通信エラーは専用の例外クラス（または標準 `\RuntimeException`, `\InvalidArgumentException`）をスロー
  - Web・API・ログ書き込みでは上位で安全に捕捉し、ユーザー体験を損なわないフェイルセーフ動作を保証

---

## 2. コアクラス詳細設計

### 2.1 `ReleaseHub\Config\ConfigLoader`
設定ファイル（`branches.json`, `webhooks.json`, `geoip.json`）の読み込み・キャッシュ・バリデーションを担当。

```php
namespace ReleaseHub\Config;

class ConfigLoader
{
    private string $configDir;
    private ?array $branchesCache = null;
    private ?array $webhooksCache = null;
    private ?array $geoipCache = null;

    public function __construct(string $configDir)
    public function getBranches(): array
    public function getTool(string $toolId): ?array
    public function getWebhooks(): array
    public function getGeoIpData(): array
    public function validateToolConfig(array $tool): bool
}
```

#### メソッド詳細
- `__construct(string $configDir)`
  - `$this->configDir = rtrim($configDir, '/\\');`
- `getBranches(): array`
  - `branches.json` を読み込み、`json_decode($json, true)` でパース。`tools` 配列を返却。ファイル不在またはJSON構文不正時は空配列 `[]` を返却（フェイルセーフ）。
- `getTool(string $toolId): ?array`
  - `getBranches()` の中から `id === $toolId` のツール設定を抽出して返却。存在しない場合は `null`。
- `validateToolConfig(array $tool): bool`
  - 必須キー（`id`, `name`, `repository`, `branch`）が存在し、空文字でないことを確認。

---

### 2.2 `ReleaseHub\Log\GeoIPResolver`
外部APIに依存せず、ローカル設定（`geoip.json`）からIPアドレスに基づき国コード・国名を判定。

```php
namespace ReleaseHub\Log;

class GeoIPResolver
{
    private array $geoData;

    public function __construct(array $geoData)
    public function resolve(string $ip): array
    private function ipToLong(string $ip): ?int
}
```

#### メソッド詳細
- `resolve(string $ip): array`
  - 戻り値: `['country_code' => 'JP', 'country_name' => 'Japan']`
  - 処理手順:
    1. IPv4判定。不正なIP形式の場合は `['country_code' => 'UNKNOWN', 'country_name' => 'Unknown']` を返却。
    2. `ip2long($ip)` で数値化（符号なし整数相当で比較）。
    3. `local` レンジ（127.0.0.1, 10.x, 192.168.x 等）に該当すれば `LOCAL / Localhost` を返却。
    4. `ranges` リストを走査し、`start <= $targetIp && $targetIp <= end` を満たすエントリを検索。
    5. 一致すればその国情報を返却。見つからなければ `['country_code' => 'OTHER', 'country_name' => 'International/Other']` を返却。

---

### 2.3 `ReleaseHub\Log\LogEngine`
ダウンロードログの日別記録（`download_logs_YYYY-MM-DD.jsonl`）およびランキング・統計の集計。

```php
namespace ReleaseHub\Log;

class LogEngine
{
    private string $logDir;
    private GeoIPResolver $geoResolver;

    public function __construct(string $logDir, GeoIPResolver $geoResolver)
    public function record(string $toolId, string $version, string $packageType, string $ip, string $userAgent, string $clientType = 'browser'): bool
    public function getToolTotalDownloads(string $toolId): int
    public function getVersionDownloads(string $toolId, string $version): int
    public function getPopularRanking(int $limit = 10, ?string $period = 'all'): array
    public function getCountryStatistics(?string $toolId = null): array
    public function getRecentDownloads(int $limit = 20): array
}
```

#### メソッド詳細
- `record(...)`:
  - 逆引きホスト名取得: `gethostbyaddr($ip)`（取得失敗またはタイムアウト時は `$ip` そのものをセット）。
  - 国情報取得: `$this->geoResolver->resolve($ip)`.
  - JSON行生成:
    `{"timestamp":"...", "tool_id":"...", "version":"...", "package_type":"...", "ip_address":"...", "host_name":"...", "country_code":"...", "country_name":"...", "user_agent":"...", "client_type":"..."}\n`
  - ファイル保存: `download_logs_YYYY-MM-DD.jsonl` へ `FILE_APPEND | LOCK_EX` で追記。書き込み失敗時も例外を投げずに `false` を返却（フェイルセーフ）。
- `getPopularRanking(...)`:
  - ログディレクトリ内の全 `.jsonl`（または指定期間）を読み込み、ツールごとのダウンロード数をカウントして降順ソート。

---

### 2.4 `ReleaseHub\Git\GitHubClient`
GitHub REST API（Releases, Tags）の通信、レートリミット監視およびアセット取得。

```php
namespace ReleaseHub\Git;

class GitHubClient
{
    private ?string $token;
    private int $rateLimitRemaining = 60;
    private int $rateLimitResetTime = 0;

    public function __construct(?string $token = null)
    public function getLatestRelease(string $repoUrl): ?array
    public function getReleases(string $repoUrl): array
    public function downloadAsset(string $assetUrl, string $savePath): bool
    public function getReadmeFiles(string $repoUrl): array
    public function isRateLimited(): bool
    public function getRateLimitResetTime(): int
    private function parseRepoPath(string $repoUrl): ?string
    private function request(string $endpoint): ?array
}
```

#### メソッド詳細
- `parseRepoPath(string $repoUrl): ?string`
  - `https://github.com/BLUE000/TrustChain.git` ➔ `BLUE000/TrustChain` を正規表現で抽出。
- `request(string $endpoint): ?array`
  - cURL を使用して `https://api.github.com/repos/{owner}/{repo}/...` をコール。
  - User-Agent ヘッダ（`ReleaseHub-Engine/1.0`）を付与。
  - レスポンスヘッダから `X-RateLimit-Remaining` と `X-RateLimit-Reset` を取得・更新。
  - HTTP 403 (Rate Limit) 時はログを記録し `null` を返却。
- `getReadmeFiles(string $repoUrl): array`
  - リポジトリのルートファイル一覧を取得（または主要な多言語README候補 `README.md`, `README.ja.md`, `README.en.md`, `README.de.md`, `README.fr.md`, `README.pt.md`, `README.ru.md` をraw取得）。
  - 存在する各READMEファイルについて、`['code' => 'ja', 'name' => '🇯🇵 日本語', 'filename' => 'README.ja.md', 'content' => '...']` の配列を生成して返却。
- `downloadAsset(string $assetUrl, string $savePath): bool`
  - アタッチされたZIPファイルをストリームダウンロードして指定パスに保存。

---

### 2.5 `ReleaseHub\Package\ZipPackager`
ZIPの解凍、差分ファイルの抽出、差分ZIPの圧縮生成、SHA-256計算を担当。

```php
namespace ReleaseHub\Package;

class ZipPackager
{
    public function calculateSha256(string $filePath): string
    public function extractZip(string $zipPath, string $extractTo): bool
    public function createDiffZip(string $prevExtractDir, string $newExtractDir, string $outputZipPath, array $excludeList = []): array
    public function createFullZip(string $sourceDir, string $outputZipPath, array $excludeList = []): bool
    private function getFileList(string $dir): array
}
```

#### メソッド詳細
- `calculateSha256(string $filePath): string`
  - `hash_file('sha256', $filePath)` を実行。
- `createDiffZip(...)`:
  - 前回ディレクトリ `$prevExtractDir` と今回ディレクトリ `$newExtractDir` の全ファイルを比較。
  - 追加ファイル・更新（MD5/ハッシュ差分）ファイルを抽出し、`ZipArchive` で `$outputZipPath` にアーカイブ化。
  - 前回存在して今回削除されたファイルの一覧（`deleted_files`）を配列で返却。

---

### 2.6 `ReleaseHub\Package\ReleaseManager`
リリース同期のライフサイクル統括、TTLキャッシュ判定、排他制御（`flock`）、多言語READMEのキャッシュ、manifest.jsonの生成・更新。

```php
namespace ReleaseHub\Package;

use ReleaseHub\Git\GitHubClient;

class ReleaseManager
{
    private string $storageDir;
    private GitHubClient $gitClient;
    private ZipPackager $packager;
    private int $ttlSeconds = 900; // 15分

    public function __construct(string $storageDir, GitHubClient $gitClient, ZipPackager $packager)
    public function getManifest(string $toolId): ?array
    public function checkAndSync(array $toolConfig, bool $force = false): array
    public function saveManifest(string $toolId, array $manifest): bool
    public function syncReadmes(array $toolConfig): array
    public function getReadmes(string $toolId): array
    public function getReadme(string $toolId, string $lang = 'ja'): ?array
    private function acquireLock(): mixed
    private function releaseLock(mixed $lockHandle): void
}
```

#### メソッド詳細
- `checkAndSync(array $toolConfig, bool $force = false): array`
  - 1. 保存済み `manifest.json` を確認。
  - 2. `$force === false` かつ `last_synced_at + $this->ttlSeconds > time()` であれば即座に既存 manifest を返却。
  - 3. `acquireLock()` で `storage/locks/sync.lock` の排他ロック取得（取得できない場合は他プロセスが同期中のため既存 manifest を返却）。
  - 4. GitHub API で最新Releaseおよび多言語READMEを取得（`syncReadmes` 実行）。
  - 5. リリースノートが空の場合はデフォルトメッセージ（`*リリースノートは記載されていません。*`）を設定。
  - 6. 新規バージョンが検出された場合：
     - アタッチされたZIPをダウンロード。
     - 前回リリースと比較して差分ZIPを作成、フルZIPを作成。
     - `manifest.json` をアトミック更新（一時ファイル作成 ➔ `rename`）。
  - 7. `releaseLock()` でロック解放。
- `syncReadmes(array $toolConfig): array`
  - GitHubから多言語READMEを取得し、`storage/readmes/{tool_id}/` に個別ファイル（`README.ja.md` 等）および目録（`readmes.json`）を保存。
- `getReadme(string $toolId, string $lang = 'ja'): ?array`
  - 指定された言語（またはデフォルト）のREADMEテキスト・HTML・利用可能言語一覧を返却。
       - アタッチZIPがあればダウンロード、なければソースから全体ZIP生成。
       - 前回バージョンがあれば `ZipPackager::createDiffZip` で差分ZIP生成。
       - SHA256ハッシュを計算し、`manifest.json` を更新。
  - 6. `releaseLock()` でロック解除して最新 manifest を返却。

---

### 2.7 `ReleaseHub\Notifier\WebhookNotifier` & `FeedGenerator`

- `WebhookNotifier::notify(array $toolConfig, array $releaseInfo): bool`
  - Discord / Slack 形式のJSONペイロードを作成し、タイムアウト3秒の非同期cURLで送信。
- `FeedGenerator::generateRss(array $allManifests, ?string $toolId = null): string`
  - RSS 2.0 準拠の XML 文字列を生成。
- `FeedGenerator::generateAppcast(array $manifest): string`
  - Sparkle / WinSparkle 互換の Appcast XML 文字列を生成。

---

### 2.8 `ReleaseHub\Template\MarkdownRenderer`
Markdownテンプレートの読み込み、プレースホルダー置換、HTMLへの安全な変換。

```php
namespace ReleaseHub\Template;

class MarkdownRenderer
{
    private string $templateDir;

    public function __construct(string $templateDir)
    public function render(string $pageTemplate, array $params = [], ?string $layout = 'layout.md'): string
    public function renderComponent(string $componentName, array $params = []): string
    public function markdownToHtml(string $markdown): string
}
```

#### メソッド詳細
- `markdownToHtml(string $markdown): string`:
  - 見出し（`#`, `##`, `###`）、リスト（`-`, `*`）、太字（`**`）、リンク（`[text](url)`）、コードブロック（` ``` `）、テーブル構文を安全なHTMLタグへ変換。
  - XSS防止のため、プレースホルダーに渡された値は `htmlspecialchars($str, ENT_QUOTES, 'UTF-8')` を適用。

---

### 2.9 `ReleaseHub\App`
アプリケーション本体。リクエストパラメータ（`$_GET['page']`, `$_GET['action']` 等）を解析し、適切なコントローラー処理へディスパッチ。

```php
namespace ReleaseHub;

use ReleaseHub\Config\ConfigLoader;
use ReleaseHub\Log\LogEngine;
use ReleaseHub\Package\ReleaseManager;
use ReleaseHub\Template\MarkdownRenderer;

class App
{
    private ConfigLoader $config;
    private LogEngine $logEngine;
    private ReleaseManager $releaseManager;
    private MarkdownRenderer $renderer;
    private string $publicDir;

    public function __construct(string $baseDir, ?string $storageDir = null, ?string $configDir = null)
    public function handleWeb(array $getParams): string
    public function handleApi(array $getParams): array
    public function handleDownload(array $getParams): void
    public function handleFeed(array $getParams): string
    private function renderToolsList(array $tools): string
    private function renderToolDetail(string $toolId): string
    private function renderRecent(array $tools): string
    private function renderReleases(array $tools): string
    private function renderReadmePage(string $toolId, string $lang = 'ja'): string
}
```

#### メソッド詳細
- `renderToolsList(array $tools): string`:
  - 各ツールのカードHTMLを生成。
  - 登録されているツールの総数（`count($tools)`）を集計。
  - `pages/tools.md` へ `{TOOL_CARDS}`, `{TOOLS_COUNT}`, `{RANKING_LIST}`, `{COUNTRY_STATS}` を渡してレンダリング。タイトル・サブタイトルには一般利用者向けの分かりやすい見出し（「登録ツール一覧」および「現在 〇〇 件のツールが公開されています」）を反映。
