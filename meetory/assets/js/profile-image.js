document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('profileImageForm');
    const preview = document.querySelector('#profileImagePreview img');
    const uploadInput = document.getElementById('profileUpload');
    const uploadError = document.getElementById('profileUploadError');

    if (!form || !preview || !uploadInput || !uploadError) {
        return;
    }

    const choiceInputs = form.querySelectorAll('input[name="profile_image_choice"]');
    let activeObjectUrl = null;
    const showUploadError = (message) => {
        uploadError.textContent = message;
        uploadError.hidden = message === '';
    };

    choiceInputs.forEach((input) => {
        input.addEventListener('change', () => {
            if (input.checked && input.dataset.imageUrl) {
                preview.src = input.dataset.imageUrl;
            }
        });
    });

    uploadInput.addEventListener('change', () => {
        const file = uploadInput.files && uploadInput.files[0];
        if (!file) {
            return;
        }

        const maxSize = Number(uploadInput.dataset.maxSize);
        if (!/\.(jpe?g|png|webp)$/i.test(file.name)) {
            uploadInput.value = '';
            showUploadError('JPG・PNG・WebP形式の画像を選択してください。');
            return;
        }
        if (file.size > maxSize) {
            uploadInput.value = '';
            showUploadError('画像は5MB以下にしてください。');
            return;
        }
        showUploadError('');

        const uploadChoice = form.querySelector(
            'input[name="profile_image_choice"][value="upload"]'
        );
        if (uploadChoice) {
            uploadChoice.checked = true;
        }

        if (activeObjectUrl) {
            URL.revokeObjectURL(activeObjectUrl);
        }
        activeObjectUrl = URL.createObjectURL(file);
        preview.src = activeObjectUrl;
    });

    form.addEventListener('submit', (event) => {
        if (!uploadError.hidden) {
            event.preventDefault();
            uploadInput.focus();
            return;
        }

        const selected = form.querySelector(
            'input[name="profile_image_choice"]:checked'
        );
        const file = uploadInput.files && uploadInput.files[0];

        if (
            file &&
            (
                !/\.(jpe?g|png|webp)$/i.test(file.name) ||
                file.size > Number(uploadInput.dataset.maxSize)
            )
        ) {
            event.preventDefault();
            showUploadError(
                file.size > Number(uploadInput.dataset.maxSize)
                    ? '画像は5MB以下にしてください。'
                    : 'JPG・PNG・WebP形式の画像を選択してください。'
            );
            uploadInput.focus();
            return;
        }

        if (selected && selected.value === 'upload' && !file) {
            return;
        }
    });
});
