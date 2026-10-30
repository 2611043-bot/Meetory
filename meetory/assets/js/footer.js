document.addEventListener('DOMContentLoaded', function () {
    const footerLinks = document.querySelectorAll('.footer-nav a');

    // 現在のページに対応するボタンを選択状態にする
    footerLinks.forEach(function (link) {
        const linkUrl = new URL(link.href, window.location.origin);

        if (linkUrl.pathname === window.location.pathname) {
            link.classList.add('active');
        }
    });
});