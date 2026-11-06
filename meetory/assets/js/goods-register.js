document.getElementById('goods_image').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const preview = document.getElementById('preview');
        preview.src = URL.createObjectURL(file);
        preview.style.display = 'block';
    }
});
