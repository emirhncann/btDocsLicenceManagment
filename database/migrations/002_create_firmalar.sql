CREATE TABLE IF NOT EXISTS Firmalar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    vkn VARCHAR(20) NOT NULL,
    firma_adi VARCHAR(200) NOT NULL,
    email VARCHAR(200) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_firmalar_vkn (vkn),
    KEY idx_firmalar_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
