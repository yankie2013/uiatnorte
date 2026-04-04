ALTER TABLE accidentes
    ADD COLUMN comunicacion_decreto VARCHAR(120) NULL AFTER comunicante_telefono,
    ADD COLUMN comunicacion_oficio VARCHAR(120) NULL AFTER comunicacion_decreto,
    ADD COLUMN comunicacion_carpeta_nro VARCHAR(120) NULL AFTER comunicacion_oficio;
