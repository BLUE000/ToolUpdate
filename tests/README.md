# ReleaseHub Automated Tests

ReleaseHub Server の自動テスト用ディレクトリ。
本番公開対象（`server/`）から完全に分離されており、開発・CI環境でのテスト実行時に使用します。
テスト時は `bootstrap.php` 等でテスト用パラメータ・一時ストレージを注入して実行します。
