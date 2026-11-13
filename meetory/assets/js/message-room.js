// ========================================
// メッセージルーム（通常ページ用）
// message-room.php に直接アクセスした場合の動作。
// 会話の切り替え・送信は message-center.php 側（message-center.js）が
// イベント委譲で担当するため、ここでは通常ページの補助機能のみを持つ。
// ========================================

(function () {
    'use strict';

    // メッセージ表示エリア
    const roomMessages = document.getElementById('roomMessages');

    // 商品カードの開閉（イベント委譲）
    if (roomMessages) {
        roomMessages.addEventListener('click', function (event) {
            const button = event.target.closest('.product-card-header');

            if (!button) {
                return;
            }

            const card = button.closest('.product-card');
            const body = card.querySelector('.product-card-body');

            const isOpen = button.getAttribute('aria-expanded') === 'true';

            button.setAttribute('aria-expanded', String(!isOpen));
            body.hidden = isOpen;
        });

        // 最初に最新メッセージまでスクロール
        roomMessages.scrollTop = roomMessages.scrollHeight;
    }

    // トーク一覧の検索
    const roomTalkSearch = document.getElementById('roomTalkSearch');

    if (roomTalkSearch) {
        roomTalkSearch.addEventListener('input', function () {
            const keyword = this.value.trim().toLowerCase();

            document.querySelectorAll('.room-talk-item').forEach(function (item) {
                const nameEl = item.querySelector('.room-talk-name-row');
                const name = nameEl ? nameEl.textContent.toLowerCase() : '';

                item.style.display = name.includes(keyword) ? 'flex' : 'none';
            });
        });
    }

    // 商品選択欄の開閉（イベント委譲）
    const openProductPicker = document.getElementById('openProductPicker');
    const productPicker = document.getElementById('productPicker');

    if (openProductPicker && productPicker) {
        openProductPicker.addEventListener('click', function () {
            productPicker.hidden = !productPicker.hidden;
            const isOpen = !productPicker.hidden;

            openProductPicker.setAttribute(
                'aria-expanded',
                String(isOpen)
            );

            const label = openProductPicker.querySelector(
                '.product-picker-toggle-label'
            );
            if (label) {
                label.textContent = isOpen ? '一覧を閉じる' : '商品を送る';
            }
        });
    }

})();