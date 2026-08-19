<div class="release-item">
    <div class="release-item-header">
        <div class="version-badge-group">
            <span class="badge-version-large">{VERSION}</span>
            {BADGES}
            <span class="release-date-text">{RELEASE_DATE}</span>
        </div>
        <span class="version-downloads">このVerのDL数: <strong>{VERSION_DOWNLOADS}</strong></span>
    </div>
    
    <div class="release-notes-box">
        <h4>📝 変更点・リリースノート</h4>
        <div class="notes-content">{RELEASE_NOTES}</div>
    </div>

    <div class="package-download-grid">
        <div class="pkg-card full-pkg">
            <div class="pkg-info">
                <span class="pkg-type">フルインストール用ZIP</span>
                <span class="pkg-size">{FULL_SIZE}</span>
            </div>
            <code class="pkg-hash">SHA256: {FULL_SHA256}</code>
            <a href="{FULL_URL}" class="btn-download btn-full">フルZIPをダウンロード</a>
        </div>
        <div class="pkg-card update-pkg">
            <div class="pkg-info">
                <span class="pkg-type">アップデート用差分ZIP</span>
                <span class="pkg-size">{UPDATE_SIZE}</span>
            </div>
            <code class="pkg-hash">SHA256: {UPDATE_SHA256}</code>
            <a href="{UPDATE_URL}" class="btn-download btn-update">差分ZIPをダウンロード</a>
        </div>
    </div>
</div>
