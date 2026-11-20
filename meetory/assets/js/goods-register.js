// =============================================
// 商品画像プレビュー
// =============================================

const goodsImage = document.getElementById('goods_image');

if (goodsImage) {
    goodsImage.addEventListener('change', function() {
        const file = this.files[0];

        if (file) {
            const preview = document.getElementById('preview');
            preview.src = URL.createObjectURL(file);
            preview.style.display = 'block';
        }
    });
}


// =============================================
// 出品タイプ切り替え
// =============================================

(function() {
    const normalFields = document.querySelector('.normal-fields');
    const auctionFields = document.querySelector('.auction-fields');

    function toggleFields() {
        const checked = document.querySelector('input[name="listing_type"]:checked');

        if (!checked || !normalFields || !auctionFields) return;

        if (checked.value === 'normal') {
            normalFields.classList.remove('is-hidden');
            auctionFields.classList.add('is-hidden');

            // オークション必須項目を一時的に不要にする
            auctionFields.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });

            normalFields.querySelector('input[name="price"]').setAttribute('required', '');

        } else {
            auctionFields.classList.remove('is-hidden');
            normalFields.classList.add('is-hidden');

            // 通常出品の必須項目を一時的に不要にする
            normalFields.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });

            auctionFields.querySelectorAll(
                'input[name="start_price"], input[name="end_at"], select[name="auto_extend"]'
            ).forEach(function(el) {
                el.setAttribute('required', '');
            });
        }
    }

    document.querySelectorAll('input[name="listing_type"]').forEach(function(radio) {
        radio.addEventListener('change', toggleFields);
    });

    // 初期表示
    toggleFields();
})();


// =============================================
// AIアシスト表示切り替え
// =============================================

(function() {
    const aiModeRadios = document.querySelectorAll(
        'input[name="listing_assist_mode"]'
    );

    const aiPanel = document.getElementById('ai-assist-panel');

    if (!aiPanel) return;

    function toggleAiPanel() {
        const checked = document.querySelector(
            'input[name="listing_assist_mode"]:checked'
        );

        if (checked && checked.value === 'ai') {
            aiPanel.classList.remove('is-hidden');
        } else {
            aiPanel.classList.add('is-hidden');
        }
    }

    aiModeRadios.forEach(function(radio) {
        radio.addEventListener('change', toggleAiPanel);
    });

    toggleAiPanel();
})();


// =============================================
// AIチャット機能
// =============================================

(function() {
    const chatMessages = document.getElementById('ai-chat-messages');
    const chatInput = document.getElementById('ai-chat-input');
    const sendButton = document.getElementById('ai-chat-send');
    const chatStatus = document.getElementById('ai-chat-status');

    const titleResult = document.getElementById('ai-title-result');
    const descriptionResult = document.getElementById('ai-description-result');

    const suggestedTitle = document.getElementById('ai-suggested-title');
    const suggestedDescription = document.getElementById('ai-suggested-description');

    const applyTitleButton = document.getElementById('ai-apply-title');
    const applyDescriptionButton = document.getElementById('ai-apply-description');

    if (
        !chatMessages ||
        !chatInput ||
        !sendButton
    ) {
        return;
    }

    // AI会話ID
    // 最初の通信ではnull、2回目以降はサーバーから受け取ったIDを使用
    let aiDataId = null;

    // 現在のAI提案
    let currentSuggestedTitle = null;
    let currentSuggestedDescription = null;


    // -----------------------------------------
    // チャットメッセージを画面に追加
    // -----------------------------------------

    function addChatMessage(message, role) {
        const messageElement = document.createElement('div');

        messageElement.classList.add('ai-chat-message');

        if (role === 'user') {
            messageElement.classList.add('user-message');
        } else {
            messageElement.classList.add('model-message');
        }

        // HTMLとして解釈させず、安全に文字列を表示する
        messageElement.textContent = message;

        chatMessages.appendChild(messageElement);

        // 最新メッセージまでスクロール
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }


    // -----------------------------------------
    // 現在の商品情報を取得
    // -----------------------------------------

    function getProductInfo() {
        const goodsName = document.getElementById('goods_name');
        const description = document.getElementById('description');
        const goodsCondition = document.getElementById('goods_condition');
        const category = document.getElementById('category_id');

        return {
            goods_name: goodsName ? goodsName.value.trim() : '',
            description: description ? description.value.trim() : '',
            goods_condition: goodsCondition ? goodsCondition.value : '',
            category: category && category.selectedIndex >= 0
                ? category.options[category.selectedIndex].text
                : ''
        };
    }


    // -----------------------------------------
    // AIにメッセージを送信
    // -----------------------------------------

    async function sendAiMessage() {
        const message = chatInput.value.trim();

        if (!message) {
            chatStatus.textContent = 'メッセージを入力してください。';
            return;
        }

        const csrfToken = document.querySelector(
            'input[name="csrf_token"]'
        )?.value;

        if (!csrfToken) {
            chatStatus.textContent = 'CSRFトークンを取得できませんでした。';
            return;
        }

        // ユーザーのメッセージを表示
        addChatMessage(message, 'user');

        chatInput.value = '';
        chatStatus.textContent = 'AIが回答を作成しています...';

        sendButton.disabled = true;
        chatInput.disabled = true;

        try {
            const response = await fetch(
                '/Meetory/meetory/goods/ai-listing-assist.php',
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        ai_data_id: aiDataId,
                        message: message,
                        product_info: getProductInfo()
                    })
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(
                    data.error || 'AIとの通信に失敗しました。'
                );
            }

            // 会話IDを保存
            aiDataId = data.ai_data_id;

            // AIの返答を表示
            addChatMessage(data.message, 'model');

            // ---------------------------------
            // 商品名の提案があれば表示
            // ---------------------------------

            if (
                typeof data.suggested_title === 'string' &&
                data.suggested_title.trim() !== ''
            ) {
                currentSuggestedTitle = data.suggested_title;

                suggestedTitle.textContent = currentSuggestedTitle;
                titleResult.classList.remove('is-hidden');
            }

            // ---------------------------------
            // 商品説明文の提案があれば表示
            // ---------------------------------

            if (
                typeof data.suggested_description === 'string' &&
                data.suggested_description.trim() !== ''
            ) {
                currentSuggestedDescription = data.suggested_description;

                suggestedDescription.textContent = currentSuggestedDescription;
                descriptionResult.classList.remove('is-hidden');
            }

            chatStatus.textContent = '';

        } catch (error) {
            console.error('AIチャットエラー:', error);

            chatStatus.textContent = error.message;
        } finally {
            sendButton.disabled = false;
            chatInput.disabled = false;
            chatInput.focus();
        }
    }


    // -----------------------------------------
    // 送信ボタン
    // -----------------------------------------

    sendButton.addEventListener('click', sendAiMessage);


    // Ctrl + Enterでも送信できるようにする
    chatInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && event.ctrlKey) {
            event.preventDefault();
            sendAiMessage();
        }
    });


    // -----------------------------------------
    // 商品名をフォームに反映
    // -----------------------------------------

    applyTitleButton.addEventListener('click', function() {
        if (!currentSuggestedTitle) return;

        const goodsName = document.getElementById('goods_name');

        goodsName.value = currentSuggestedTitle;

        chatStatus.textContent = 'AIの提案した商品名を反映しました。';
    });


    // -----------------------------------------
    // 商品説明文をフォームに反映
    // -----------------------------------------

    applyDescriptionButton.addEventListener('click', function() {
        if (!currentSuggestedDescription) return;

        const description = document.getElementById('description');

        description.value = currentSuggestedDescription;

        chatStatus.textContent = 'AIの提案した商品説明文を反映しました。';
    });

})();