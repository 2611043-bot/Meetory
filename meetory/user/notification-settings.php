<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../meetory-functions.php';

$user_id = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
if ($user_id === false || $user_id === null || $user_id <= 0) {
    header('Location: login.php');
    exit;
}

$pdo = getPDO();

$typeColumns = [
    'message'      => 'type_message',
    'purchase'     => 'type_purchase',
    'shipping'     => 'type_shipping',
    'received'     => 'type_received',
    'review'       => 'type_review',
    'favorite'     => 'type_favorite',
    'price_down'   => 'type_price_down',
    'follow'       => 'type_follow',
    'admin'        => 'type_admin',
];

$typeLabels = [
    'message'    => 'メッセージ',
    'purchase'   => '購入',
    'shipping'   => '発送',
    'received'   => '受取',
    'review'     => '評価',
    'favorite'   => 'お気に入り',
    'price_down' => '値下げ',
    'follow'     => 'フォロー',
    'admin'      => '運営からのお知らせ',
];

$typeDescriptions = [
    'message'    => 'ほかのユーザーからメッセージを受信したときに通知します。',
    'purchase'   => '商品が購入されたときや、購入に関するお知らせを通知します。',
    'shipping'   => '購入した商品が発送されたときに通知します。',
    'received'   => '取引相手が商品を受け取ったときや、受取に関するお知らせを通知します。',
    'review'     => '取引相手から評価を受けたときに通知します。',
    'favorite'   => '自分の商品がお気に入りに追加されたときに通知します。',
    'price_down' => 'お気に入りに登録した商品が値下げされたときに通知します。',
    'follow'     => 'ほかのユーザーからフォローされたときに通知します。',
    'admin'      => '運営から重要なお知らせやご案内が届いたときに通知します。',
];

$csrf_token = $_SESSION['csrf_token'] ?? '';
if ($csrf_token === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $csrf_token = $_SESSION['csrf_token'];
}

$flash_message = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (
        isset($_SESSION['csrf_token']) &&
        is_string($_SESSION['csrf_token']) &&
        $_SESSION['csrf_token'] !== '' &&
        is_string($post_csrf) &&
        hash_equals($_SESSION['csrf_token'], $post_csrf)
    ) {
        // オークション関連通知の単一トグルを5カラムに展開
        $auctionColumns = [
            'type_auction_bid_surpassed',
            'type_auction_won',
            'type_auction_ending',
            'type_auction_bid_success',
            'type_auction_ended_seller',
        ];
        if (isset($_POST['auction_notifications'])) {
            foreach ($auctionColumns as $col) {
                $_POST[$col] = '1';
            }
        }

        $success = saveNotificationSettings($pdo, $user_id, $_POST);
        if ($success) {
            $_SESSION['notify_flash_message'] = '通知設定を保存しました。';
            $_SESSION['notify_flash_type'] = 'success';
        } else {
            $_SESSION['notify_flash_message'] = '設定の保存に失敗しました。時間をおいて再度お試しください。';
            $_SESSION['notify_flash_type'] = 'error';
        }
    } else {
        $_SESSION['notify_flash_message'] = '不正なリクエストです。ページを開き直してください。';
        $_SESSION['notify_flash_type'] = 'error';
    }

    header('Location: notification-settings.php');
    exit;
}

$settings = getNotificationSettings($pdo, $user_id);

// オークション関連通知：5つのカラムを1つのトグルで連動
$auctionColumns = [
    'type_auction_bid_surpassed',
    'type_auction_won',
    'type_auction_ending',
    'type_auction_bid_success',
    'type_auction_ended_seller',
];
$auctionEnabled = false;
foreach ($auctionColumns as $col) {
    if (isset($settings[$col]) && (int) $settings[$col] === 1) {
        $auctionEnabled = true;
        break;
    }
}

require '../../meetory-header.php';
?>

<link
    rel="stylesheet"
    href="../assets/css/notification-settings.css?v=<?= filemtime(__DIR__ . '/../assets/css/notification-settings.css') ?>"
>

<main class="notification-settings-page">
    <header class="notification-settings-heading">
        <h1>通知設定</h1>
        <p>受け取る通知の種類と通知音を設定できます。</p>
    </header>

    <?php
    $flash_message = $_SESSION['notify_flash_message'] ?? '';
    $flash_type = $_SESSION['notify_flash_type'] ?? '';
    unset($_SESSION['notify_flash_message'], $_SESSION['notify_flash_type']);
    ?>
    <?php if ($flash_message !== ''): ?>
        <div class="flash-message flash-message-<?= htmlspecialchars($flash_type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
            <?= htmlspecialchars($flash_message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- 自動保存トースト通知 -->
    <div id="toastContainer" class="toast-container"></div>

    <form action="notification-settings.php" method="post" class="notification-settings-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <section class="settings-section">
            <h2>全体設定</h2>
            <div class="setting-item">
                <div class="setting-info">
                    <h3>通知を受け取る</h3>
                    <p>すべての通知を一括でON/OFFします。OFFにすると通知一覧に表示されず、未読数も0になります。</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="globalToggle" name="enabled" value="1" <?= (int)$settings['enabled'] === 1 ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        <!-- 全体設定がOFFのときに表示する案内 -->
        <div id="globalOffMessage" class="global-off-message" <?= (int)$settings['enabled'] === 1 ? 'style="display:none;"' : '' ?>>
            <p>全体設定がOFFのため、通知は停止しています。</p>
        </div>

        <section class="settings-section<?= (int)$settings['enabled'] !== 1 ? ' settings-section-disabled' : '' ?>" id="individualSettings">
            <div class="settings-section-header">
                <h2>種類別設定</h2>
                <button type="button" id="selectAllBtn" class="btn btn-small btn-outline" <?= (int)$settings['enabled'] !== 1 ? 'disabled' : '' ?>>すべてON</button>
            </div>
            <p class="settings-section-desc">個別に通知を受け取るかどうかを設定できます。「通知を受け取る」がOFFの場合、この設定に関わらず通知は届きません。</p>
            <?php foreach ($typeColumns as $typeKey => $colName): ?>
                <div class="setting-item">
                    <div class="setting-info">
                        <h3><?= htmlspecialchars($typeLabels[$typeKey], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p><?= htmlspecialchars($typeDescriptions[$typeKey], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="<?= htmlspecialchars($colName, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= (int)$settings[$colName] === 1 ? 'checked' : '' ?> <?= (int)$settings['enabled'] !== 1 ? 'disabled' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            <?php endforeach; ?>

            <!-- オークション関連通知（5種類を1つのトグルで連動） -->
            <div class="setting-item">
                <div class="setting-info">
                    <h3>オークション関連通知</h3>
                    <p>入札成功・入札額の更新・落札・オークション終了などの通知をまとめて設定します。</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="auction_notifications" value="1" <?= $auctionEnabled ? 'checked' : '' ?> <?= (int)$settings['enabled'] !== 1 ? 'disabled' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        <section class="settings-section">
            <h2>通知音</h2>
            <div class="setting-item">
                <div class="setting-info">
                    <h3>通知音を再生する</h3>
                    <p>新着通知があったときに音を鳴らします。通知一覧や未読数には影響しません。</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" name="sound_enabled" value="1" <?= (int)$settings['sound_enabled'] === 1 ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </section>

        <div class="settings-actions">
            <a href="mypage.php" class="btn btn-secondary">設定を閉じる</a>
        </div>
    </form>

    <script>
    (function () {
        'use strict';

        var globalToggle = document.getElementById('globalToggle');
        var individualSettings = document.getElementById('individualSettings');
        var globalOffMessage = document.getElementById('globalOffMessage');
        var form = document.querySelector('.notification-settings-form');
        var csrfTokenInput = form.querySelector('input[name="csrf_token"]');
        var selectAllBtn = document.getElementById('selectAllBtn');

        if (!globalToggle || !individualSettings || !globalOffMessage || !form || !csrfTokenInput || !selectAllBtn) {
            return;
        }

        var csrfToken = csrfTokenInput.value;
        var debounceTimer = null;
        var requestSequence = 0;
        var SAVE_URL = 'save-notification-settings.php';

        // 保存状態をトースト通知で表示
        var toastTimer = null;

        function showStatus(message, type) {
            // 既存のトーストを削除
            var existing = document.querySelector('.toast');
            if (existing) {
                existing.remove();
            }

            // 古いタイマーをキャンセル（新しい通知で古いタイマーが消さないように）
            if (toastTimer !== null) {
                clearTimeout(toastTimer);
                toastTimer = null;
            }

            var container = document.getElementById('toastContainer');
            if (!container) {
                return;
            }

            var toast = document.createElement('div');
            toast.className = 'toast toast-' + type;

            var iconClass = '';
            if (type === 'saving') {
                iconClass = 'fa-solid fa-circle-notch fa-spin';
            } else if (type === 'success') {
                iconClass = 'fa-solid fa-circle-check';
            } else if (type === 'error') {
                iconClass = 'fa-solid fa-circle-exclamation';
            }

            toast.innerHTML = '<i class="' + iconClass + '"></i>' + message;
            container.appendChild(toast);

            // アニメーション開始（少し遅延してtransitionを適用）
            setTimeout(function () {
                toast.classList.add('show');
            }, 10);

            // 非表示タイマー（成功2秒、エラー4秒、保存中は非表示にしない）
            var hideDelay = (type === 'success') ? 2000 : (type === 'error') ? 4000 : 0;
            if (hideDelay > 0) {
                toastTimer = setTimeout(function () {
                    toast.classList.remove('show');
                    toastTimer = null;
                    setTimeout(function () {
                        if (toast.parentNode) {
                            toast.remove();
                        }
                    }, 300);
                }, hideDelay);
            }
        }

        // 全種類別設定のチェック状態を取得（disabledでもcheckedプロパティは読める）
        function collectSettings() {
            var data = {};
            data.csrf_token = csrfToken;

            // 全体設定
            data.enabled = globalToggle.checked ? '1' : '';

            // 種類別設定
            var individualInputs = individualSettings.querySelectorAll('input[type="checkbox"]');
            individualInputs.forEach(function (input) {
                data[input.name] = input.checked ? '1' : '';
            });

            // 通知音（種類別設定セクション外）
            var soundInput = form.querySelector('input[name="sound_enabled"]');
            if (soundInput) {
                data.sound_enabled = soundInput.checked ? '1' : '';
            }

            return data;
        }

        // Ajax保存
        function saveSettings(data, sequence) {
            var formData = new FormData();
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    formData.append(key, data[key]);
                }
            }

            showStatus('保存中...', 'saving');

            fetch(SAVE_URL, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('保存に失敗しました');
                }
                return response.json();
            })
            .then(function (result) {
                if (sequence !== requestSequence) {
                    return; // 古いリクエストの結果は無視
                }
                if (result.success) {
                    showStatus('設定を保存しました', 'success');
                } else {
                    showStatus('保存に失敗しました。もう一度お試しください。', 'error');
                }
            })
            .catch(function () {
                if (sequence !== requestSequence) {
                    return;
                }
                showStatus('保存に失敗しました。もう一度お試しください。', 'error');
            });
        }

        function requestSave() {
            requestSequence++;
            var sequence = requestSequence;

            if (debounceTimer !== null) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }

            var data = collectSettings();

            debounceTimer = setTimeout(function () {
                saveSettings(data, sequence);
            }, 500);
        }

        function updateIndividualSettingsState(isEnabled) {
            if (isEnabled) {
                individualSettings.classList.remove('settings-section-disabled');
                globalOffMessage.style.display = 'none';
                var inputs = individualSettings.querySelectorAll('input[type="checkbox"]');
                inputs.forEach(function (input) {
                    input.removeAttribute('disabled');
                });
                if (selectAllBtn) {
                    selectAllBtn.removeAttribute('disabled');
                }
            } else {
                individualSettings.classList.add('settings-section-disabled');
                globalOffMessage.style.display = 'block';
                var inputs = individualSettings.querySelectorAll('input[type="checkbox"]');
                inputs.forEach(function (input) {
                    input.setAttribute('disabled', 'disabled');
                });
                if (selectAllBtn) {
                    selectAllBtn.setAttribute('disabled', 'disabled');
                }
            }
        }

        // 初期状態を設定
        updateIndividualSettingsState(globalToggle.checked);

        // 全体設定切り替え時に連動＋自動保存
        globalToggle.addEventListener('change', function () {
            updateIndividualSettingsState(this.checked);
            requestSave();
        });

        // 種類別設定の切り替え時に自動保存
        var individualInputs = individualSettings.querySelectorAll('input[type="checkbox"]');
        individualInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                requestSave();
            });
        });

        // 通知音の切り替え時に自動保存
        var soundInput = form.querySelector('input[name="sound_enabled"]');
        if (soundInput) {
            soundInput.addEventListener('change', function () {
                requestSave();
            });
        }

        // すべてONボタンのクリック
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                if (selectAllBtn.disabled) {
                    return;
                }
                var allInputs = individualSettings.querySelectorAll('input[type="checkbox"]');
                allInputs.forEach(function (input) {
                    input.checked = true;
                });
                requestSave();
            });
        }

        // フォーム送信を無効化（自動保存のみ使用）
        form.addEventListener('submit', function (event) {
            event.preventDefault();
        });
    })();
    </script>

</main>

<?php require '../../meetory-footer.php'; ?>