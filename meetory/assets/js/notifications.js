/**
 * 通知センター：Ajaxによる10秒ごとの自動更新
 *
 * - ヘッダーの未読件数バッジを更新（全ページで動作）
 * - 通知センター画面では新着通知を一覧先頭に追加
 * - 10秒間隔ポーリング。前回リクエスト中は次のリクエストを開始しない
 * - ページ非表示・離脱時は停止
 */

document.addEventListener('DOMContentLoaded', function () {
    const API_URL = '/Meetory/meetory/notifications/unread-count.php';
    const INTERVAL_MS = 10000;

    let isFetching = false;
    let timerId = null;
    let knownNotificationIds = new Set();
    let csrfToken = '';

    // 通知音関連
    let soundEnabled = true; // 初期設定はON（サーバー応答で上書き）
    let seenNotificationIds = new Set(); // 初回取得時点での基準＋それ以降で見たID
    let isFirstFetch = true;
    let audioCtx = null;
    let audioInitialized = false;
    let pendingSounds = 0; // 未再生の通知音キュー数
    let soundPlaying = false;

    const badgeEl = document.getElementById('notificationBadge');
    const bellEl = document.getElementById('notificationBell');
    const isNotificationsPage = document.body.classList.contains('notifications-page');
    const listEl = document.querySelector('.notification-items');

    // CSRFトークンをページから取得（存在する場合）
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
        csrfToken = csrfInput.value;
    }

    // 既にDOMにある通知IDを収集（重複追加防止）
    function collectKnownIds() {
        knownNotificationIds.clear();
        if (!listEl) {
            return;
        }
        listEl.querySelectorAll('.notification-item[data-notification-id]').forEach(function (el) {
            const id = parseInt(el.getAttribute('data-notification-id'), 10);
            if (!isNaN(id)) {
                knownNotificationIds.add(id);
            }
        });
    }

    // ヘッダーバッジを更新
    function updateBadge(count) {
        count = parseInt(count, 10) || 0;
        const label = count > 0 ? '通知（未読' + count + '件）' : '通知（未読なし）';

        if (bellEl) {
            bellEl.setAttribute('aria-label', label);
        }

        if (count > 0) {
            const displayText = count >= 100 ? '99+' : String(count);
            if (badgeEl) {
                badgeEl.textContent = displayText;
                badgeEl.style.display = 'flex';
            } else if (bellEl) {
                // バッジ要素が未作成の場合（初期0件）は追加
                const newBadge = document.createElement('span');
                newBadge.id = 'notificationBadge';
                newBadge.className = 'notification-badge';
                newBadge.setAttribute('aria-hidden', 'true');
                newBadge.textContent = displayText;
                bellEl.appendChild(newBadge);
            }
        } else if (badgeEl) {
            badgeEl.style.display = 'none';
        }
    }

    // 通知アイテムHTMLを生成（通知センター用、軽量版）
    function createNotificationItem(item) {
        const isRead = item.is_read === true;
        const typeLabel = item.type_label || item.type;
        const notificationId = item.notification_id;
        const li = document.createElement('li');
        li.className = 'notification-item' + (isRead ? '' : ' notification-item-unread');
        li.setAttribute('data-notification-id', notificationId);

        // クリック遷移URL（click.phpへ）
        const clickUrl = 'click.php?notification_id=' + encodeURIComponent(notificationId);

        const date = new Date(item.created_at);
        const formattedDate = date.toLocaleString('ja-JP', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });

        const titleEsc = escapeHtml(item.title);
        const messageEsc = escapeHtml(item.message);
        const typeLabelEsc = escapeHtml(typeLabel);
        const dateEsc = escapeHtml(formattedDate);

        let unreadMark = '';
        if (!isRead) {
            unreadMark = '<span class="notification-unread-mark">未読</span>';
        }

        li.innerHTML =
            '<a href="' + escapeHtml(clickUrl) + '" class="notification-link" aria-label="' + titleEsc + 'を開く">' +
            '<div class="notification-content">' +
            '<span class="notification-type-badge">' + typeLabelEsc + '</span>' +
            '<div class="notification-text">' +
            '<h3 class="notification-title">' + titleEsc + '</h3>' +
            '<p class="notification-message">' + nl2br(messageEsc) + '</p>' +
            '</div>' +
            '<div class="notification-meta">' +
            '<span class="notification-date">' + dateEsc + '</span>' +
            unreadMark +
            '</div>' +
            '</div>' +
            '</a>';

        // 個別既読フォーム（未読のみ）
        if (!isRead && csrfToken) {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = 'index.php';
            form.className = 'notification-item-form';
            form.style.display = 'inline-block';
            form.style.marginLeft = 'auto';

            form.innerHTML =
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
                '<input type="hidden" name="action" value="mark_read">' +
                '<input type="hidden" name="notification_id" value="' + notificationId + '">' +
                '<input type="hidden" name="type" value="' + escapeHtml(item.type) + '">' +
                '<input type="hidden" name="page" value="1">' +
                '<button type="submit" class="action-btn action-btn-read" onclick="return confirm(\'この通知を既読にしますか？\');">' +
                '既読にする' +
                '</button>';

            li.appendChild(form);
        }

        // 個別削除フォーム
        if (csrfToken) {
            const delForm = document.createElement('form');
            delForm.method = 'post';
            delForm.action = 'index.php';
            delForm.className = 'notification-item-form';
            delForm.style.display = 'inline-block';
            delForm.style.marginLeft = 'auto';

            delForm.innerHTML =
                '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' +
                '<input type="hidden" name="action" value="delete">' +
                '<input type="hidden" name="notification_id" value="' + notificationId + '">' +
                '<input type="hidden" name="type" value="' + escapeHtml(item.type) + '">' +
                '<input type="hidden" name="page" value="1">' +
                '<button type="submit" class="action-btn action-btn-delete" onclick="return confirm(\'この通知を削除しますか？元に戻せません。\');">' +
                '削除' +
                '</button>';

            li.appendChild(delForm);
        }

        return li;
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function nl2br(text) {
        return String(text).replace(/\r\n|\r|\n/g, '<br>');
    }

    // ------------------------------------
    // 通知音：Web Audio APIで短く控えめな通知音を再生
    // ------------------------------------

    // オーディオコンテキストを初期化（ブラウザの自動再生制限に備え、最初は作成のみ）
    function initAudio() {
        if (audioInitialized) {
            return;
        }
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) {
            audioInitialized = true;
            return;
        }
        try {
            audioCtx = new AudioContext();
        } catch (error) {
            audioCtx = null;
        }
        audioInitialized = true;
    }

    // 短く控えめな二音の通知音を再生
    function playSound() {
        if (!soundEnabled || !audioCtx) {
            return;
        }

        try {
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }

            const now = audioCtx.currentTime;

            // ボリュームとエンベロープ（控えめに0.12）
            const gainNode = audioCtx.createGain();
            gainNode.gain.setValueAtTime(0.12, now);
            gainNode.gain.exponentialRampToValueAtTime(0.01, now + 0.35);
            gainNode.connect(audioCtx.destination);

            // 第一音：650Hz → 850Hz
            const osc1 = audioCtx.createOscillator();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(650, now);
            osc1.frequency.exponentialRampToValueAtTime(850, now + 0.17);
            osc1.connect(gainNode);
            osc1.start(now);
            osc1.stop(now + 0.35);

            // 第二音：850Hz → 1050Hz（少し重ねて心地よく）
            const osc2 = audioCtx.createOscillator();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(850, now + 0.17);
            osc2.frequency.exponentialRampToValueAtTime(1050, now + 0.35);
            osc2.connect(gainNode);
            osc2.start(now + 0.17);
            osc2.stop(now + 0.52);
        } catch (error) {
            // 再生に失敗しても画面全体を停止させない
            console.warn('通知音の再生に失敗しました:', error);
        }
    }

    // 新着通知音をキューに追加して順次再生（連続通知でも騒がしくしない）
    function enqueueNewNotificationSounds(count) {
        if (!soundEnabled) {
            return;
        }
        // 最大3回までに制限し、連続鳴らしすぎを防ぐ
        pendingSounds += Math.min(count, 3);
        processSoundQueue();
    }

    function processSoundQueue() {
        if (!soundEnabled || pendingSounds <= 0 || soundPlaying) {
            return;
        }
        pendingSounds--;
        soundPlaying = true;
        playSound();
        setTimeout(function () {
            soundPlaying = false;
            processSoundQueue();
        }, 400);
    }

    // 初回ユーザー操作でオーディオコンテキストを再生可能にする
    function enableAudioOnInteraction() {
        document.addEventListener('click', function () {
            initAudio();
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
        }, { once: true });
    }

    // 新着通知を一覧先頭に追加（重複はスキップ）
    function prependNewNotifications(items) {
        if (!isNotificationsPage || !listEl) {
            return;
        }

        const filtered = items.filter(function (item) {
            return !knownNotificationIds.has(item.notification_id);
        });

        if (filtered.length === 0) {
            return;
        }

        // サーバーはcreated_at DESCで返すため、逆順にしてprependすれば
        // 一覧全体として新しい順が保たれる（古いものから順に先頭へ挿入）
        filtered.slice().reverse().forEach(function (item) {
            knownNotificationIds.add(item.notification_id);
            const el = createNotificationItem(item);
            listEl.prepend(el);
        });
    }

    // メイン取得処理
    async function fetchNotifications() {
        if (isFetching) {
            return;
        }

        // タブが非表示の場合は頻度を抑える（取得は行わずスキップ）
        if (document.visibilityState === 'hidden') {
            scheduleNext();
            return;
        }

        isFetching = true;

        // 現在のフィルター種別をURLから取得して維持
        const url = new URL(API_URL, window.location.origin);
        const currentFilter = getCurrentFilter();
        url.searchParams.set('type', currentFilter);

        try {
            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                // 認証エラーなどは画面を壊さず silently スキップ
                throw new Error('HTTP ' + response.status);
            }

            const data = await response.json();

            if (!data || data.success !== true) {
                throw new Error('invalid response');
            }

            // 未読件数をヘッダーに反映
            if (typeof data.unread_count !== 'undefined') {
                updateBadge(data.unread_count);
            }

            // 通知音ON/OFFをサーバー応答から更新（デフォルトON）
            if (typeof data.sound_enabled !== 'undefined') {
                soundEnabled = (data.sound_enabled === 1 || data.sound_enabled === '1' || data.sound_enabled === true);
            }

            // 新着通知のサウンド処理：初回取得後かつ未見のIDだけを新着として扱う
            if (Array.isArray(data.notifications)) {
                const newItems = [];
                for (const item of data.notifications) {
                    if (!seenNotificationIds.has(item.notification_id)) {
                        seenNotificationIds.add(item.notification_id);
                        newItems.push(item);
                    }
                }
                // 初回取得では基準を確立するのみで音を鳴らさない
                if (!isFirstFetch && newItems.length > 0) {
                    enqueueNewNotificationSounds(newItems.length);
                }
                isFirstFetch = false;

                // 通知センターでは一覧も更新
                if (isNotificationsPage) {
                    collectKnownIds();
                    prependNewNotifications(data.notifications);
                }
            }

        } catch (error) {
            // 通信エラー時は画面全体を壊さず、ログのみ記録
            console.warn('通知の自動更新に失敗しました:', error);
        } finally {
            isFetching = false;
            scheduleNext();
        }
    }

    // 現在のフィルター種別をURLから取得（未ログイン時はすべて）
    function getCurrentFilter() {
        if (!isNotificationsPage) {
            return 'all';
        }
        try {
            const params = new URLSearchParams(window.location.search);
            const type = params.get('type');
            const allowed = [
                'all', 'message', 'purchase', 'shipping', 'received',
                'review', 'favorite', 'price_down', 'follow', 'admin',
            ];
            return allowed.includes(type) ? type : 'all';
        } catch (error) {
            return 'all';
        }
    }

    // 次回取得をスケジュール
    function scheduleNext() {
        if (timerId) {
            clearTimeout(timerId);
        }
        timerId = setTimeout(fetchNotifications, INTERVAL_MS);
    }

    // 初回取得＆定期スケジュール開始
    // ベルが存在しない（未ログイン等）かつ通知センターでもない場合は何もしない
    if (!bellEl && !isNotificationsPage) {
        return;
    }

    collectKnownIds();
    fetchNotifications();

    // 初回ユーザー操作でオーディオコンテキストを再生可能にする（自動再生制限対策）
    enableAudioOnInteraction();

    // ページ非表示時はタイマーを停止し、再表示時に再開
    document.addEventListener('visibilitychange', function () {
        if (timerId) {
            clearTimeout(timerId);
            timerId = null;
        }
        if (document.visibilityState === 'visible') {
            // 再表示時に一度取得してから再開
            fetchNotifications();
        }
    });

    // ページ離脱時に停止
    window.addEventListener('beforeunload', function () {
        if (timerId) {
            clearTimeout(timerId);
            timerId = null;
        }
    });
});
