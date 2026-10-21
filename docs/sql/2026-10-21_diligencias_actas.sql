CREATE TABLE IF NOT EXISTS diligencias_actas (
    diligencia_id INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    acta_id INT NOT NULL,
    PRIMARY KEY (diligencia_id, tipo, acta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
