DROP VIEW IF EXISTS `v_accidente_personas_vinculadas`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `v_accidente_personas_vinculadas` AS
SELECT
    CAST('INV' AS CHAR CHARACTER SET utf8mb4) AS `fuente`,
    `ip`.`id` AS `fuente_id`,
    `ip`.`accidente_id` AS `accidente_id`,
    `p`.`tipo_doc` COLLATE utf8mb4_general_ci AS `tipo_doc`,
    `p`.`num_doc` COLLATE utf8mb4_general_ci AS `num_doc`,
    `p`.`nombres` COLLATE utf8mb4_general_ci AS `nombres`,
    `p`.`apellido_paterno` COLLATE utf8mb4_general_ci AS `apellido_paterno`,
    COALESCE(`p`.`apellido_materno`, '') COLLATE utf8mb4_general_ci AS `apellido_materno`,
    `p`.`domicilio` COLLATE utf8mb4_general_ci AS `domicilio`,
    `p`.`fecha_nacimiento` AS `fecha_nacimiento`,
    COALESCE(`pr`.`Nombre`, 'Relacionado') COLLATE utf8mb4_general_ci AS `relacion`,
    CAST('' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS `extra`
FROM `involucrados_personas` `ip`
JOIN `personas` `p` ON `p`.`id` = `ip`.`persona_id`
LEFT JOIN `participacion_persona` `pr` ON `pr`.`Id` = `ip`.`rol_id`

UNION ALL

SELECT
    CAST('PNP' AS CHAR CHARACTER SET utf8mb4) AS `fuente`,
    `pi`.`id` AS `fuente_id`,
    `pi`.`accidente_id` AS `accidente_id`,
    `p`.`tipo_doc` COLLATE utf8mb4_general_ci AS `tipo_doc`,
    `p`.`num_doc` COLLATE utf8mb4_general_ci AS `num_doc`,
    `p`.`nombres` COLLATE utf8mb4_general_ci AS `nombres`,
    `p`.`apellido_paterno` COLLATE utf8mb4_general_ci AS `apellido_paterno`,
    COALESCE(`p`.`apellido_materno`, '') COLLATE utf8mb4_general_ci AS `apellido_materno`,
    `p`.`domicilio` COLLATE utf8mb4_general_ci AS `domicilio`,
    NULL AS `fecha_nacimiento`,
    CAST('Efectivo policial' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS `relacion`,
    CONCAT('Grado: ', COALESCE(`pi`.`grado_policial`, ''), ' - Dep.: ', COALESCE(`pi`.`dependencia_policial`, '')) COLLATE utf8mb4_general_ci AS `extra`
FROM `policial_interviniente` `pi`
JOIN `personas` `p` ON `p`.`id` = `pi`.`persona_id`

UNION ALL

SELECT
    CAST('PRO' AS CHAR CHARACTER SET utf8mb4) AS `fuente`,
    `pv`.`id` AS `fuente_id`,
    `pv`.`accidente_id` AS `accidente_id`,
    `p`.`tipo_doc` COLLATE utf8mb4_general_ci AS `tipo_doc`,
    `p`.`num_doc` COLLATE utf8mb4_general_ci AS `num_doc`,
    `p`.`nombres` COLLATE utf8mb4_general_ci AS `nombres`,
    `p`.`apellido_paterno` COLLATE utf8mb4_general_ci AS `apellido_paterno`,
    COALESCE(`p`.`apellido_materno`, '') COLLATE utf8mb4_general_ci AS `apellido_materno`,
    `p`.`domicilio` COLLATE utf8mb4_general_ci AS `domicilio`,
    NULL AS `fecha_nacimiento`,
    CAST('Propietario del vehiculo' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS `relacion`,
    COALESCE(`pv`.`rol_legal`, '') COLLATE utf8mb4_general_ci AS `extra`
FROM `propietario_vehiculo` `pv`
JOIN `personas` `p` ON `p`.`id` = `pv`.`propietario_persona_id`

UNION ALL

SELECT
    CAST('FAM' AS CHAR CHARACTER SET utf8mb4) AS `fuente`,
    `ff`.`id` AS `fuente_id`,
    `ff`.`accidente_id` AS `accidente_id`,
    `p`.`tipo_doc` COLLATE utf8mb4_general_ci AS `tipo_doc`,
    `p`.`num_doc` COLLATE utf8mb4_general_ci AS `num_doc`,
    `p`.`nombres` COLLATE utf8mb4_general_ci AS `nombres`,
    `p`.`apellido_paterno` COLLATE utf8mb4_general_ci AS `apellido_paterno`,
    COALESCE(`p`.`apellido_materno`, '') COLLATE utf8mb4_general_ci AS `apellido_materno`,
    `p`.`domicilio` COLLATE utf8mb4_general_ci AS `domicilio`,
    NULL AS `fecha_nacimiento`,
    COALESCE(`ff`.`parentesco`, 'Familiar mas cercano') COLLATE utf8mb4_general_ci AS `relacion`,
    CAST('' AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_general_ci AS `extra`
FROM `familiar_fallecido` `ff`
JOIN `personas` `p` ON `p`.`id` = `ff`.`familiar_persona_id`;
