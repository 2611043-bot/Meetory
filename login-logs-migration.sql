-- ログイン成功・失敗履歴記録用テーブル作成マイグレーション
-- ログイン試行ごとにユーザーID、入力ユーザー名、IPアドレス、成否、日時を記録する。
-- パスワードやセッションIDは保存しない。

CREATE TABLE IF NOT EXISTS login_logs (
    log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NULL COMMENT '該当ユーザーが存在する場合のユーザーID。存在しない場合はNULL',
    user_name VARCHAR(255) NOT NULL COMMENT 'ログイン時に入力されたユーザー名',
    ip_address VARCHAR(45) NOT NULL COMMENT '接続元IPアドレス（IPv4/IPv6対応）',
    success TINYINT(1) NOT NULL COMMENT '成功1、失敗0',
    attempted_at DATETIME NOT NULL COMMENT 'ログイン試行日時',
    PRIMARY KEY (log_id),
    INDEX idx_user_id (user_id),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_success (success),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
