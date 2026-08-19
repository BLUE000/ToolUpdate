# ソフトウェア更新・フルパッケージ管理システム（ReleaseHub） 結合テスト仕様書

- **文書バージョン**: 1.0.0
- **作成日**: 2026-08-20
- **ステータス**: 結合テスト仕様書完了 / ユーザーレビュー待ち
- **対応設計書**: [basic_design.md](file:///d:/prog/PHP/ReleaseHub/doc/basic_design.md) (基本設計書)

---

## 1. 結合テスト方針

- **対象スコープ**: 複数モジュールの連携、各Web/API/Feedエンドポイント、データ連携・画面描画結合。
- **実行環境**: `tests/run.php` によるCLI一括実行および擬似HTTPリクエスト結合テスト。
- **合格基準**: 全結合テストケースが期待通りのレスポンス・HTML・バイナリ・ログを生成すること。

---

## 2. 結合テストケース一覧

### 2.1 APIルート結合テスト

| No | テスト項目 | 実行リクエスト / 条件 | 検証対象 | 期待結果 (合格基準) |
| :-: | :--- | :--- | :--- | :--- |
| IT-01 | バージョン確認 API (更新あり) | `GET api.php?action=check&tool=TrustChain&current=v2.0.0` | `ReleaseHub\App::handleApi` | JSON `status === 'success'`, `has_update === true`, `latest_version === 'v2.1.0'`, 各DL用URL・SHA256が含まれる |
| IT-02 | バージョン確認 API (最新状態) | `GET api.php?action=check&tool=TrustChain&current=v2.1.0` | `ReleaseHub\App::handleApi` | JSON `status === 'success'`, `has_update === false` が返る |
| IT-03 | バージョン確認 API (不正なツールID) | `GET api.php?action=check&tool=NotExistTool&current=v1.0.0` | `ReleaseHub\App::handleApi` | HTTP 404相当のJSONエラー `{"status":"error", "message":"Tool not found"}` が返る |
| IT-04 | パッケージDL API (差分ZIP) | `GET api.php?action=download&tool=TrustChain&version=v2.1.0&type=update` | `App::handleDownload` & `LogEngine` | 正しいZIPバイナリがストリーム出力され、当日の `download_logs_YYYY-MM-DD.jsonl` にDLログが追記される |
| IT-05 | パッケージDL API (フルZIP) | `GET api.php?action=download&tool=TrustChain&version=v2.1.0&type=full` | `App::handleDownload` & `LogEngine` | フルZIPバイナリが出力され、DLログに `package_type: "full"` が記録される |
| IT-06 | パッケージDL API (ファイル不在) | `GET api.php?action=download&tool=TrustChain&version=v9.9.9&type=full` | `App::handleDownload` | 例外にならず 404 Not Found レスポンスが返る |

---

### 2.2 Webポータル画面結合テスト

| No | テスト項目 | 実行リクエスト / 条件 | 検証対象 | 期待結果 (合格基準) |
| :-: | :--- | :--- | :--- | :--- |
| IT-07 | ポータルTOP/一覧画面ルート | `GET index.php` または `?page=tools` | `App::handleWeb` & `MarkdownRenderer` | ツール一覧カード、人気ランキングTOP10、国別統計、検索窓を含むHTMLが正常描画される |
| IT-08 | ツール詳細・リリース履歴ルート | `GET index.php?page=tool&id=TrustChain` | `App::handleWeb` & `LogEngine` | ツール詳細ヘッダー、累計DL数、バージョン別履歴、DLボタン、SHA256ハッシュが表示される |
| IT-09 | 最近のリリース画面ルート | `GET index.php?page=recent` | `App::handleWeb` | 直近リリースツールの時系列リストとリリースノート要約が表示される |
| IT-10 | 全体リリース年表ルート | `GET index.php?page=releases` | `App::handleWeb` | 全ツールの全バージョンが時系列タイムラインとして描画される |
| IT-11 | 不明なページパラメータ | `GET index.php?page=invalid_page` | `App::handleWeb` | デフォルトの一覧画面（`tools`）へ安全にフォールバック表示される |

---

### 2.3 フィード配信結合テスト

| No | テスト項目 | 実行リクエスト / 条件 | 検証対象 | 期待結果 (合格基準) |
| :-: | :--- | :--- | :--- | :--- |
| IT-12 | 全体RSS 2.0フィード | `GET feed.php?type=rss` | `FeedGenerator` | 有効なXML構文で全ツールの最新リリース履歴RSS 2.0が出力される |
| IT-13 | ツール個別RSSフィード | `GET feed.php?type=rss&tool=TrustChain` | `FeedGenerator` | `TrustChain` の更新履歴のみを含むRSS 2.0が出力される |
| IT-14 | WinSparkle Appcast XML | `GET feed.php?type=xml&tool=TrustChain` | `FeedGenerator` | Sparkle/WinSparkle互換のAppcast XMLタグ（`<enclosure>`, `<sparkle:releaseNotesLink>` 等）が出力される |

---

### 2.4 リリース同期・耐障害性結合テスト

| No | テスト項目 | 実行条件 | 検証対象 | 期待結果 (合格基準) |
| :-: | :--- | :--- | :--- | :--- |
| IT-15 | リリース自動同期 & 差分生成結合 | 新規Release（v2.1.0）が存在する状態 | `ReleaseManager` & `ZipPackager` | アタッチZIPが取得され、差分ZIPが自動生成され、`manifest.json` が最新化される |
| IT-16 | GitHub APIレートリミット耐性 | GitHub APIがHTTP 403を返す状態 | `GitHubClient` & `ReleaseManager` | 既存の `manifest.json` を維持し、Web画面・APIが停止せず正常稼働を継続する |
| IT-17 | 同時アクセス排他制御結合 | 2つのプロセスが同時に同期を実行 | `ReleaseManager` (flock) | 一方がロックを取得して同期を完了し、もう一方は待機または既存キャッシュを返して安全に終了する |
