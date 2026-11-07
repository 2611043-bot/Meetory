// ほしいものリスト追加/解除ボタンの処理
document.addEventListener('DOMContentLoaded', function () {
    const favoriteButtons = document.querySelectorAll('.js-favorite-btn');

    favoriteButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            const goodsId = button.dataset.goodsId;
            const isLoggedIn = button.dataset.isLoggedIn === '1';
            const addUrl = '/Meetory/meetory/goods/goods-favorite-add.php';
            const deleteUrl = '/Meetory/meetory/goods/goods-favorite-delete.php';
            const currentUrl = window.location.pathname + window.location.search;
            const csrfToken = button.dataset.csrfToken || '';

            // 未ログイン時はログイン画面へ誘導（元のページURLを保持）
            if (!isLoggedIn) {
                const loginUrl = '/Meetory/meetory/user/login.php?return_url=' + encodeURIComponent(currentUrl);
                window.location.href = loginUrl;
                return;
            }

            // CSRFトークンがない場合は通常のPOSTフォーム送信に委譲
            if (!csrfToken) {
                return;
            }

            // 既に保存済みか判定（ハートが塗りつぶされているか）
            const isFavorited = button.classList.contains('is-favorited');
            const url = isFavorited ? deleteUrl : addUrl;

            // フォームを動的生成してPOST送信
            const form = document.createElement('form');
            form.method = 'post';
            form.action = url;

            const goodsInput = document.createElement('input');
            goodsInput.type = 'hidden';
            goodsInput.name = 'goods_id';
            goodsInput.value = goodsId;

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;

            const returnInput = document.createElement('input');
            returnInput.type = 'hidden';
            returnInput.name = 'return_url';
            returnInput.value = currentUrl;

            form.appendChild(goodsInput);
            form.appendChild(csrfInput);
            form.appendChild(returnInput);
            document.body.appendChild(form);
            form.submit();
        });
    });
});
