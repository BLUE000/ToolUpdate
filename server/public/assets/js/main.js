// ReleaseHub Client Interactive Scripts
document.addEventListener('DOMContentLoaded', () => {
    // ツール名リアルタイム検索機能
    const searchInput = document.getElementById('toolSearchInput');
    const toolGrid = document.getElementById('toolCardsGrid');

    if (searchInput && toolGrid) {
        const cards = toolGrid.querySelectorAll('.tool-card');

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();

            cards.forEach(card => {
                const name = card.querySelector('.tool-name')?.textContent.toLowerCase() || '';
                const desc = card.querySelector('.tool-description')?.textContent.toLowerCase() || '';
                const id = card.getAttribute('data-tool-id')?.toLowerCase() || '';

                if (name.includes(query) || desc.includes(query) || id.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // 現在のページに応じたナビゲーションアクティブ状態の付与
    const currentParams = new URLSearchParams(window.location.search);
    const currentPage = currentParams.get('page') || 'tools';
    const navItems = document.querySelectorAll('.global-nav .nav-item');

    navItems.forEach(item => {
        const href = item.getAttribute('href') || '';
        if (href.includes(`page=${currentPage}`) || (currentPage === 'tools' && href.includes('index.php') && !href.includes('page='))) {
            item.classList.add('active');
        }
    });

    // ========================================================
    // 多言語READMEモーダル制御
    // ========================================================
    const modal = document.getElementById('readmeModal');
    const modalTitle = document.getElementById('modalToolTitle');
    const langSelect = document.getElementById('readmeLangSelect');
    const contentArea = document.getElementById('readmeContentArea');
    const loadingEl = document.getElementById('readmeLoading');
    const newTabLink = document.getElementById('readmeNewTabLink');
    const closeModalBtn = document.getElementById('closeReadmeModal');

    async function loadReadme(toolId, lang) {
        if (!modal || !contentArea) return;

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        if (loadingEl) loadingEl.style.display = 'flex';
        contentArea.style.display = 'none';

        try {
            const res = await fetch(`api.php?action=readme&tool=${encodeURIComponent(toolId)}&lang=${encodeURIComponent(lang)}`);
            if (!res.ok) {
                throw new Error('README fetch failed');
            }
            const data = await res.json();

            if (modalTitle && data.tool_name) {
                modalTitle.textContent = `${data.tool_name} - ドキュメント`;
            }

            if (langSelect && data.available_languages) {
                langSelect.innerHTML = '';
                data.available_languages.forEach(l => {
                    const opt = document.createElement('option');
                    opt.value = l.code;
                    opt.textContent = l.name;
                    if (l.code === data.current_lang) {
                        opt.selected = true;
                    }
                    langSelect.appendChild(opt);
                });
                langSelect.setAttribute('data-tool', toolId);
            }

            if (newTabLink) {
                newTabLink.href = `?page=readme&tool=${encodeURIComponent(toolId)}&lang=${encodeURIComponent(data.current_lang)}`;
            }

            contentArea.innerHTML = data.content_html || '<p class="empty-text">ドキュメント本文はありません。</p>';
        } catch (err) {
            contentArea.innerHTML = '<p class="error-text">ドキュメント (README) の取得に失敗しました。</p>';
        } finally {
            if (loadingEl) loadingEl.style.display = 'none';
            contentArea.style.display = 'block';
        }
    }

    function closeModal() {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // モーダル開くボタンのイベントリスナー
    document.querySelectorAll('.open-readme-btn, .open-readme-link').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const toolId = btn.getAttribute('data-tool');
            const lang = btn.getAttribute('data-lang') || 'ja';
            if (toolId) {
                loadReadme(toolId, lang);
            }
        });
    });

    // リスト内などで動的に追加されたリンクへのイベントデリゲーション
    document.addEventListener('click', (e) => {
        const link = e.target.closest('.open-readme-link');
        if (link) {
            e.preventDefault();
            const toolId = link.getAttribute('data-tool');
            const lang = link.getAttribute('data-lang') || 'ja';
            if (toolId) {
                loadReadme(toolId, lang);
            }
        }
    });

    // 言語切り替えイベント
    if (langSelect) {
        langSelect.addEventListener('change', (e) => {
            const toolId = langSelect.getAttribute('data-tool');
            const selectedLang = e.target.value;
            if (toolId) {
                loadReadme(toolId, selectedLang);
            }
        });
    }

    // 閉じるボタン
    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', closeModal);
    }

    // モーダル外枠クリックで閉じる
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    // ESCキーで閉じる
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
            closeModal();
        }
    });
});
