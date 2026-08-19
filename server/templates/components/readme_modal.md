<div class="readme-modal-overlay" id="readmeModal" style="display: none;">
    <div class="readme-modal-container">
        <div class="readme-modal-header">
            <div class="modal-title-group">
                <span class="modal-icon">📖</span>
                <h3 class="modal-title" id="modalToolTitle">{TOOL_NAME} - ドキュメント</h3>
            </div>
            <div class="modal-controls">
                <div class="lang-selector-group">
                    <label for="readmeLangSelect" class="lang-label">言語:</label>
                    <select id="readmeLangSelect" class="readme-lang-select" data-tool="{TOOL_ID}">
                        {LANG_OPTIONS}
                    </select>
                </div>
                <a href="{BASE_URL}/?page=readme&tool={TOOL_ID}" id="readmeNewTabLink" class="btn-newtab" target="_blank" title="新しいタブで開く">別タブで開く ↗</a>
                <button type="button" class="btn-close-modal" id="closeReadmeModal" aria-label="閉じる">✕</button>
            </div>
        </div>
        <div class="readme-modal-body">
            <div id="readmeLoading" class="readme-loading" style="display: none;">
                <div class="spinner"></div>
                <p>ドキュメントを読み込み中...</p>
            </div>
            <div id="readmeContentArea" class="readme-content-area notes-content">
                <!-- 非同期でMarkdown HTMLが挿入されます -->
            </div>
        </div>
    </div>
</div>
