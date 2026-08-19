<div class="tool-detail-page">
    <div class="detail-hero-card">
        <div class="hero-main-info">
            <a href="?page=tools" class="back-link">← ツール一覧に戻る</a>
            <h2 class="detail-title">{TOOL_NAME}</h2>
            <p class="detail-desc">{TOOL_DESC}</p>
            <div class="meta-tags">
                <span class="meta-tag">累計ダウンロード数: <strong>{TOTAL_DOWNLOADS} 回</strong></span>
            </div>
        </div>
        <div class="hero-actions">
            <a href="{BASE_URL}/feed.php?type=rss&tool={TOOL_ID}" class="btn-rss" target="_blank">📡 RSSフィード</a>
        </div>
    </div>

    <div class="releases-history-section">
        <h3>📦 バージョン・リリース履歴一覧</h3>
        <div class="releases-list">
            {RELEASES_LIST}
        </div>
    </div>
</div>
