# ソフトウェア更新・フルパッケージ管理システム（ToolUpdate） 要件定義書

## 1. プロジェクト概要
- **システム名**: ToolUpdate（ソフトウェア更新・フルパッケージ管理システム）
- **本システム Git リポジトリ**: `https://github.com/BLUE000/ToolUpdate.git`
- **本システム デフォルトブランチ**: `master`

### 1.1 目的
本システムは、管理対象となる複数の外部/内部ソフトウェア（各Gitリポジトリ）のブランチ情報をJSON形式で保持・管理し、リリース発生時（タグ付けやマージなど）に各ソフトウェアの「アップデート用ファイル（差分）」および「フルインストール用ファイル（一式）」をGitから自動取得・生成・管理するPHPアプリケーションです。

---

## 2. システム構成・全体像

```mermaid
flowchart TD
    Config[ブランチ定義 JSON\n(branches.json)] --> System[ToolUpdate\nPHP管理システム]
    GitRepo[(各ソフトウェアのGitリポジトリ\nGitHub / GitLab / ローカル)] <-->|Git コマンド / API| System
    System -->|差分抽出 & Zip化| UpdatePkg[アップデートファイル\n(update_vX.X.X.zip)]
    System -->|アーカイブ作成| FullPkg[フルインストールファイル\n(full_vX.X.X.zip)]
    System --> Meta[リリース履歴・メタデータJSON]
    UpdatePkg --> Storage[(パッケージ保存領域 /storage/)]
    FullPkg --> Storage
```

---

## 3. 機能要件

### 3.1 ブランチ・ソフトウェア情報管理機能
- 管理対象ソフトウェアごとに、ソフトウェアID、表示名、GitリポジトリURL（またはローカルパス）、対象ブランチ、除外ファイル/フォルダ設定などをJSON形式（`branches.json`）で保持・管理する。
- 複数ソフトウェア（複数リポジトリ）の登録・設定更新に対応する。

### 3.2 Git連携 & リリース検知/指定機能
- **リリース検知/指定方式**:
  - タグ指定（例: `v1.0.1` などのタグ指定）
  - コミットハッシュ / リビジョン指定
  - ブランチの最新HEAD取得
- **Git操作**:
  - 対象リポジトリのClone / Fetch / Checkout
  - ローカルのGit CLI（`git archive` / `git diff` 等）または API（GitHub/GitLab REST API 等）を利用した取得処理。

### 3.3 パッケージ生成機能
1. **アップデートファイル（差分パッケージ）**:
   - 指定した前回リリースバージョン（タグまたはコミット）と今回リリースバージョンの差分（`git diff --name-only` / `git archive` 等）を抽出し、変更・追加されたファイル群のみをアーカイブ（.zip / .tar.gz）化。
   - 削除されたファイルの一覧（`delete_list.txt` 等）も含めて管理。
2. **フルインストールファイル（全体パッケージ）**:
   - 指定ブランチ・タグのリリース時点の全ファイルをアーカイブ（.zip / .tar.gz）化。
   - `.git` ディレクトリや `.env`、CI設定ファイルなどの除外対象（`.gitattributes` や設定の除外ルール）を適切に除外。

### 3.4 配布パッケージ・リリース履歴管理
- 生成したパッケージを指定の保存ディレクトリ（`storage/releases/<software_id>/<version>/` 等）に配置。
- 各リリースのメタデータ（バージョン名、コミットハッシュ、リリース日時、ファイルサイズ、SHA256チェックサム等）を記録・管理。
- 必要に応じて最新バージョンの参照（`latest.json` 等）を出力・更新。

### 3.5 実行インターフェース
- **CLI（コマンドライン）実行**:
  - コマンド一発で指定ソフトウェアのリリース取得・生成を実行（Cron実行やCI/CD連携を想定）。
  - 例: `php bin/release.php --app=app_a --tag=v1.2.0 --prev=v1.1.0`
- **Web UI / Webhook（将来/拡張）**:
  - 管理画面からの手動実行、GitHub/GitLab等のWebhook通知連動による自動生成。

---

## 4. データ構造定義

### 4.1 管理対象ソフトウェア・ブランチ定義 (`config/branches.json`)
本システムが管理する対象ソフトウェア一覧を定義するJSONファイル。

```json
{
  "software_list": [
    {
      "id": "sample_app_a",
      "name": "Sample Application A",
      "repository": "https://github.com/example/sample-app-a.git",
      "branch": "main",
      "work_dir": "./repos/sample_app_a",
      "exclude": [
        ".git",
        ".github",
        ".gitignore",
        "tests",
        ".env.example"
      ]
    },
    {
      "id": "sample_app_b",
      "name": "Sample Application B",
      "repository": "https://github.com/example/sample-app-b.git",
      "branch": "master",
      "work_dir": "./repos/sample_app_b",
      "exclude": [
        ".git",
        ".gitignore"
      ]
    }
  ]
}
```

### 4.2 リリース履歴メタデータ (`storage/releases/<software_id>/manifest.json`)
各ソフトウェアのリリース履歴を記録するメタデータファイル。

```json
{
  "software_id": "sample_app_a",
  "latest_version": "v1.2.0",
  "releases": [
    {
      "version": "v1.2.0",
      "prev_version": "v1.1.0",
      "release_date": "2026-08-19T08:30:00+09:00",
      "commit_hash": "a1b2c3d4e5f...",
      "full_package": {
        "filename": "sample_app_a_v1.2.0_full.zip",
        "size": 15423800,
        "sha256": "..."
      },
      "update_package": {
        "filename": "sample_app_a_v1.2.0_update_from_v1.1.0.zip",
        "size": 425100,
        "sha256": "..."
      }
    }
  ]
}
```

---

## 5. ディレクトリ構成案

```
d:/prog/PHP/updates/
├── bin/
│   └── release.php             # CLIエントリーポイント
├── config/
│   └── branches.json           # 管理対象ソフトウェア・ブランチ設定
├── doc/
│   └── requirements.md         # 要件定義書（本ドキュメント）
├── repos/                      # 管理対象ソフトウェアのGitクローン/作業領域
├── src/                        # PHPソースコード
│   ├── Config/                 # 設定・JSONローダー
│   ├── Git/                    # Git操作（クローン・差分・タグ取得等）
│   ├── Package/                # Zipアーカイブ・差分パッケージ生成
│   └── ReleaseManager.php      # リリース処理全体の制御クラス
├── storage/                    # 生成されたZIPファイル・マニフェスト保存領域
│   └── releases/
└── composer.json
```

---

## 6. 環境構成・リポジトリ運用方針

### 6.1 ソースコード管理・運用
- **リポジトリ**: `https://github.com/BLUE000/ToolUpdate.git`
- **デフォルトブランチ**: `master`
- 本リポジトリにて、本システム（ToolUpdate）のソースコード・設定テンプレート・ドキュメント等を一元管理します。

### 6.2 開発環境と本番環境の分離
- **開発環境**: 本ローカル作業環境。機能実装・検証・テスト用。
- **本番環境**: 別途用意される稼働サーバー（Linux / Windows / Docker 等）。
- **環境ポータビリティ**:
  - 相対パスまたは設定可能なベースパスを基盤とし、本番環境へのデプロイが容易な設計とする。
  - Gitリポジトリで管理するものと、実行時に生成されるファイル（Git管理除外）を明確に分離する。

### 6.3 Git除外対象 (`.gitignore`)
- 管理対象ソフトウェアのクローン作業領域: `/repos/*`
- リリースパッケージ・生成されたアーカイブ: `/storage/releases/*`
- 実行ログ・一時ファイル: `/logs/*`, `/tmp/*`
- 依存関係ライブラリ: `/vendor/*`
- 環境固有設定: `config/branches.local.json` (オーバーライド用)

---

## 7. 非機能要件・考慮事項

1. **環境要件**:
   - PHP 8.1以上
   - `git` コマンドが実行可能な環境（またはZipArchive拡張機能などの標準機能活用）
2. **安全性・整合性**:
   - 生成された各Zipファイルの破損検証（Zip整合性チェックおよびSHA-256ハッシュ計算）。
   - Git操作時の排他制御（同一リポジトリに対する同時更新処理の防止）。
3. **エラーハンドリング**:
   - Git fetch/checkout 失敗時の適切なログ出力とロールバック。
   - 差分が存在しない場合のハンドリング。
4. **ポータビリティ・環境適応性**:
   - 開発環境（ローカル）と本番環境でパスやGitの実行権限が異なる場合にも設定の切り替えのみで動作可能な構造。
