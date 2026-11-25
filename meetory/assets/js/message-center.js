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
    let swipeDebugStart = null;
    const swipeDebugEnabled = new URLSearchParams(window.location.search).get('swipeDebug') === '1';
    const swipeDebugState = {
        documentEvent: '未受信',
        layoutEvent: '未開始',
        target: '—',
        start: '—',
        current: '—',
        distance: '—',
        decision: '未判定',
        closeRoom: '未呼出'
    };
    let swipeDebugPanel = null;

    function isMobile() {
        return window.matchMedia('(max-width: 700px)').matches;
    }

    function formatPoint(x, y) {
        return Math.round(x) + ', ' + Math.round(y);
    }

    function hasTextSelection() {
        const selection = window.getSelection();
        return Boolean(selection && !selection.isCollapsed);
    }

    function describeTouchTarget(target) {
        if (!(target instanceof Element)) {
            return '不明';
        }

        const className = typeof target.className === 'string'
            ? target.className.trim().split(/\s+/).slice(0, 2).join('.')
            : '';
        return target.tagName.toLowerCase() +
            (target.id ? '#' + target.id : '') +
            (className ? '.' + className : '');
    }

    function renderSwipeDebug() {
        if (!swipeDebugEnabled || !swipeDebugPanel) {
            return;
        }

        swipeDebugPanel.textContent = [
            'スワイプ診断（Touch Events）',
            'document: ' + swipeDebugState.documentEvent,
            'layout: ' + swipeDebugState.layoutEvent,
            '対象: ' + swipeDebugState.target,
            '開始: ' + swipeDebugState.start + ' / 現在: ' + swipeDebugState.current,
            '移動: ' + swipeDebugState.distance,
            '判定: ' + swipeDebugState.decision,
            '戻る処理: ' + swipeDebugState.closeRoom
        ].join('\n');
    }

    function updateSwipeDebug(values) {
        if (!swipeDebugEnabled) {
            return;
        }

        Object.assign(swipeDebugState, values);
        renderSwipeDebug();
    }

    if (swipeDebugEnabled) {
        swipeDebugPanel = document.getElementById('messageSwipeDebug');
        if (!swipeDebugPanel) {
            swipeDebugPanel = document.createElement('pre');
            swipeDebugPanel.id = 'messageSwipeDebug';
            swipeDebugPanel.setAttribute('aria-label', 'スワイプ操作の診断情報');
            document.body.appendChild(swipeDebugPanel);
        }

        ['touchstart', 'touchmove', 'touchend', 'touchcancel'].forEach(function (eventName) {
            document.addEventListener(eventName, function (event) {
                const touch = event.changedTouches[0] ||
                    (event.touches.length ? event.touches[0] : null);
                const inLayout = event.target instanceof Node && layout.contains(event.target);
                const targetName = describeTouchTarget(event.target);

                if (eventName === 'touchstart' && touch) {
                    swipeDebugStart = { x: touch.clientX, y: touch.clientY };
                    updateSwipeDebug({
                        documentEvent: 'touchstart 受信',
                        layoutEvent: inLayout ? 'layoutへ伝播待ち' : 'layout外で開始',
                        target: targetName + (inLayout ? '（layout内）' : '（layout外）'),
                        start: formatPoint(touch.clientX, touch.clientY),
                        current: formatPoint(touch.clientX, touch.clientY),
                        distance: '0, 0',
                        decision: '未判定',
                        closeRoom: '未呼出'
                    });
                    return;
                }

                if (touch && swipeDebugStart) {
                    const deltaX = touch.clientX - swipeDebugStart.x;
                    const deltaY = touch.clientY - swipeDebugStart.y;
                    const absX = Math.abs(deltaX);
                    const absY = Math.abs(deltaY);
                    let decision = '移動中・未判定';

                    if (deltaX < -12) {
                        decision = '左方向の移動を検知';
                    } else if (absY > 14 && absY > absX * 1.1) {
                        decision = '縦方向の移動を検知';
                    } else if (deltaX > 12 && absX > absY * 1.1) {
                        decision = '右方向の横移動を検知';
                    }

                    updateSwipeDebug({
                        documentEvent: eventName + ' 受信',
                        target: targetName + (inLayout ? '（layout内）' : '（layout外）'),
                        current: formatPoint(touch.clientX, touch.clientY),
                        distance: Math.round(deltaX) + ', ' + Math.round(deltaY),
                        decision: decision
                    });
                } else {
                    updateSwipeDebug({
                        documentEvent: eventName + ' 受信',
                        target: targetName + (inLayout ? '（layout内）' : '（layout外）'),
                        decision: eventName === 'touchcancel'
                            ? 'touchcancel: ブラウザー側で中断'
                            : swipeDebugState.decision
                    });
                }

                if (eventName === 'touchend' || eventName === 'touchcancel') {
                    swipeDebugStart = null;
                }
            }, { passive: true, capture: true });
        });

        renderSwipeDebug();
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

    function closeRoom(reason) {
        updateSwipeDebug({
            closeRoom: 'closeRoom()呼出: ' + (reason || '不明')
        });
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

        if (swipeDebugEnabled) {
            requestAnimationFrame(function () {
                updateSwipeDebug({
                    closeRoom: layout.classList.contains('is-room-open')
                        ? '呼出済み・一覧切替失敗'
                        : '呼出済み・一覧表示中'
                });
            });
        }
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
            closeRoom('戻るボタン');
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
                closeRoom('Escape');
            }
        }
    });

    layout.addEventListener('touchstart', function (event) {
        const touch = event.changedTouches[0];
        if (
            !isMobile() ||
            !layout.classList.contains('is-room-open') ||
            messageRoomPanel.querySelector('#imageModal:not([hidden])')
        ) {
            swipeStart = null;
            updateSwipeDebug({
                layoutEvent: 'touchstart受信・対象外（端末幅/画面状態/画像表示）'
            });
            return;
        }

        const target = event.target instanceof Element ? event.target : null;
        if (!touch) {
            swipeStart = null;
            updateSwipeDebug({ layoutEvent: 'touchstart受信・座標なし' });
            return;
        }

        updateSwipeDebug({
            layoutEvent: 'touchstart受信',
            target: describeTouchTarget(event.target) + '（layout内）',
            start: formatPoint(touch.clientX, touch.clientY),
            current: formatPoint(touch.clientX, touch.clientY),
            distance: '0, 0',
            decision: '未判定',
            closeRoom: '未呼出'
        });

        const startsInRoom = target && messageRoomPanel.contains(target);
        const startsOnControl = target && target.closest(
            'a:not(.room-back), button, input, textarea, select, ' +
            '[contenteditable="true"], [role="button"], [role="link"], .product-card-image'
        );
        if (!startsInRoom || startsOnControl || hasTextSelection()) {
            swipeStart = null;
            updateSwipeDebug({
                layoutEvent: 'touchstart受信・会話外または操作要素のため除外',
                decision: '対象外'
            });
            return;
        }

        swipeStart = {
            x: touch.clientX,
            y: touch.clientY,
            maxVerticalDistance: 0,
            cancelled: false
        };
    }, { passive: true, capture: true });

    layout.addEventListener('touchmove', function (event) {
        if (!swipeStart || !isMobile()) {
            if (swipeDebugEnabled) {
                updateSwipeDebug({ layoutEvent: 'touchmove受信・有効な開始なし' });
            }
            return;
        }

        const touch = event.changedTouches[0];
        if (!touch) {
            updateSwipeDebug({ layoutEvent: 'touchmove受信・座標なし' });
            return;
        }
        const deltaX = touch.clientX - swipeStart.x;
        const deltaY = touch.clientY - swipeStart.y;
        const verticalDistance = Math.abs(deltaY);
        swipeStart.maxVerticalDistance = Math.max(
            swipeStart.maxVerticalDistance,
            verticalDistance
        );

        if (
            deltaX < -12 ||
            (verticalDistance > 14 && verticalDistance > Math.abs(deltaX) * 1.1) ||
            hasTextSelection()
        ) {
            swipeStart.cancelled = true;
        }

        updateSwipeDebug({
            layoutEvent: 'touchmove受信',
            current: formatPoint(touch.clientX, touch.clientY),
            distance: Math.round(deltaX) + ', ' + Math.round(deltaY),
            decision: swipeStart.cancelled
                ? (hasTextSelection()
                    ? 'テキスト選択のため除外'
                    : deltaX < -12
                        ? '左方向のため除外'
                        : '縦スクロールのため除外')
                : '移動中・未確定'
        });
    }, { passive: true, capture: true });

    layout.addEventListener('touchend', function (event) {
        if (!swipeStart || !isMobile()) {
            swipeStart = null;
            updateSwipeDebug({ layoutEvent: 'touchend受信・有効な開始なし' });
            return;
        }

        const touch = event.changedTouches[0];
        if (!touch) {
            swipeStart = null;
            updateSwipeDebug({ layoutEvent: 'touchend受信・座標なし' });
            return;
        }
        const deltaX = touch.clientX - swipeStart.x;
        const deltaY = Math.max(
            swipeStart.maxVerticalDistance,
            Math.abs(touch.clientY - swipeStart.y)
        );
        const cancelled = swipeStart.cancelled;
        swipeStart = null;

        const shouldClose =
            !cancelled &&
            !hasTextSelection() &&
            deltaX >= 72 &&
            deltaX > deltaY * 1.35;
        updateSwipeDebug({
            layoutEvent: 'touchend受信',
            current: formatPoint(touch.clientX, touch.clientY),
            distance: Math.round(deltaX) + ', ' + Math.round(deltaY),
            decision: shouldClose ? '右スワイプ条件成立' : '条件不成立'
        });

        if (shouldClose) {
            closeRoom('右スワイプ');
        }
    }, { passive: true, capture: true });

    layout.addEventListener('touchcancel', function () {
        swipeStart = null;
        updateSwipeDebug({
            layoutEvent: 'touchcancel受信',
            decision: 'ブラウザー等によりキャンセル'
        });
    }, { passive: true, capture: true });

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
    // ========================================
    // 右側メッセージエリアの拡大・縮小切り替え
    // ========================================
    function isExpanded() {
        return layout.classList.contains('is-expanded');
    }

    function updateExpandButtons() {
        const expanded = isExpanded();
        messageRoomPanel.querySelectorAll('.room-expand-btn').forEach(function (btn) {
            btn.setAttribute('aria-pressed', String(expanded));
            btn.title = expanded ? '元のサイズに戻す' : 'メッセージ画面を拡大';
            btn.innerHTML = expanded
                ? '<i class="fa-solid fa-compress"></i><span>戻す</span>'
                : '<i class="fa-solid fa-expand"></i><span>拡大</span>';
        });
    }

    function setExpanded(expanded) {
        if (isMobile()) {
            return;
        }
        layout.classList.toggle('is-expanded', expanded);
        document.body.classList.toggle('is-message-expanded', expanded);
        updateExpandButtons();
    }

    function injectExpandButton() {
        if (isMobile()) {
            return;
        }
        const targetHeader =
            messageRoomPanel.querySelector('.room-header') ||
            messageRoomPanel.querySelector('.talk-welcome');

        if (!targetHeader || targetHeader.querySelector('.room-expand-btn')) {
            updateExpandButtons();
            return;
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'room-expand-btn';
        targetHeader.appendChild(btn);
        updateExpandButtons();
    }

    // トーク一覧の各項目にホバー用タイトルを付与（縮小表示時に相手名が分かるように）
    talkList.querySelectorAll('.talk-item').forEach(function (item) {
        const nameEl = item.querySelector('.talk-user-name');
        const ctxEl = item.querySelector('.talk-context');
        if (nameEl && !item.title) {
            item.title = nameEl.textContent.trim() + (ctxEl ? '（' + ctxEl.textContent.trim() + '）' : '');
        }
    });

    // 右パネルの中身が切り替わるたびに拡大ボタンを自動挿入
    const panelObserver = new MutationObserver(function () {
        injectExpandButton();
    });
    panelObserver.observe(messageRoomPanel, { childList: true });
    injectExpandButton();

    // 拡大ボタンのクリック処理
    messageRoomPanel.addEventListener('click', function (event) {
        const expandBtn = event.target.closest('.room-expand-btn');
        if (!expandBtn) {
            return;
        }
        event.preventDefault();
        setExpanded(!isExpanded());
    });
})();
