-- Permite registrar personas sin documento, sexo o fecha de nacimiento.
ALTER TABLE personas
  MODIFY num_doc VARCHAR(15) COLLATE utf8mb4_unicode_ci NULL,
  MODIFY sexo ENUM('M','F') COLLATE utf8mb4_unicode_ci NULL,
  MODIFY fecha_nacimiento DATE NULL;
