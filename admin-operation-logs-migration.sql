-- 管理者操作履歴記録用テーブル作成マイグレーション
-- 管理者による操作（制限設定・解除、通報対応、お問い合わせ返信等）を記録する。
-- パスワードやセッションIDは保存しない。

CREATE TABLE IF NOT EXISTS admin_operation_logs (
    log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT NULL COMMENT '操作した管理者のユーザーID。ユーザー削除時はNULL',
    action_type VARCHAR(50) NOT NULL COMMENT '操作種別',
    target_type VARCHAR(50) NULL COMMENT '対象種別',
    target_id INT UNSIGNED NULL COMMENT '対象ID',
    details TEXT NULL COMMENT '操作の詳細',
    ip_address VARCHAR(45) NULL COMMENT '接続元IPアドレス',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作日時',
    PRIMARY KEY (log_id),
    INDEX idx_admin_id (admin_id),
    INDEX idx_action_type (action_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (admin_id) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
