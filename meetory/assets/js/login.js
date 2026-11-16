document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const eyeIcon = document.getElementById('eyeIcon');

    if (togglePasswordBtn && passwordInput && eyeIcon) {
        togglePasswordBtn.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

            if (isPassword) {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
    }

    // エラー表示と赤枠を消す処理
    const userNameInput = document.getElementById('user_name');
    const errorMessage = document.querySelector('.error-message');

    function clearError() {
        if (userNameInput) {
            userNameInput.classList.remove('input-error');
        }
        if (passwordInput) {
            passwordInput.classList.remove('input-error');
        }
        if (errorMessage) {
            errorMessage.remove();
        }
    }

    if (userNameInput) {
        userNameInput.addEventListener('input', clearError);
    }
    if (passwordInput) {
        passwordInput.addEventListener('input', clearError);
    }
});
