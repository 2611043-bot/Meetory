
document.addEventListener('DOMContentLoaded', function () {

    // レビュー一覧を取得
    const reviews = document.querySelectorAll('.review-card');

    // ボタンを取得
    const showMoreButton = document.getElementById('show-more-button');
    const collapseButton = document.getElementById('collapse-button');

    // レビューが存在しない場合は終了
    if (reviews.length === 0) {
        return;
    }

    // 最初に表示する件数
    const initialCount = 3;

    // 追加で表示する件数
    const additionalCount = 5;

    // 現在表示している件数
    let visibleCount = initialCount;

    // レビューの表示状態を更新
    function updateReviews() {

        reviews.forEach(function (review, index) {

            if (index < visibleCount) {
                review.style.display = '';
            } else {
                review.style.display = 'none';
            }

        });

        // すべてのレビューが表示されているか確認
        const allVisible = visibleCount >= reviews.length;

        // もっと見るボタンの表示
        if (showMoreButton) {
            showMoreButton.hidden = allVisible;
        }

        // 非表示にするボタンの表示
        if (collapseButton) {
            collapseButton.hidden =
                visibleCount <= initialCount || !allVisible;
        }
    }

    // もっと見るボタン
    if (showMoreButton) {

        showMoreButton.addEventListener('click', function () {

            visibleCount += additionalCount;

            if (visibleCount > reviews.length) {
                visibleCount = reviews.length;
            }

            updateReviews();

        });

    }

    // 非表示にするボタン
    if (collapseButton) {

        collapseButton.addEventListener('click', function () {

            visibleCount = initialCount;

            updateReviews();

        });

    }

    // 最初の表示設定
    updateReviews();

});
