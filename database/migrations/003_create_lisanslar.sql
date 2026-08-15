CREATE TABLE IF NOT EXISTS Lisanslar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    firma_id INT UNSIGNED NOT NULL,
    baslangic_tarihi DATE NOT NULL,
    bitis_tarihi DATE NOT NULL,
    api_key VARCHAR(64) NOT NULL,
    status ENUM('aktif', 'pasif', 'suresi_dolmus') NOT NULL DEFAULT 'aktif',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lisanslar_api_key (api_key),
    KEY idx_lisanslar_firma_id (firma_id),
    KEY idx_lisanslar_status (status),
    CONSTRAINT fk_lisanslar_firma
        FOREIGN KEY (firma_id) REFERENCES Firmalar (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lisanslar_created_by
        FOREIGN KEY (created_by) REFERENCES AdminUsers (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
