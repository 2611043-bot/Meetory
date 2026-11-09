// HTMLの読み込みが完了してから実行
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('searchInput');
    const searchOverlay = document.getElementById('searchOverlay');

    // 検索欄とオーバーレイが存在する場合だけ処理する
    if (searchInput && searchOverlay) {
        searchInput.addEventListener('focus', function () {
            searchOverlay.classList.add('active');
        });

        searchInput.addEventListener('blur', function () {
            searchOverlay.classList.remove('active');
        });
    }

    // サイドメニューの開閉
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebarMenu = document.getElementById('sidebarMenu');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarClose = document.getElementById('sidebarClose');

    // 要素が存在しない場合は処理を中断
    if (!hamburgerBtn || !sidebarMenu || !sidebarOverlay || !sidebarClose) {
        return;
    }

    function openSidebar() {
        sidebarMenu.classList.add('open');
        sidebarOverlay.classList.add('open');
        // 体のスクロールを禁止
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebarMenu.classList.remove('open');
        sidebarOverlay.classList.remove('open');
        // 体のスクロールを再開
        document.body.style.overflow = '';
    }

    // 三本線ボタンクリックでメニューを開く
    hamburgerBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        openSidebar();
    });

    // 閉じるボタンクリックでメニューを閉じる
    sidebarClose.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeSidebar();
    });

    // 背景オーバーレイクリックでメニューを閉じる
    sidebarOverlay.addEventListener('click', function (event) {
        // オーバーレイ自体がクリックされた場合のみ閉じる（メニュー内のクリックは無視）
        if (event.target === sidebarOverlay) {
            closeSidebar();
        }
    });

    // メニューリンククリックでメニューを閉じる
    const sidebarLinks = document.querySelectorAll('.sidebar-link');
    sidebarLinks.forEach(function (link) {
        link.addEventListener('click', closeSidebar);
    });
});
