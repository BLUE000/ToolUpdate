<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{PAGE_TITLE} - ReleaseHub</title>
    <link rel="stylesheet" href="{BASE_URL}/assets/css/common.css">
    <link rel="stylesheet" href="{BASE_URL}/assets/css/components/nav.css">
    <link rel="stylesheet" href="{BASE_URL}/assets/css/components/ranking.css">
    <link rel="stylesheet" href="{BASE_URL}/assets/css/components/tool_card.css">
    <link rel="stylesheet" href="{BASE_URL}/assets/css/components/release_table.css">
    <link rel="stylesheet" href="{BASE_URL}/assets/css/components/country_stats.css">
    <link rel="stylesheet" href="{BASE_URL}/assets/css/components/readme_modal.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="dark-theme">
    <div class="app-wrapper">
        <header class="app-header">
            <div class="container header-container">
                <div class="logo-area">
                    <a href="{BASE_URL}/index.php" class="logo-link">
                        <span class="logo-icon">📦</span>
                        <span class="logo-text">Release<span class="highlight">Hub</span></span>
                    </a>
                    <span class="badge-version">v1.0.0</span>
                </div>
                {GLOBAL_NAV}
            </div>
        </header>

        <main class="app-main">
            <div class="container">
                {CONTENT}
            </div>
        </main>

        <footer class="app-footer">
            <div class="container footer-container">
                <p>&copy; 2026 ReleaseHub - PCツール向け更新・フルパッケージ管理システム</p>
                <div class="footer-links">
                    <a href="{BASE_URL}/feed.php?type=rss" class="footer-link">📡 RSS 2.0</a>
                    <a href="{BASE_URL}/feed.php?type=xml" class="footer-link">📜 Appcast XML</a>
                </div>
            </div>
        </footer>
    </div>
    <script src="{BASE_URL}/assets/js/main.js"></script>
</body>
</html>
