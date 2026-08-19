# ソフトウェア更新・フルパッケージ管理システム（ReleaseHub） 単体テスト仕様書

- **文書バージョン**: 1.0.0
- **作成日**: 2026-08-20
- **ステータス**: 単体テスト仕様書完了 / ユーザーレビュー待ち
- **対応設計書**: [detailed_design.md](file:///d:/prog/PHP/ReleaseHub/doc/detailed_design.md) (詳細設計書)

---

## 1. 単体テスト方針

- **対象スコープ**: `ReleaseHub\` 名前空間配下の全個別クラス・メソッド。
- **実行環境**: CLI環境 (`tests/run.php` / PHPUnit)。本番データ非依存の隔離テスト。
- **合格基準**: 全単体テストケースが例外・エラーなく PASS すること。

---

## 2. 単体テストケース一覧

### 2.1 `ConfigLoaderTest` (`ReleaseHub\Config\ConfigLoader`)

| No | テスト項目 | 入力・事前条件 | 実行処理 | 期待結果 (検証内容) |
| :-: | :--- | :--- | :--- | :--- |
| UT-01-01 | 正常系: 設定ファイル読み込み | 正常な `branches.json` を配置 | `getBranches()` | 登録されているツール配列が正しく返る |
| UT-01-02 | 正常系: ツール個別取得 | 存在するツールID `TrustChain` | `getTool('TrustChain')` | 該当ツールの連想配列が返る |
| UT-01-03 | 異常系: 存在しないツール取得 | 存在しないツールID `DummyTool` | `getTool('DummyTool')` | `null` が返る |
| UT-01-04 | 異常系: 不正なJSONファイル | JSON構文エラーのファイルを配置 | `getBranches()` | 例外を出さずに空配列 `[]` が返る (フェイルセーフ) |
| UT-01-05 | 正常系: ツール設定バリデーション | 必須項目が揃ったツール設定 | `validateToolConfig($tool)` | `true` が返る |
| UT-01-06 | 異常系: 必須項目欠落バリデーション | `repository` が欠落した配列 | `validateToolConfig($tool)` | `false` が返る |

---

### 2.2 `GeoIPResolverTest` (`ReleaseHub\Log\GeoIPResolver`)

| No | テスト項目 | 入力・事前条件 | 実行処理 | 期待結果 (検証内容) |
| :-: | :--- | :--- | :--- | :--- |
| UT-02-01 | 正常系: 国内IPの国名判定 | 日本国内IP `123.45.67.89` | `resolve('123.45.67.89')` | `country_code === 'JP'`, `country_name === 'Japan'` |
| UT-02-02 | 正常系: 海外IPの国名判定 | 米国IP `8.8.8.8` | `resolve('8.8.8.8')` | `country_code === 'US'`, `country_name === 'United States'` |
| UT-02-03 | 正常系: ローカルIP判定 | `127.0.0.1`, `192.168.1.1` | `resolve('127.0.0.1')` | `country_code === 'LOCAL'`, `country_name === 'Localhost'` |
| UT-02-04 | 異常系: 範囲外IP判定 | 定義テーブルにないIP `99.99.99.99` | `resolve('99.99.99.99')` | `country_code === 'OTHER'` が返る |
| UT-02-05 | 異常系: 不正な文字列 | 不正なIP形式 `invalid.ip` | `resolve('invalid.ip')` | `country_code === 'UNKNOWN'` が返る |

---

### 2.3 `LogEngineTest` (`ReleaseHub\Log\LogEngine`)

| No | テスト項目 | 入力・事前条件 | 実行処理 | 期待結果 (検証内容) |
| :-: | :--- | :--- | :--- | :--- |
| UT-03-01 | 正常系: 日別ログ書き込み | ツール `TrustChain`, Ver `v2.1.0` | `record(...)` | 当日の `download_logs_YYYY-MM-DD.jsonl` にJSONが追記される |
| UT-03-02 | 正常系: ツール累計DL数集計 | 複数件のログが存在する状態 | `getToolTotalDownloads('TrustChain')` | 正確な累計カウント数値が返る |
| UT-03-03 | 正常系: バージョン別DL数集計 | 複数バージョンのログが存在 | `getVersionDownloads('TrustChain', 'v2.1.0')` | 該当バージョンのカウントが返る |
| UT-03-04 | 正常系: 人気ランキング集計 | 複数ツールのログが存在 | `getPopularRanking(5)` | DL数降順でソートされた配列が返る |
| UT-03-05 | 正常系: 国別統計集計 | 国内外のログが存在 | `getCountryStatistics()` | 国別の割合・件数配列が返る |
| UT-03-06 | 異常系: ログ書込失敗時フェイルセーフ | 書き込み不能ディレクトリを指定 | `record(...)` | 例外でクラッシュせず `false` を返却する |

---

### 2.4 `ZipPackagerTest` (`ReleaseHub\Package\ZipPackager`)

| No | テスト項目 | 入力・事前条件 | 実行処理 | 期待結果 (検証内容) |
| :-: | :--- | :--- | :--- | :--- |
| UT-04-01 | 正常系: SHA-256ハッシュ計算 | テスト用ダミーZIPファイル | `calculateSha256($path)` | 正確な64桁の16進数ハッシュ文字列が返る |
| UT-04-02 | 正常系: 全体ZIP圧縮 | テスト用ソースディレクトリ | `createFullZip($src, $out)` | 有効なZIPファイルが生成される |
| UT-04-03 | 正常系: 差分ZIP生成 | v1.0(ファイルA, B) と v1.1(ファイルA変更, C追加) | `createDiffZip($v1, $v1_1, $out)` | 差分ZIP内に変更A・追加Cのみが含まれる |
| UT-04-04 | 正常系: 削除ファイルリスト抽出 | v1.0(ファイルA, B) と v1.1(ファイルAのみ, B削除) | `createDiffZip($v1, $v1_1, $out)` | 戻り値 `deleted_files` に `['B']` が含まれる |

---

### 2.5 `MarkdownRendererTest` (`ReleaseHub\Template\MarkdownRenderer`)

| No | テスト項目 | 入力・事前条件 | 実行処理 | 期待結果 (検証内容) |
| :-: | :--- | :--- | :--- | :--- |
| UT-05-01 | 正常系: Markdown構文変換 | `# タイトル`, `**太字**`, `[リンク](url)` | `markdownToHtml($md)` | `<h1>`, `<strong>`, `<a href="...">` に正しく変換される |
| UT-05-02 | 正常系: プレースホルダー置換 | テンプレート `{TOOL_NAME}`, パラメータ `TrustChain` | `renderComponent(...)` | プレースホルダーが `TrustChain` に置換される |
| UT-05-03 | 正常系: XSSエスケープ検証 | パラメータ `<script>alert(1)</script>` | `renderComponent(...)` | `&lt;script&gt;` に無害化されて出力される |
| UT-05-04 | 正常系: レイアウト結合 | `layout.md` と `pages/tools.md` | `render('pages/tools.md', ...)` | ヘッダー・フッターを含む完全なHTMLが生成される |
| UT-05-05 | 正常系: リスト＆インデント変換 | `- **項目**`, `  - サブ`, `• バレット` | `markdownToHtml($md)` | ネストされたリストおよび太字がHTMLとして正しく変換される |

---

### 2.6 `GitHubClientTest` (`ReleaseHub\Git\GitHubClient`)

| No | テスト項目 | 入力・事前条件 | 実行処理 | 期待結果 (検証内容) |
| :-: | :--- | :--- | :--- | :--- |
| UT-06-01 | 正常系: リポジトリURL解析 | `https://github.com/BLUE000/TrustChain.git` | `parseRepoPath(...)` | `BLUE000/TrustChain` が抽出される |
| UT-06-02 | 正常系: レートリミット状態判定 | `rateLimitRemaining = 0` | `isRateLimited()` | `true` が返る |
| UT-06-03 | 正常系: レートリミット通常時判定 | `rateLimitRemaining = 50` | `isRateLimited()` | `false` が返る |
| UT-06-04 | 正常系: 多言語README取得 | 多言語READMEが存在するリポジトリURL | `getReadmeFiles($repoUrl)` | `README.md`, `README.ja.md` 等の言語コード付き配列が返る |

---

### 2.7 `ReleaseManagerTest` (`ReleaseHub\Package\ReleaseManager`)

| No | テスト項目 | 入力・事前条件 | 実行処理 | 期待結果 (検証内容) |
| :-: | :--- | :--- | :--- | :--- |
| UT-07-01 | 正常系: manifest保存・取得 | 正常なmanifestデータ | `saveManifest()`, `getManifest()` | 保存した通りのデータがJSONから復元される |
| UT-07-02 | 正常系: TTLキャッシュ判定 | 直近(1分前)に同期済みのデータ | `checkAndSync($tool, force: false)` | GitHub APIを呼ばずに既存manifestを返却する |
| UT-07-03 | 正常系: 排他ロック制御 | ロックファイルが存在しない状態 | `acquireLock()` / `releaseLock()` | ロックが正常に取得・解放される |
| UT-07-04 | 正常系: 多言語READMEキャッシュ・取得 | `README.ja.md`, `README.md` を保存 | `getReadme('TwitchFollowerList', 'ja')` | 日本語READMEテキスト・HTMLおよび言語一覧が返る |
| UT-07-05 | 正常系: リリースノート空時デフォルト補完 | `body` が空のリリース | `checkAndSync($tool, force: true)` | `*リリースノートは記載されていません。*` が設定される |
