(function () {
    'use strict';

    const layout = document.getElementById('messageLayout');
    const messageRoomPanel = document.getElementById('messageRoomPanel');
    const talkList = document.getElementById('talkList');
    const searchInput = document.getElementById('talkSearch');
    const talkEmpty = document.getElementById('talkEmpty');
    const noTalks = document.getElementById('noTalks');

    if (!layout || !messageRoomPanel || !talkList) {
        console.error('メッセージ画面の要素を読み込めませんでした。');
        return;
    }

    let currentConversation = null;
    let loadController = null;
    let loadSequence = 0;
    let swipeStart = null;

    function isMobile() {
        return window.matchMedia('(max-width: 700px)').matches;
    }

    function updateAddressBar(conversation) {
        const url = new URL(window.location.href);
        ['user_id', 'goods_id', 'transaction_id', 'partner_id'].forEach(function (key) {
            url.searchParams.delete(key);
        });

        if (conversation.type === 'direct') {
            url.searchParams.set('user_id', conversation.partnerId);
        } else {
            url.searchParams.set(
                conversation.type === 'goods' ? 'goods_id' : 'transaction_id',
                conversation.contextId
            );
            url.searchParams.set('partner_id', conversation.partnerId);
        }

        window.history.replaceState(null, '', url);
    }

    function conversationFromTalk(talkItem) {
        const partnerId = Number.parseInt(talkItem.dataset.partnerId, 10);
        const type = talkItem.dataset.conversationType;
        const contextId = Number.parseInt(talkItem.dataset.contextId, 10);

        if (!partnerId || !['direct', 'goods', 'transaction'].includes(type)) {
            return null;
        }

        if (type !== 'direct' && !contextId) {
            return null;
        }

        return {
            type: type,
            contextId: type === 'direct' ? null : contextId,
            partnerId: partnerId
        };
    }

    function updateSelectedTalk(conversation) {
        talkList.querySelectorAll('.talk-item').forEach(function (item) {
            const itemConversation = conversationFromTalk(item);
            const isSelected = itemConversation &&
                itemConversation.type === conversation.type &&
                itemConversation.partnerId === conversation.partnerId &&
                itemConversation.contextId === conversation.contextId;
            item.classList.toggle('active', Boolean(isSelected));
        });
    }

    function setRoomVisible(visible) {
        layout.classList.toggle('is-room-open', visible);
    }

    function roomEndpoint(conversation) {
        const url = new URL(
            conversation.type === 'direct' ? 'message-room.php' : 'message-list.php',
            window.location.href
        );
        url.searchParams.set('partial', '1');

        if (conversation.type === 'direct') {
            url.searchParams.set('user_id', conversation.partnerId);
        } else {
            url.searchParams.set(
                conversation.type === 'goods' ? 'goods_id' : 'transaction_id',
                conversation.contextId
            );
            url.searchParams.set('partner_id', conversation.partnerId);
        }

        return url;
    }

    function closeImageModal() {
        const modal = messageRoomPanel.querySelector('#imageModal');
        const image = messageRoomPanel.querySelector('#imageModalImg');

        if (modal) {
            modal.hidden = true;
        }
        if (image) {
            image.src = '';
        }
        document.body.style.overflow = '';
    }

    function showRoomError(message) {
        const error = document.createElement('div');
        error.className = 'room-error';
        const text = document.createElement('p');
        text.textContent = message;
        error.appendChild(text);
        messageRoomPanel.replaceChildren(error);
    }

    async function loadConversation(conversation, options) {
        const settings = options || {};
        currentConversation = conversation;
        updateSelectedTalk(conversation);
        setRoomVisible(true);

        if (!settings.keepAddress) {
            updateAddressBar(conversation);
        }

        if (loadController) {
            loadController.abort();
        }
        loadController = new AbortController();
        const sequence = ++loadSequence;

        const loading = document.createElement('div');
        loading.className = 'room-loading';
        const loadingText = document.createElement('p');
        loadingText.textContent = '会話を読み込み中...';
        loading.appendChild(loadingText);
        messageRoomPanel.replaceChildren(loading);

        try {
            const response = await fetch(roomEndpoint(conversation), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: loadController.signal
            });

            if (!response.ok) {
                throw new Error('会話を読み込めませんでした。');
            }

            const html = await response.text();
            if (sequence !== loadSequence) {
                return;
            }

            messageRoomPanel.innerHTML = html;
            const roomMessages = messageRoomPanel.querySelector('#roomMessages');
            if (roomMessages) {
                roomMessages.scrollTop = roomMessages.scrollHeight;
            }
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            console.error(error);
            if (sequence === loadSequence) {
                showRoomError('会話を読み込めませんでした。時間をおいて再度お試しください。');
            }
        }
    }

    function closeRoom() {
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }
        if (loadController) {
            loadController.abort();
            loadController = null;
        }
        loadSequence++;
        closeImageModal();
        currentConversation = null;
        setRoomVisible(false);
        updateSelectedTalk({ type: '', partnerId: 0, contextId: null });
        messageRoomPanel.innerHTML =
            '<div class="talk-welcome">' +
            '<div class="welcome-icon"><i class="fa-regular fa-comments"></i></div>' +
            '<h2>メッセージ</h2>' +
            '<p>左側のトーク一覧から相手を選択すると、ここに会話が表示されます。</p>' +
            '</div>';

        const url = new URL(window.location.href);
        ['user_id', 'goods_id', 'transaction_id', 'partner_id'].forEach(function (key) {
            url.searchParams.delete(key);
        });
        window.history.replaceState(null, '', url);
    }

    function findTalk(conversation) {
        return Array.from(talkList.querySelectorAll('.talk-item')).find(function (item) {
            const itemConversation = conversationFromTalk(item);
            return itemConversation &&
                itemConversation.type === conversation.type &&
                itemConversation.partnerId === conversation.partnerId &&
                itemConversation.contextId === conversation.contextId;
        });
    }

    function createTalkItem(conversation, preview) {
        const room = messageRoomPanel.querySelector('.message-room');
        const partnerNameElement = room && room.querySelector('.room-partner h1');
        const partnerName = partnerNameElement
            ? partnerNameElement.textContent.trim()
            : 'メッセージ相手';
        const contextElement = room && room.querySelector('.room-context-meta a');
        const contextLabel = conversation.type === 'goods'
            ? (contextElement ? contextElement.textContent.trim() : '商品へのDM')
            : conversation.type === 'transaction'
                ? '取引 #' + conversation.contextId
                : 'メッセージ';

        const item = document.createElement('a');
        const url = new URL('message-center.php', window.location.href);
        if (conversation.type === 'direct') {
            url.searchParams.set('user_id', conversation.partnerId);
        } else {
            url.searchParams.set(
                conversation.type === 'goods' ? 'goods_id' : 'transaction_id',
                conversation.contextId
            );
            url.searchParams.set('partner_id', conversation.partnerId);
        }

        item.href = url.href;
        item.className = 'talk-item active';
        item.dataset.conversationType = conversation.type;
        item.dataset.contextId = String(conversation.contextId || 0);
        item.dataset.partnerId = String(conversation.partnerId);
        item.dataset.name = partnerName + ' ' + contextLabel;

        const avatar = document.createElement('div');
        avatar.className = 'talk-avatar';
        const roomAvatar = room && room.querySelector('.room-avatar');
        if (roomAvatar) {
            Array.from(roomAvatar.childNodes).forEach(function (child) {
                avatar.appendChild(child.cloneNode(true));
            });
        }

        const info = document.createElement('div');
        info.className = 'talk-info';
        const nameRow = document.createElement('div');
        nameRow.className = 'talk-name-row';
        const name = document.createElement('span');
        name.className = 'talk-user-name';
        name.textContent = partnerName;
        const time = document.createElement('span');
        time.className = 'talk-time';
        time.textContent = new Date().toLocaleTimeString('ja-JP', {
            hour: '2-digit',
            minute: '2-digit'
        });
        nameRow.append(name, time);

        const context = document.createElement('span');
        context.className = 'talk-context';
        context.textContent = contextLabel;
        const messageRow = document.createElement('div');
        messageRow.className = 'talk-message-row';
        const previewElement = document.createElement('p');
        previewElement.className = 'talk-last-message';
        previewElement.textContent = preview;
        messageRow.appendChild(previewElement);
        info.append(nameRow, context, messageRow);
        item.append(avatar, info);

        talkList.prepend(item);
        if (noTalks) {
            noTalks.hidden = true;
        }
        return item;
    }

    function updateTalkPreview(conversation, preview) {
        const item = findTalk(conversation) || createTalkItem(conversation, preview);

        const previewElement = item.querySelector('.talk-last-message');
        const timeElement = item.querySelector('.talk-time');
        if (previewElement) {
            previewElement.textContent = preview;
        }
        if (timeElement) {
            timeElement.textContent = new Date().toLocaleTimeString('ja-JP', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        talkList.prepend(item);
    }

    async function submitMessage(form, isProductMessage) {
        const conversation = currentConversation;
        if (!conversation) {
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
        }

        const formData = new FormData(form);
        let endpoint;

        if (isProductMessage || conversation.type === 'direct') {
            const url = new URL('message-room.php', window.location.href);
            url.searchParams.set('partial', '1');
            url.searchParams.set('user_id', conversation.partnerId);
            endpoint = url;
            formData.set('action', isProductMessage ? 'goods' : 'text');
        } else {
            endpoint = roomEndpoint(conversation);
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error('メッセージを送信できませんでした。');
            }

            if (currentConversation !== conversation) {
                return;
            }

            const preview = isProductMessage
                ? '商品を紹介しました。'
                : (formData.get('content') || '').toString().trim();
            updateTalkPreview(conversation, preview);
            await loadConversation(conversation, { keepAddress: true });
        } catch (error) {
            console.error(error);
            window.alert('メッセージを送信できませんでした。入力内容や通信状態をご確認ください。');
        } finally {
            if (submitButton) {
                submitButton.disabled = false;
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const keyword = this.value.trim().toLocaleLowerCase();
            let visibleCount = 0;

            talkList.querySelectorAll('.talk-item').forEach(function (item) {
                const name = (item.dataset.name || '').toLocaleLowerCase();
                const isMatch = name.includes(keyword);
                item.hidden = !isMatch;
                if (isMatch) {
                    visibleCount++;
                }
            });

            if (talkEmpty) {
                talkEmpty.hidden = visibleCount !== 0;
            }
        });
    }

    talkList.addEventListener('click', function (event) {
        const talkItem = event.target.closest('.talk-item');
        if (!talkItem) {
            return;
        }

        event.preventDefault();
        const conversation = conversationFromTalk(talkItem);
        if (!conversation) {
            return;
        }

        loadConversation(conversation);
    });

    messageRoomPanel.addEventListener('click', function (event) {
        const imageContainer = event.target.closest('.product-card-image');
        if (imageContainer) {
            const imageSource = imageContainer.dataset.imageSrc;
            const modal = messageRoomPanel.querySelector('#imageModal');
            const image = messageRoomPanel.querySelector('#imageModalImg');
            if (imageSource && modal && image) {
                image.src = imageSource;
                image.alt = imageContainer.dataset.imageAlt || '';
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
            }
            return;
        }

        if (
            event.target.closest('.image-modal-close') ||
            event.target.closest('.image-modal-overlay')
        ) {
            closeImageModal();
            return;
        }

        const backLink = event.target.closest('.room-back');
        if (backLink) {
            event.preventDefault();
            closeRoom();
            return;
        }

        const openPicker = event.target.closest('#openProductPicker');
        if (openPicker) {
            const picker = messageRoomPanel.querySelector('#productPicker');
            if (!picker) {
                return;
            }

            picker.hidden = !picker.hidden;
            openPicker.setAttribute('aria-expanded', String(!picker.hidden));
            const label = openPicker.querySelector('.product-picker-toggle-label');
            if (label) {
                label.textContent = picker.hidden ? '商品を送る' : '一覧を閉じる';
            }
        }
    });

    messageRoomPanel.addEventListener('submit', function (event) {
        const form = event.target;
        const isProductMessage = form.classList.contains('product-send-form');

        if (!form.matches('[data-ajax-message-form]') && !isProductMessage) {
            return;
        }

        event.preventDefault();
        submitMessage(form, isProductMessage);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            const modal = messageRoomPanel.querySelector('#imageModal');
            if (modal && !modal.hidden) {
                closeImageModal();
            } else if (isMobile() && layout.classList.contains('is-room-open')) {
                closeRoom();
            }
        }
    });

    messageRoomPanel.addEventListener('touchstart', function (event) {
        if (
            !isMobile() ||
            !layout.classList.contains('is-room-open') ||
            messageRoomPanel.querySelector('#imageModal:not([hidden])')
        ) {
            swipeStart = null;
            return;
        }

        const touch = event.changedTouches[0];
        if (
            touch.clientX > 28 ||
            event.target.closest('input, textarea, select, button, [contenteditable="true"]')
        ) {
            swipeStart = null;
            return;
        }

        swipeStart = { x: touch.clientX, y: touch.clientY };
    }, { passive: true });

    messageRoomPanel.addEventListener('touchend', function (event) {
        if (!swipeStart || !isMobile()) {
            swipeStart = null;
            return;
        }

        const touch = event.changedTouches[0];
        const deltaX = touch.clientX - swipeStart.x;
        const deltaY = Math.abs(touch.clientY - swipeStart.y);
        swipeStart = null;

        if (deltaX >= 80 && deltaY <= 60 && deltaX > deltaY * 1.4) {
            closeRoom();
        }
    }, { passive: true });

    messageRoomPanel.addEventListener('touchcancel', function () {
        swipeStart = null;
    }, { passive: true });

    function initialConversationFromUrl() {
        const params = new URLSearchParams(window.location.search);
        const partnerId = Number.parseInt(params.get('partner_id'), 10);
        const transactionId = Number.parseInt(params.get('transaction_id'), 10);
        const goodsId = Number.parseInt(params.get('goods_id'), 10);
        const directUserId = Number.parseInt(params.get('user_id'), 10);

        if (transactionId > 0 && partnerId > 0) {
            return { type: 'transaction', contextId: transactionId, partnerId: partnerId };
        }
        if (goodsId > 0 && partnerId > 0) {
            return { type: 'goods', contextId: goodsId, partnerId: partnerId };
        }
        if (directUserId > 0) {
            return { type: 'direct', contextId: null, partnerId: directUserId };
        }
        return null;
    }

    const initialConversation = initialConversationFromUrl();
    if (initialConversation) {
        loadConversation(initialConversation, { keepAddress: true });
    }
})();
