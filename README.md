# ReleaseHub (ソフトウェア更新・フルパッケージ管理システム)

PC向けクライアントアプリケーション（デスクトップアプリ/CLI等）のバージョンアップ、差分/フルパッケージ生成、自動配信、ダウンロード統計ログ解析を提供するWebシステムです。

---

## 1. ディレクトリ構成

```
ReleaseHub/
├── server/                     # コアシステム (PHP 8.1+ Webポータル / API / リリース管理)
│   ├── config/                 # 監視対象ツール・Webhook・GeoIP設定
│   ├── public/                 # Web公開領域 (index.php, api.php, feed.php, assets/)
│   ├── src/                    # PHPコアクラス群
│   ├── storage/                # パッケージ(ZIP)・ログ・ロック保存領域 (要書込権限)
│   └── templates/              # パーツ別Markdownテンプレート
├── updater/                    # クライアント側アップデータ (Qt6 / C++20) ソースコード
├── tests/                      # 自動テストスイート (本番デプロイ不要)
└── doc/                        # 要件定義書・設計書
```

---

## 2. Webサーバーへの設置手順 (さくらインターネット等)

本システムは、ドキュメントルート直下だけでなく、**任意のサブディレクトリ（例: `https://example.com/releasehub/`）への設置に完全対応**しています。

### ステップ 1: ファイルのアップロード
SFTPまたはファイルマネージャー等を使用し、`server/` ディレクトリ内のファイル一式をサーバー上の公開したいディレクトリへアップロードします。

- **配置例**:
  - ドキュメントルート配下に `releasehub` フォルダを作成し、その中に `server/` 配下の全ファイル・フォルダ（`config/`, `public/`, `src/`, `storage/`, `templates/` 等）を配置します。

### ステップ 2: ディレクトリの書き込み権限 (パーミッション) 設定
PHPがリリースZIPの保管やログの記録を行えるよう、以下のディレクトリに書き込み権限を付与します：

- `server/storage/` (および配下の `releases/`, `logs/`, `locks/`, `tmp/`) ➔ **`chmod 755` または `chmod 777`**

### ステップ 3: 動作確認
ブラウザから設置先URLへアクセスします：
- ポータル画面: `https://example.com/releasehub/public/index.php` (または `.htaccess` 等で `public/` をルーティング)
- APIエンドポイント: `https://example.com/releasehub/public/api.php?action=check&tool=SampleTool&current=v1.0.0`

---

## 3. Gitリポジトリ（監視対象ツール）の登録手順

新しいツールを管理対象に追加する際は、`server/config/branches.json` に設定を追記します（Web画面からの編集機能はなく、設定ファイルの直接配置・更新で行います）。

### 設定例 (`server/config/branches.json`)
※Git URL等は架空のサンプルです。

```json
{
  "tools": [
    {
      "id": "SampleTool",
      "name": "Sample Desktop Tool",
      "description": "配信者向けのリアルタイム音量調整＆ショートカット管理デスクトップツール",
      "repository": "https://github.com/example-org/sample-tool.git",
      "branch": "master",
      "exclude": [
        ".git",
        ".github",
        ".gitignore",
        "tests"
      ],
      "webhook_url": "https://discord.com/api/webhooks/123456789/sample-webhook-token"
    }
  ]
}
```

#### 各設定項目の説明
| 項目名 | 必須 | 説明・用途 |
| :--- | :---: | :--- |
| `id` | ○ | ツールの識別子（英数字・ハイフン・アンダースコア推奨）。URLパラメータ（`?page=tool&id=SampleTool`）やAPIクエリで使用されます。 |
| `name` | ○ | Webポータル上のタイトルや一覧カードに表示されるツールの正式名称・表示名。 |
| `description` | ○ | **ツールの概要・紹介文**。Webポータルのツール一覧カードや詳細画面のヘッダーに説明文として表示されます。 |
| `repository` | ○ | 監視対象となるGitHubリポジトリのURL（HTTPS形式）。 |
| `branch` | ○ | 監視対象のブランチ名（通常は `master` または `main`）。 |
| `exclude` | - | （フォールバック時）ソースからZIP自動生成する際に除外するフォルダ・ファイル名の配列。 |
| `webhook_url` | - | 新バージョンリリース時に専用通知を送りたい場合のWebhook URL（Discord/Slack等）。省略時は全体通知のみ行われます。 |

### GitHub Releases でのリリース運用
1. GitHubリポジトリで新しい Release（例: `v1.1.0`）を作成します。
2. **推奨**: ビルド済みのフルZIP（例: `SampleTool_v1.1.0_full.zip`）を Release Assets に添付（アタッチ）して公開します。
   - ReleaseHub はアタッチされたZIPを自動検知して直接保管し、前回バージョンとの差分ZIPを自動生成します。
   - ※アタッチされていない場合は、GitソースツリーからZIP圧縮を自動実行します。

---

## 4. 自動テストの実行方法 (開発・CI環境)

本番データ（`server/storage/`）を汚染しない隔離環境（`tests/temp_storage/`）で全ルート検証を一括実行できます：

```bash
php tests/run.php
```

実行後、コンソールに結果サマリ（実行件数/OK/NG/実行時間）が表示され、`tests/logs/` に詳細ログが自動保管されます。

---

## 5. ライセンス & サードパーティ権利表記

### 本プロジェクトのライセンス
本ソフトウェアは [MIT License](LICENSE) の下で公開されています。

```text
Copyright (c) 2026 BLUE
```

### アップデータ (Qt6 / C++20) に関する権利表記
本システムのクライアント側アップデータモジュールは、**C++20** および **Qt 6 Framework** を使用して構築されています。

- **Qt 6 Framework**:
  - Copyright (C) 2026 The Qt Company Ltd. and other contributors.
  - Qt is available under the GNU Lesser General Public License (LGPL) version 3, the GNU General Public License (GPL) version 3, or commercial licenses.
  - For more information, please visit [https://www.qt.io/licensing/](https://www.qt.io/licensing/).
- **C++ Standard Library**:
  - ISO/IEC 14882:2020 (C++20 Standard).
