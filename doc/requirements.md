# ソフトウェア更新・フルパッケージ管理システム（ReleaseHub） 要件定義書

## 1. プロジェクト概要
- **システム名**: ReleaseHub（PCツール向け更新・フルパッケージ管理システム）
- **本システム Git リポジトリ**: `https://github.com/BLUE000/ReleaseHub.git`
- **本システム デフォルトブランチ**: `master`

### 1.1 目的・背景
PC上で動作する複数のクライアントツール（デスクトップアプリ/CLIツール等）において、バージョンアップ時の**「手動でZIPファイルをダウンロードして手作業で解凍・配置する」という手間を解消**することを目的とします。

本システム（ReleaseHub）は、各ツールのGitリポジトリ（GitHub Releases / タグ）を監視・管理し、リリース時に自動でアップデート用差分ZIPおよびフルインストールZIPを生成・保管します。また、PCツール側の「アップデート用EXE」と連携した自動最新化、Web画面からの手動ダウンロード、ダウンロード履歴の詳細ログ解析（国・ホスト名特定）、およびTOPページでのダウンロードランキング表示を提供します。

---

## 2. システム全体像 & アーキテクチャ

```mermaid
flowchart TD
    subgraph GitEnv [開発・Git環境]
        Repos[(監視対象ツールのGitリポジトリ\nGitHub Releases / タグ)]
    end

    subgraph ToolUpdateServer [ToolUpdate (本システム / サーバー環境)]
        Config[監視対象ブランチ定義\nconfig/branches.json]
        ReleaseEngine[リリース生成エンジン\n(差分/フルZIP & 変更点生成)]
        LogEngine[ダウンロードログ・解析エンジン\n(ホスト名・国特定 & ランキング集計)]
        Storage[(パッケージ・ログ保管庫\n/storage/)]
        
        WebPortal[Web管理 & ポータル画面\n(一覧 / 最近 / 全体 / ランキング)]
        UpdateAPI[アップデート配信 API\n/api/check, /api/download]
    end

    subgraph ClientPC [PC環境 (ユーザー端末)]
        ToolApp[PCツール本体]
        UpdaterExe[アップデート用 EXE\n(各ツールに付属)]
    end

    %% リリース生成フロー
    Repos -->|① リリース検知 / 取得| ReleaseEngine
    Config --> ReleaseEngine
    ReleaseEngine -->|② ZIP & manifest 生成| Storage
    Storage --> WebPortal
    Storage --> UpdateAPI

    %% 手動ダウンロード
    WebPortal -.->|ブラウザから手動DL (最新/旧Ver)| ClientPC
    WebPortal -->|DLログ記録| LogEngine

    %% 自動アップデート (EXE連携)
    ToolApp -->|起動 / 更新チェック| UpdaterExe
    UpdaterExe -->|③ 最新Ver問い合わせ| UpdateAPI
    UpdateAPI -->|④ manifest(URL, SHA256等)| UpdaterExe
    UpdaterExe -->|⑤ ZIP自動取得| UpdateAPI
    UpdateAPI -->|DLログ記録| LogEngine
    UpdaterExe -->|⑥ 最新化完了| ToolApp

    %% ログ集計
    LogEngine --> Storage
    Storage -->|ランキング・統計データ| WebPortal
```

---

## 3. シーケンス（リリースからPCツールの自動最新化・ログ記録まで）

```mermaid
sequenceDiagram
    autonumber
    actor Dev as 開発者
    participant Git as GitHubリポジトリ
    participant Server as ToolUpdate (本サーバー)
    actor User as PCユーザー / 運用者
    participant Updater as PCツール側 アップデータEXE
    participant App as PCツール本体

    Note over Dev,Server: 1. リリース発生・パッケージ自動生成
    Dev->>Git: 新バージョン (GitHub Release または タグ) 作成
    Server->>Git: リリース検知 (CLI / Webhook / 手動同期)
    Server->>Server: 差分ZIP & フルZIP & manifest.json & 変更点抽出を自動実行

    Note over User,App: 2. 配布・ダウンロード & ログ記録
    alt Web画面からの手動ダウンロード
        User->>Server: Webポータルにアクセス
        Server-->>User: ZIPダウンロード
        Server->>Server: DLログ記録 (日時, ツール, Ver, IP, ホスト名, 国名)
    else アップデータEXEによる自動更新 (推奨)
        User->>Updater: アップデータEXEを実行
        Updater->>Server: GET /api/v1/check?tool=TrustChain&current=v2.0.0
        Server-->>Updater: 最新版情報 (v2.1.0, DL先URL, SHA256, 変更点, 削除ファイル一覧)
        Updater->>Server: GET /api/v1/download?tool=TrustChain&version=v2.1.0&type=update
        Server-->>Updater: update_v2.1.0.zip を送信
        Server->>Server: DLログ記録 (日時, ツール, Ver, IP, ホスト名, 国名, Client=EXE)
        Updater->>Updater: ハッシュ検証 ➔ バックアップ ➔ ZIP解凍・上書き
        Updater-->>App: 最新化完了 (ツール本体を再起動)
    end
```

---

## 4. 機能要件

### 4.1 監視対象リポジトリ・ブランチ管理
- 複数のPCツールを登録可能（JSON設定ファイル `config/branches.json`）。
- ツールID、表示名、GitリポジトリURL、対象ブランチ、除外ファイル設定（`.git`, テスト等）を保持。
- GitHub Releases（アセットZIP）および Gitタグ/ブランチからのソースアーカイブの双方に対応。

### 4.2 リリース生成 & 変更点（リリースノート）自動抽出
1. **パッケージ生成**:
   - **アップデート用ファイル（差分ZIP）**: 前回バージョンと今回バージョンの追加・変更ファイルのみをアーカイブ化。削除ファイル一覧（`delete_list.json`）も生成。
   - **フルインストール用ファイル（全体ZIP）**: 全ファイル一式アーカイブ。
   - **整合性ハッシュ計算**: 各ZIPのSHA-256ハッシュ値を算出し改ざん防止。
2. **変更点（リリースノート）抽出ロジック**:
   - **第一優先**: GitHub Release の本文（Markdown説明文）を取得。
   - **フォールバック**: Release本文が空の場合、前回タグからの `git log`（コミットメッセージ一覧）から自動生成。

### 4.3 Web管理 & ポータル画面（UI構成）
画面上部のグローバルナビゲーションに以下のメニューを配置：

1. **ツール/システム一覧 (`/` または `/tools`)**:
   - リリース物としてGitから取得した全ツール・システムの一覧表示。
   - ツール名、概要、最新バージョン、最終更新日、総ダウンロード数を表示。
   - **人気ツールダウンロードランキング**: TOPページ上部に人気ツールランキング（TOP 5/10）を表示。
   - **国別アクセス統計**: どの国・地域から多くダウンロードされているかの分布サマリー。
   - ツール名によるリアルタイム検索窓。
2. **ツール詳細 & リリース一覧 (`/tools/{tool_id}`)**:
   - ツール概要、リポジトリリンク、**TOTALダウンロード数（累計）**を表示。
   - **バージョン別人気ランキング**: ツール内でどのバージョンが多く利用されているかを表示。
   - 「Gitから最新リリースを再取得」ボタン（手動同期）。
   - バージョンごとのリリース履歴一覧（変更点、フルDL数、差分DL数、ダウンロードリンク、SHA256ハッシュ）。
3. **最近のリリース (`/recent`)**:
   - 直近でリリースのあったツールの一覧（時系列順）。
   - ツール名リンク、最新バージョン、リリース日、変更点サマリーを表示。
4. **リリース全体 (`/releases`)**:
   - 全ツール横断の全リリース年表（タイムライン）。いつ・どのツールが更新されたかを一覧表示。

### 4.4 ダウンロードログ記録 & アクセス解析
ダウンロード発生時にバックグラウンドで詳細ログを記録・集計：
- **記録項目**:
  - ダウンロード日時（年・月・日・時・分・秒）
  - ツールID、ツール名、ダウンロードバージョン
  - パッケージ種別（フル / アップデート差分）
  - IPアドレス、**ホスト名**（`gethostbyaddr` 逆引き）、**国コード・国名**（GeoIP / オフラインIP判定）
  - User-Agent、クライアント種別（Webブラウザ / アップデータEXE）
- **集計・活用**:
  - ツール別累計ダウンロード数、バージョン別ダウンロード数、期間別（全期間/月間/週間）ランキングの自動算出。

### 4.5 アップデート配信 API（アップデータEXE用エンドポイント）
1. **バージョン確認 API** (`GET /api/v1/check`):
   - クエリ: `tool_id`, `current_version`
   - レスポンス: 最新バージョン、更新要否、リリースノート、ZIPのダウンロードURL、SHA256ハッシュ、削除対象ファイル一覧。
2. **ダウンロード API** (`GET /api/v1/download`):
   - 指定されたバージョン・種類のZIPをストリーム返却（DLログを自動記録）。

### 4.6 外部連携 & 更新通知機能 (RSS / XML / WebHook)
新バージョンリリース時に、外部システムやユーザーへ即座に更新情報を周知・連携する仕組みを提供します。**「システム全体の総合通知」**および**「ツール/システム毎の個別通知」**の双方向での配信に対応します。

1. **RSS 2.0 フィード配信**:
   - **全体フィード (`GET /feed.php?type=rss` または `/rss.xml`)**:
     - 全ツールの最新リリース履歴を時系列でまとめた総合RSSフィード。
   - **ツール個別フィード (`GET /feed.php?type=rss&tool={tool_id}`)**:
     - 特定のツール（例: `TrustChain` のみ）のリリース履歴・更新情報に限定した個別RSSフィード。
2. **XML / Appcast 配信**:
   - **全体XML (`GET /feed.php?type=xml`)**:
     - 全ツールの最新バージョン情報を一覧できる汎用XML。
   - **ツール個別Appcast (`GET /feed.php?type=xml&tool={tool_id}`)**:
     - WinSparkle / Sparkle等の自動更新ライブラリが直接参照できる個別Appcast XML。
3. **アウトゴーイング WebHook 送信**:
   - **全体通知用Webhook**:
     - いずれかのツールがリリースされた際に、共通のチャンネル（例: 全体通知Slack/Discord）へ通知。
   - **ツール個別Webhook**:
     - ツールごとに個別のWebhook URL（`config/branches.json` 内で定義）を設定可能。該当ツールのリリース時のみ専用チャンネルへ通知。
   - **送信ペイロード (JSON)**:
     - ツールID、ツール名、バージョン、リリース日時、変更点（リリースノート要約）、フル/差分ダウンロードURL、SHA256ハッシュ。

---

## 5. データ構造定義

### 5.1 監視対象ツール定義 (`config/branches.json`)
```json
{
  "tools": [
    {
      "id": "TrustChain",
      "name": "TrustChain",
      "description": "C++アプリ向けビルド出自証明＆オンラインライセンス認証モジュール",
      "repository": "https://github.com/BLUE000/TrustChain.git",
      "branch": "master",
      "work_dir": "./repos/TrustChain",
      "exclude": [
        ".git",
        ".github",
        ".gitignore",
        "tests"
      ]
    }
  ]
}
```

### 5.2 ダウンロードログ構造 (`storage/logs/download_logs.jsonl`)
```json
{
  "timestamp": "2026-08-19T09:50:00+09:00",
  "tool_id": "TrustChain",
  "version": "v2.1.0",
  "package_type": "update",
  "ip_address": "123.45.67.89",
  "host_name": "client.example.isp.ne.jp",
  "country_code": "JP",
  "country_name": "Japan",
  "user_agent": "ToolUpdater/1.0",
  "client_type": "updater_exe"
}
```

### 5.3 リリース管理メタデータ (`storage/releases/<tool_id>/manifest.json`)
```json
{
  "tool_id": "TrustChain",
  "tool_name": "TrustChain",
  "latest_version": "v2.1.0",
  "total_downloads": 1250,
  "releases": [
    {
      "version": "v2.1.0",
      "prev_version": "v2.0.0",
      "release_date": "2026-08-19T09:00:00+09:00",
      "commit_hash": "e6f1d1e...",
      "release_notes": "開発ブランチでの改ざん検知対応の改善\nGitHub API のレートリミット対策",
      "full_package": {
        "filename": "TrustChain_v2.1.0_full.zip",
        "size": 15423800,
        "sha256": "3a7bd...",
        "downloads": 820
      },
      "update_package": {
        "filename": "TrustChain_v2.1.0_update_from_v2.0.0.zip",
        "size": 425100,
        "sha256": "8f12c...",
        "downloads": 430,
        "deleted_files": []
      }
    }
  ]
}
```

---

## 6. ディレクトリ構成案

```
d:/prog/PHP/updates/
├── bin/
│   └── release.php             # リリース生成・同期CLIコマンド
├── config/
│   ├── branches.json           # 監視対象ツール・ブランチ設定
│   ├── webhooks.json           # Webhook送信先設定
│   └── geoip.json              # 簡易IP-国マッピング（またはGeoIP設定）
├── doc/
│   └── requirements.md         # 要件定義書（本ドキュメント）
├── public/                     # Web公開領域（WebUI & API & Feeds）
│   ├── assets/                 # CSS, JS, アイコン等の静的アセット
│   │   ├── css/style.css
│   │   └── js/main.js
│   ├── index.php               # Webポータル（一覧 / 詳細 / 最近 / 全体 / ランキング）
│   ├── api.php                 # アップデータEXE向け REST API
│   └── feed.php                # RSS 2.0 / XML / Appcast 出力エンドポイント
├── repos/                      # 監視対象ツールのGitクローン/作業領域 (.gitignore)
├── src/                        # PHPソースコード
│   ├── Api/                    # APIコントローラー
│   ├── Config/                 # 設定ローダー
│   ├── Git/                    # Git/GitHub API操作
│   ├── Log/                    # ダウンロードログ記録・ホスト/国解析・ランキング集計
│   ├── Notifier/               # Webhook送信 / RSS・XML生成
│   ├── Package/                # ZIPアーカイブ・差分生成
│   └── ReleaseManager.php      # リリース処理全体の統括
├── storage/                    # 生成ZIP・ログ保存領域 (.gitignore)
│   ├── releases/               # ZIPファイル & manifest.json
│   └── logs/                   # ダウンロードログファイル
└── composer.json
```

---

## 7. 環境構成・リポジトリ運用方針

1. **本システム自体のGit管理**:
   - リポジトリ: `https://github.com/BLUE000/ReleaseHub.git` (ブランチ: `master`)
2. **ポータビリティ**:
   - 相対パス・設定駆動で構築し、開発環境（ローカル）から本番サーバー（Linux / Windows）へのデプロイを容易にする。
3. **アップデータEXEとの高い互換性**:
   - 軽量なHTTP GET JSON API（`/api.php?action=check` / `action=download`）により、C# / C++ (Qt) / Go / Rust / Python など多様な言語で作成されたクライアントEXEから容易に接続可能。
