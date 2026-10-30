// HTMLの読み込みが完了してから実行
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const searchOverlay = document.getElementById('searchOverlay');

    // 検索欄とオーバーレイが存在する場合だけ処理する
    if (searchInput && searchOverlay) {
        // 検索欄を選択したとき
        searchInput.addEventListener('focus', function () {
            searchOverlay.classList.add('active');
        });

        // 検索欄からフォーカスが外れたとき
        searchInput.addEventListener('blur', function () {
            searchOverlay.classList.remove('active');
        });
    }
});