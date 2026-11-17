(function () {
    var bidForm = document.getElementById('bid-form');
    var bidBtn = document.getElementById('bid-submit-btn');
    var bidMessage = document.getElementById('bid-message');
    var currentPriceEl = document.getElementById('current-price');
    var endTimeEl = document.getElementById('end-time');

    if (!bidForm || !bidBtn || !bidMessage) {
        return;
    }

    function showMessage(text, type) {
        bidMessage.textContent = text;
        bidMessage.className = 'bid-message bid-message--' + type;
        bidMessage.style.display = 'block';
    }

    function hideMessage() {
        bidMessage.style.display = 'none';
        bidMessage.textContent = '';
        bidMessage.className = 'bid-message';
    }

    function setButtonLoading(isLoading) {
        bidBtn.disabled = isLoading;
    }

    bidForm.addEventListener('submit', function (event) {
        event.preventDefault();
        event.stopPropagation();

        hideMessage();
        setButtonLoading(true);

        var formData = new FormData(bidForm);

        fetch(bidForm.action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(
                    function (data) {
                        if (!response.ok) {
                            var err = new Error(
                                data.message ||
                                'HTTP ' + response.status + ' エラーが発生しました。'
                            );
                            err.status = response.status;
                            err.data = data;
                            throw err;
                        }
                        return data;
                    },
                    function () {
                        // JSONとして解析できない場合
                        if (!response.ok) {
                            var err = new Error(
                                'HTTP ' + response.status + ' エラーが発生しました。'
                            );
                            err.status = response.status;
                            throw err;
                        }
                        throw new Error('レスポンスの解析に失敗しました。');
                    }
                );
            })
            .then(function (data) {
                if (data.success) {
                    if (data.is_buyout) {
                        // 即決成立：購入確認画面へ遷移
                        showMessage(
                            '即決成立しました！購入手続きへ進みます。',
                            'buyout'
                        );
                        setTimeout(function () {
                            window.location.href =
                                'auction-purchase.php?goods_id=' +
                                encodeURIComponent(formData.get('goods_id'));
                        }, 1500);
                    } else {
                        // 通常入札成功：価格と終了時刻を更新
                        showMessage('入札を受け付けました。', 'success');

                        // 新しい現在価格は入札した金額
                        var bidAmount = formData.get('bid_amount');
                        if (currentPriceEl && bidAmount) {
                            currentPriceEl.textContent =
                                numberFormatWithCommas(parseInt(bidAmount, 10));
                        }

                        if (endTimeEl && data.new_end_at) {
                            endTimeEl.textContent = data.new_end_at;
                        }

                        // 入札履歴に新しい入札を先頭に追加
                        var bidHistoryList = document.getElementById('bid-history-list');
                        if (
                            bidHistoryList &&
                            data.user_name &&
                            data.bid_at &&
                            bidAmount
                        ) {
                            var li = document.createElement('li');
                            li.textContent =
                                data.user_name + '：' +
                                numberFormatWithCommas(parseInt(bidAmount, 10)) + '円 (' +
                                data.bid_at + ')';
                            bidHistoryList.insertBefore(
                                li,
                                bidHistoryList.firstChild
                            );
                        }
                    }
                } else {
                    showMessage(data.message || '入札に失敗しました。', 'error');
                }
            })
            .catch(function (error) {
                var msg = '通信エラーが発生しました。';
                if (error instanceof SyntaxError) {
                    msg = 'レスポンスの解析に失敗しました。';
                } else if (error.message) {
                    msg = error.message;
                }
                showMessage(msg, 'error');
            })
            .finally(function () {
                setButtonLoading(false);
            });
    });

    // カンマ区切りの数値フォーマット
    function numberFormatWithCommas(num) {
        return String(num).replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,');
    }
})();
