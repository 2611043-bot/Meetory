ALTER TABLE users
    ADD COLUMN profile_image_type VARCHAR(10) NOT NULL DEFAULT 'preset',
    ADD COLUMN profile_image VARCHAR(64) NOT NULL DEFAULT 'preset-01';
