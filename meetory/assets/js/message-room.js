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

    // 画像拡大モーダル
    const imageModal = document.getElementById('imageModal');
    const imageModalImg = document.getElementById('imageModalImg');
    const imageModalClose = imageModal ? imageModal.querySelector('.image-modal-close') : null;
    const imageModalOverlay = imageModal ? imageModal.querySelector('.image-modal-overlay') : null;

    // モーダルを開く
    function openImageModal(imageSrc, imageAlt) {
        if (!imageModal || !imageModalImg || !imageSrc) {
            return;
        }
        imageModalImg.src = imageSrc;
        imageModalImg.alt = imageAlt || '';
        imageModal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    // モーダルを閉じる
    function closeImageModal() {
        if (!imageModal) {
            return;
        }
        imageModal.hidden = true;
        imageModalImg.src = '';
        document.body.style.overflow = '';
    }

    // モーダル関連のイベント
    if (imageModal) {
        // 閉じるボタン
        if (imageModalClose) {
            imageModalClose.addEventListener('click', function (e) {
                e.stopPropagation();
                closeImageModal();
            });
        }

        // オーバーレイクリック
        if (imageModalOverlay) {
            imageModalOverlay.addEventListener('click', closeImageModal);
        }

        // ESCキー
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !imageModal.hidden) {
                closeImageModal();
            }
        });
    }

    // 商品画像クリックでモーダル表示（イベント委譲）
    if (roomMessages) {
        roomMessages.addEventListener('click', function (event) {
            const imageContainer = event.target.closest('.product-card-image');

            if (imageContainer) {
                // 画像クリック時はリンク移動を防ぐ
                event.preventDefault();
                event.stopPropagation();

                const imageSrc = imageContainer.dataset.imageSrc;
                const imageAlt = imageContainer.dataset.imageAlt;

                if (imageSrc) {
                    openImageModal(imageSrc, imageAlt);
                }
                return;
            }

            // 最初に最新メッセージまでスクロール
            roomMessages.scrollTop = roomMessages.scrollHeight;
        });
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