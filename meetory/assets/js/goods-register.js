document.getElementById('goods_image').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const preview = document.getElementById('preview');
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    }
});

// 出品タイプ切り替え
(function() {
    const normalFields = document.querySelector('.normal-fields');
    const auctionFields = document.querySelector('.auction-fields');

    function toggleFields() {
        const checked = document.querySelector('input[name="listing_type"]:checked');
        if (!checked) return;

        if (checked.value === 'normal') {
            normalFields.style.display = '';
            auctionFields.style.display = 'none';
            // オークション必須項目を一時的に不要にする
            auctionFields.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
            normalFields.querySelector('input[name="price"]').setAttribute('required', '');
        } else {
            auctionFields.style.display = '';
            normalFields.style.display = 'none';
            // 通常必須項目を一時的に不要にする
            normalFields.querySelectorAll('[required]').forEach(function(el) {
                el.removeAttribute('required');
            });
            auctionFields.querySelectorAll('input[name="start_price"], input[name="end_at"], select[name="auto_extend"]').forEach(function(el) {
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
