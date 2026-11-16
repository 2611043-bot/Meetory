// ========================================
// メッセージセンター（SPA風）
// トーク一覧のクリックでページ遷移せず、
// 右側の会話エリアだけを非同期で切り替える。
// ========================================

(function () {
    'use strict';

    // 会話表示エリア（未選択時は案内表示）
    const messageRoomPanel = document.getElementById('messageRoomPanel');
    const talkList = document.getElementById('talkList');
    const searchInput = document.getElementById('talkSearch');
    const talkEmpty = document.getElementById('talkEmpty');

    // 現在表示中の相手ID
    let currentUserId = null;

    // ------------------------------------
    // HTMLエスケープ（動的挿入時のXSS対策）
    // ------------------------------------
    function escapeHTML(text) {
        return String(text).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    // ------------------------------------
    // トーク検索
    // ------------------------------------
    if (searchInput && talkList) {
        searchInput.addEventListener('input', function () {
            const keyword = this.value.trim().toLowerCase();
            let visibleCount = 0;

            talkList.querySelectorAll('.talk-item').forEach(function (item) {
                const name = (item.dataset.name || '').toLowerCase();
                const isMatch = name.includes(keyword);

                item.style.display = isMatch ? 'flex' : 'none';

                if (isMatch) {
                    visibleCount++;
                }
            });

            if (talkEmpty) {
                talkEmpty.hidden = visibleCount !== 0;
            }
        });
    }

    // ------------------------------------
    // 会話エリアのHTMLを取得して差し替え
    // ------------------------------------
    async function loadRoom(userId) {
        if (!messageRoomPanel) {
            console.error('messageRoomPanel が見つかりません');
            return;
        }

        // 読み込み中表示
        messageRoomPanel.innerHTML =
            '<div class="room-loading">' +
            '<p>読み込み中...</p>' +
            '</div>';

        try {
            const url = new URL('message-room.php', window.location.href);
            url.searchParams.set('user_id', userId);
            url.searchParams.set('partial', '1');

            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error('会話の読み込みに失敗しました');
            }

            const html = await response.text();

            messageRoomPanel.innerHTML = html;

            // メッセージ欄を最新へスクロール
            scrollToBottom();

        } catch (error) {
            console.error(error);
            messageRoomPanel.innerHTML =
                '<div class="room-error">' +
                '<p>会話を読み込めませんでした。</p>' +
                '</div>';
        }
    }

    // ------------------------------------
    // トーク相手をクリック（イベント委譲）
    // ------------------------------------
    if (talkList) {
        talkList.addEventListener('click', function (event) {
            const talkItem = event.target.closest('.talk-item');

            if (!talkItem) {
                return;
            }

            // 通常のページ遷移を止める
            event.preventDefault();

            const userId = parseInt(talkItem.dataset.userId, 10);

            if (!userId) {
                return;
            }

            currentUserId = userId;

            // 選択状態を更新
            talkList.querySelectorAll('.talk-item').forEach(function (item) {
                item.classList.toggle('active', item === talkItem);
            });

            loadRoom(userId);
        });
    }

    // ------------------------------------
    // 会話エリア内のイベント委譲
    // （fetchで後から挿入された要素にも対応）
    // ------------------------------------
    if (messageRoomPanel) {

        // クリック系（商品カード開閉・商品選択・送信ボタン）
        messageRoomPanel.addEventListener('click', function (event) {

            // 商品カードの開閉
            const cardHeader = event.target.closest('.product-card-header');
            if (cardHeader) {
                const card = cardHeader.closest('.product-card');
                const body = card.querySelector('.product-card-body');

                const isOpen =
                    cardHeader.getAttribute('aria-expanded') === 'true';

                cardHeader.setAttribute('aria-expanded', String(!isOpen));
                body.hidden = isOpen;
                return;
            }

            // 商品選択欄の開閉
            const openPicker = event.target.closest('#openProductPicker');
            if (openPicker) {
                const picker = document.getElementById('productPicker');

                if (!picker) {
                    return;
                }

                picker.hidden = !picker.hidden;
                const isOpen = !picker.hidden;
                openPicker.setAttribute(
                    'aria-expanded',
                    String(isOpen)
                );
                const label =
                    openPicker.querySelector('.product-picker-toggle-label');
                if (label) {
                    label.textContent = isOpen
                        ? '一覧を閉じる'
                        : '商品を送る';
                }
                return;
            }

            // スマホ用：一覧に戻る（会話パネルを閉じる）
            const backLink = event.target.closest('.room-back');
            if (backLink) {
                event.preventDefault();
                closeRoomOnMobile();
                return;
            }
        });

        // 送信系（通常メッセージ・商品紹介）
        messageRoomPanel.addEventListener('submit', function (event) {
            const form = event.target;

            // 通常メッセージ送信
            if (form.id === 'messageForm') {
                event.preventDefault();
                sendTextMessage(form);
                return;
            }

            // 商品紹介送信
            if (form.classList.contains('product-send-form')) {
                event.preventDefault();
                sendGoodsMessage(form);
                return;
            }
        });
    }

    // ------------------------------------
    // スマホで会話を閉じて一覧に戻る
    // ------------------------------------
    function closeRoomOnMobile() {
        if (!messageRoomPanel) {
            return;
        }

        messageRoomPanel.innerHTML =
            '<div class="talk-welcome">' +
            '<div class="welcome-icon"><i class="fa-regular fa-comments"></i></div>' +
            '<h2>メッセージ</h2>' +
            '<p>左側のトーク一覧から相手を選択すると、ここに会話が表示されます。</p>' +
            '</div>';

        currentUserId = null;

        if (talkList) {
            talkList.querySelectorAll('.talk-item').forEach(function (item) {
                item.classList.remove('active');
            });
        }
    }

    // ------------------------------------
    // 通常メッセージ送信（fetch）
    // ------------------------------------
    async function sendTextMessage(form) {
        if (!currentUserId) {
            return;
        }

        const input = form.querySelector('#messageInput');
        const content = input ? input.value.trim() : '';

        // 空メッセージの拒否
        if (content === '') {
            return;
        }

        // 文字数制限
        if (content.length > 2000) {
            alert('メッセージは2000文字以内で入力してください。');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        try {
            const formData = new FormData(form);
            formData.set('action', 'text');

            const response = await fetch(
                'message-room.php?user_id=' + currentUserId + '&partial=1',
                {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }
            );

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error('送信に失敗しました');
            }

            // 入力をクリアして会話を再読み込み
            if (input) {
                input.value = '';
            }

            await loadRoom(currentUserId);

        } catch (error) {
            console.error(error);
            alert('メッセージの送信に失敗しました。');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    }

    // ------------------------------------
    // 商品紹介メッセージ送信（fetch）
    // ------------------------------------
    async function sendGoodsMessage(form) {
        if (!currentUserId) {
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        try {
            const formData = new FormData(form);
            formData.set('action', 'goods');

            const response = await fetch(
                'message-room.php?user_id=' + currentUserId + '&partial=1',
                {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }
            );

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error('送信に失敗しました');
            }

            // 商品選択欄を閉じる
            const picker = document.getElementById('productPicker');
            if (picker) {
                picker.hidden = true;
            }
            const openPickerButton =
                document.getElementById('openProductPicker');
            if (openPickerButton) {
                openPickerButton.setAttribute('aria-expanded', 'false');
                const label = openPickerButton.querySelector(
                    '.product-picker-toggle-label'
                );
                if (label) {
                    label.textContent = '商品を送る';
                }
            }

            await loadRoom(currentUserId);

        } catch (error) {
            console.error(error);
            alert('商品の送信に失敗しました。');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    }

    // ------------------------------------
    // 最新メッセージまでスクロール
    // ------------------------------------
    function scrollToBottom() {
        const roomMessages = document.getElementById('roomMessages');

        if (roomMessages) {
            roomMessages.scrollTop = roomMessages.scrollHeight;
        }
    }

})();