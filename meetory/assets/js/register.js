document.addEventListener('DOMContentLoaded', function() {
    // --- 1. パスワード表示・非表示の切り替え処理 ---
    const toggleBtns = document.querySelectorAll('.toggle-password-btn');

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const eyeIcon = this.querySelector('i');

            if (targetInput && eyeIcon) {
                const isPassword = targetInput.getAttribute('type') === 'password';
                targetInput.setAttribute('type', isPassword ? 'text' : 'password');

                if (isPassword) {
                    eyeIcon.classList.remove('fa-eye-slash');
                    eyeIcon.classList.add('fa-eye');
                } else {
                    eyeIcon.classList.remove('fa-eye');
                    eyeIcon.classList.add('fa-eye-slash');
                }
            }
        });
    });

    // --- 2. パスワード強度判定処理 ---
    const passwordInput = document.getElementById('password');
    const wrapper = document.getElementById('strength-wrapper');
    const bar = document.getElementById('strength-bar');
    const text = document.getElementById('strength-text');

    if (!passwordInput || !wrapper || !bar || !text) return;

    passwordInput.addEventListener('input', function() {
        const password = this.value;

        // 未入力時は非表示
        if (password.length === 0) {
            wrapper.style.display = 'none';
            bar.style.width = '0%';
            text.textContent = '';
            return;
        }

        wrapper.style.display = 'block';

        let score = 0;

        // 1. 文字数による基本スコア
        if (password.length >= 1) score += 1;
        if (password.length >= 6) score += 1;
        if (password.length >= 8) score += 1;

        // 2. 文字種の多様性チェック
        if (/[0-9]/.test(password) && /[a-zA-Z]/.test(password)) score += 1;
        if (/[A-Z]/.test(password) || /[^a-zA-Z0-9]/.test(password)) score += 1;

        // 3. 安全性（危険パターンの減点チェック）
        const lowerPass = password.toLowerCase();
        const weakPatterns = ['1234', 'password', 'qwerty', 'abc', '0000', '1111', 'admin'];
        const isWeakWord = weakPatterns.some(pattern => lowerPass.includes(pattern));
        const hasRepeatingChar = /(.)\1\1/.test(password);

        if (isWeakWord) score -= 2;
        if (hasRepeatingChar) score -= 1;

        // スコアの範囲制限 (0〜5)
        score = Math.max(0, score);

        // 4. 強度表示（強・中・弱）
        if (score <= 2) {
            bar.style.width = '33%';
            bar.style.backgroundColor = '#f44336';
            text.textContent = '強度: 弱';
            text.style.color = '#f44336';
        } else if (score <= 3) {
            bar.style.width = '66%';
            bar.style.backgroundColor = '#ff9800';
            text.textContent = '強度: 中';
            text.style.color = '#ff9800';
        } else {
            bar.style.width = '100%';
            bar.style.backgroundColor = '#4caf50';
            text.textContent = '強度: 強';
            text.style.color = '#4caf50';
        }
    });
});
