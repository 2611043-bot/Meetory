// =============================================
// 商品画像プレビュー
// =============================================

const goodsImage = document.getElementById('goods_image');

if (goodsImage) {
    let previewUrl = null;

    goodsImage.addEventListener('change', function() {
        const file = this.files[0];
        const preview = document.getElementById('preview');

        if (!preview) return;

        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        if (file) {
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.style.display = 'block';
        } else {
            preview.removeAttribute('src');
            preview.style.display = 'none';
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
        const checked = document.querySelector(
            'input[name="listing_type"]:checked'
        );

        if (!checked || !normalFields || !auctionFields) return;

        if (checked.value === 'normal') {
            normalFields.classList.remove('is-hidden');
            auctionFields.classList.add('is-hidden');

            auctionFields.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });

            const price = normalFields.querySelector('input[name="price"]');

            if (price) {
                price.setAttribute('required', '');
            }

        } else {
            auctionFields.classList.remove('is-hidden');
            normalFields.classList.add('is-hidden');

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

    if (!chatMessages || !chatInput || !sendButton) {
        return;
    }

    // AI会話ID
    let aiDataId = null;

    // 現在のAI提案
    let currentSuggestedTitle = null;
    let currentSuggestedDescription = null;

    // 二重送信防止
    let isSending = false;


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

        // HTMLとして解釈させず、安全に文字列として表示
        messageElement.textContent = message;

        chatMessages.appendChild(messageElement);
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
    // 古い提案をリセット
    // -----------------------------------------

    function clearSuggestions() {
        currentSuggestedTitle = null;
        currentSuggestedDescription = null;

        if (titleResult) {
            titleResult.classList.add('is-hidden');
        }

        if (descriptionResult) {
            descriptionResult.classList.add('is-hidden');
        }

        if (suggestedTitle) {
            suggestedTitle.textContent = '';
        }

        if (suggestedDescription) {
            suggestedDescription.textContent = '';
        }
    }


    // -----------------------------------------
    // エラー表示用メッセージ
    // -----------------------------------------

    function getErrorMessage(data, response) {
        if (data && typeof data.error === 'string' && data.error !== '') {
            return data.error;
        }

        if (response.status === 429) {
            return 'AIの利用回数または利用量の上限に達しました。しばらく時間をおいてください。';
        }

        if (response.status === 502 || response.status === 503) {
            return 'AI側で一時的な問題が発生しています。少し時間をおいて再度お試しください。';
        }

        if (response.status === 504) {
            return 'AIの応答に時間がかかっています。少し時間をおいて再度お試しください。';
        }

        if (response.status === 401) {
            return 'ログイン状態を確認してください。';
        }

        if (response.status === 403) {
            return 'ページを再読み込みしてから、もう一度お試しください。';
        }

        if (response.status >= 500) {
            return 'サーバーでエラーが発生しました。時間をおいて再度お試しください。';
        }

        return 'AIとの通信に失敗しました。入力内容を確認してください。';
    }


    // -----------------------------------------
    // AIにメッセージを送信
    // -----------------------------------------

    async function sendAiMessage() {
        if (isSending) return;

        const message = chatInput.value.trim();

        if (!message) {
            chatStatus.textContent = 'メッセージを入力してください。';
            return;
        }

        const csrfToken = document.querySelector(
            'input[name="csrf_token"]'
        )?.value;

        if (!csrfToken) {
            chatStatus.textContent = 'CSRFトークンを取得できませんでした。ページを再読み込みしてください。';
            return;
        }

        isSending = true;

        addChatMessage(message, 'user');

        chatInput.value = '';
        chatStatus.textContent = 'AIが回答を作成しています...';

        sendButton.disabled = true;
        chatInput.disabled = true;

        clearSuggestions();

        try {
            const formData = new FormData();

            formData.append('csrf_token', csrfToken);
            formData.append('ai_data_id', aiDataId ?? '');
            formData.append('message', message);
            formData.append(
                'product_info',
                JSON.stringify(getProductInfo())
            );

            // 選択されている画像を送信
            // PHP側でハッシュを比較し、同じ画像の再解析を防ぐ
            const imageFile = goodsImage?.files?.[0];

            if (imageFile) {
                formData.append('goods_image', imageFile);
            }

            const response = await fetch(
                '/Meetory/meetory/goods/ai-listing-assist.php',
                {
                    method: 'POST',
                    body: formData
                }
            );

            // JSON以外のレスポンスにも対応
            let data;

            try {
                data = await response.json();
            } catch (jsonError) {
                throw new Error(
                    'サーバーから正しい応答を取得できませんでした。ページを再読み込みして再度お試しください。'
                );
            }

            if (!response.ok || !data.success) {
                throw new Error(getErrorMessage(data, response));
            }

            if (
                typeof data.message !== 'string' ||
                data.message.trim() === ''
            ) {
                throw new Error('AIから有効な返答を取得できませんでした。');
            }

            // 会話IDを保存
            aiDataId = data.ai_data_id;

            // AIの返答を表示
            addChatMessage(data.message, 'model');


            // ---------------------------------
            // 商品名の提案
            // ---------------------------------

            if (
                typeof data.suggested_title === 'string' &&
                data.suggested_title.trim() !== ''
            ) {
                currentSuggestedTitle = data.suggested_title;

                if (suggestedTitle && titleResult) {
                    suggestedTitle.textContent = currentSuggestedTitle;
                    titleResult.classList.remove('is-hidden');
                }
            }


            // ---------------------------------
            // 商品説明文の提案
            // ---------------------------------

            if (
                typeof data.suggested_description === 'string' &&
                data.suggested_description.trim() !== ''
            ) {
                currentSuggestedDescription = data.suggested_description;

                if (suggestedDescription && descriptionResult) {
                    suggestedDescription.textContent =
                        currentSuggestedDescription;

                    descriptionResult.classList.remove('is-hidden');
                }
            }

            chatStatus.textContent = '';

        } catch (error) {
            console.error('AIチャットエラー:', error);

            chatStatus.textContent =
                error.message || 'AIとの通信に失敗しました。';

        } finally {
            isSending = false;
            sendButton.disabled = false;
            chatInput.disabled = false;
            chatInput.focus();
        }
    }


    // -----------------------------------------
    // 送信ボタン
    // -----------------------------------------

    sendButton.addEventListener('click', sendAiMessage);


    // Ctrl + Enterでも送信
    chatInput.addEventListener('keydown', function(event) {
        if (event.key === 'Enter' && event.ctrlKey) {
            event.preventDefault();
            sendAiMessage();
        }
    });


    // -----------------------------------------
    // 商品名をフォームに反映
    // -----------------------------------------

    if (applyTitleButton) {
        applyTitleButton.addEventListener('click', function() {
            if (!currentSuggestedTitle) return;

            const goodsName = document.getElementById('goods_name');

            if (goodsName) {
                goodsName.value = currentSuggestedTitle;
                chatStatus.textContent =
                    'AIの提案した商品名を反映しました。';
            }
        });
    }


    // -----------------------------------------
    // 商品説明文をフォームに反映
    // -----------------------------------------

    if (applyDescriptionButton) {
        applyDescriptionButton.addEventListener('click', function() {
            if (!currentSuggestedDescription) return;

            const description = document.getElementById('description');

            if (description) {
                description.value = currentSuggestedDescription;
                chatStatus.textContent =
                    'AIの提案した商品説明文を反映しました。';
            }
        });
    }

})();