<div class="portal-dashboard">
    <div class="dashboard-sidebar">
        <div class="panel-card ranking-panel">
            <div class="panel-header">
                <h3>🏆 人気ツールランキング</h3>
                <span class="badge-accent">TOP 5</span>
            </div>
            <div class="ranking-list">
                {RANKING_LIST}
            </div>
        </div>

        <div class="panel-card stats-panel">
            <div class="panel-header">
                <h3>🌍 国別アクセス統計</h3>
            </div>
            <div class="country-stats-list">
                {COUNTRY_STATS}
            </div>
        </div>
    </div>

    <div class="dashboard-main">
        <div class="tools-header-area">
            <div class="title-wrap">
                <h2>登録ツール一覧</h2>
                <p class="subtitle">現在 <strong>{TOOLS_COUNT}</strong> 件のツールが公開されています</p>
            </div>
            <div class="search-box">
                <input type="text" id="toolSearchInput" placeholder="ツール名で絞り込み..." class="search-input">
            </div>
        </div>

        <div class="tool-cards-grid" id="toolCardsGrid">
            {TOOL_CARDS}
        </div>
    </div>
</div>
