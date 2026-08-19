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
});
