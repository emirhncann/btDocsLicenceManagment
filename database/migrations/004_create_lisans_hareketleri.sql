CREATE TABLE IF NOT EXISTS LisansHareketleri (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lisans_id INT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    detay TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lisans_hareketleri_lisans_id (lisans_id),
    CONSTRAINT fk_lisans_hareketleri_lisans
        FOREIGN KEY (lisans_id) REFERENCES Lisanslar (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
