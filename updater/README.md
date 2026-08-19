# ReleaseHub Updater (Client Updater)

各PCツール側に同梱・連携する自動更新クライアントモジュール（Qt6 / C++20）のソースコード置き場です。

---

## 1. 概要
- **対象プラットフォーム**: Windows (x64 / x86) 等
- **開発言語 / フレームワーク**: C++20, Qt 6 Framework (Qt Widgets / Qt Network / Qt Core)
- **機能**:
  - ReleaseHub Server の REST API (`api.php?action=check`, `action=download`) との通信
  - SHA-256 改ざん防止ハッシュ検証
  - 適用前バックアップ ＆ 自動ロールバック機能
  - 差分ZIP解凍・上書き適用 ＆ 削除リスト（`delete_list.json`）処理
  - アプリケーション本体の終了待機・更新後自動再起動

---

## 2. ライセンス & 権利表記

- **本アップデータコード**: [MIT License](../LICENSE)
- **Qt 6 Framework**:
  - Copyright (C) 2026 The Qt Company Ltd. and other contributors.
  - Qt is available under the GNU Lesser General Public License (LGPL) version 3, GNU General Public License (GPL) version 3, or Commercial licenses.
  - [https://www.qt.io/licensing/](https://www.qt.io/licensing/)
- **C++ Standard**: ISO/IEC 14882:2020 (C++20)
