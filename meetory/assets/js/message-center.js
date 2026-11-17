// ========================================
// 繝｡繝・そ繝ｼ繧ｸ繧ｻ繝ｳ繧ｿ繝ｼ・・PA鬚ｨ・・// 繝医・繧ｯ荳隕ｧ縺ｮ繧ｯ繝ｪ繝・け縺ｧ繝壹・繧ｸ驕ｷ遘ｻ縺帙★縲・// 蜿ｳ蛛ｴ縺ｮ莨夊ｩｱ繧ｨ繝ｪ繧｢縺縺代ｒ髱槫酔譛溘〒蛻・ｊ譖ｿ縺医ｋ縲・// ========================================

(function () {
    'use strict';

    // 莨夊ｩｱ陦ｨ遉ｺ繧ｨ繝ｪ繧｢・域悴驕ｸ謚樊凾縺ｯ譯亥・陦ｨ遉ｺ・・    const messageRoomPanel = document.getElementById('messageRoomPanel');
    const talkList = document.getElementById('talkList');
    const searchInput = document.getElementById('talkSearch');
    const talkEmpty = document.getElementById('talkEmpty');

    // 迴ｾ蝨ｨ陦ｨ遉ｺ荳ｭ縺ｮ逶ｸ謇紀D
    let currentUserId = null;

    // 繝｢繝ｼ繝繝ｫ隕∫ｴ・亥虚逧・↓蜿門ｾ暦ｼ・    let imageModal = null;
    let imageModalImg = null;
    let imageModalClose = null;
    let imageModalOverlay = null;

    // ------------------------------------
    // 繝｢繝ｼ繝繝ｫ髢｢騾｣
    // ------------------------------------
    function initModalElements() {
        imageModal = document.getElementById('imageModal');
        imageModalImg = imageModal ? document.getElementById('imageModalImg') : null;
        imageModalClose = imageModal ? imageModal.querySelector('.image-modal-close') : null;
        imageModalOverlay = imageModal ? imageModal.querySelector('.image-modal-overlay') : null;
    }

    function openImageModal(imageSrc, imageAlt) {
        initModalElements();
        if (!imageModal || !imageModalImg || !imageSrc) {
            return;
        }
        imageModalImg.src = imageSrc;
        imageModalImg.alt = imageAlt || '';
        imageModal.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    function closeImageModal() {
        initModalElements();
        if (!imageModal) {
            return;
        }
        imageModal.hidden = true;
        if (imageModalImg) {
            imageModalImg.src = '';
        }
        document.body.style.overflow = '';
    }

    function setupModalEvents() {
        initModalElements();
        if (!imageModal) {
            return;
        }

        // 髢峨§繧九・繧ｿ繝ｳ
        if (imageModalClose) {
            imageModalClose.addEventListener('click', function (e) {
                e.stopPropagation();
                closeImageModal();
            });
        }

        // 繧ｪ繝ｼ繝舌・繝ｬ繧､繧ｯ繝ｪ繝・け
        if (imageModalOverlay) {
            imageModalOverlay.addEventListener('click', closeImageModal);
        }

        // ESC繧ｭ繝ｼ
        document.addEventListener('keydown', function (e) {
            initModalElements();
            if (e.key === 'Escape' && imageModal && !imageModal.hidden) {
                closeImageModal();
            }
        });
    }

    // 蛻晄悄蛹匁凾縺ｫ繝｢繝ｼ繝繝ｫ繧､繝吶Φ繝医ｒ險ｭ螳・    setupModalEvents();

    // ------------------------------------
// HTMLエスケープ（動的挿入時のXSS対策）
    // ------------------------------------
    function escapeHTML(text) {
        return String(text).replace(/[&<>"']/g, function (char) {
            return {
                '&': '&',
                '<': '<',
                '>': '>',
                '"': '"',
                "'": '&#039;'
            }[char];
        });
    }

    // ------------------------------------
    // 繝医・繧ｯ讀懃ｴ｢
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
    // 莨夊ｩｱ繧ｨ繝ｪ繧｢縺ｮHTML繧貞叙蠕励＠縺ｦ蟾ｮ縺玲崛縺・    // ------------------------------------
    async function loadRoom(userId) {
        if (!messageRoomPanel) {
            console.error('messageRoomPanel 縺瑚ｦ九▽縺九ｊ縺ｾ縺帙ｓ');
            return;
        }

        // 隱ｭ縺ｿ霎ｼ縺ｿ荳ｭ陦ｨ遉ｺ
        messageRoomPanel.innerHTML =
            '<div class="room-loading">' +
            '<p>隱ｭ縺ｿ霎ｼ縺ｿ荳ｭ...</p>' +
            '</div>';

        try {
            const url = new URL('message-room.php', window.location.href);
            url.searchParams.set('user_id', userId);
            url.searchParams.set('partial', '1');

            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!response.ok) {
                throw new Error('莨夊ｩｱ縺ｮ隱ｭ縺ｿ霎ｼ縺ｿ縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
            }

            const html = await response.text();

            messageRoomPanel.innerHTML = html;

            // 繝｢繝ｼ繝繝ｫ隕∫ｴ繧貞・蛻晄悄蛹厄ｼ・TML縺悟・繧梧崛繧上ｋ縺溘ａ・・            setupModalEvents();

            // 繝｡繝・そ繝ｼ繧ｸ谺・ｒ譛譁ｰ縺ｸ繧ｹ繧ｯ繝ｭ繝ｼ繝ｫ
            scrollToBottom();

        } catch (error) {
            console.error(error);
            messageRoomPanel.innerHTML =
                '<div class="room-error">' +
                '<p>莨夊ｩｱ繧定ｪｭ縺ｿ霎ｼ繧√∪縺帙ｓ縺ｧ縺励◆縲・/p>' +
                '</div>';
        }
    }

    // ------------------------------------
    // 繝医・繧ｯ逶ｸ謇九ｒ繧ｯ繝ｪ繝・け・医う繝吶Φ繝亥ｧ碑ｭｲ・・    // ------------------------------------
    if (talkList) {
        talkList.addEventListener('click', function (event) {
            const talkItem = event.target.closest('.talk-item');

            if (!talkItem) {
                return;
            }

            // 騾壼ｸｸ縺ｮ繝壹・繧ｸ驕ｷ遘ｻ繧呈ｭ｢繧√ｋ
            event.preventDefault();

            const userId = parseInt(talkItem.dataset.userId, 10);

            if (!userId) {
                return;
            }

            currentUserId = userId;

            // 驕ｸ謚樒憾諷九ｒ譖ｴ譁ｰ
            talkList.querySelectorAll('.talk-item').forEach(function (item) {
                item.classList.toggle('active', item === talkItem);
            });

            loadRoom(userId);
        });
    }

    // ------------------------------------
    // 莨夊ｩｱ繧ｨ繝ｪ繧｢蜀・・繧､繝吶Φ繝亥ｧ碑ｭｲ
    // ・・etch縺ｧ蠕後°繧画諺蜈･縺輔ｌ縺溯ｦ∫ｴ縺ｫ繧ょｯｾ蠢懶ｼ・    // ------------------------------------
    if (messageRoomPanel) {

        // 繧ｯ繝ｪ繝・け邉ｻ・亥膚蜩∫判蜒上Δ繝ｼ繝繝ｫ繝ｻ蝠・刀驕ｸ謚槭・騾∽ｿ｡繝懊ち繝ｳ・・        messageRoomPanel.addEventListener('click', function (event) {

            // 蝠・刀逕ｻ蜒上け繝ｪ繝・け縺ｧ繝｢繝ｼ繝繝ｫ陦ｨ遉ｺ
            const imageContainer = event.target.closest('.product-card-image');
            if (imageContainer) {
                // 逕ｻ蜒上け繝ｪ繝・け譎ゅ・繝ｪ繝ｳ繧ｯ遘ｻ蜍輔ｒ髦ｲ縺・                event.preventDefault();
                event.stopPropagation();

                const imageSrc = imageContainer.dataset.imageSrc;
                const imageAlt = imageContainer.dataset.imageAlt;

                if (imageSrc) {
                    openImageModal(imageSrc, imageAlt);
                }
                return;
            }

            // 蝠・刀驕ｸ謚樊ｬ・・髢矩哩
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
                        ? '荳隕ｧ繧帝哩縺倥ｋ'
                        : '蝠・刀繧帝√ｋ';
                }
                return;
            }

            // 繧ｹ繝槭・逕ｨ・壻ｸ隕ｧ縺ｫ謌ｻ繧具ｼ井ｼ夊ｩｱ繝代ロ繝ｫ繧帝哩縺倥ｋ・・            const backLink = event.target.closest('.room-back');
            if (backLink) {
                event.preventDefault();
                closeRoomOnMobile();
                return;
            }
        });

        // 騾∽ｿ｡邉ｻ・磯壼ｸｸ繝｡繝・そ繝ｼ繧ｸ繝ｻ蝠・刀邏ｹ莉具ｼ・        messageRoomPanel.addEventListener('submit', function (event) {
            const form = event.target;

            // 騾壼ｸｸ繝｡繝・そ繝ｼ繧ｸ騾∽ｿ｡
            if (form.id === 'messageForm') {
                event.preventDefault();
                sendTextMessage(form);
                return;
            }

            // 蝠・刀邏ｹ莉矩∽ｿ｡
            if (form.classList.contains('product-send-form')) {
                event.preventDefault();
                sendGoodsMessage(form);
                return;
            }
        });
    }

    // ------------------------------------
    // 繧ｹ繝槭・縺ｧ莨夊ｩｱ繧帝哩縺倥※荳隕ｧ縺ｫ謌ｻ繧・    // ------------------------------------
    function closeRoomOnMobile() {
        if (!messageRoomPanel) {
            return;
        }

        messageRoomPanel.innerHTML =
            '<div class="talk-welcome">' +
            '<div class="welcome-icon"><i class="fa-regular fa-comments"></i></div>' +
            '<h2>繝｡繝・そ繝ｼ繧ｸ</h2>' +
            '<p>蟾ｦ蛛ｴ縺ｮ繝医・繧ｯ荳隕ｧ縺九ｉ逶ｸ謇九ｒ驕ｸ謚槭☆繧九→縲√％縺薙↓莨夊ｩｱ縺瑚｡ｨ遉ｺ縺輔ｌ縺ｾ縺吶・/p>' +
            '</div>';

        currentUserId = null;

        if (talkList) {
            talkList.querySelectorAll('.talk-item').forEach(function (item) {
                item.classList.remove('active');
            });
        }
    }

    // ------------------------------------
    // 騾壼ｸｸ繝｡繝・そ繝ｼ繧ｸ騾∽ｿ｡・・etch・・    // ------------------------------------
    async function sendTextMessage(form) {
        if (!currentUserId) {
            return;
        }

        const input = form.querySelector('#messageInput');
        const content = input ? input.value.trim() : '';

        // 遨ｺ繝｡繝・そ繝ｼ繧ｸ縺ｮ諡貞凄
        if (content === '') {
            return;
        }

        // 譁・ｭ玲焚蛻ｶ髯・        if (content.length > 2000) {
            alert('繝｡繝・そ繝ｼ繧ｸ縺ｯ2000譁・ｭ嶺ｻ･蜀・〒蜈･蜉帙＠縺ｦ縺上□縺輔＞縲・);
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
                throw new Error('騾∽ｿ｡縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
            }

            // 蜈･蜉帙ｒ繧ｯ繝ｪ繧｢縺励※莨夊ｩｱ繧貞・隱ｭ縺ｿ霎ｼ縺ｿ
            if (input) {
                input.value = '';
            }

            await loadRoom(currentUserId);

        } catch (error) {
            console.error(error);
            alert('繝｡繝・そ繝ｼ繧ｸ縺ｮ騾∽ｿ｡縺ｫ螟ｱ謨励＠縺ｾ縺励◆縲・);
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    }

    // ------------------------------------
    // 蝠・刀邏ｹ莉九Γ繝・そ繝ｼ繧ｸ騾∽ｿ｡・・etch・・    // ------------------------------------
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
                throw new Error('騾∽ｿ｡縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
            }

            // 蝠・刀驕ｸ謚樊ｬ・ｒ髢峨§繧・            const picker = document.getElementById('productPicker');
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
                    label.textContent = '蝠・刀繧帝√ｋ';
                }
            }

            await loadRoom(currentUserId);

        } catch (error) {
            console.error(error);
            alert('蝠・刀縺ｮ騾∽ｿ｡縺ｫ螟ｱ謨励＠縺ｾ縺励◆縲・);
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        }
    }

    // ------------------------------------
    // 譛譁ｰ繝｡繝・そ繝ｼ繧ｸ縺ｾ縺ｧ繧ｹ繧ｯ繝ｭ繝ｼ繝ｫ
    // ------------------------------------
    function scrollToBottom() {
        const roomMessages = document.getElementById('roomMessages');

        if (roomMessages) {
            roomMessages.scrollTop = roomMessages.scrollHeight;
        }
    }

})();


