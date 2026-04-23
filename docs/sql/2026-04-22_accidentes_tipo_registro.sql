ALTER TABLE accidentes
  ADD COLUMN tipo_registro ENUM('Carpeta','Intervencion') NULL DEFAULT NULL AFTER registro_sidpol;
