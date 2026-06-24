-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:8889
-- Tiempo de generación: 11-05-2026 a las 23:26:51
-- Versión del servidor: 8.0.44
-- Versión de PHP: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `uiatnorte_dev`
--

DELIMITER $$
--
-- Funciones
--
CREATE DEFINER=`root`@`localhost` FUNCTION `initcap_words` (`input_text` TEXT) RETURNS TEXT CHARSET utf8mb4 DETERMINISTIC BEGIN
  DECLARE i INT DEFAULT 1;
  DECLARE len INT DEFAULT 0;
  DECLARE result TEXT DEFAULT '';
  DECLARE ch VARCHAR(1);
  DECLARE upper_next BOOLEAN DEFAULT TRUE;

  IF input_text IS NULL THEN
    RETURN NULL;
  END IF;

  SET input_text = TRIM(REGEXP_REPLACE(COALESCE(input_text, ''), '[[:space:]]+', ' '));
  SET len = CHAR_LENGTH(input_text);

  WHILE i <= len DO
    SET ch = SUBSTRING(input_text, i, 1);

    IF ch = ' ' THEN
      SET result = CONCAT(result, ' ');
      SET upper_next = TRUE;
    ELSEIF upper_next THEN
      SET result = CONCAT(result, UPPER(ch));
      SET upper_next = FALSE;
    ELSE
      SET result = CONCAT(result, LOWER(ch));
      SET upper_next = FALSE;
    END IF;

    SET i = i + 1;
  END WHILE;

  RETURN result;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `abogados`
--

CREATE TABLE `abogados` (
  `id` int NOT NULL,
  `persona_id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `condicion` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `colegiatura` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `casilla_electronica` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domicilio_procesal` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `celular` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `abogados`
--

INSERT INTO `abogados` (`id`, `persona_id`, `accidente_id`, `condicion`, `nombres`, `apellido_paterno`, `apellido_materno`, `colegiatura`, `registro`, `casilla_electronica`, `domicilio_procesal`, `celular`, `email`, `creado_en`, `actualizado_en`) VALUES
(1, 27, 18, NULL, 'Giancarlo Jorge', 'MERINO', 'SANCHO', 'Colegio de Abogados de Lima', '00028', 'SINOE 987654', 'Av. Enrique Fermi 374 Urb. Fiori SMP', '986571975', 'merinosancho@gmail.com', '2025-10-15 04:25:46', '2025-10-15 04:25:46'),
(2, 29, 20, NULL, 'David', 'REYES', 'LOPEZ', 'Colegio de Abogados del Callao', 'CAC Nro. 5872', '47975 Pagina Web del Poder Judicial', 'Casilla 1761 de la Central de Notificaciones de Lima norte', '997218596', 'davidreyes274jus@gmail.com', '2025-10-15 17:46:17', '2025-10-15 17:46:17'),
(3, 28, 19, NULL, 'Víctor Humberto', 'CABELLO', 'FLORES', 'Colegio de Abogados de Lima', 'CAL Nro. 0521', '35650', 'Jr. Zarumilla Nro. 162 Urb. San Felipe- 1era. Etapa-Comas', '', 'victorab2020@gmail.com', '2025-10-20 19:36:02', '2025-10-20 19:36:02'),
(4, 24, 19, NULL, 'Julio Alberto', 'PISCOYA', 'NUÑEZ', 'Colegio de Abogados de Lima', 'CAL Nro. 13836', '', 'Jr. Huallaga 160 oficina 404-Cercado de Lima', '993535105', 'jpiscoya@gmail.com', '2025-10-23 16:40:27', '2025-10-23 16:40:27'),
(5, 44, 26, NULL, 'Richard Junior', 'Yon', 'Marquina', 'Colegio de Abogados de Lima', 'CAL Nro. 86063', 'Casilla SINOE 136888', 'Calle Los Olivos Mz I1 lote 14 Urb. Los Jazmines del Naranjal-SMP', '952349473', 'richardyonmarquina@gmail.com', '2025-10-27 01:43:41', '2025-10-27 01:43:41'),
(6, 49, 27, NULL, 'Kenia Dilan', 'LEON', 'TORRES', 'Colegio de Abogados de Lima', 'CAL Nro. 88471', '', 'Mz A lote 12 Asoc. Corazón de Jesús-Puente Piedra', '902742460', 'areapenal.asocalef@gmail.com', '2025-10-27 04:21:58', '2025-10-27 04:21:58'),
(7, 54, 29, NULL, 'Edgar Fernando', 'ORE', 'ZEGARRA', 'Colegio de Abogados de Lima', 'CAL Nro. 93346', '', 'Jr. Encinas Nro. 575 oficina 601 URb. Los Jardines-SMP', '962753330', 'derechodepaso.abogadosyasesores@gmail.com', '2025-11-01 03:34:00', '2025-11-01 03:34:00'),
(8, 40, 24, NULL, 'John Rolando', 'CCORMORAY', 'SALINAS', 'CALN', '1478', '', 'Jr. Las Toronjas 233 Urb. Naranjal-SMP', '954151406', 'estudiojuridicoccormoray@gmail.com', '2025-11-16 01:26:58', '2025-11-16 01:26:58'),
(9, 64, 25, NULL, 'Katherin Estefita', 'ROJAS', 'ROJAS', 'CAL', 'CAL NRO. 94963', '174005', 'Av. Paseo de la República 291 ofc. 1407-Cercado de Lima', '934192542', 'rojaslawfirm@gmail.com', '2025-11-21 05:41:14', '2025-11-21 05:41:14'),
(10, 42, 25, NULL, 'María Julia', 'CANEVARO', 'BOCANEGRA', 'CAL NRO. 24049', 'CAL Nro. 24049', 'Casilla SINOE 1571', 'Calle Ladislao Espinar 260 Torre 2 Dpto. 202-San Miguel', '944801532', 'majucanevaro@gmail.com', '2025-11-21 05:45:16', '2025-11-21 05:45:16'),
(11, 65, 30, NULL, 'Karen Jesús', 'CALLE', 'CALLE', 'ICAP', 'ICAP Nro. 005090', '108477', 'Condominio Torres de Vista Sol Block G32 Dpto. 202-Comas', '946574848', 'karencallecalle@hotmail.com', '2025-11-25 06:02:22', '2025-11-25 06:02:22'),
(12, 79, 32, NULL, 'Juan Hilmer', 'CUMBIA', 'ZAMBORA', 'CALN', '2095', '4213', 'Av. Alfonso Ugarte 1228 oficina 306-Breña', '964290903', 'juancumbiaabogado@gmail.com', '2025-12-02 05:39:47', '2025-12-02 05:39:47'),
(14, 83, 33, NULL, 'Javier Gian Pierre', 'DEGOLLAR', 'YARINGAÑO', 'CAL', '77505', '', 'Av. Morro de Arica 148 Dpto. 8 Rimac', '972255084', 'gianpierredegollar@gmail.com', '2025-12-10 04:28:24', '2025-12-10 04:28:24'),
(15, 47, 26, NULL, 'Elder P.', 'ALEGRE', 'PEREZ', 'CAL', '102427', '1923', 'Jr. Lampa  Nro. 1174 3er Piso-Cercado de Lima', '970339567', 'perulegal28@gmail.com', '2025-12-15 05:14:44', '2025-12-15 05:14:44'),
(16, 94, 35, NULL, 'Edwin', 'SAUCEDO', 'COLLANTES', 'CAL', '92390', 'SINOE 162409', 'Urb. Santa Rosa de Carabayllo Mz D lote 4- Carabayllo', '930859376', 'saucedovoxpopuli24@gmail.com', '2026-01-11 23:25:06', '2026-01-11 23:25:06'),
(17, 122, 38, NULL, 'Diego Alejandro', 'VALLADARES', 'VILLAREAL', 'CAH N° 1973', '1973', '', '', '961513471', '', '2026-03-08 19:26:38', '2026-03-08 19:26:38'),
(18, 135, 40, NULL, 'Jhon Kleber', 'BENITES', 'TANGOA', 'CAL', '83990', 'SINOE N° 128420', 'Jr. Pira N° 607 Urb. El Parque Naranjal - 3° piso-Los Olivos', '946035434', 'corporacionjuridicabenites@gmail.com', '2026-03-17 01:24:37', '2026-03-17 01:24:37'),
(19, 132, 40, NULL, 'Dayana Madeleyne', 'GUERRA', 'NUÑEZ', 'CAL', '73145', 'SINOE 164092', 'Av. España N° 288 ofc. 210-Cercado de Lima', '989112110', 'justice.law.aw@gmail.com', '2026-03-17 02:02:04', '2026-03-17 02:02:04'),
(20, 98, 36, NULL, 'Sandy Milagros', 'CRUZ', 'ROBALINO', 'Colegio de Abogados de Lima', '88268', 'SINOE 155318', 'Av. Tantamayo Urb. Villareal Mz \"U\" lote 01-SMP', '960745719', 'joe02ago@gmail.com', '2026-04-09 15:50:45', '2026-04-09 15:50:45'),
(21, 37, 23, NULL, 'Fredy', 'SILVA', 'VALER', 'Colegio de Abogados de Lima', 'CAL N° 26956', 'SINOE 818', 'SINOE 818', '999062372', 'fsilvaler@gmail.com', '2026-04-13 09:04:07', '2026-04-13 09:04:07'),
(22, 66, 23, NULL, 'Fredy', 'SILVA', 'VALER', 'CAL', '26956', 'SINOE 818', NULL, '999062372', 'fsilvaler@gmail.com', '2026-04-13 09:05:20', '2026-04-13 09:05:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accidentes`
--

CREATE TABLE `accidentes` (
  `id` int NOT NULL,
  `sidpol` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registro_sidpol` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_registro` enum('Carpeta','Intervencion') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lugar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referencia` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitud` decimal(10,7) DEFAULT NULL,
  `longitud` decimal(10,7) DEFAULT NULL,
  `cod_dep` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_prov` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_dist` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comisaria_id` int DEFAULT NULL,
  `fecha_accidente` datetime NOT NULL,
  `estado` enum('Pendiente','Resuelto','Con diligencias','Desestimado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendiente',
  `fecha_comunicacion` datetime DEFAULT NULL,
  `fecha_intervencion` datetime DEFAULT NULL,
  `comunicante_nombre` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comunicante_telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comunicacion_decreto` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comunicacion_oficio` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comunicacion_carpeta_nro` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fiscalia_id` int DEFAULT NULL,
  `fiscal_id` int DEFAULT NULL,
  `nro_informe_policial` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folder` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sentido` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `secuencia` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `priority` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `accidentes`
--

INSERT INTO `accidentes` (`id`, `sidpol`, `registro_sidpol`, `tipo_registro`, `lugar`, `referencia`, `latitud`, `longitud`, `cod_dep`, `cod_prov`, `cod_dist`, `comisaria_id`, `fecha_accidente`, `estado`, `fecha_comunicacion`, `fecha_intervencion`, `comunicante_nombre`, `comunicante_telefono`, `comunicacion_decreto`, `comunicacion_oficio`, `comunicacion_carpeta_nro`, `fiscalia_id`, `fiscal_id`, `nro_informe_policial`, `folder`, `sentido`, `secuencia`, `creado_en`, `actualizado_en`, `priority`) VALUES
(18, '00000018', '32267340', NULL, 'Carretera Panamericana Norte Km. 42-Santa Rosa', 'altura garita control SUNARP', NULL, NULL, '15', '01', '39', 5, '2025-04-29 03:20:00', 'Resuelto', '2025-04-29 05:00:00', '2025-04-29 05:30:00', NULL, NULL, NULL, NULL, NULL, 3, 3, '042-2025', NULL, 'De Norte a Sur', 'Vehiculo se despiste y se choca con un camion estacionado en el lado derecho zona de tierra', '2025-09-29 05:53:15', '2026-01-20 08:43:11', 0),
(19, '00000019', '26898040', NULL, 'Av. Heroes del Alto Cenepa (ex trapiche)', 'altura paradero ANYPSA', NULL, NULL, '15', '01', '06', 12, '2023-07-15 20:15:00', 'Resuelto', NULL, NULL, 'Carpeta Fiscal', NULL, NULL, NULL, NULL, 4, 4, '018-2025', NULL, 'De oeste a este', 'el accidente de transito se produce el 15JUL2023 y la persona de CLEMENTE CANCINO fallece el 03SET2023', '2025-10-12 08:09:15', '2025-10-27 00:52:33', 0),
(20, '00000020', '33525426', NULL, 'Av. Los Alisos 1320', '', NULL, NULL, '15', '01', '35', 13, '2025-10-13 17:15:00', 'Resuelto', '2025-10-14 11:30:00', '2025-10-14 12:30:00', 'SB.PNP SAAVEDRA', '949173415', NULL, NULL, NULL, 1, 5, '105-2025', NULL, 'De este a oeste', 'Motocicleta circulaba por la calzada de la Av. Los Alisos en sentido de este a oeste.', '2025-10-14 23:06:07', '2026-04-10 04:50:37', 0),
(21, '00000021', '32983772', NULL, 'Av. Dos de Marzo cdra. 2', '', NULL, NULL, '15', '01', '12', 14, '2025-08-03 01:39:00', 'Pendiente', NULL, NULL, 'Oficio Nro. 3358-2025-2D-FPPCETySV/HEFA', NULL, NULL, NULL, NULL, 6, 6, '141-2025', NULL, NULL, NULL, '2025-10-16 15:35:10', '2026-01-06 19:18:47', 0),
(22, '00000022', '33512124', NULL, 'Carretera Panamericana Norte km 38+750', 'altura Puente Villa Estela', NULL, NULL, '15', '01', '02', 22, '2025-10-12 01:30:00', 'Resuelto', '2025-10-12 03:30:00', '2025-10-12 04:15:00', NULL, NULL, NULL, NULL, NULL, 7, 8, '102-2025', NULL, 'De norte a sur', NULL, '2025-10-18 15:07:47', '2026-03-24 16:18:19', 0),
(23, '00000023', '33393268', NULL, 'Carretera Panamericana Norte km 26', 'altura paradero 3 ruedas', NULL, NULL, '15', '01', '25', 16, '2025-09-25 14:20:00', 'Resuelto', '2025-09-25 15:30:00', '2025-09-25 16:10:00', NULL, NULL, NULL, NULL, NULL, 8, 9, '096-2025', '2', 'De norte a sur', 'Camion que se vuelva hacia el lado derecho aplastando a un automovil que circulaba a su lado', '2025-10-20 00:04:05', '2026-04-20 15:23:30', 1),
(24, '00000024', '33261206', NULL, 'Av. Tupac Amaru Cdra. 54', 'altura Av. San Carlos', NULL, NULL, '15', '01', '10', 12, '2025-09-08 23:05:00', 'Resuelto', '2025-09-09 00:30:00', '2025-09-09 01:30:00', NULL, NULL, NULL, NULL, NULL, 9, 10, '088-2025', NULL, 'De Norte a Sur', 'El automovil al pareces se quedo dormido y se despista proyectando a la peatona que camina por la zona de tierra', '2025-10-20 06:01:06', '2025-12-09 17:56:31', 0),
(25, '00000025', '32982355', NULL, 'Carretera Panamericana Norte km 39.500', '', NULL, NULL, '15', '01', '02', 4, '2025-08-04 04:30:00', 'Resuelto', '2025-08-04 07:30:00', '2025-08-04 08:30:00', NULL, NULL, NULL, NULL, NULL, 10, 11, '075-2025', NULL, 'De sur a norte', 'Automóvil fugado atropella a peatón; seguidamente, moto evita el accidente y es alcanzada por otro automóvil que también se da a la fuga.', '2025-10-20 14:36:58', '2026-03-09 06:56:23', 0),
(26, '00000026', '32813425', 'Carpeta', 'Calle Los Tamarindos- Urb. Los Jardines', '', -12.0125850, -77.0538360, '15', '01', '35', 17, '2025-07-09 08:30:00', 'Pendiente', NULL, NULL, 'Carpeta Fiscal Nro. 606015901-2025-2918-0', NULL, NULL, NULL, NULL, 4, 4, '078-2025', '7', 'Camioneta rural de oeste a este y trimoto de pasajeros de sur a norte', 'Ambos vehículos, al llegar a la intersección, colisionan, provenientes de vías distintas. El conductor de la trimoto, a consecuencia de la fuerza de impacto, es expulsado de su habitáculo hacia el exterior, cayendo violentamente hacia la superficie de la calzada.', '2025-10-26 17:32:38', '2026-05-01 05:34:41', 1),
(27, '00000027', '32178313', NULL, 'Carretera Panamericana Norte Vía Serpentín Pasamayo km 1.500', '', NULL, NULL, '15', '01', '02', 4, '2025-04-16 22:35:00', 'Resuelto', '2025-04-17 00:05:00', '2025-04-17 00:50:00', 'S1. PNP JACOBO CARDENAS', NULL, NULL, NULL, NULL, 11, 12, '035-2025', NULL, 'De norte a sur', NULL, '2025-10-27 04:01:37', '2025-10-30 17:43:38', 0),
(29, '00000029', '32464625', NULL, 'Carretera Panamericana Norte km 50+500-Variante Pasamayo', '', NULL, NULL, '15', '01', '02', 4, '2025-05-26 16:30:00', 'Resuelto', '2025-05-26 18:00:00', '2025-05-26 18:30:00', 'S3.PNP Juan Romulo ESTRADA PASTOR', NULL, NULL, NULL, NULL, 10, 14, '047-2025', NULL, 'De sur a norte', NULL, '2025-11-01 00:30:31', '2026-03-24 15:45:05', 0),
(30, '00000030', '31556962', NULL, 'Av. Universitaria cdra. 71', 'altura inmueble 7131 Urb. Retablo', NULL, NULL, '15', '01', '10', 6, '2025-01-29 19:00:00', 'Pendiente', '2025-10-21 10:00:00', '2025-10-21 10:00:00', 'Oficio Nro. 646-2025-04°D-FPPCTYSV-LN-MP-FN', NULL, NULL, NULL, NULL, 9, 15, '121-2025', NULL, 'De norte a sur', 'Trimoto de pasajeros se desplazaba en sentido correcto, mientras que la motocicleta circulaba en sentido contrario acompañado de una persona de sexo femenino, ambos resultan con lesiones y son llevados al Hospital Sergio Bernales, fallece el chofer el dia 06FEB2025', '2025-11-24 21:26:47', '2025-11-27 19:12:52', 0),
(31, '00000031', '32715160', NULL, 'Carretera Panamericana Norte km 17', '', NULL, NULL, '15', '01', '17', 13, '2025-06-29 04:30:00', 'Con diligencias', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '062-2025', NULL, 'De norte a sur', 'motocicleta choca contra auto, conductor y ocupante caen a la calzada, conductor es atropellado', '2025-11-29 06:38:42', '2026-01-20 08:43:36', 0),
(32, '00000032', '33897881', NULL, 'Carretera Panamericana Norte km 30+197', 'altura puente paradero Tottus', NULL, NULL, '15', '01', '25', 16, '2025-11-30 19:00:00', 'Pendiente', '2025-12-01 01:30:00', '2025-12-01 02:30:00', NULL, NULL, NULL, NULL, NULL, 13, 16, '126-2025', NULL, 'De norte a sur', NULL, '2025-12-02 03:54:30', '2025-12-09 17:56:33', 0),
(33, '00000033', '32798470', NULL, 'Calle Integración', 'AA.HH San Benito Etapa 8 Mz B3 lote 7', NULL, NULL, '15', '01', '06', 19, '2025-07-09 21:00:00', 'Pendiente', '2025-07-10 08:10:00', '2025-07-10 09:30:00', NULL, NULL, NULL, NULL, NULL, 12, 13, '067-2025', '5', 'De norte a sur', 'Zona de abismo y precipicio, zona de lluvia, calzada mojada barro', '2025-12-10 01:11:11', '2025-12-10 03:35:56', 0),
(34, '00000034', '31315029', NULL, 'Carretera Lima Canta km 32.5', 'Carwash Lucas', NULL, NULL, '15', '01', '06', 10, '2025-12-30 21:00:00', 'Resuelto', '2025-12-30 22:35:00', '2025-12-30 23:40:00', NULL, NULL, NULL, NULL, NULL, 9, 15, '136-2024', NULL, 'De sur a norte', NULL, '2025-12-11 03:23:49', '2025-12-30 14:18:50', 0),
(35, '00000035', '34207350', NULL, 'Carretera Panamericana Norte km 33.500', 'Mercado Tres Regiones', NULL, NULL, '15', '01', '25', 21, '2026-01-11 03:30:00', 'Pendiente', '2026-01-11 04:10:00', '2026-01-11 05:15:00', 'S2.PNP Apolinario', NULL, NULL, NULL, NULL, 14, 17, '01-2026', NULL, 'De sur a norte', 'Minibus de la nueva estrella en crucero peatonal no se percata de cruce de peaton, lo atropella, arrastra y continua su recorrido, para posteriormente presentarse a la Comisaria del sector', '2026-01-11 15:54:00', '2026-01-11 20:41:43', 0),
(36, '00000036', '34373525', NULL, 'Av. Universitaria cdra. 55', 'altura Hostal Confort', NULL, NULL, '15', '01', '17', 18, '2026-01-31 02:20:00', 'Pendiente', '2026-01-31 04:10:00', '2026-01-31 05:30:00', 'S2. PNP HUAMAN', NULL, NULL, NULL, NULL, 9, 10, '010-2025', '3', 'norte a sur', 'automovil de placa F4P-020 se desplazaba por el carril izquierdo y al parecer llega a tener contacto con otro vehiculo que circulaba por el carril central, circunstancias donde pierde el control de su unidad despistandose hacia el separador central e impacta contra el tronco de una plantacion de palmera, fallece cuatro personas de nacionalidad venezolana, una lesionada y el conductor en UCI.', '2026-02-01 04:29:10', '2026-04-14 01:59:15', 0),
(37, '00000037', '29816407', NULL, 'Av. Tomas Valle cdra. 01', '', NULL, NULL, '15', '01', '35', 17, '2024-06-25 11:15:00', 'Pendiente', '2024-06-25 14:35:00', '2024-06-25 16:40:00', NULL, NULL, NULL, NULL, NULL, 1, 1, '062-2024', NULL, 'De este a oeste', NULL, '2026-02-05 06:30:10', '2026-02-05 06:30:10', 0),
(38, '00000038', '08032026', NULL, 'Av. Carretera Lima Canta km 32.500', '', NULL, NULL, '15', '01', '06', 10, '2026-03-07 22:00:00', 'Pendiente', '2026-03-07 23:40:00', '2026-03-07 00:50:00', 'S1.PNP CARMONA PRINCIPE', NULL, NULL, NULL, NULL, 1, 18, '026-2026', '4', 'De sur a norte', 'Station wagon que se desplazaba por la calzada este con sentido de sur a norte, la peaton cruzaba con sentido de oeste a este y es atropellada, al parecer se encontraba departiendo en un local recreacional justo en el lugar del accidente y solo habia salido acompañar a un familiar para que tomara su taxi', '2026-03-08 16:26:53', '2026-03-09 06:56:19', 0),
(39, '00000039', '32661054', NULL, 'Carretera Panamericana Norte km. 17', 'altura Av. Los Alisos', NULL, NULL, '15', '01', '12', 23, '2025-06-21 17:30:00', 'Pendiente', '2025-06-22 05:00:00', '2025-06-22 07:00:00', NULL, NULL, NULL, NULL, NULL, 9, 19, '058-2025', NULL, 'De sur a norte', NULL, '2026-03-12 06:25:11', '2026-03-12 06:25:11', 0),
(40, '00000040', '32771686', 'Carpeta', 'Av. Naranjal cdra. 05', '', NULL, NULL, '15', '01', '17', 18, '2025-07-06 18:40:00', 'Pendiente', NULL, NULL, 'Carpeta Fiscal Nro. 606015901-2025-3434-0', NULL, NULL, NULL, NULL, 2, 7, '029-2026', '1', 'De este a oeste', 'motocicleta que se desplazaba por la Av. Naranjal cdra. 05 con sentido de este a oeste circulando por el centro de la calzada atropella a peaton que realizaba el cruce de la calzada', '2026-03-13 06:46:55', '2026-04-23 04:15:35', 0),
(41, '00000041', '34824602', NULL, 'Av. Santa Rosa interseccion con la Av. Ancash', '', NULL, NULL, '15', '01', '10', 24, '2026-03-27 20:15:00', 'Pendiente', '2026-03-28 07:50:00', '2026-03-28 09:00:00', 'S2.PNP LOPEZ', '917295362', NULL, NULL, NULL, 9, 10, '035-2026', NULL, 'De este a oeste', 'Trimoto de pasajeros que realizaba su recorrido por la Av. Santa de bajada este a oeste, impacta a peatón cruzando la calzada', '2026-03-31 14:32:54', '2026-03-31 14:32:54', 0),
(42, '00000042', NULL, 'Intervencion', 'Av. Naranjal cdra. 10', '', NULL, NULL, '15', '01', '17', 18, '2026-05-12 10:00:00', 'Pendiente', '2026-05-12 11:00:00', '2026-05-12 12:00:00', 'S1. CARMONA', '999888777', NULL, NULL, NULL, 1, 5, '028-2025', NULL, NULL, NULL, '2026-05-01 06:44:11', '2026-05-01 06:44:11', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accidente_analisis_imagenes`
--

CREATE TABLE `accidente_analisis_imagenes` (
  `id` int UNSIGNED NOT NULL,
  `accidente_id` int NOT NULL,
  `seccion` enum('danos','lesiones') COLLATE utf8mb4_general_ci NOT NULL,
  `sort_order` tinyint UNSIGNED NOT NULL,
  `archivo_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `archivo_nombre` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `file_size` int UNSIGNED DEFAULT NULL,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `accidente_analisis_imagenes`
--

INSERT INTO `accidente_analisis_imagenes` (`id`, `accidente_id`, `seccion`, `sort_order`, `archivo_path`, `archivo_nombre`, `mime_type`, `file_size`, `creado_en`) VALUES
(1, 20, 'danos', 1, 'uploads/analisis/accidente_20/danos/01_e318be65b1c0a8cf.jpg', 'unnamed.jpg', 'image/jpeg', 61067, '2026-04-05 18:29:30'),
(2, 20, 'lesiones', 1, 'uploads/analisis/accidente_20/lesiones/01_ef8360f5371189ed.jpg', '40b1f79c-5303-46fe-94b1-4c030a2f662f.jpeg', 'image/jpeg', 303924, '2026-04-06 11:06:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accidente_consecuencia`
--

CREATE TABLE `accidente_consecuencia` (
  `accidente_id` int NOT NULL,
  `consecuencia_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `accidente_consecuencia`
--

INSERT INTO `accidente_consecuencia` (`accidente_id`, `consecuencia_id`) VALUES
(18, 1),
(19, 1),
(20, 1),
(21, 1),
(22, 1),
(23, 1),
(24, 1),
(25, 1),
(26, 1),
(27, 1),
(29, 1),
(30, 1),
(31, 1),
(32, 1),
(33, 1),
(34, 1),
(35, 1),
(36, 1),
(37, 1),
(38, 1),
(39, 1),
(40, 1),
(41, 1),
(42, 1),
(20, 2),
(31, 2),
(36, 2),
(18, 3),
(19, 3),
(20, 3),
(21, 3),
(22, 3),
(23, 3),
(24, 3),
(39, 3),
(25, 4),
(26, 4),
(27, 4),
(29, 4),
(30, 4),
(31, 4),
(32, 4),
(33, 4),
(35, 4),
(36, 4),
(37, 4),
(38, 4),
(40, 4),
(41, 4),
(42, 4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accidente_modalidad`
--

CREATE TABLE `accidente_modalidad` (
  `accidente_id` int NOT NULL,
  `modalidad_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `accidente_modalidad`
--

INSERT INTO `accidente_modalidad` (`accidente_id`, `modalidad_id`) VALUES
(18, 1),
(21, 1),
(25, 1),
(26, 1),
(29, 1),
(30, 1),
(31, 1),
(36, 1),
(19, 2),
(20, 2),
(22, 2),
(24, 2),
(25, 2),
(27, 2),
(31, 2),
(32, 2),
(34, 2),
(35, 2),
(37, 2),
(38, 2),
(40, 2),
(41, 2),
(42, 2),
(20, 3),
(23, 3),
(25, 3),
(29, 3),
(30, 3),
(31, 3),
(33, 3),
(18, 4),
(21, 4),
(33, 4),
(39, 4),
(20, 8),
(25, 8),
(30, 8),
(31, 8),
(22, 9),
(25, 9),
(34, 9),
(36, 9),
(41, 9);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_persona_cache`
--

CREATE TABLE `api_persona_cache` (
  `id` bigint UNSIGNED NOT NULL,
  `dni` varchar(12) COLLATE utf8mb4_general_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `foto_path` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `obtenido_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carroceria_vehiculo`
--

CREATE TABLE `carroceria_vehiculo` (
  `id` int NOT NULL,
  `tipo_id` int NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `carroceria_vehiculo`
--

INSERT INTO `carroceria_vehiculo` (`id`, `tipo_id`, `nombre`, `descripcion`, `creado_en`) VALUES
(1, 1, 'Sedán', 'Automóvil de tres volúmenes', '2025-09-17 05:40:12'),
(2, 1, 'Hatchback', 'Automóvil de dos volúmenes con maletera integrada', '2025-09-17 05:40:12'),
(3, 1, 'SUV', 'Vehículo utilitario deportivo', '2025-09-17 05:40:12'),
(4, 1, 'Station Wagon', 'Automóvil familiar con maletera extendida', '2025-09-17 05:40:12'),
(5, 4, 'Trimoto de pasajeros', NULL, '2025-09-18 05:47:48'),
(6, 5, 'Motocicleta', '', '2025-09-21 07:02:33'),
(7, 6, 'Furgón', NULL, '2025-09-29 04:36:58'),
(8, 9, 'Volquete', '', '2025-10-20 04:20:58'),
(10, 6, 'Volquete', NULL, '2025-10-20 04:37:29'),
(11, 3, 'SUV', '', '2025-10-26 17:39:17'),
(12, 10, 'Camioneta Pick-up', NULL, '2025-10-27 04:05:26'),
(13, 2, 'SUV', '', '2025-11-29 06:47:58'),
(14, 11, 'Ómnibus Urbano', '', '2025-12-02 04:01:01'),
(15, 11, 'Minibús', '', '2026-01-11 21:01:48'),
(16, 23, 'Remolcador', '', '2026-02-05 06:35:36'),
(17, 9, 'Furgón', '', '2026-02-05 06:39:20'),
(18, 26, 'Furgón', '', '2026-02-05 06:43:23'),
(19, 8, 'Multipropósito', NULL, '2026-03-05 08:37:43'),
(20, 28, 'Station Wagon', NULL, '2026-03-08 16:29:47'),
(21, 5, 'Triciclo', NULL, '2026-03-12 06:34:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria_vehiculos`
--

CREATE TABLE `categoria_vehiculos` (
  `id` int NOT NULL,
  `codigo` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categoria_vehiculos`
--

INSERT INTO `categoria_vehiculos` (`id`, `codigo`, `descripcion`, `creado_en`) VALUES
(1, 'M1', 'Vehículos para transporte de personas, hasta 8 asientos', '2025-09-17 05:28:42'),
(2, 'M2', 'Vehículos para transporte de personas, más de 8 asientos, hasta 5t', '2025-09-17 05:28:42'),
(3, 'M3', 'Vehículos para transporte de personas, más de 8 asientos, más de 5t', '2025-09-17 05:28:42'),
(4, 'N1', 'Vehículos para transporte de mercancías, hasta 3.5t', '2025-09-17 05:28:42'),
(5, 'N2', 'Vehículos para transporte de mercancías, entre 3.5 y 12t', '2025-09-17 05:28:42'),
(6, 'N3', 'Vehículos para transporte de mercancías, más de 12t', '2025-09-17 05:28:42'),
(7, 'O1', 'Remolques con MMA ≤ 0.75t', '2025-09-17 05:28:42'),
(8, 'O2', 'Remolques con 0.75t < MMA ≤ 3.5t', '2025-09-17 05:28:42'),
(9, 'O3', 'Remolques con 3.5t < MMA ≤ 10t', '2025-09-17 05:28:42'),
(10, 'O4', 'Remolques con MMA > 10t', '2025-09-17 05:28:42'),
(11, 'L1', 'Motocicletas hasta 50 cc y máx. 45 km/h', '2025-09-17 05:28:42'),
(12, 'L2', 'Motocicletas de tres ruedas hasta 50 cc y máx. 45 km/h', '2025-09-17 05:28:42'),
(13, 'L3', 'Motocicletas de dos ruedas > 50 cc', '2025-09-17 05:28:42'),
(14, 'L4', 'Motocicletas con sidecar > 50 cc', '2025-09-17 05:28:42'),
(15, 'L5', 'Motocicletas de tres ruedas > 50 cc', '2025-09-17 05:28:42'),
(16, 'L6', 'Ciclomotores de cuatro ruedas (cuadriciclos ligeros)', '2025-09-17 05:28:42'),
(17, 'L7', 'Cuadriciclos pesados', '2025-09-17 05:28:42'),
(18, 'VMP', 'Vehículos de Movilidad Personal, ej: scooter eléctrico', '2025-09-17 05:28:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citacion`
--

CREATE TABLE `citacion` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `fuente` enum('INV','PNP','PRO','FAM') COLLATE utf8mb4_general_ci NOT NULL,
  `fuente_id` int NOT NULL,
  `persona_nombres` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `persona_apep` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `persona_apem` varchar(80) COLLATE utf8mb4_general_ci DEFAULT '',
  `persona_doc_tipo` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `persona_doc_num` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `persona_domicilio` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `persona_edad` tinyint UNSIGNED DEFAULT NULL,
  `en_calidad` enum('Efectivo policial','Familiar más cercano','Investigado','Testigo','Abogado','Propietario del vehículo','Conductor','Pasajero','Peatón','Relacionado') COLLATE utf8mb4_general_ci NOT NULL,
  `tipo_diligencia` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `lugar` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `motivo` text COLLATE utf8mb4_general_ci NOT NULL,
  `orden_citacion` int DEFAULT '1',
  `oficio_id` int DEFAULT NULL,
  `google_calendar_event_id` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `google_calendar_event_link` text COLLATE utf8mb4_general_ci,
  `google_calendar_sync_status` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `google_calendar_synced_at` datetime DEFAULT NULL,
  `google_calendar_last_error` text COLLATE utf8mb4_general_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `citacion`
--

INSERT INTO `citacion` (`id`, `accidente_id`, `fuente`, `fuente_id`, `persona_nombres`, `persona_apep`, `persona_apem`, `persona_doc_tipo`, `persona_doc_num`, `persona_domicilio`, `persona_edad`, `en_calidad`, `tipo_diligencia`, `fecha`, `hora`, `lugar`, `motivo`, `orden_citacion`, `oficio_id`, `google_calendar_event_id`, `google_calendar_event_link`, `google_calendar_sync_status`, `google_calendar_synced_at`, `google_calendar_last_error`, `creado_en`) VALUES
(1, 18, 'PNP', 1, 'Giancarlo Jorge', 'MERINO', 'SANCHO', 'DNI', '45867229', 'AV. LOS PRECURSORES P.J.SAN CAMILO MZ. N LT. 1', NULL, 'Efectivo policial', 'Toma de declaración', '2025-10-23', '10:00:00', 'Carretera Panamericana Norte km 42- Santa Rosa (alt.garita control SUNAT)', 'Recibir su manifestacion', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-12 03:32:58'),
(4, 26, 'FAM', 5, 'Luis Antonio', 'BARRERA', 'BARRETO', 'DNI', '77243359', 'PSJ. ALFONSO UGARTE ASENT.H. 30 DE ENERO MZ. A LT. 05', NULL, 'Familiar más cercano', 'Reconstrucción', '2025-10-31', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Visualización de video', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-27 03:37:16'),
(5, 26, 'PRO', 5, 'John Richard', 'TITTO', 'VALENCIA', 'DNI', '10191431', 'PROLONG.FAUSTINO SANCHEZ CARRION 179 URB. EL RETABLO ETAPA I', NULL, 'Investigado', 'Reconstrucción', '2025-10-31', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Visualización de video', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-27 03:39:24'),
(6, 24, 'PNP', 6, 'Roni William', 'QUILCA', 'ANAHUI', 'DNI', '46714098', 'JR. BREÑA 100 P.J. MIGUEL GRAU ZN-A ETAPA III', NULL, 'Efectivo policial', 'Toma de declaración', '2025-11-27', '09:30:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-16 02:08:12'),
(7, 24, 'PNP', 6, 'Roni William', 'QUILCA', 'ANAHUI', 'DNI', '46714098', 'JR. BREÑA 100 P.J. MIGUEL GRAU ZN-A ETAPA III', NULL, 'Efectivo policial', 'Toma de declaración', '2025-11-28', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 2, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-16 05:35:33'),
(9, 22, 'FAM', 4, 'Silvia', 'ARANGO', 'GODOY', 'DNI', '42984799', 'URB. SAN JUAN MASIAS SECTOR I MZ.K1 LT.52', NULL, 'Familiar más cercano', 'Toma de declaración', '2025-11-28', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-26 18:17:50'),
(10, 22, 'PNP', 9, 'Jesus Vitaliano', 'ALVAREZ', 'ALARCON', 'DNI', '75551732', 'ASENT.H. JOSE CARLOS MARIATEGUI ETAPA 5 MZ. U9 LT. 2', NULL, 'Efectivo policial', 'Toma de declaración', '2025-11-28', '09:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-26 18:36:06'),
(13, 26, 'INV', 36, 'John Richard', 'TITTO', 'VALENCIA', 'DNI', '10191431', 'PROLONG.FAUSTINO SANCHEZ CARRION 179 URB. EL RETABLO ETAPA I', 53, 'Investigado', 'Reconocimiento', '2025-12-18', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Inspección Técnico Policial', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-15 05:01:25'),
(14, 26, 'FAM', 5, 'Luis Antonio', 'BARRERA', 'BARRETO', 'DNI', '77243359', 'PSJ. ALFONSO UGARTE ASENT.H. 30 DE ENERO MZ. A LT. 05', NULL, 'Familiar más cercano', 'Reconocimiento', '2025-12-18', '10:00:00', 'Lugar de los hechos', 'Inspección Técnico Policial', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-15 05:05:11'),
(15, 20, 'INV', 25, 'Diego Alberto', 'FLORES', 'HUINCHA', 'DNI', '75393977', 'ASOC. DE PROPIETARIOS LAS MERCEDES MZ. C LT. 17', 27, 'Investigado', 'Reconstrucción', '2026-02-11', '09:30:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Visualización de video', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-02 04:11:14'),
(16, 20, 'FAM', 3, 'Shella Johanna', 'LA ROSA', 'VASQUEZ', 'DNI', '10681315', 'CALLE LOS NOGALES ASENT.H. JAZMINES DEL NARANJAL MZ. S1 LT. 41', NULL, 'Familiar más cercano', 'Reconstrucción', '2026-02-11', '09:30:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Visualización de video', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-02 04:13:01'),
(17, 20, 'FAM', 3, 'Shella Johanna', 'LA ROSA', 'VASQUEZ', 'DNI', '10681315', 'CALLE LOS NOGALES ASENT.H. JAZMINES DEL NARANJAL MZ. S1 LT. 41', NULL, 'Familiar más cercano', 'Reconstrucción', '2026-03-11', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Visualización de video', 2, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-05 19:51:04'),
(18, 20, 'INV', 25, 'Diego Alberto', 'FLORES', 'HUINCHA', 'DNI', '75393977', 'ASOC. DE PROPIETARIOS LAS MERCEDES MZ. C LT. 17', 27, 'Investigado', 'Reconstrucción', '2026-03-11', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Visualización de video', 2, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-05 19:56:31'),
(19, 32, 'INV', 53, 'Carlos Gilbert', 'ARCE', 'PEÑA', 'DNI', '10389997', 'CALLE FEDERICO BARRETO 117 URB. SAN AGUSTIN 2DA. ETAPA', 56, 'Investigado', 'Reconstrucción', '2026-03-19', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Visualización de video', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-12 04:21:34'),
(20, 32, 'FAM', 11, 'Ly Hernan', 'MANRRIQUE', 'CASTELLANO', 'DNI', '44579792', 'CALLE LOS ALHELIES URB. LOS LIRIOS MZ. D LT. 16', NULL, 'Familiar más cercano', 'Toma de declaración', '2026-03-19', '09:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-12 04:22:54'),
(21, 32, 'FAM', 11, 'Ly Hernan', 'MANRRIQUE', 'CASTELLANO', 'DNI', '44579792', 'CALLE LOS ALHELIES URB. LOS LIRIOS MZ. D LT. 16', NULL, 'Familiar más cercano', 'Reconstrucción', '2026-03-19', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Visualización de video', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-12 04:23:28'),
(22, 40, 'FAM', 20, 'Amparo', 'QUISPE', 'TRUJILLANO', 'DNI', '09623726', 'JR PIRA 607 URB EL PARQUE NARANJAL', NULL, 'Familiar más cercano', 'Toma de declaración', '2026-03-24', '09:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-17 01:27:17'),
(23, 40, 'INV', 73, 'Misael', 'LOPEZ', 'BERRU', 'DNI', '74739871', 'Dpto. 5 Urb. Viñas de Naranjal-SMP', 30, 'Investigado', 'Toma de declaración', '2026-03-24', '10:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-17 01:28:05'),
(24, 40, 'PRO', 19, 'Maritza Lucero', 'ALZAMORA', 'CONGA', 'DNI', '74380102', 'AV. ERNESTO MALINOWSKY 338 MZ. G LT. 11', NULL, 'Propietario del vehículo', 'Toma de declaración', '2026-03-24', '11:30:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-17 01:33:34'),
(26, 40, 'FAM', 20, 'Amparo', 'QUISPE', 'TRUJILLANO', 'DNI', '09623726', 'JR PIRA 607 URB EL PARQUE NARANJAL', NULL, 'Familiar más cercano', 'Toma de declaración', '2026-03-27', '14:00:00', 'Carretera Panamericana Norte Km. 42 (alt. Garita control SUNAT)-Santa Rosa- sede UIAT NORTE', 'Rendir manifestación', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 15:17:05'),
(27, 40, 'INV', 73, 'Misael', 'LOPEZ', 'BERRU', 'DNI', '74739871', 'Dpto. 5 Urb. Viñas de Naranjal-SMP', 30, 'Investigado', 'Reconstrucción', '2026-03-31', '18:00:00', 'Lugar de los hechos', 'Inspección Técnico Policial', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 17:47:06'),
(28, 40, 'INV', 73, 'Misael', 'LOPEZ', 'BERRU', 'DNI', '74739871', 'Dpto. 5 Urb. Viñas de Naranjal-SMP', 30, 'Conductor', 'Reconstruccion', '2026-04-06', '17:00:00', 'Lugar de los hechos', 'Inspección Técnico Policial', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 21:23:31'),
(29, 40, 'INV', 73, 'Misael', 'LOPEZ', 'BERRU', 'DNI', '74739871', 'Dpto. 5 Urb. Viñas de Naranjal-SMP', 30, 'Investigado', 'Reconstruccion', '2026-04-13', '17:00:00', 'Lugar de los hechos', 'Inspección Técnico Policial', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 21:23:40'),
(30, 40, 'FAM', 20, 'Amparo', 'QUISPE', 'TRUJILLANO', 'DNI', '09623726', 'Jr. Pira 607 Urb. El Parque Naranjal', NULL, 'Familiar más cercano', 'Reconstruccion', '2026-04-13', '17:00:00', 'Lugar de los hechos', 'Inspección Técnico Policial', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-06 21:31:55'),
(31, 36, 'PNP', 15, 'Enzo Giovanni', 'ANGELES', 'ANGELES', 'DNI', '46506905', 'Urb. Santa Teresita Mz D-7-Ancash-Huaylas-Caraz', NULL, 'Efectivo policial', 'Toma de declaracion', '2026-04-16', '10:00:00', 'Carretera Panamericana Norte km. 42 (alt. garita control SUNAT) sede de la Unidad de Investigacion de Accidentes de Transito-Lima Norte', 'Rendir manifestacion', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-09 15:40:39'),
(35, 40, 'INV', 73, 'Misael', 'LOPEZ', 'BERRU', 'DNI', '74739871', 'Dpto. 5 Urb. Viñas de Naranjal-SMP', 30, 'Investigado', 'Toma de declaracion', '2026-05-11', '10:00:00', 'Carretera Panamericana Norte km. 42 (alt. garita control SUNAT) sede de la Unidad de Investigacion de Accidentes de Transito-Lima Norte', 'Rendir manifestacion', 1, NULL, 'ua3qao7j556d48s5gukhget2d0', 'https://www.google.com/calendar/event?eid=dWEzcWFvN2o1NTZkNDhzNWd1a2hnZXQyZDAgN250Ym05aGFibG00am0wbGJpMXN2Mm83YTRAZw', 'sincronizado', '2026-05-05 12:12:08', NULL, '2026-05-05 17:12:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comisarias`
--

CREATE TABLE `comisarias` (
  `id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lon` decimal(10,7) DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `comisarias`
--

INSERT INTO `comisarias` (`id`, `nombre`, `tipo`, `direccion`, `telefono`, `correo`, `lat`, `lon`, `notas`, `activo`, `creado_en`, `actualizado_en`) VALUES
(4, 'Comisaria PNP Ancón', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-09-18 08:37:06', '2025-09-18 08:37:06'),
(5, 'Comisaria PNP Santa Rosa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-09-18 08:41:04', '2025-09-18 08:41:04'),
(6, 'Comisaria PNP Santa Luzmila', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-09-18 08:55:06', '2025-09-18 08:55:06'),
(7, 'Comisaria PNP La Pascana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-09-18 08:55:32', '2025-09-18 08:55:32'),
(8, 'Comisaria PNP Túpac Amaru', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-09-18 08:56:01', '2025-09-18 08:56:01'),
(10, 'Comisaria PNP El Progreso', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-09-21 15:56:47', '2025-09-21 15:56:47'),
(11, 'Comisaría PNP Barboncitos', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-09-23 03:27:52', '2025-12-10 20:33:38'),
(12, 'Comisaria PNP Santa Isabel', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-10-12 08:04:49', '2025-10-12 08:04:49'),
(13, 'Comisaria PNP Sol de Oro', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-10-14 22:59:44', '2025-10-14 22:59:44'),
(14, 'Comisaria PNP La Unificada', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-10-16 15:30:13', '2025-10-16 15:30:13'),
(16, 'Comisaria PNP Puente Piedra', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-10-20 04:09:30', '2025-10-20 04:09:30'),
(17, 'Comisaria PNP San Martín de Porres', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-10-26 17:18:58', '2025-10-26 17:18:58'),
(18, 'Comisaria PNP Laura Caller Ibérico', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-10-30 20:47:47', '2025-10-30 20:47:47'),
(19, 'Comisaría PNP Carabayllo', 'A', NULL, '959091071 / 959096091', 'carabayllo.cpnp@gmail.com', NULL, NULL, NULL, 1, '2025-12-09 23:46:33', '2025-12-10 20:27:41'),
(21, 'Comisaría PNP Zapallal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-11 20:34:08', '2026-01-11 20:34:08'),
(22, 'Ancón', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-12 05:39:51', '2026-02-12 05:39:51'),
(23, 'Comisaría PNP Independencia', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-12 06:20:37', '2026-03-12 06:20:37'),
(24, 'Comisaría PNP Collique', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-31 14:29:57', '2026-03-31 14:29:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comisaria_distrito`
--

CREATE TABLE `comisaria_distrito` (
  `comisaria_id` int NOT NULL,
  `cod_dep` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_prov` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_dist` char(2) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `comisaria_distrito`
--

INSERT INTO `comisaria_distrito` (`comisaria_id`, `cod_dep`, `cod_prov`, `cod_dist`) VALUES
(4, '15', '01', '02'),
(5, '15', '01', '39'),
(6, '15', '01', '10'),
(7, '15', '01', '10'),
(8, '15', '01', '10'),
(10, '15', '01', '06'),
(11, '15', '01', '35'),
(12, '15', '01', '06'),
(12, '15', '01', '10'),
(13, '15', '01', '17'),
(13, '15', '01', '35'),
(14, '15', '01', '12'),
(16, '15', '01', '25'),
(17, '15', '01', '35'),
(18, '15', '01', '17'),
(19, '15', '01', '06'),
(21, '15', '01', '25'),
(22, '15', '01', '02'),
(23, '15', '01', '12'),
(24, '15', '01', '10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consecuencia_accidente`
--

CREATE TABLE `consecuencia_accidente` (
  `id` int NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `consecuencia_accidente`
--

INSERT INTO `consecuencia_accidente` (`id`, `nombre`, `descripcion`, `creado_en`) VALUES
(1, 'Muerte', 'Accidente con resultado de fallecimiento', '2025-09-17 06:45:06'),
(2, 'Lesiones', 'Accidente con personas heridas', '2025-09-17 06:45:06'),
(3, 'Daños', 'Accidente con daños materiales únicamente', '2025-09-17 06:45:06'),
(4, 'Daños materiales', NULL, '2025-10-20 14:30:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `diligencias_pendientes`
--

CREATE TABLE `diligencias_pendientes` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `citacion_id` int DEFAULT NULL,
  `oficio_id` int DEFAULT NULL,
  `tipo_diligencia_id` int DEFAULT NULL,
  `tipo_diligencia` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('Pendiente','En proceso','Realizado','Cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendiente',
  `documento_realizado` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documentos_recibidos` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `diligencias_pendientes`
--

INSERT INTO `diligencias_pendientes` (`id`, `accidente_id`, `citacion_id`, `oficio_id`, `tipo_diligencia_id`, `tipo_diligencia`, `contenido`, `estado`, `documento_realizado`, `documentos_recibidos`, `creado_en`, `actualizado_en`) VALUES
(1, 30, NULL, NULL, 4, 'Manifestación', 'RECIBASE la declaración testimonial de la sucesora de Q.E.V.F. Bruno Crisóstomo\r\nmadre MAR1A ISABEL-AlEXANDRA BOLAÑOS-REY-ES:-', 'Pendiente', NULL, NULL, '2025-11-25 08:46:55', '2025-11-25 08:46:55'),
(2, 30, NULL, NULL, 4, 'Manifestación', 'RECIBÁSE la declaración del propietario· del vehíeL/lo con pla\'cá ·rodéife (4509-LA}', 'Pendiente', NULL, NULL, '2025-11-25 08:52:58', '2025-11-25 08:52:58'),
(3, 33, NULL, NULL, 7, 'Resultado de dosaje etilico', 'Se solicite el protocolo de necropsia del conductor a la comisaria', 'Pendiente', NULL, NULL, '2025-12-10 23:13:03', '2025-12-10 23:13:03'),
(4, 26, NULL, NULL, 8, 'Inspección Técnico Policial', 'Citar a todas las partes para poder desarrolla la diligencia de IPT en el lugar de los hechos', 'Pendiente', 'Se ha realizado las citaciones correspondientes y se ha remitido a las partes involucradas', NULL, '2025-12-15 05:19:01', '2025-12-15 05:20:52'),
(5, 20, NULL, NULL, 9, 'Visualización de Video', 'Realizar diligencian policial de visualizacion que se pudo haber obtenido con presencia de todas las partes', 'Realizado', 'Se realizo citaciones para ambas partes', NULL, '2026-02-02 04:15:30', '2026-04-05 01:52:44'),
(6, 20, NULL, NULL, 4, 'Manifestación', 'Recibir la manifestacion de copiloto', 'Pendiente', NULL, NULL, '2026-02-02 04:19:48', '2026-02-02 04:19:48'),
(7, 40, NULL, NULL, 4, 'Manifestación', 'manifestacion investigado Misael LOPEZ BERRU', 'Realizado', 'Se envio citación policial al investigado a traves de su numero de telefono, fue recepcionado fecha 24MAR2026 a las 10 de la mañana', NULL, '2026-03-16 15:26:22', '2026-03-17 04:15:16'),
(8, 40, NULL, NULL, 4, 'Manifestación', 'Tercero vicil responsable propietario del vehiculo de placa de rodaje 9128-7D', 'En proceso', NULL, NULL, '2026-03-16 15:28:09', '2026-03-17 05:44:06'),
(9, 40, NULL, NULL, 4, 'Manifestación', 'Recibir manifestacion del familiar mas cercano', 'Realizado', 'Se realizo citacion policial, fue remitido directamente al abogado para el 24MAR2026', NULL, '2026-03-16 15:28:42', '2026-03-17 04:15:20'),
(10, 40, NULL, NULL, 5, 'Informe Policial', 'Realizar el informe tecnico policial con factores', 'Pendiente', NULL, NULL, '2026-03-16 15:29:21', '2026-03-16 15:29:21'),
(11, 40, NULL, NULL, 10, 'Croquis demostrativo', 'Se elabore el diagrama que presente el detalle secuencial del evento materia de investigacion.', 'Pendiente', NULL, NULL, '2026-03-16 15:31:06', '2026-03-16 15:31:06'),
(12, 40, NULL, NULL, 11, 'Oficio Solicitar', 'Solicitar al Hospital Rebagliati  que remitan historia clinica del agraviado NArciso QUISPE RUPA, una vez recaba remitir conjuntamente con el informe medico procededente de la clinica JEsus del Norte (obrante en los actuados) al IML y ciencias forences', 'Realizado', NULL, NULL, '2026-03-16 15:33:12', '2026-03-17 05:43:49'),
(13, 40, NULL, NULL, 1, 'Protocolo de Necropsia', 'Recabas acta de defuncion de Q.E.V.F. Narciso QUISPE RUPA', 'Realizado', NULL, NULL, '2026-03-16 15:33:48', '2026-03-17 05:43:58'),
(14, 40, NULL, NULL, 2, 'Peritaje de constatación de daños', 'Solicitar el Peritaje de daños del vehiculo de placa de rodaje a la comisaria PNP Laura Caller', 'En proceso', NULL, NULL, '2026-03-16 15:35:54', '2026-03-17 05:44:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documentos_recibidos`
--

CREATE TABLE `documentos_recibidos` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `asunto` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_persona` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_documento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_documento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `fecha_recepcion` date DEFAULT NULL,
  `fecha_documento` date DEFAULT NULL,
  `contenido` text COLLATE utf8mb4_unicode_ci,
  `referencia_oficio_id` int DEFAULT NULL,
  `estado` enum('Pendiente','Revisado','Archivado') COLLATE utf8mb4_unicode_ci DEFAULT 'Pendiente',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `documentos_recibidos`
--

INSERT INTO `documentos_recibidos` (`id`, `accidente_id`, `asunto`, `entidad_persona`, `tipo_documento`, `numero_documento`, `fecha`, `fecha_recepcion`, `fecha_documento`, `contenido`, `referencia_oficio_id`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 33, 'Remite certificado de dosaje etilico', 'Comisaria de Carabayllo', 'Oficio', '714-2025-CC-SIAT', '2025-12-13', '2025-12-13', '2025-12-13', 'Remite certificado de dosaje etilico de Prospero Flores VEga', 27, 'Archivado', '2025-12-13 15:25:51', '2026-04-04 20:56:14'),
(2, 22, 'Disposicion fiscal Nro 02 de fecha 05DIC2025', 'Primera Fiscalia provincial penal corporativa de santa rosa', 'OFicio', '593-FN-MP-3D', '2025-12-13', '2025-12-13', '2025-12-13', 'DECRETO 110-2025 .-----Se remitr la disposicion nro 02 , a fin de poner en conocimiento la ampliacion de la investigacion preliminar en sede policial', NULL, 'Revisado', '2025-12-13 15:43:44', '2026-04-04 20:56:14'),
(3, 23, 'Camara de video vigilancia GALLINAZOS', 'Concesionario Rutas de Lima', 'Carta', '019155-VNL-PNP', '2025-09-29', '2025-09-29', '2025-09-29', 'Remite imagenes de la camara de video vigilancia altura de paradero Gallinazos', NULL, 'Archivado', '2025-12-15 07:22:50', '2026-04-04 20:56:14'),
(4, 34, 'Disposicion fiscal', 'Fiscalia Provincial Corporativa de Tránsito y Seguridad Vial-Cuarto Despacho', 'Carpeta Fiscal', '606015901-2025-4922-0', '2025-12-01', '2025-12-01', '2025-12-01', 'Dispone diligencias relacionadas al caso de atropello y fuga', NULL, 'Revisado', '2025-12-15 08:16:36', '2026-04-04 20:56:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_dosaje`
--

CREATE TABLE `documento_dosaje` (
  `id` int NOT NULL,
  `persona_id` int NOT NULL,
  `numero` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `numero_registro` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_extraccion` datetime DEFAULT NULL,
  `resultado_cualitativo` enum('Positivo','Negativo','No concluyente') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `resultado_cuantitativo` decimal(5,2) DEFAULT NULL,
  `leer_cuantitativo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_general_ci,
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `documento_dosaje`
--

INSERT INTO `documento_dosaje` (`id`, `persona_id`, `numero`, `numero_registro`, `fecha_extraccion`, `resultado_cualitativo`, `resultado_cuantitativo`, `leer_cuantitativo`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(5, 24, '0001-007105', '035767', '2023-07-15 23:17:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-10-12 08:36:15', '2025-10-12 08:36:15'),
(6, 25, '0001-007104', '035768', '2023-07-16 03:15:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-10-12 08:39:41', '2025-10-12 08:39:41'),
(7, 22, '0001-007103', '035769', '2023-07-16 03:20:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-10-12 08:41:36', '2025-10-12 08:41:36'),
(8, 49, '001-040384', '009510', '2025-04-17 03:28:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-10-27 04:26:32', '2025-10-27 04:26:32'),
(9, 50, '001-040385', '009511', '2025-04-17 04:30:00', 'Negativo', 1.80, 'Un gramo ochenta centigramos de alcohol por litro de sangre (1.80 g/L)', NULL, '2025-10-27 04:30:07', '2025-10-27 04:30:07'),
(10, 54, '001-001751', '013227', '2025-05-26 23:03:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-11-01 04:08:23', '2025-11-01 04:08:23'),
(11, 55, '001-001752', '013228', '2025-05-27 00:40:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-11-01 04:27:10', '2025-11-01 04:27:10'),
(12, 42, '001-007994', '019399', '2025-08-04 10:40:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-11-21 16:41:16', '2025-11-21 16:41:16'),
(13, 41, '001-007995', '019400', '2025-08-04 12:00:00', 'Positivo', 1.08, 'Un gramo ocho centigramos de alcohol por litro de sangre (1.08 g/L)', 'Muestra extraida en el mortuorio del Hospital Carlos Lanfranco la Hoz-Puente Piedra. Datos según oficio.', '2025-11-21 16:46:23', '2025-11-21 16:46:23'),
(14, 65, '001-033045', '00282', '2025-01-30 12:00:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-11-25 04:29:36', '2025-11-25 04:29:36'),
(15, 67, '001-033045', '002282', '2025-01-30 12:00:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-11-25 05:08:45', '2025-11-25 05:08:45'),
(16, 40, '001-011121', '022481', '2025-09-09 03:33:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-11-27 21:35:16', '2025-11-27 21:35:16'),
(17, 39, '001-011122', '022482', '2025-11-09 03:44:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-11-27 21:39:42', '2025-11-27 21:39:42'),
(18, 79, '001-018291', '029503', '2025-11-30 22:49:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-12-02 04:43:19', '2025-12-02 04:43:19'),
(19, 80, '001-018292', '029504', '2025-12-01 00:40:00', 'Negativo', 1.38, 'Un gramo treinta y ocho centigramos de alcohol por litro de sangre (1.38 g/L)', NULL, '2025-12-02 04:45:07', '2025-12-02 04:45:07'),
(20, 90, '001-030489', '028754', '2025-12-31 00:30:00', 'Positivo', 2.20, 'Dos gramos veinte centigramos de alcohol por litro de sangre (2.20 g/L)', NULL, '2025-12-11 03:48:33', '2025-12-11 03:48:40'),
(21, 83, '001-005816', '017254', '2025-07-10 02:55:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2025-12-15 07:02:14', '2025-12-15 07:02:14'),
(22, 29, '001-014167', '025458', '2025-10-14 00:50:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2026-02-07 21:15:10', '2026-02-07 21:15:10'),
(23, 122, '001-004249', '005651', '2026-03-08 03:53:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2026-03-27 16:16:44', '2026-03-27 16:16:44'),
(24, 124, '001-004250', '005652', '2026-03-08 05:45:00', 'Positivo', 1.38, 'Un gramo treinta y ocho centigramos de alcohol por litro de sangre (1.38 g/L)', NULL, '2026-03-27 16:34:13', '2026-03-27 16:34:13'),
(25, 30, '001-014168', '025459', '2025-10-14 17:15:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2026-04-04 23:37:33', '2026-04-04 23:37:33'),
(26, 37, '001-99999', '999999', '2026-04-12 10:00:00', 'Negativo', 0.00, 'Cero gramos de alcohol por litro de sangre (0.00 g/L)', NULL, '2026-04-14 06:25:44', '2026-04-14 06:25:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_lc`
--

CREATE TABLE `documento_lc` (
  `id` int NOT NULL,
  `persona_id` int NOT NULL,
  `clase` varchar(10) NOT NULL,
  `categoria` varchar(10) NOT NULL,
  `numero` varchar(30) NOT NULL,
  `expedido_por` varchar(100) DEFAULT NULL,
  `vigente_desde` date DEFAULT NULL,
  `vigente_hasta` date DEFAULT NULL,
  `restricciones` text,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Volcado de datos para la tabla `documento_lc`
--

INSERT INTO `documento_lc` (`id`, `persona_id`, `clase`, `categoria`, `numero`, `expedido_por`, `vigente_desde`, `vigente_hasta`, `restricciones`, `creado_en`, `actualizado_en`) VALUES
(8, 24, 'A', 'I', 'Q06874413', 'MTC', '1998-05-06', '2025-10-05', '', '2025-10-12 03:37:29', '2025-10-12 03:37:29'),
(9, 29, 'B', 'IIc', 'VMH-75393977', 'Municipalidad Provincial de Huarochiri-Matucana', '2016-04-21', '2028-08-11', 'Sin restricciones', '2025-10-16 01:33:08', '2025-10-16 01:33:08'),
(10, 45, 'B', 'IIc', 'VM10159943', 'Municipalidad Provincial del Callao', '2019-01-20', '2024-02-19', 'Sin restricciones', '2025-10-26 21:05:54', '2025-10-26 21:05:54'),
(11, 44, 'A', 'I', 'Q10191431', 'Ministerio de Transporte y Telecomunicaciones', '2002-01-09', '2031-05-31', 'Sin restricciones', '2025-10-26 21:37:12', '2025-10-26 21:37:12'),
(12, 49, 'A', 'IIa', '31639324', 'Policía Nacional del Perú', '2017-07-06', '2026-07-17', '', '2025-10-26 23:23:55', '2025-10-26 23:23:55'),
(13, 54, 'A', 'I', 'Q40348826', 'MTC', '2008-12-03', '2034-10-16', '', '2025-10-31 22:59:53', '2025-10-31 22:59:53'),
(14, 40, 'A', 'IIb', 'Q43165106', 'MTC', '2017-10-04', '2027-11-10', '', '2025-11-18 18:16:14', '2025-11-18 18:16:14'),
(15, 42, 'B', 'IIb', 'VM71389155', 'Municipalidad Provincial de Barranca', '2023-02-03', '2028-02-02', 'Sin restricciones', '2025-11-21 11:28:10', '2025-11-21 11:28:10'),
(17, 65, 'B', 'IIc', 'VM10749424', 'Municipalidad Provincial del Callao', '2017-08-11', '2022-08-10', 'Sin restricciones', '2025-11-24 23:25:55', '2025-11-24 23:25:55'),
(18, 79, 'A', 'IIIa', 'Q10389997', 'MTC', '2011-02-20', '2026-10-18', '', '2025-12-01 23:40:47', '2025-12-01 23:40:47'),
(19, 83, 'B', 'IIc', 'G0101010505', 'Municipalidad Provincial del Callao', '2020-01-16', '2030-02-21', 'Sin restriciones', '2025-12-09 21:59:18', '2025-12-09 21:59:18'),
(20, 37, 'A', 'IIIb', 'Q45782193', 'MTC', '2013-11-19', '2028-05-04', NULL, '2026-04-13 01:42:55', '2026-04-13 01:42:55'),
(21, 38, 'A', 'IIa', 'Q10015491', 'MTC', '2013-08-09', '2027-09-23', NULL, '2026-04-13 02:45:13', '2026-04-13 02:45:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_occiso`
--

CREATE TABLE `documento_occiso` (
  `id` int NOT NULL,
  `persona_id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `fecha_levantamiento` date DEFAULT NULL,
  `hora_levantamiento` time DEFAULT NULL,
  `lugar_levantamiento` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `posicion_cuerpo_levantamiento` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lesiones_levantamiento` text COLLATE utf8mb4_general_ci,
  `presuntivo_levantamiento` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `legista_levantamiento` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `cmp_legista` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observaciones_levantamiento` text COLLATE utf8mb4_general_ci,
  `numero_pericial` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_pericial` date DEFAULT NULL,
  `hora_pericial` time DEFAULT NULL,
  `observaciones_pericial` text COLLATE utf8mb4_general_ci,
  `numero_protocolo` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_protocolo` date DEFAULT NULL,
  `hora_protocolo` time DEFAULT NULL,
  `lesiones_protocolo` text COLLATE utf8mb4_general_ci,
  `presuntivo_protocolo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dosaje_protocolo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `toxicologico_protocolo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nosocomio_epicrisis` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero_historia_epicrisis` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tratamiento_epicrisis` text COLLATE utf8mb4_general_ci,
  `hora_alta_epicrisis` time DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `documento_occiso`
--

INSERT INTO `documento_occiso` (`id`, `persona_id`, `accidente_id`, `fecha_levantamiento`, `hora_levantamiento`, `lugar_levantamiento`, `posicion_cuerpo_levantamiento`, `lesiones_levantamiento`, `presuntivo_levantamiento`, `legista_levantamiento`, `cmp_legista`, `observaciones_levantamiento`, `numero_pericial`, `fecha_pericial`, `hora_pericial`, `observaciones_pericial`, `numero_protocolo`, `fecha_protocolo`, `hora_protocolo`, `lesiones_protocolo`, `presuntivo_protocolo`, `dosaje_protocolo`, `toxicologico_protocolo`, `nosocomio_epicrisis`, `numero_historia_epicrisis`, `tratamiento_epicrisis`, `hora_alta_epicrisis`, `creado_en`, `actualizado_en`) VALUES
(2, 16, 18, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025010101001296', '2025-04-29', '11:59:00', 'Se interno como NN', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-11 21:27:52', '2025-10-11 21:27:52'),
(3, 45, 26, '2025-07-12', '10:00:00', 'Mortuorio Hospital Nacional Cayetano Heredia', 'Decubito Dorsal', 'Herida de tipo quirurgica de 15 cm en region superior derecho de la cabeza.\r\nEscoriación en dorso de nariz y región anterior de rodilla derecho e izquierda.\r\nEquimosis periocular derecho e izquierda multiples.\r\nEquimosis en miembros superiores e inferiores.\r\nHerida de tipo quirúrgica de 8 cm en region temporal derecho.', 'Traumatismo craneo encefálico severo', 'Dr. Aldo Plinio POMA TORRES', 'CMP Nro. 25653', NULL, '2025010101002015', '2025-07-12', '14:37:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hospital Nacional Cayetano Heredia', '2397935', NULL, NULL, '2025-10-27 02:29:54', '2025-10-27 02:29:54'),
(4, 50, 27, '2025-04-17', '09:20:00', 'Mortuorio Hospital Carlos Lanfranco La Hoz', 'Decúbito Dorsal sobre camilla metalica', 'Herida contusa con excoriación en ceja izquierda pomulo izquierdo y lado izquierdo de la nariz.\r\nEscoriación en torax anterior derecho, ambos codos, region escapular izquierda brazo derecho, cara lateral derecha del toraxy abdomen, piernas.\r\nSignos de fractura costal derecha.', 'Traumatismo Torácico Abdominal Cerrado', 'Segundo German MILLONES GOMEZ', 'CMP Nro. 037714', NULL, '2025010101001199', '2025-04-17', '12:57:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hospital Carlos Lanfranco La Hoz', '829138', NULL, NULL, '2025-10-27 04:36:29', '2025-10-27 04:36:29'),
(5, 39, 24, '2025-09-09', '10:15:00', 'mortuorio del hospital Sergio Bernales Collique', 'Decúbito dorsal', 'Presenta tumefacción mas excoriación en la región parieto temporal derecho en cuero cabelludo.\r\nPresenta equimosis violacea en la región posterior del hombro derecho.\r\nEquimosis violacea mas excoriación en el tercio distal de la región posterio interna del brazo derecho\r\nEquimosis en la región lumbar derecha.\r\nA la digilopresión de la región lateral derecha de toráx.\r\nSe percibe un crujido y un leve desplazamiento de los arcos costales derecho\r\nEscoriación por fricción en el tercio proximal de la region externa del muslo derecho.\r\nEquimosis violacea en tercio medio y distal de la región posterior del muslo derecho.\r\nEscoriación por fricción en la región anterior de la pierna derecha.\r\nEquimosis violacea en la región antero interna de la rodilla derecha.\r\nMovilización anomala y signos de crujido en rodilla derecha.\r\nEscoriación por fricción en tercio  distal de la región interna de la pierna izquierda.\r\nMovilidad anomala del tercio distal de pierna tobilla izquierda, con signos de crujido.\r\nEquimosis violacea en tercio medio de la región anterior de la pierna izquierda.\r\nTres equimosis  violaceas en la región anterior e interior de la rodilla izquierda.\r\nEquimosis violacea en el tercio medio de la región anterior de ambos muslos.\r\nUna equimosis mas excoriación por fricción en cresta iliaca superior de cadera derecha.\r\nCuatro excoriaciones en la región peri umbilical inferior.\r\nHerida cortante en la región interna de muñeca derecha que abarca hasta la región anterior de mano derecha con exposición de tejido y ligamentos, equimosis violacea en dorso de mano derecha.\r\nEquimosis mas escoriacion en el tercio distal de la región externa del antebrazo derecho.\r\nEquimosis violacea en la region externa del codo derecho.\r\nEquimosis violacea tenue en la región  externa del hombro derecho.\r\nHematoma violacea en la región ciliar y palpebral del ojo derecho.\r\nEquimosis violacea mas escoriación en la region mentoneana y submentoneana.\r\nEquimosis  en el tercio distal de la región  anterior del cuello.\r\nEquimosis  violacea en la región infraclavicular izquierdo.\r\nEquimosis violacea en la región anterior del hombro izquierdo.\r\nEquimosis violacea rojiza mas herida contusa en la región posterior de muñeca, dorso de mano izquierda.\r\nPresenta movilidad anómala de la articulación de muñeca izquierda.', 'Politraumatismo, TEC, descartar fracturas en parrilla costal derecho, muñeca izquierda, pierna izquierda , rodilla derecha.', 'Marcus Kevin VILLANUEVA MENDOZA', '52260', NULL, '2025010101002658', '2025-09-09', '14:19:00', NULL, '002658-2025', '2025-09-22', '09:59:00', 'CABEZA\r\nExcoriación pardo rojiza de 3.5 x 0.4 cm ubicado en region frontal derecha\r\nTumefacción de 5.5 x4x0.5 cm con equimosis violácea de 5x3 ubicado en región palpebral superior derecha.\r\nEquimosis violacea de 9x7 cm  ubicado en región supraescapular derecha\r\nABDOMEN\r\nExcoriaciones multiples pardo rojiza en area de 9x7 cm ubicado en hipocondrio derecho\r\nExcoriacion pardo rojiza de 6x0.3 cm ubicado en mesogastrio\r\nExcoriación pardo rojiza de 2.5 x 0.5 cm ubicado en región hipogastrio\r\nMIEMBROS SUPERIORES\r\nExcoriación pardo rojiza de 2x2 cm con equimosis violacea de 8x5 5cm ubicado en cara lateral tercio distal de antebrazo derecho\r\nHerida contusa con bordes irregulares de 7x2 cm con profundidad de 1 cm ubicado en palma de mano derecha\r\nExcoriacion pardo rojiza de 1x0.1 cm ubicado en dorso de mano derecha\r\nEquimosis violacea de 9.5x 9 cm ubicado en cara posterior tercio distal de brazo derecho, codo derecho y cara posterior tercio proximal de antebrazo derecho\r\nHerida contusa con bordes irregulares de 1x0.3 cm con equimosis violacea de 6x5 cm ubicado en cara posterior tercio distal de antebrazo izquierdo\r\nLuxo fractura abierta de muñeca izquierda\r\nMIEMBROS INFERIORES\r\nEquimosis violacea de 13x9 cm ubicado en cara anterior tercio medio de muslo derecho\r\nEquimosis violacea de 9x8 cm ubicado en cara lateral externa tercio medio de muslo derecho\r\nEquimosis violacea de 8 x2.5 ubicado en cara lateral interna tercio distal de muslo derecho\r\nLuxacción de rodilla derecha\r\nExcoriación pardo rojiza de 20 x 0.5 cm ubicado en cara anterior de pierna derecha\r\nEquimosis violacea de 12 x 6.5 ubicado en cara anterior tercio medio de muslo izquierdo\r\nHerida contusa con bordes irregulares de 0.5 x 0.2 cm ubicado en cara anterior tercio distal de  pierna izquierda\r\nLuxofractura de todillo izquierdo\r\nLESIONES INTERNAS\r\nCABEZA\r\nHematoma rojo oscuro de 18x11x0.5 cm ubicado en area que abarca las regiones parietal izquierdo y temporal izquierdo de cara interna de cuero cabelludo y región subyacente de región  epicraneana\r\nCUELLO\r\nLuxofractura entre la cuarta y quinta vertebra cervical con hematoma rojo oscuro de tejido blandos subyacentes\r\nTORAX\r\nFractura de tercio proximal de clavicula izquierda\r\nLaceración de musculos del primer espacio intercostal izquierdo produciendo hemotorax izquierdo ya descrito\r\nLaceración de pericardio anterosuperior y posterior', 'Traumatismo Múltiple por suceso de tránsito', NULL, NULL, 'Hospital Sergio Bernales-Collique', '1481234', NULL, NULL, '2025-10-31 04:19:43', '2025-11-27 23:00:49'),
(6, 55, 29, '2025-05-26', '21:00:00', 'Carretera Panamericana Norte km 50+500', 'Decúbito dorsal pasivo', 'Herida contusa abierta de bordes irregulares de 3.5 cm x 0.5 cm ubicado en la región temporal izquierdo\r\nEquimosis rojizas de 4x2 cm ubicado en región palpebral superior izquierdo', 'Traumatismo Encéfalo Craneano abierto', 'Jonathan Evans VALVERDE SIFUENTES', '066116', NULL, '2025010101001572', '2025-05-27', '00:03:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-01 04:34:07', '2025-11-01 04:34:07'),
(7, 41, 25, '2025-08-04', '12:20:00', 'Mortuorio del Hospital de Puente Piedra-Carlos Lanfranco la Hoz', 'Decubito dorsal', 'Deformación a nivel de mandibula inferior con palpación de fractura cerrada\r\nHemorragia subconjuntival lateral\r\nPresencia de hemorragia en oido bilateral\r\nExcoriación en placa tipo arrastre de 47 cm x 20 cm a nivel abdominal izquierdo\r\nExcoriacion de 17 cm x 12 cm en placa tipo arrastre en región abdominal derecha\r\nSe palpa deformación a nivel de tóraxy esternón\r\nExcoriación lineal de 3 cm x 1 cm en región pectoral izquierda\r\nDeformación compatible con fractura cerrada en antebrazo izquierdo.\r\nMultiples escoriaciones en dorso de mano, antebrazo y brazo derecho\r\nExcoriación en placa tipo arrastre de 15 cm x 15 cm  en borde interno de muslo izquierdo\r\nExcoriación en placa de 8 cm x 7 cm en rodilla izquierda\r\nExcoriación en placa tipo arrastre de 18 cm x 7 cm en bordes internos de pierna izquierda\r\nFractura abierta con exposicion de tejido oseo en pierna derecha', 'Politraumatismo por suceso de tránsito', 'Daniel G. GURREONERO TRUJILLO', 'CMP NRO. 86634', NULL, '2025010101002268', '2025-08-04', '15:30:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-21 16:50:18', '2025-11-21 17:35:25'),
(8, 67, 30, '2025-02-06', '23:30:00', 'Mortuorio hospital Sergio Bernales-Collique', 'Decúbito dorsal', 'Herida contusa suturadas region malar derecha\r\nHerida contusa suturada region maxilar inferior derecho\r\nMúltiples excoriaciones difusas region pectoral derecha\r\nMúltiples excoriaciones en proceso de costrificacion en región posterior antebrazo\r\nRegión rodilla derecha y escoriaciones costrificacion muslo', 'Traumatismo Encéfalo Craneano. politraumatizado', 'William Raymundo VILCHEZ MALPARTIDA', 'CMP Nro. 53153', NULL, '2025010101000403', '2025-02-07', '04:09:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-11-25 05:21:17', '2025-11-25 05:21:17'),
(9, 80, 32, '2025-12-01', '12:20:00', 'Mortuorio del Hospital Carlos Lanfranco La Hoz', 'Decúbito Dorsa', 'Herida suturada de aprox. 6 cm en region frontal derecho\r\nTumefacción de 6x5 cm en region frontal con escoriacion.\r\nEscoración que abarca región supracilicar región frontal y periorbicular izquierda de aprox 7 cm x 9 cm\r\nEscoriación de 5.5 x 2 cm en dorso de nariz, se palpa deformidad en nariz.\r\nEscoriación  de 7 x 4 cm en región lateral derecho de abdomen\r\nVenopunción en flexuras de codo en ambas de miembros superiores', 'Traumatismo Encéfalo Craneano, traumatismo abdominal cerrado  toráxico abdominal', 'Grace Briggite HOYOS SAMPERTEGUI', '077967', NULL, '2025010101003478', '2025-12-01', '16:30:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hospital Carlos Lanfranco La Hoz', '847640', NULL, '13:00:00', '2025-12-02 05:02:07', '2025-12-02 05:02:07'),
(10, 84, 33, '2025-07-10', '10:42:00', 'Mortuorio Hospital Nacional Arzobispo Loayza', 'Decúbito Dorsal', 'Herida contusa suturada en región frontal derecho.\r\nEquimosis múltiples en región deltoide izquierda y mamaria izquierda.\r\nPresenta férula con vendaje en todo el miembro inferior derecho.', 'Traumatismos múltiples por suceso de tránsito', 'Juan Hugo APAZA PINO', '34864', NULL, '2025010101001994', '2025-01-10', '16:18:00', NULL, '001994-2025', '2025-07-18', '03:27:00', 'Examen Externo\r\nCABEZA\r\nUna herida contusa suturada que mide 4x0.2 cm con bordes rojizos ubicada en la región frontal derecha\r\nUna herida consuta suturada que mide 5x0.2 cm con bordes rojizos ubicada en la región temporal derecha\r\nUna equimosis violacea que mide 0.5 x 0.5 cm de forma irregular ubicada en dorso nasal.\r\nTÓRAX\r\nUna equimosis violacea que mide 8x6 cm de forma irregular ubicada en región pectoral.\r\nUna equimosis violacea que mide 3x3 cm de forma irregular ubicada en región infraclavicular\r\nUna equimosis violacea que mide 7x5 cm de forma irregular ubicada en la región supraclavicular izquierda\r\nUna equimosis violacea que mide 7x5 cm de forma irregular ubicada en hombro izquierda.\r\nExamen Interno\r\nUn hematoma de 20 x 18 x 0.3 cm rojo vinoso de forma irregular que abarca la cara interna de cuero cabelludo de la región frontal temporal y parietal derecho e izquierdo.\r\nUn infiltrado hemorrágico que mide 9x6.5 de forma irregular ubicada en músculo temporal derecho.\r\nEn cerebro se observa hemorragia subaracnoidea que compromete el 40 % de color rojizo oscuro de forma dispersa ubicada en ambos hemisferios (derecha e izquierda) con multiples areas de contusión que miden en promedio 0.5 x 0.4 cm  de forma irregular violacea ubicadas en región frontoparietal izquierda, al corte se observa hemorragia intraparenquimal de forma dispersa en el el parenquima cerebral.', 'Hemorragia dubaranoidea, traumatismo craneo encefálico cerrado', 'Positivo', NULL, 'Hospital Nacional Arzobispo Loayza', '3271565', NULL, '03:20:00', '2025-12-10 03:59:27', '2025-12-10 04:06:58'),
(11, 90, 34, '2024-12-31', '01:15:00', 'Carretera Lima Canta km 32.5-Carabayllo', 'Decúbito ventral', 'Herida contusa 3x1 en pierna derecha\r\nEscoriación superfacial dorso tórax izquierda y region lumbar\r\nEscoriación interescapular derecha\r\nExcoriación amplia en pierna derecha cara antero interna dorso de pie derecho.\r\nFractura pierna derecho tercio medio.\r\nTumefacción en región malar izquierda\r\nHerida contusa 3x2 rodilla derecho\r\nEscoriación en rodilla y pierna izquierda mitad proximal\r\nEscoriación en dorso de pierna\r\nEscoriación en dorso de antebrazo derecho mitad proximal  y codo adyacente\r\nEscoriación lineal en abdomen lado derecho y otros adyacentes\r\nEscoriación en hombro izquierdo\r\nEscoriacion en en región malar derecha y ciliar derecha\r\nHerida contusa 3x1 en región ciliar izquierda en dorso nasal tercio superior\r\nHerida contusa 3x2 supraciliar izquierdo\r\nAmplio semilunar abarca desde región frontal parietal derecho, perital izquierda y temporal izquierda', 'Traumatismo multiples', 'Roger Ernesto VELASQUEZ GUEVARA', '49768', NULL, '2024010101004093', '2024-12-31', '03:56:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-18 03:34:57', '2025-12-18 03:34:57'),
(12, 124, 38, '2026-03-08', '01:35:00', 'Carretera LIma Canta -Carabayllo', 'Decúbito dorsal con la pierna y pie izquierdo flexionado', 'Herida desde region ciliar derecha hasta región temporal derecha de cuero cabelludo.\r\nHerida desde región efenoidal izquierda hasta la región occipital del cuero cabelludo .\r\nPalidez en labio, herida cortante en region posterior del brazo derecho.\r\nMúltiples heridas en región posterior del codo derecho en tercio proximal media de la region posterior y externa del antebrazo derecho, en dorse de falange del 3ro y 5to dedo de la mano derecha\r\nUn area con multiples escoriaciones y heridas por fricción en la region lateral derecho de torax\r\nEquimosis rojiza violacea en lado izquierdo del dorso del pie izquierdo.\r\nEquimosis violacea en tercio proximal y medio de la region antero interna de la pierna izquierda\r\nDos escoriaciones en el tercio proximal y distal de la region antero externa del muslo derecho.\r\nEquimosis rojiza en la región infraescapular derecha.\r\nA su digito presión se evidencian crujido y movilidad de arco costales de la región posterior y lateral derecha .\r\nEquimosis violacea en tercio distal de la region posterior externa muldo derecho.\r\nEn la region posterior externa de rodilla derecha y en tercio proximal de la región externa de la pierna derecha.\r\nEquimosis violacea en el tercio proximal medio de la región interna de la pierna izquierda\r\nEquimosis violacea en la region interna del talon del pie izquierdo.\r\nArea con multiples excoriaciones por fricción en la región lateral izquierda del tórax', 'Politraumatismo, traumatismo craneo facial, traumatismo torácico cerrado, fractura de arcos costales derecho.', 'Marcus Kevin VILLANUEVA MENDOZA', '52260', NULL, '2026010101000759', '2026-03-08', '04:54:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-27 17:00:08', '2026-03-27 17:00:08'),
(13, 30, 20, '2026-10-14', '12:50:00', 'Mortuorio de la clínica Jesús del Norte', 'Decúbito Dorsal', 'Erosión en mucosa labial inferior izquierda.\r\nEquimosis violacea tercio superior anterior de brazo izquierdo.\r\nEquimosis violacea difuso en región lumbar.\r\nCadera lado izquierdo, excoriaciones pequeñas varias en cara posterior de brazo izquierdo,.\r\nPequeñas erociones en dorso y palma de mano izquierda.', 'Policontuso por accidente de tránsito', 'Leyle E. MOTTA LAZO', '32481', NULL, '2025010101003008', '2025-10-14', '15:13:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Clínica Jesús del Norte', '5338402', NULL, '10:26:00', '2026-04-04 23:38:43', '2026-04-04 23:45:22'),
(14, 38, 23, '2025-09-25', '18:30:00', 'Carretera Panamericana Norte km. 26.5-Puente Piedra', 'Semi sentado sobre lado derecho', 'Secreción hemática proveniente de fosas nasales y boca, aparenta aumento de volumen en rostro\r\nDepresión de parrilla costal izquierda', 'Traumatismo toraxico cerrado por aplastamiento', 'Daniel RAMIREZ CASTAÑEDA', '98827', NULL, '2025010101002811', '2025-09-25', '21:53:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-13 07:51:44', '2026-04-13 07:54:31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_rml`
--

CREATE TABLE `documento_rml` (
  `id` int NOT NULL,
  `persona_id` int NOT NULL,
  `accidente_id` int DEFAULT NULL,
  `numero` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `incapacidad_medico` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `atencion_facultativo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `documento_rml`
--

INSERT INTO `documento_rml` (`id`, `persona_id`, `accidente_id`, `numero`, `fecha`, `incapacidad_medico`, `atencion_facultativo`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(2, 49, NULL, '005678-L-D', '2025-04-17', 'No requiere', 'No requiere', NULL, '2025-10-26 23:24:46', '2025-10-26 23:24:46'),
(3, 54, NULL, '007599', '2025-05-27', '4', '2', NULL, '2025-10-31 23:03:51', '2025-10-31 23:03:51'),
(4, 42, NULL, '011160-L-D', '2025-08-04', '6', '2', '1.Presenta signos de lesiones fisicas traumaticas recientes\r\n2.Lesiones ocasionadas por agente contundente duro y mecanismo de friccion con superficie aspera y rugosa\r\n3.Requieres dias de descando medico legal.', '2025-11-21 11:40:18', '2025-11-21 11:40:18'),
(5, 65, NULL, '003703-L', '0025-12-30', '06', '02', NULL, '2025-11-24 23:28:42', '2025-11-24 23:28:42'),
(6, 40, NULL, '030262-LD', '2025-09-09', 'No requiere', 'No requiere', NULL, '2025-11-27 16:34:33', '2025-11-27 16:34:33'),
(7, 79, NULL, '016996-L-D', '2025-12-01', 'No requiere', 'No requiere', NULL, '2025-12-01 23:42:28', '2025-12-01 23:42:28'),
(8, 83, NULL, '003433-L-D', '2025-07-10', '03', '01', 'Presenta huellas de lesiones corporales traumaticas recientes', '2025-12-09 22:01:56', '2025-12-09 22:01:56'),
(9, 37, NULL, '013712-LT-D', '2025-09-26', 'No requiere', 'No requiere', NULL, '2026-04-13 01:43:58', '2026-04-13 01:43:58');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `documento_vehiculo`
--

CREATE TABLE `documento_vehiculo` (
  `id` int NOT NULL,
  `involucrado_vehiculo_id` int NOT NULL,
  `vehiculo_id` int DEFAULT NULL,
  `numero_propiedad` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `titulo_propiedad` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `partida_propiedad` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sede_propiedad` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `numero_soat` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `aseguradora_soat` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vigente_soat` date DEFAULT NULL,
  `vencimiento_soat` date DEFAULT NULL,
  `numero_revision` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `certificadora_revision` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vigente_revision` date DEFAULT NULL,
  `vencimiento_revision` date DEFAULT NULL,
  `numero_peritaje` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fecha_peritaje` date DEFAULT NULL,
  `perito_peritaje` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sistema_electrico_peritaje` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sistema_frenos_peritaje` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sistema_direccion_peritaje` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sistema_transmision_peritaje` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sistema_suspension_peritaje` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `planta_motriz_peritaje` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `otros_peritaje` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `danos_peritaje` text COLLATE utf8mb4_general_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `documento_vehiculo`
--

INSERT INTO `documento_vehiculo` (`id`, `involucrado_vehiculo_id`, `vehiculo_id`, `numero_propiedad`, `titulo_propiedad`, `partida_propiedad`, `sede_propiedad`, `numero_soat`, `aseguradora_soat`, `vigente_soat`, `vencimiento_soat`, `numero_revision`, `certificadora_revision`, `vigente_revision`, `vencimiento_revision`, `numero_peritaje`, `fecha_peritaje`, `perito_peritaje`, `sistema_electrico_peritaje`, `sistema_frenos_peritaje`, `sistema_direccion_peritaje`, `sistema_transmision_peritaje`, `sistema_suspension_peritaje`, `planta_motriz_peritaje`, `otros_peritaje`, `danos_peritaje`, `creado_en`, `actualizado_en`) VALUES
(2, 15, 10, '70057890', '122399-2022', '51875408', 'LIMA', '862583900', 'Rimac Seguros', '2023-05-17', '2024-05-17', 'C-2023-013-400-000901', 'Revisiones Técnicas del Perú', '2023-02-07', '2024-02-07', '2447', '2023-07-17', 'SS. PNP Carlos S. SOTO BEDOYA', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Placa de rodaje anterior tercio izquierdo doblado\r\nFunda de parachoques anterior tercio medio raspado y descuadrado con todo mascarilla inferior\r\nMascara principal tercio medio e izquierdo inferior rota\r\nCapot de motor tercio medio raspado, hundido y descuadrado\r\nLuna parabrisas anterior rota', '2025-10-12 08:59:27', '2025-10-23 16:32:53'),
(3, 26, 21, '73963959', '2023-3201197', '55087914', 'Lima', '3022400376979', 'MAPFRE PERU', '2024-11-10', '2025-11-10', '', '', NULL, NULL, '1368', '2025-04-17', 'ST3.PNP Valentin FERNANDEZ NOLAZCO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Parachoque delantero tercio medio doblado y hundido, funda del mismo raspado, hundido y descuadrado.\r\nPlaca de rodaje delantero doblada y hundida, porta placa del mismo roto.\r\nMascara frontal tercio medio hundido y roto.\r\nFaros neblineros tipo led en el mismo lado derecho e izquierdo descuadrados.\r\nCapot de motor descuadrado, tercio medio abollado, raspado y hundido, moldura anterior del mismo descuadrado.\r\nLuna parabrisas tercio inferior medio trizado.', '2025-10-27 04:56:21', '2025-10-27 04:56:21'),
(4, 27, 23, '44415650', '2023-2973243', '53574697', 'Lima', '2010038543', 'Pacifico Seguros', '2025-05-17', '2026-05-17', 'C-2025-115-145-001478', 'Revisiones de Automotores S.A.C.', '2025-03-17', '2026-03-17', '1826', '2025-05-29', 'S1.PNP Christian Steven RIOS ARGE', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Funda del parachoques delantero tercio derecho deformado, roto, descuadrado y desprendido\r\nFaro delantero derecho roto y descuadrado.\r\nRejilla y mascara anterior deformado, roto y desprendido\r\nParachoque delantero tercio derecho, doblado y descuadrado\r\nPuente frontal superior tercio derecho descuadrado\r\nCondensador y radiador doblado y descuadrado\r\nCapot del motor tercio derecho, abollado, doblado y descuadrado, chapa del mismo descuadrado\r\nDeposito de limpia parabrisas deformado roto y descuadrado\r\nMotor y caja desplazado, soporte roto.\r\nGuardafangos delantero derecho abollado, doblado, corrugado y descuadrado; mandil del mismo deformado y descuadrado\r\nRueda delantero derecho descentrado y desplazado de adelante hacia atras; trapecio, McPherson , conjunto  de dirección y palier doblado y desentrado\r\nParabrisas anterior tercio derecho trizado y roto\r\nPoste delantero derecho hundido\r\nPuerta delantero derecho raspado, hundido y descuadrado\r\nPuerta posterior raspado y hundido\r\nDos (02) bolsas de aire o airbag delantero del conductor y copiloto se encuentran activados', '2025-11-01 04:40:10', '2025-11-01 04:54:21'),
(5, 28, 24, '0004969482', '2012-00067157', '52305065', 'Lima', '2003368726', 'PACIFICO SEGUROS', '2019-01-15', '2020-01-15', '', '', NULL, NULL, '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2025-11-01 05:02:11', '2025-11-01 05:02:11'),
(6, 23, 18, '56708164', '2024-514036', '55152248', 'Lima', '141226564', 'La Positiva', '2025-03-10', '2026-03-10', '', '', NULL, NULL, '2633', '2025-08-12', 'ST3. PNP FERNANDEZ', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Suspension posterior doblado\r\nTapabarro posterior descentrado, lado derecho raspado, hundido y roto, estructura metalica doblada\r\nDisco de freno posterior cara externa raspada y doblada\r\nTrapecio posterior lado derecho abollada, raspada y hundida\r\nestribo posterior lado derecho rasado, roto y desprendido, soporte del mismo raspado y roto\r\nmoldura de asiento lado derecho raspado, roto y descuadrado\r\nestructura metálica posterior (pasamanos y base de caja porta objetos) descuadrado y desplazado hacia la izquierda, lado derecho raspado, hundido y roto\r\nFaro direccional posterior lado derecho roto y desprendido parcialmente\r\nsoporte posterior desprendido, soporte del mismo roto y desprendido\r\nFaro direccional posterior lado izquierdo roto\r\nFaros posteriores descuadrados\r\nAsiento descuadrado, tapa en parte central rota\r\nEstructura metálica de asiento lado izquierdo descuadrado, soportes rotos, trapecio posterior lado izquierdo doblado\r\nCadena de arrastre desprendida de su posición, mandador del mismo raspado, roto y descarrilado\r\nEstribo posterior lado izquierdo raspado\r\ncara externa de neumático posterior lado izquierdo raspado\r\ntapa central (moldura) lado izquierdo descuadrado\r\nprotector metálico tubular contra caídas (slider) lado izquierdo doblado, topes de gomas de los mismos superior e inferior raspados\r\npedal de freno lado atrás\r\ntimón de manubrio lado izquierdo desplazado hacia atrás, y externo de manubrio lado izquierdo raspado y roto\r\nprotector de mano del mismo roto y desprendido, manija de embrague raspada y rota\r\nespejo retrovisor lado izquierdo roto y soporte del mismo rotos y desprendidos\r\nFaro delantero y máscara (moldura) del mismo rotos y desprendidos\r\nFaros direccionales, delanteros lado mismo raspado; roto y desprendido\r\nMica parabrisas roto, protector del mismo raspado, roto y desprendido\r\ntablero de instrumentos roto, cables y conexiones frontales rotas\r\nTope externo de manubrio lado derecho raspado y desprendido parcialmente\r\nProtector de manos del mismo roto parcialmente\r\nTopes de goma superior e inferior de slider lado derecho raspados\r\nRueda posterior descentrada e inclinada hacia la izquierda, neumatico lado derecho raspado, eje del mismo doblado', '2025-11-22 01:44:05', '2025-11-23 20:06:54'),
(7, 29, 26, '0900037967', '2018-1515871', '53864873', 'Lima', '2024032129', 'AutoSeguro', '2025-09-17', '2025-09-17', '', '', NULL, NULL, '486', '2025-02-06', 'ST3. PNP Juan GUADAMUR CANALES', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Carrocería delantero tercio derecho abollado, hundido y descuadrado.\r\nFaro delantero derecho roto.\r\nMoldura de faro delantero derecho raspado, roto y descuadrado.\r\nFaro direccional delantero derecho roto.\r\nParabrisas delantero y posterior roto y desprendido.\r\nBase y espejo externo delantero derecho doblado y desalineado.\r\nPuerta de fibra de vidrio delantero derecho raspado, doblado y descuadrado.\r\nPuerta de fibra de vidrio central delantero y posterior derecho roto y desprendido.\r\nPoste de fibra de vidrio central derecho roto.\r\nEstribo y piso lateral derecho – tercio anterior y medio – raspado, doblado y descuadrado.\r\nTecho de carrocería de fibra de vidrio lateral derecho tercio anterior y medio roto y descuadrado.\r\nCompartimiento delantero derecho doblado de adelante hacia atrás y descuadrado.\r\nCaña de timón doblado y descuadrado.', '2025-11-25 04:20:11', '2025-11-25 04:20:11'),
(8, 30, 27, '57603439', '2025-34044', '55337252', 'Lima', '14098542', 'La Positiva', '2025-01-14', '2026-01-14', '', '', NULL, NULL, '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2025-11-25 05:04:20', '2025-11-25 05:04:20'),
(9, 22, 17, '09000473846', '2019-1587323', '54089939', 'Lima', '594336955', 'Interseguro', '2025-07-23', '2026-07-23', 'C-2025-333-501-010622', 'Multiservicios Turing Cusco E.I.R.L.', '2025-05-10', '2025-11-10', '3011', '2025-09-12', 'ST3.PNP Valentin FERNANDEZ NOLASCO', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Parachoque delantero tercio medio derecho doblado y hundido, funda del mismo raspado, roto y descuadrado, soportes rotos.\r\nPlaca de rodaje delantera lado derecha doblada y desprendida parcialmente, porta placa del mismo roto parcialmente.\r\nFaro principal delantero lado derecho descuadrado, soportes rotos\r\nCapot de motor descuadrado, tercio derecho abollado, raspado y hundido.\r\nLuna parabrisas tercio inferior derecho trizado.\r\nGuardafango delantero lado derecho descuadrado y desplazado hacia atras.', '2025-11-27 20:45:19', '2025-11-27 20:45:19'),
(10, 36, 34, '0900438332', '2019-1636668', '50793095', 'Lima', '117233630012', 'Rimac', '2025-08-01', '2026-08-01', 'C-2025-201-301-008977', 'IVETEC PERU S.A.C.', '2025-09-20', '2026-01-29', '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2025-12-02 04:39:13', '2025-12-02 04:39:13'),
(11, 37, 35, '54347162', '2020-1756543', '54347162', 'Lima', '2024045239', 'Autoseguro', '2024-10-29', '2025-10-29', '', '', NULL, NULL, '', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', '2025-12-10 02:55:56', '2025-12-10 02:55:56'),
(12, 16, 11, '62749659', '2024-3587959', '55147277', 'Lima', '141191296', 'La Positiva', '2025-03-02', '2026-03-02', NULL, NULL, NULL, NULL, '3548', '2025-10-31', 'ST3.PNP Valentin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Mica parabrisas rajada, soportes doblados.\nEstructura metalica tubular contra caidas (slider) lado derecho raspado y doblado hacia atras, tope de goma del mismo raspado.\nProtector de manos lado derecho raspado\nCubierta del espejo retrovisor lado derecho borde externo raspado\nSilenciador, protector y abrazadera del mismo raspados.\nMoldura de asiendo lado derecho raspado y descuadrado.\nProtector de manos lado izquierdo raspado.\nEspejo retrovisor lado izquierdo roto y desprendido.\nEstructura metalica tubular contra caidas (slider) lado izquierdo raspado.\nMoldura de tanque de combustible lado izquierdo roto y descuadrado.\nProtector de goma del pedal selector de cambios de velocidad raspado.\nEstribo delantero lado izquierdo raspado.', '2026-04-04 23:15:03', '2026-04-04 23:32:06'),
(13, 45, 14, '72825849', '2021-24659348', '54430090', 'Lima', '0594094398', 'Interseguro', '2025-02-18', '2026-02-18', 'C-2025-365-567-001215', 'C.I.T.V GRUPO J&L S.A.C.', '2025-02-25', '2026-02-25', '3177', '2025-09-27', 'ST3. PNP Valentin FERNANDEZ NOLASCO', 'Daños: No presenta', 'Daños: No presenta', 'Daños: No presenta', 'Daños: No presenta', 'Daños: No presenta', 'Daños: No presenta', 'Vehiculo revisado en el frontis de la UIAT NORTE', 'Enganche de remolque en parte posterior central doblado.', '2026-04-13 07:17:28', '2026-04-13 07:30:20'),
(14, 46, 15, '72827033', '3023951-2021', '54302175', 'Lima', NULL, NULL, NULL, NULL, 'C-2024-138-356-018479', 'CITV GRUPO J & J S.A.C.', '2024-12-10', '2025-12-10', '3178', '2025-09-27', 'ST3.PNP Valentin FERNANDEZ NOLASCO', 'Daños: No presenta', 'Daños: No presenta', NULL, NULL, 'Daños: No presenta', NULL, 'Vehículo revisado en el frontis de la UIAT NORTE', 'Estructura metálica frontal tipo lanza de acoplamiento tercio anterior (ojo) doblado\nCarrocería lado derecho raspado\nMarco superior lado derecho tercio medio posterior hundido', '2026-04-13 07:31:26', '2026-04-13 07:37:56'),
(15, 21, 16, '16220388', '2879345-2024', '53177321', 'Lima', '141376470', 'La Positiva', '2025-04-16', '2026-04-16', 'C-2025-395-598-003271', 'CITV EL ARCO DE JICAMARCA E.I.R.L.', '2025-04-16', '2026-04-16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-13 07:39:12', '2026-04-13 07:43:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `enlace_interes`
--

CREATE TABLE `enlace_interes` (
  `id` int UNSIGNED NOT NULL,
  `categoria` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'OTROS',
  `nombre` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_general_ci,
  `orden` int NOT NULL DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `enlace_interes`
--

INSERT INTO `enlace_interes` (`id`, `categoria`, `nombre`, `url`, `descripcion`, `orden`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 'TRANSITO', 'Licencia de conducir por puntos', 'https://slcp.mtc.gob.pe/', 'consulta de puntos por dni licencia de conducir', 0, 1, '2026-04-04 20:34:53', '2026-04-04 20:34:53'),
(2, 'VEHICULAR', 'Consulta vehicular', 'https://www.gob.pe/358-consultar-los-datos-de-un-vehiculo-consulta-vehicular', 'consulta caracteristicas de vehiculos SUNARP', 0, 1, '2026-04-04 20:35:43', '2026-04-04 20:35:43'),
(3, 'TRANSITO', 'Record de conductor', 'https://recordconductor.mtc.gob.pe/', 'consulta record de conductor de una persona', 0, 1, '2026-04-04 20:36:57', '2026-04-04 20:36:57'),
(4, 'VEHICULAR', 'Consulta SOAT', 'https://www.apeseg.org.pe/consultas-soat/', 'Consultar vigencia de certificado SOAT', 0, 1, '2026-04-04 20:37:48', '2026-04-04 20:41:09'),
(5, 'VEHICULAR', 'Consulta AFOCAT', 'https://servicios.sbs.gob.pe/reportesoat/', 'consultas CAT de vehiculos', 0, 1, '2026-04-04 20:38:48', '2026-04-04 20:41:22'),
(6, 'VEHICULAR', 'Consulta Inspección Técnico Vehicular', 'https://rec.mtc.gob.pe/Citv/ArConsultaCitv', 'consulta CITV MTC', 0, 1, '2026-04-04 20:40:53', '2026-04-04 20:40:53'),
(7, 'PNP', 'Sistema de Denuncias Policiales', 'https://denuncias.policia.gob.pe/sidpol/Login.aspx?ReturnUrl=%2Fsidpol%2F', 'consulta de denuncias PNP', 0, 1, '2026-04-04 20:43:08', '2026-04-04 20:43:08'),
(8, 'PNP', 'Correo PNP', 'https://correo.policia.gob.pe/owa/auth/logon.aspx?replaceCurrent=1&url=https%3a%2f%2fcorreo.policia.gob.pe%2fOWA%2f', NULL, 0, 1, '2026-04-04 20:43:45', '2026-04-04 20:43:45'),
(9, 'PNP', 'Esinpol PNP', 'https://sinpol.policia.gob.pe/esinpol/', NULL, 0, 1, '2026-04-04 20:44:21', '2026-04-04 20:44:21'),
(10, 'PNP', 'consulta SICPIC', 'https://sicpip.policia.gob.pe/login', NULL, 0, 1, '2026-04-04 20:48:14', '2026-04-04 20:48:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `familiar_fallecido`
--

CREATE TABLE `familiar_fallecido` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `fallecido_inv_id` int NOT NULL,
  `familiar_persona_id` int NOT NULL,
  `parentesco` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_general_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `familiar_fallecido`
--

INSERT INTO `familiar_fallecido` (`id`, `accidente_id`, `fallecido_inv_id`, `familiar_persona_id`, `parentesco`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(1, 18, 18, 26, 'Tia', '', '2025-10-11 06:22:57', NULL),
(2, 19, 23, 28, 'Hijo', '', '2025-10-12 08:51:30', NULL),
(3, 20, 26, 32, 'Hija', '', '2025-10-15 17:49:53', '2025-10-15 20:38:04'),
(4, 22, 29, 35, 'Esposa', '', '2025-10-18 15:15:40', NULL),
(5, 26, 37, 47, 'Hijo', '', '2025-10-27 01:29:02', NULL),
(6, 27, 39, 53, 'Conviviente', '', '2025-10-27 04:19:54', NULL),
(7, 29, 42, 57, 'Esposa', '', '2025-11-01 03:18:52', NULL),
(8, 24, 32, 61, 'Hija', '', '2025-11-15 23:37:29', NULL),
(9, 25, 34, 64, 'Hijo', '', '2025-11-21 05:26:12', NULL),
(10, 30, 45, 70, 'Madre', '', '2025-11-25 05:56:31', NULL),
(11, 32, 54, 81, 'Hermano político', '', '2025-12-02 05:34:26', NULL),
(12, 33, 56, 87, 'Hermana', '', '2025-12-10 04:11:32', NULL),
(13, 34, 59, 91, 'Hija', '', '2025-12-15 08:26:31', NULL),
(14, 35, 60, 97, 'Sobrino', '', '2026-01-12 02:06:09', NULL),
(15, 36, 66, 104, 'Papá', '', '2026-02-05 04:32:58', NULL),
(16, 36, 65, 105, 'Mamá', '', '2026-02-05 04:39:48', NULL),
(17, 36, 63, 106, 'Hermana política', '', '2026-02-05 04:48:02', NULL),
(18, 38, 71, 127, 'hijo', '', '2026-03-08 21:00:34', NULL),
(19, 39, 72, 131, 'Hermana', '', '2026-03-12 16:23:18', NULL),
(20, 40, 74, 135, 'Hija', '', '2026-03-17 01:18:56', NULL),
(21, 41, 75, 140, NULL, NULL, '2026-04-09 20:42:23', NULL),
(22, 23, 31, 143, 'Hermana', NULL, '2026-04-13 08:55:48', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fiscales`
--

CREATE TABLE `fiscales` (
  `id` int NOT NULL,
  `fiscalia_id` int NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` char(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `fiscales`
--

INSERT INTO `fiscales` (`id`, `fiscalia_id`, `nombres`, `apellido_paterno`, `apellido_materno`, `dni`, `telefono`, `correo`, `cargo`, `notas`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'Jonatan', 'GONZALES', 'WONG', NULL, '932308517', NULL, 'Fiscal Adjunto', NULL, '2025-09-18 06:55:12', '2025-09-18 06:55:12'),
(2, 2, 'Lesly Scarlet', 'CAMARGO', 'DIAZ', NULL, '942640000', NULL, 'Fiscal Adjunta', NULL, '2025-09-18 06:57:09', '2025-09-18 06:57:09'),
(3, 3, 'Armando M.', 'DIAZ', 'ECHEGARAY', NULL, '986610038', NULL, 'Fiscal Adjunto', NULL, '2025-09-29 05:50:58', '2025-09-29 05:50:58'),
(4, 4, 'Roberto Cesar', 'RUIZ', 'TAVARES', NULL, '995343039', NULL, 'Fiscal Provincial', NULL, '2025-10-12 08:07:50', '2025-10-12 08:07:50'),
(5, 1, 'Flavio', 'SEGOVIA', 'QUISPE', NULL, NULL, NULL, 'Fisca Adjunto', NULL, '2025-10-16 06:17:06', '2025-10-16 06:17:06'),
(6, 6, 'Herberth', 'FARRO', 'AQUINO', NULL, NULL, NULL, 'Fiscal Provincial', NULL, '2025-10-16 15:35:02', '2025-10-16 15:35:02'),
(7, 2, 'Herbert E.', 'FARRO', 'AQUINO', NULL, '945409399', NULL, 'Fiscal Provincial', NULL, '2025-10-16 16:03:38', '2025-10-16 16:03:38'),
(8, 7, 'Rosalia', 'TORRES', 'CALENI', NULL, NULL, NULL, 'Fiscal Adjunta', NULL, '2025-10-18 15:07:41', '2025-10-18 15:07:41'),
(9, 8, 'Estefani Beatriz', 'GUSMAN', 'GARCIA', NULL, '968301302', NULL, 'Fiscal Adjunta', NULL, '2025-10-20 00:03:12', '2025-10-20 00:03:12'),
(10, 9, 'Jorge Luís', 'RODRIGUEZ', 'LOARTE', NULL, NULL, NULL, 'Fiscal Adjunto', NULL, '2025-10-20 05:59:47', '2025-10-20 05:59:47'),
(11, 10, 'María Esperanza', 'POLO', 'ZAPATA', NULL, NULL, NULL, 'Fiscal Adjunta', NULL, '2025-10-20 14:35:11', '2025-10-20 14:35:11'),
(12, 11, 'José Manuel', 'SANDOVAL', 'PALOMINO', NULL, NULL, NULL, 'Fiscal Adjunto', NULL, '2025-10-27 04:01:10', '2025-10-27 04:01:10'),
(13, 12, 'Roberto Cesar', 'RUIZ', 'TAVARES', NULL, '995343039', NULL, 'Fiscal Provincial', NULL, '2025-10-30 20:51:43', '2025-10-30 20:51:43'),
(14, 10, 'Edwin G.', 'TOLENTINO', 'GABANCHO', NULL, '948677244', NULL, 'Fiscal Adjunto', NULL, '2025-11-01 00:27:56', '2025-11-01 00:27:56'),
(15, 9, 'José Cornelio', 'CASTILLA', 'CISNEROS', NULL, '987811183', NULL, 'Fiscal Provincial', NULL, '2025-11-24 21:11:34', '2025-11-24 21:11:34'),
(16, 13, 'Francy', 'CHUQUIHUACCHA', 'MORALES', NULL, '944622501', NULL, 'Fiscal Adjunto', NULL, '2025-12-02 03:52:43', '2025-12-02 03:52:43'),
(17, 14, 'Eva María', 'SOSA', 'CUSTODIO', NULL, '995613282', NULL, 'Fiscal Adjunta', NULL, '2026-01-11 15:52:34', '2026-01-11 15:52:34'),
(18, 1, 'Mario Alvino', 'DULANTO', 'TRUJILLO', NULL, NULL, NULL, 'Fiscal Provincial', NULL, '2026-03-08 16:25:04', '2026-03-08 16:25:04'),
(19, 9, 'Jerzy Mirko', 'ALBA', 'HUERTA', NULL, NULL, NULL, 'Fiscal Adjunto', NULL, '2026-03-12 06:23:35', '2026-03-12 06:23:35');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fiscalia`
--

CREATE TABLE `fiscalia` (
  `id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `fiscalia`
--

INSERT INTO `fiscalia` (`id`, `nombre`, `direccion`, `telefono`, `correo`, `notas`, `creado_en`, `actualizado_en`) VALUES
(1, 'Fiscalia Corporativa de Tránsito y Seguridad Vial-Primer Despacho-Lima Norte', NULL, NULL, NULL, NULL, '2025-09-18 06:17:58', '2025-09-18 06:17:58'),
(2, 'Fiscalia Corporativa de Tránsito y Seguridad Vial-Segundo Despacho-Lima Norte', NULL, NULL, NULL, NULL, '2025-09-18 06:56:18', '2025-09-18 06:56:18'),
(3, 'Tercera Fiscalia Provincial Penal Corporativa de Santa Rosa-Primer Despacho-Distrito Fiscal de Lima Noroeste', NULL, NULL, NULL, NULL, '2025-09-29 05:50:09', '2025-09-29 05:50:09'),
(4, 'Fiscalia Corporativa de Tránsito y Seguridad Vial Lima Norte-Tercer Despacho', NULL, NULL, NULL, NULL, '2025-10-12 08:06:53', '2025-10-12 08:06:53'),
(5, 'Fiscalía Corporativa de Tránsito y Seguridad Vial Lima Norte- Primer Despacho', NULL, NULL, NULL, NULL, '2025-10-14 23:03:40', '2025-10-14 23:03:40'),
(6, 'Fiscalía Corporativa de Tránsito y Seguridad Vial -Segun Despacho-Lima Norte', NULL, NULL, NULL, NULL, '2025-10-16 15:34:35', '2025-10-16 15:34:35'),
(7, 'Primera Fiscalia Provincial Penal Corporativa de Santa Rosa- Tercer Despacho-Distrito Fiscal de Lima Noroeste', NULL, NULL, NULL, NULL, '2025-10-18 15:07:17', '2025-10-18 15:07:17'),
(8, 'Segunda Fiscalia Provincial Penal Corporativa de Puente Piedra-Segundo Despacho-Distrito Fiscal de Lima Noroeste', NULL, NULL, NULL, NULL, '2025-10-19 23:59:55', '2025-10-19 23:59:55'),
(9, 'Fiscalia Corporativa de Tránsito y Seguridad Vial-Cuarto Despacho-Lima Norte', NULL, NULL, NULL, NULL, '2025-10-20 05:59:17', '2025-10-20 05:59:17'),
(10, 'Primera Fiscalía Penal Corporativa de Santa Rosa-Segundo Despacho-Distrito Fiscal de Lima Noroeste', NULL, NULL, NULL, NULL, '2025-10-20 14:34:44', '2025-10-20 14:34:44'),
(11, 'Segunda Fiscalía Provincial Penal Corporativa de Santa Rosa-Segundo Despacho-Distrito Fiscal de Lima Noroeste', NULL, NULL, NULL, NULL, '2025-10-27 04:00:25', '2025-10-27 04:00:25'),
(12, 'Fiscalía Corporativa de Tránsito y Seguridad Vial-Tercer Despacho-Lima norte', NULL, NULL, NULL, NULL, '2025-10-30 20:49:41', '2025-10-30 20:49:41'),
(13, 'Primera Fiscalía Provincial Penal Corporativo de Puente Piedra-Tercer Despacho- D.F. Lima Noroeste', NULL, NULL, NULL, NULL, '2025-12-02 03:45:42', '2025-12-02 03:45:42'),
(14, 'Tercera Fiscalía Provincial Penal Corporativo de Puente Piedra-Primer Despacho-D.F. Lima Noroeste', NULL, NULL, NULL, NULL, '2026-01-11 15:49:08', '2026-01-11 15:49:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `grado_cargo`
--

CREATE TABLE `grado_cargo` (
  `id` int NOT NULL,
  `tipo` enum('GRADO','CARGO') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'CARGO',
  `nombre` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `abreviatura` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `orden` int DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `grado_cargo`
--

INSERT INTO `grado_cargo` (`id`, `tipo`, `nombre`, `abreviatura`, `orden`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 'GRADO', 'Coronel PNP', 'Crnel. PNP', 0, 1, '2025-10-10 04:03:47', '2025-10-10 04:03:47'),
(2, 'GRADO', 'General PNP', 'Gral. PNP', 0, 1, '2025-10-10 04:04:00', '2025-10-10 04:04:00'),
(4, 'CARGO', 'Subgerente', NULL, 0, 1, '2025-10-10 04:58:22', '2025-10-10 04:58:22'),
(5, 'GRADO', 'Superior PNP', 'SS. PNP', 0, 1, '2025-10-10 06:04:31', '2025-10-10 06:04:31'),
(6, 'CARGO', 'Administrador', NULL, 0, 1, '2025-10-22 14:35:25', '2025-10-22 14:35:25'),
(7, 'GRADO', 'Fiscal Adjunto', NULL, 0, 1, '2025-11-29 07:11:54', '2025-11-29 07:11:54'),
(8, 'GRADO', 'Comandante PNP', 'Cmdte.PNP', 0, 1, '2025-12-10 15:00:48', '2025-12-10 15:00:48'),
(9, 'GRADO', 'Mayor PNP', 'May. PNP', 0, 1, '2025-12-10 15:04:09', '2025-12-10 15:04:09'),
(10, 'CARGO', 'Capitán de Navío', 'Cap.', 0, 1, '2026-02-19 16:20:22', '2026-02-19 16:20:22'),
(11, 'CARGO', 'Gerente', NULL, 0, 1, '2026-03-08 17:15:28', '2026-03-08 17:15:28'),
(12, 'CARGO', 'Director', NULL, 0, 1, '2026-03-17 05:15:32', '2026-03-17 05:15:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `involucrados_personas`
--

CREATE TABLE `involucrados_personas` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `persona_id` int NOT NULL,
  `rol_id` tinyint UNSIGNED NOT NULL,
  `orden_persona` char(1) COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT 'A,B,C… para ordenar y rotular personas por vehículo/rol',
  `vehiculo_id` int DEFAULT NULL,
  `lesion` enum('Ileso','Herido','Fallecido') COLLATE utf8mb4_general_ci DEFAULT 'Ileso',
  `observaciones` text COLLATE utf8mb4_general_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `involucrados_personas`
--

INSERT INTO `involucrados_personas` (`id`, `accidente_id`, `persona_id`, `rol_id`, `orden_persona`, `vehiculo_id`, `lesion`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(17, 18, 15, 1, NULL, 8, 'Ileso', NULL, '2025-09-29 05:56:33', '2025-09-29 05:56:33'),
(18, 18, 16, 4, NULL, 7, 'Fallecido', NULL, '2025-09-29 05:57:42', '2025-09-29 05:57:42'),
(19, 18, 17, 1, NULL, 7, 'Ileso', NULL, '2025-09-29 06:03:59', '2025-09-29 06:03:59'),
(22, 19, 24, 1, NULL, 10, 'Ileso', NULL, '2025-10-12 08:16:05', '2025-10-12 08:16:05'),
(23, 19, 25, 2, 'A', NULL, 'Fallecido', 'Fallece 03 de setiembre de 2023', '2025-10-12 08:31:49', '2025-10-12 08:31:49'),
(24, 19, 22, 2, 'B', NULL, 'Herido', NULL, '2025-10-12 08:34:25', '2025-10-12 08:34:41'),
(25, 20, 29, 1, NULL, 11, 'Herido', 'Conductor de la motocicleta', '2025-10-15 17:14:51', '2025-10-15 17:17:31'),
(26, 20, 30, 2, NULL, NULL, 'Fallecido', NULL, '2025-10-15 17:15:58', '2025-10-15 17:15:58'),
(27, 20, 31, 4, NULL, 11, 'Ileso', NULL, '2025-10-15 17:16:53', '2025-10-15 17:16:53'),
(28, 21, 33, 1, NULL, 12, 'Fallecido', 'conductor conducia motocicleta y se despisto solo, al parecer en estado de ebriedad', '2025-10-17 15:57:02', '2025-10-17 15:57:02'),
(29, 22, 34, 2, NULL, NULL, 'Fallecido', NULL, '2025-10-18 15:10:15', '2025-10-18 15:10:15'),
(30, 23, 37, 1, NULL, 14, 'Ileso', NULL, '2025-10-20 04:44:16', '2025-10-20 04:49:47'),
(31, 23, 38, 1, NULL, 16, 'Fallecido', NULL, '2025-10-20 04:46:34', '2025-10-20 04:46:34'),
(32, 24, 39, 2, NULL, NULL, 'Fallecido', NULL, '2025-10-20 06:09:23', '2025-10-20 06:09:23'),
(33, 24, 40, 1, NULL, 17, 'Ileso', NULL, '2025-10-20 06:12:53', '2025-10-20 06:12:53'),
(34, 25, 41, 2, NULL, NULL, 'Fallecido', NULL, '2025-10-20 14:59:22', '2025-10-20 14:59:22'),
(35, 25, 42, 1, NULL, 18, 'Ileso', NULL, '2025-10-20 15:21:11', '2025-10-20 15:21:11'),
(36, 26, 44, 1, NULL, 20, 'Ileso', NULL, '2025-10-26 17:43:25', '2025-10-26 17:43:25'),
(37, 26, 45, 1, NULL, 19, 'Fallecido', NULL, '2025-10-26 17:44:45', '2025-10-26 17:44:45'),
(38, 27, 49, 1, NULL, 21, 'Ileso', NULL, '2025-10-27 04:08:11', '2025-10-27 04:08:11'),
(39, 27, 50, 2, NULL, NULL, 'Fallecido', NULL, '2025-10-27 04:12:21', '2025-10-27 04:12:21'),
(40, 27, 52, 4, NULL, 21, 'Ileso', NULL, '2025-10-27 04:17:43', '2025-10-27 04:17:43'),
(41, 29, 54, 1, NULL, 23, 'Ileso', NULL, '2025-11-01 02:42:32', '2025-11-01 02:42:32'),
(42, 29, 55, 1, NULL, 24, 'Fallecido', NULL, '2025-11-01 02:56:31', '2025-11-01 02:56:31'),
(43, 29, 56, 4, NULL, 23, 'Ileso', NULL, '2025-11-01 03:02:16', '2025-11-01 03:02:16'),
(44, 30, 65, 1, NULL, 26, 'Ileso', 'Conductora de trimoto de pasajeros', '2025-11-25 03:46:16', '2025-11-25 03:46:16'),
(45, 30, 67, 1, NULL, 27, 'Fallecido', 'Conductor fallecido, en hospital Sergio Bernales, despues de una semana', '2025-11-25 03:56:34', '2025-11-25 03:56:34'),
(46, 30, 68, 4, NULL, 27, 'Herido', NULL, '2025-11-25 04:02:26', '2025-11-25 04:02:40'),
(47, 31, 73, 1, NULL, 30, 'Ileso', NULL, '2025-11-29 06:57:53', '2025-11-29 06:58:52'),
(48, 31, 74, 1, NULL, 29, 'Fallecido', NULL, '2025-11-29 07:00:33', '2025-11-29 07:00:33'),
(49, 31, 75, 1, NULL, 31, 'Ileso', NULL, '2025-11-29 07:01:22', '2025-11-29 07:01:22'),
(50, 31, 76, 1, NULL, 32, 'Ileso', NULL, '2025-11-29 07:02:30', '2025-11-29 07:02:30'),
(51, 31, 77, 1, NULL, 33, 'Ileso', NULL, '2025-11-29 07:03:27', '2025-11-29 07:03:27'),
(52, 31, 78, 4, NULL, 29, 'Herido', NULL, '2025-11-29 07:05:14', '2025-11-29 07:05:22'),
(53, 32, 79, 1, NULL, 34, 'Ileso', NULL, '2025-12-02 04:08:30', '2025-12-02 04:08:30'),
(54, 32, 80, 2, NULL, NULL, 'Fallecido', NULL, '2025-12-02 04:25:39', '2025-12-02 04:25:39'),
(55, 33, 83, 1, NULL, 35, 'Ileso', NULL, '2025-12-10 02:02:30', '2025-12-10 02:02:30'),
(56, 33, 84, 3, 'A', 35, 'Fallecido', NULL, '2025-12-10 02:06:11', '2025-12-10 02:06:11'),
(57, 33, 85, 3, 'B', 35, 'Ileso', NULL, '2025-12-10 02:47:57', '2025-12-10 02:47:57'),
(58, 33, 86, 3, 'C', 35, 'Ileso', NULL, '2025-12-10 02:49:24', '2025-12-10 02:49:24'),
(59, 34, 90, 2, NULL, NULL, 'Fallecido', NULL, '2025-12-11 03:47:15', '2025-12-11 03:47:15'),
(60, 35, 93, 2, NULL, NULL, 'Fallecido', NULL, '2026-01-11 20:46:39', '2026-01-11 20:46:39'),
(61, 35, 94, 1, NULL, 36, 'Ileso', NULL, '2026-01-11 21:05:08', '2026-01-11 21:05:08'),
(62, 36, 98, 1, NULL, 37, 'Ileso', NULL, '2026-02-03 14:57:12', '2026-02-03 14:57:12'),
(63, 36, 99, 4, 'D', 37, 'Fallecido', NULL, '2026-02-03 14:57:44', '2026-02-03 15:05:17'),
(64, 36, 100, 4, 'C', 37, 'Fallecido', NULL, '2026-02-03 14:58:13', '2026-02-03 15:04:11'),
(65, 36, 101, 4, 'B', 37, 'Fallecido', NULL, '2026-02-03 15:01:36', '2026-02-03 15:03:51'),
(66, 36, 102, 4, 'A', 37, 'Fallecido', NULL, '2026-02-03 15:03:01', '2026-02-03 15:03:01'),
(67, 36, 103, 4, 'E', 37, 'Herido', NULL, '2026-02-03 15:12:53', '2026-02-05 04:47:21'),
(68, 37, 108, 1, NULL, 38, 'Ileso', NULL, '2026-02-05 07:25:14', '2026-02-05 07:25:14'),
(69, 37, 109, 2, NULL, NULL, 'Fallecido', NULL, '2026-02-05 07:29:20', '2026-02-05 07:29:20'),
(70, 38, 122, 1, NULL, 57, 'Ileso', NULL, '2026-03-08 16:38:05', '2026-03-08 16:38:05'),
(71, 38, 124, 2, NULL, NULL, 'Fallecido', NULL, '2026-03-08 16:40:18', '2026-03-08 16:40:18'),
(72, 39, 128, 1, NULL, 58, 'Fallecido', NULL, '2026-03-12 06:36:45', '2026-03-12 06:36:45'),
(73, 40, 132, 1, NULL, 59, 'Ileso', NULL, '2026-03-13 07:36:11', '2026-03-13 07:36:11'),
(74, 40, 133, 2, NULL, NULL, 'Fallecido', NULL, '2026-03-13 07:39:31', '2026-03-13 07:39:31'),
(75, 41, 139, 2, NULL, NULL, 'Fallecido', NULL, '2026-04-09 20:35:57', '2026-04-09 20:35:57');

--
-- Disparadores `involucrados_personas`
--
DELIMITER $$
CREATE TRIGGER `bi_involucrados_personas` BEFORE INSERT ON `involucrados_personas` FOR EACH ROW BEGIN
  DECLARE req TINYINT;
  SELECT RequiereVehiculo INTO req FROM participacion_persona WHERE Id = NEW.rol_id;
  IF req = 1 AND NEW.vehiculo_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este rol requiere vehiculo_id';
  END IF;
  IF req = 0 AND NEW.vehiculo_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este rol no debe tener vehiculo_id';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `bu_involucrados_personas` BEFORE UPDATE ON `involucrados_personas` FOR EACH ROW BEGIN
  DECLARE req TINYINT;
  SELECT RequiereVehiculo INTO req FROM participacion_persona WHERE Id = NEW.rol_id;
  IF req = 1 AND NEW.vehiculo_id IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este rol requiere vehiculo_id';
  END IF;
  IF req = 0 AND NEW.vehiculo_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este rol no debe tener vehiculo_id';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `involucrados_vehiculos`
--

CREATE TABLE `involucrados_vehiculos` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `vehiculo_id` int NOT NULL,
  `orden_participacion` enum('UT-1','UT-2','UT-3','UT-4','UT-5','UT-6','UT-7') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'UT-1',
  `tipo` varchar(50) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Unidad',
  `observaciones` text COLLATE utf8mb4_general_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `involucrados_vehiculos`
--

INSERT INTO `involucrados_vehiculos` (`id`, `accidente_id`, `vehiculo_id`, `orden_participacion`, `tipo`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(13, 18, 7, 'UT-1', 'Unidad', 'Vehículo Ocasionante del accidente', '2025-09-29 05:54:27', '2025-10-02 06:02:59'),
(14, 18, 8, 'UT-2', 'Unidad', 'Vehículo Recibio Impacto', '2025-09-29 05:55:23', '2025-10-02 06:02:59'),
(15, 19, 10, 'UT-1', 'Unidad', '', '2025-10-12 08:14:58', '2025-10-12 08:14:58'),
(16, 20, 11, 'UT-1', 'Unidad', '', '2025-10-14 23:43:45', '2025-10-14 23:43:45'),
(17, 21, 12, 'UT-1', 'Unidad', '', '2025-10-16 23:06:15', '2025-10-16 23:06:15'),
(21, 23, 16, 'UT-3', 'Unidad', '', '2025-10-20 04:26:28', '2025-10-20 04:26:28'),
(22, 24, 17, 'UT-1', 'Unidad', '', '2025-10-20 06:04:26', '2025-10-20 06:04:26'),
(23, 25, 18, 'UT-1', 'Unidad', '', '2025-10-20 14:54:52', '2025-10-20 14:54:52'),
(24, 26, 19, 'UT-1', 'Unidad', '', '2025-10-26 17:37:12', '2025-10-26 17:37:12'),
(25, 26, 20, 'UT-2', 'Unidad', '', '2025-10-26 17:40:59', '2025-10-26 17:40:59'),
(26, 27, 21, 'UT-1', 'Unidad', '', '2025-10-27 04:04:49', '2025-10-27 04:04:49'),
(27, 29, 23, 'UT-1', 'Unidad', '', '2025-11-01 02:22:10', '2025-11-01 02:22:10'),
(28, 29, 24, 'UT-2', 'Unidad', '', '2025-11-01 02:33:55', '2025-11-01 02:33:55'),
(29, 30, 26, 'UT-1', 'Unidad', '', '2025-11-24 21:44:54', '2025-11-24 21:44:54'),
(30, 30, 27, 'UT-2', 'Unidad', '', '2025-11-24 21:46:55', '2025-11-24 21:46:55'),
(31, 31, 29, 'UT-1', 'Unidad', '', '2025-11-29 06:43:39', '2025-11-29 06:43:39'),
(32, 31, 30, 'UT-2', 'Unidad', '', '2025-11-29 06:45:58', '2025-11-29 06:45:58'),
(33, 31, 31, 'UT-3', 'Unidad', '', '2025-11-29 06:49:42', '2025-11-29 06:49:42'),
(34, 31, 32, 'UT-4', 'Unidad', '', '2025-11-29 06:51:52', '2025-11-29 06:51:52'),
(35, 31, 33, 'UT-5', 'Unidad', '', '2025-11-29 06:56:36', '2025-11-29 06:56:36'),
(36, 32, 34, 'UT-1', 'Unidad', '', '2025-12-02 04:06:40', '2025-12-02 04:06:40'),
(37, 33, 35, 'UT-1', 'Unidad', '', '2025-12-10 01:50:00', '2025-12-10 01:50:00'),
(38, 35, 36, 'UT-1', 'Unidad', '', '2026-01-11 21:03:03', '2026-01-11 21:03:03'),
(39, 36, 37, 'UT-1', 'Unidad', '', '2026-02-01 04:32:57', '2026-02-01 04:32:57'),
(40, 37, 38, 'UT-1', 'Combinado vehicular 1', '', '2026-02-05 06:37:58', '2026-02-05 06:37:58'),
(41, 37, 39, 'UT-2', 'Combinado vehicular 2', '', '2026-02-05 06:45:12', '2026-02-05 06:45:12'),
(42, 38, 57, 'UT-1', 'Unidad', '', '2026-03-08 16:33:38', '2026-03-08 16:33:38'),
(43, 39, 58, 'UT-1', 'Unidad', '', '2026-03-12 06:36:20', '2026-03-12 06:36:20'),
(44, 40, 59, 'UT-1', 'Unidad', '', '2026-03-13 06:51:21', '2026-03-13 06:51:21'),
(45, 23, 14, 'UT-1', 'Combinado vehicular 1', NULL, '2026-04-13 07:13:57', '2026-04-13 07:13:57'),
(46, 23, 15, 'UT-1', 'Combinado vehicular 2', NULL, '2026-04-13 07:13:57', '2026-04-13 07:13:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `itp`
--

CREATE TABLE `itp` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `fecha_itp` date DEFAULT NULL,
  `hora_itp` time DEFAULT NULL,
  `ocurrencia_policial` text COLLATE utf8mb4_general_ci,
  `llegada_lugar` text COLLATE utf8mb4_general_ci,
  `localizacion_unidades` text COLLATE utf8mb4_general_ci,
  `forma_via` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `punto_referencia` text COLLATE utf8mb4_general_ci,
  `ubicacion_gps` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `descripcion_via1` text COLLATE utf8mb4_general_ci,
  `configuracion_via1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `material_via1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `señalizacion_via1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ordenamiento_via1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `iluminacion_via1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visibilidad_via1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `intensidad_via1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fluidez_via1` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `medidas_via1` text COLLATE utf8mb4_general_ci,
  `observaciones_via1` text COLLATE utf8mb4_general_ci,
  `descripcion_via2` text COLLATE utf8mb4_general_ci,
  `configuracion_via2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `material_via2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `señalizacion_via2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ordenamiento_via2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `iluminacion_via2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `visibilidad_via2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `intensidad_via2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `fluidez_via2` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `medidas_via2` text COLLATE utf8mb4_general_ci,
  `observaciones_via2` text COLLATE utf8mb4_general_ci,
  `evidencia_biologica` text COLLATE utf8mb4_general_ci,
  `evidencia_fisica` text COLLATE utf8mb4_general_ci,
  `evidencia_material` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `itp`
--

INSERT INTO `itp` (`id`, `accidente_id`, `fecha_itp`, `hora_itp`, `ocurrencia_policial`, `llegada_lugar`, `localizacion_unidades`, `forma_via`, `punto_referencia`, `ubicacion_gps`, `descripcion_via1`, `configuracion_via1`, `material_via1`, `señalizacion_via1`, `ordenamiento_via1`, `iluminacion_via1`, `visibilidad_via1`, `intensidad_via1`, `fluidez_via1`, `medidas_via1`, `observaciones_via1`, `descripcion_via2`, `configuracion_via2`, `material_via2`, `señalizacion_via2`, `ordenamiento_via2`, `iluminacion_via2`, `visibilidad_via2`, `intensidad_via2`, `fluidez_via2`, `medidas_via2`, `observaciones_via2`, `evidencia_biologica`, `evidencia_fisica`, `evidencia_material`) VALUES
(1, 29, '2025-05-26', '20:50:00', NULL, NULL, '- Conductor motocicleta : Se ubico en la zona de tierra del extremo oeste  el cuerpo tendido decúbito dorsal  perteneciente al conductor de la motocicleta de placa de rodaje  ubicando su punto medio del P.R. hacia el este a 30.50 m. y de allí en ángulo recto al norte a 24.10 m., - Automovil : Se ubico en la zona de tierra del extremo este  el automovil sin placa ubicando su vertice posterior izquierda del P.R.  hacia el este a 23.54 m. y de allí al norte a 27.75 m.  y su vertice anterior derecho se ubica del P.R.  al este a 27.89 m. y de allí hacia el norte a 32.25 m., - Motocicleta : no fue ubicada', 'Recta', 'Poste de tendido eléctrico Nro. 2020 13 300 2 165-360, ubicado en la amplia zona de tierra del extremo oeste', NULL, 'Es una vía conformada por una calzada principal con capacidad para dos carriles de circulación vehicular, limitando hacia el extremo este con amplia zona de tierra y roca; hacia el extremo este limita con berma y amplia zona de tierra y roca, seguido de límite de propiedad.', 'Plano con ligera inclinación hacia el lado este, curva abierta', 'De asfalto seco en regular estado de conservación', 'lineas discontinuas separadores de carril, lineas longitudinales de limite de calzada.', 'De sur a norte', 'Nula', 'mala', 'discontinua', 'moderada a rápida', '- Calzada principal : 07.45 m, - Berma este : 02.45 m', 'En el extremo del evento existen accesos tipo camino de tierra en los extremos este y oeste, Se constata en el tramo del evento  no existe postes de alumbrado publico próximos a la calzada principal, Se observa postes de alumbrado publico en los extremos este y oeste ubicados a unos 50 metros de distancia del lugar del evento.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 24, '2025-09-09', '03:30:00', 'Nro de Orden :  33261206 Clave : rZErXlxJ \r\nrZErXlxJ\r\n\r\n\r\nFecha y Hora Registro	09/09/2025 02:02:57 Hrs.	\r\n\r\nFormalidad	ESCRITA	Fecha y Hora Hecho	08/09/2025 23:05:00 Hrs.\r\nCondición de la Denuncia	[TRAN] ACTA DE INTERVENCION Nro : 225\r\nCódigo QR\r\n\r\nTIPIFICACION\r\nACCIDENTES DE TRANSITO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / CARABAYLLO / OTROS AV. TUPAC AMARU CON AV. SAN CARLOS\r\nDETENIDO\r\n1) LUIS AGUSTO CARRILLO NIEVES(40), CON FECHA DE NACIMIENTO 04/08/1985 , ESTADO CIVIL : CASADO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 43165106, DIRECCION : LIMA / LIMA / SAN MARTIN DE PORRES : MZ. G LT. 17 PROP. VIRGEN DE LAS MERCEDES\r\nFALLECIDO\r\n1) ANTONIA NIEVES ALDABA(57), CON FECHA DE NACIMIENTO 18/01/1968 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 22660353, DIRECCION : LIMA / LIMA / COMAS : MZ. G LT. 7 ASENT. H. SAN JUAN BAUTISTA\r\n\r\nVEHICULO(S)\r\n1) AUTOMOVIL - MARCA : NO INDICA - MODELO : NO INDICA - PLACA : BLA148 - COLOR : - AÑO FAB : - SITUACION : ACCIDENTE DE TRANSITO - SERIE : - MOTOR : - OBS :\r\nCONTENIDO\r\nACTA DE INTERVENCIÓN POLICIAL. --- EN LA CIUDAD DE LIMA DISTRITO DE COMAS, SIENDO LAS 23:45 HORAS DE LA FECHA 09 DE SEPTIEMBRE DEL 2025, EL SUSCRITO S3 PNP RONI WILLIAM QUILCA ANAHUI CON CIP:31619390 ENCONTRANDOME COMO OPERADOR DE LA UU.MM. KL- 27892, AL MANDO DEL S1 LOPEZ ANCO ROY CON CIP:31548953, BOMBERA LOZANA MUÑOZ SAMANTHA CON DNI NRO. 73945913 CODIGO DE BOM. A25593 Y EL PARAMEDICO RESCATISTA BALLARTA NUÑEZ VICTOR GUSTABO CON DNI NRO. 70481832 A BORDO DE LA UNIDA MOVIL DE SERENAZGO DE COMAS EUI-711, EL DETENIDO LUIS AGUSTO CARRILLO NIEVES (40), NATURAL DE PIURA, CASADO, CONDUCTOR, IDENTIFICADO CON DNI NRO. 43165106, DOMICILIADO EN MZ B LTE. 02 PASAJE LAS ORQUIDIAS DE NARANJAL-DISTRITO DE SAN MARTIN DE PORRES Y QUE EN VIDA FUE LA PERSONA DE SEXO FEMENINO KARINA ALVA NIEVES (57) S/D/P/V, POR LO QUE SE PROCEDE A REDACTAR EN LA OFICINA DE LA COMISARÍA DE SANTA ISABEL EL ACTA SEGÚN SE DETALLA. ----- SIENDO LAS 23:20 HORAS DE LA PRESENTE FECHA, EN CIRCUNSTANCIAS QUE SE REALIZABA EL PATRULLAJE MOTORIZADO POR INMEDIACIONES DE LA JURISDICCIÓN DE SANTA ISABEL, POR DISPOSICIÓN DEL COMANDANTE DE GUARDIA Y SOLICITUD DE UNA LLAMADA DE 105, CON LA FINALIDAD DE VERIFICAR UN ACCIDENTE DE TRÁNSITO INSITU SE HABIA SUSCITADO UN ACCIDENTE DE TRANSITO POR INMEDIACIONES DE AV. TUPAC AMARU CON AV. SAN CARLOS, PRESENTES EN EL LUGAR APRECIA A LA PERSONA KARINA ALVA NIEVES (57) QUE EN VIDA FUE) TENDIDA EN LE PISO SIENDO ATENDIDOS POR EL BOMBERO Y PARAMÉDICO LINEAS ARRIBA MENCIONADO LOS CUALES LO ESTABILIZARON EN LA CAMILLA DE EMERGENCIA SIENDO TRASLADO AL HOSPITAL NACIONAL SERGIO E. BERNALES-COLLIQUE. CABE MENCIONAR QUE SE ACERCO A LUGAR EL ALFZ PNP BEJAMIN PILCO CARRILLO QUIEN REFIERE QUE EL LA AGRAVIADA LLEGO AL HOSPITAL SIN SIGNOS VITALES DIGANOSTICO QUE FUE DADO POR EL DR. ENRIQUE VIDAL HERRERA LIMA CON CMP-20628 CABE MENCIONAR QUE EN EL LUGAR PARTICIPO EN EL ACCIDENTE DE TRÁNSITO LA PERSONA LUIS AGUSTO CARRILLO NIEVES (40) QUIEN ERA EL CONDUCTOR DEL VEHÍCULO DE PLACA BLA-148, CHEVROLET, MODELO SAIL. COLOR NEGRO EL CUAL PRESENTES DAÑOS CAPOT ABOLLADO Y PARA BRIZAS DELANTERO ROTO, PRESENTADO SU DOCUMENTACIÓN Y DEL VEHICULO UN (01) LICENCIA DE CONDUCIR A DOS B PROFESIONAL, UN (01) TARJETA DE PROPIEDAD, REFIRIENDO QUE TIENE UN (01) SOAT DIGITAL Y UN (01) REVISION TECNICA, QUIEN REFIERE QUE EN CIRCUNSTANCIAS QUE SE ENCONTRABA MANEJANDO DE NORTE A SUR VISUALIZO QUE UNA PERSONA INVADIÓ EL CARRIL, ESTO OCASIONAR QUE CHOQUE CON EL VEHICULO EN MENCIÓN A LA PERSONA QUE EN VIDA FUE FEMENINO KARINA ALVA NIEVES. --- CABE MENCIONAR QUE DICHO VEHÍCULO Y EL DETENIDO FUE TRASLADADO ESTA DEPENDENCIA POLICIAL PARA REALIZAR LAS DILIGENCIAS CORRESPONDIENTES, SE ADJUNTA LOS DOCUMENTOS LÍNEAS ARRIBA MENCIONADO, LLAVE DE CONTACTO, UN (01) ACTA DE REGISTRO VEHICULAR, UN (01) ACTA DE LECTURA DE DERECHO Y BUEN TRATO, UN ACTA DE DETENCIÓN, UN ACTA DE SITAUCION VEHICULAR. --- CABE MENCIONAR QUE LA PRESENTE ACTA SE CONCLUYO EN LA DEPENDENCIA POLICIAL POR FACTOR LOGISTICO, RESPETANDO SUS DERECHOS HUMANOS Y APLICANDO EL DL-1186. --- SIENDO LAS 01:50 HORAS DE LA FECHA 09 DE SETIEMBRE DEL 2025, SE DA POR CONCLUIDA LA PRESENTE ACTA FIRMANDO Y LEIDA POR LOS PARTICIPANTES EN SEÑAL DE CONFORIDAD.\r\n   \r\n\r\nAMPLIACION 1 	\r\nINSTRUCTOR : SO.3RA. PNP GUEVARA TAFUR ,ABRAHAM JOHEL FECHA AMPLIACION : 09/09/2025---02:30:47\r\n\r\nPROCEDENTE DEL AREA DE SERVICIO POLICIAL DEL HOSPITAL SERGIO BERNALES COLLIQUE, SE RECEPCIONO EL DOCUMENTO QUE A CONTINUACION SE DETALLA: ---- ACTA DE OCURRENCIA POLICIAL. --- EN LA LOCALIDAD DE COLLIQUE, DISTRITO DE COMAS. SIENDO LAS 01:30 HORAS DEL DIA 09SETIEMBRE2025, EL SUSCRITO ENCONTRÁNDOSE DE SERVICIO EN LA OFICINA PNP, DEL ÁREA DE EMERGENCIAS DEL HNSEBC, A ESTA OFICINA, SE HISO PRESENTE PERSONAL TÉCNICO DEL ÁREA DE CONSULTORIO DE ATENCIÓN RÁPIDA (CAR), DEL HNSEB-COLLIQUE, PARA COMUNICAR EL FALLECIMIENTO A HORAS 00:18, APROX DEL MISMO DIA, DE LA PERSONA DE NOMBRE: ANTONIA NIEVES ALDABA (57), CON DNI. NRO. 22660353, DOMICILIADA: SAN JUAN BAUTISTA MZ \"G\", LOTE:7, AA. HH: SAN JUAN BAUTISTA COMAS. SIENDO ATENDIDO POR EL DR. ENRIQUE VIDAL HERRERA LIMA CON CMP-020628, QUIEN DIAGNÓSTICO: LLEGO CADÁVER (SIN SIGNOS VITALES), ORDENANDO SU INTERNAMIENTO EN ESTE NOSOCOMIO PARA SU POSTERIOR TRASLADO A LA MORGUE CENTRAL DE LIMA. --- SE HACE MENCIÓN QUE DICHA FALLECIDO INGRESO COMO (NN), A ESTE NOSOCOMIO EL DIA 08SETIEMBRE2025 A HORAS 23:40 APROX, POR HABER SUFRIDO UN ACCIDENTE DE TRÁNSITO, EN LA JURISDICCIÓN DE LA CIA PNP DE SANTA ISABEL. EL SUSCRITO REALIZO UNA LLAMADA TELEFÓNICA AL NRO. 959064116, ASIGNADO A LA CIA PNP DE SANTA ISABEL, PARA CORROBORAR DICHA INFORMACIÓN, CONVERSANDO CON EL S3.PNP QUILCA ANAHUI RONY, Y CONFIRMANDO LA INTERVENCIÓN POLICIAL POR ACCIDENTE DE TRÁNSITO, COMO INSTRUCTOR, HECHO SUSCITADO EN LA AV. TÜPAC AMARU ENTRE LAS AV. SAN FELIPE / AV, SAN CARLOS DEL DÍA O8SETIEMBRE A HORAS 23:05 APROX. ---SE HACE DE CONOCIMIENTO QUE SIENDO LAS HORAS 01:50 APROX, DEL MISMO DA, EL SUSCRITO COMUNICO DEL HECHO A LA CIA DE SANTA ISABEL, PARA QUE REALICEN LAS DILIGENCIAS DE LEY POR SER SU JURISDICCIÓN POLICIAL. --- SIEND0 LAS HORAS 01:55 APROX, DEL MISMO DÍA, SE DA POR CONCLUIDA LA PRESENTE ACTA FIRMAND0 A CONTINUACIÓN EL INSTRUCTOR EN SEÑAL DE CONFORMIDAD. ----EL INSTRUCTOR NUÑEZ SEGURA RIDER, ST3 PNP, CIP. 31399613\r\nAMPLIACION 2	\r\nINSTRUCTOR : SO.3RA. PNP GUEVARA TAFUR ,ABRAHAM JOHEL FECHA AMPLIACION : 09/09/2025---09:25:29\r\n\r\nEN ESTE ACTO SE HACE DE CONOCIMIENTO QUE POR ERROR INVOLUNTARIO SE CONSIGNO ERRONEAMENTE EL NOMBRE DE LA PERSONA FALLECIDA EN EL CONTENIDO CUYO NOMBRE CORRESPONDE A ANTONIA NIEVES ALDABA (57) CON DNI. 22660353, LO QUE SE DA CUENTA PARA LOS FINES CORRESPONDIENTES.\r\n 	 \r\nINTERVINIENTE : SO.3RA. PNP RONI WILLIAM QUILCA ANAHUI\r\nAUTENTIFICADOR 1 : S3 PNP ABRAHAM JOHEL GUEVARA TAFUR', 'Constituido al lugar personal especializado de la UIAT NORTE, no encontró personal policial de la Comisaría del Sector a cargo de la protección y aislamiento del lugar de los hechos, esto debido a lo extemporáneo de la intervención y a que, a consecuencia del impacto, la peatón lesionada fue auxiliada y trasladada al hospital Sergio Bernales-Collique, mientras que el vehículo participante fue puesto a disposición de la Comisaría PNP Santa Isabel.', '- Automovil : No fue ubicada en su posicion final debido que posterior al accidente fue removido por personal interviniente y puesto a disposicion de la comisaria PNP Santa Isabel, - Peatón : No fue encontrada en el lugar del accidente  debido a que posterio al accidente fue auxiliada y trasladada al hospital Sergio Bernales-Collique  donde falleció posteriormente.', 'Recta', 'poste de alumbrado publico Nro. 1114-9-200-150 EDELNOR ubicado en separador lateral oeste', '-11.909651, -77.037296', 'Es una vía que consta de dos calzadas principales con capacidad para dos senderos de circulación cada una, las cuales se encuentran divididas por un separador central de tierra, delimitado por bordes de sardinel peraltado, en cuyo interior se observan áreas de jardín y secciones de tierra tipo loma, así como muro de contención de concreto a mayor nivel de la calzada oeste; asimismo, la calzada éste seguido del separador lateral este, seguido de calzada auxiliar, zona de estacionamiento, acera y límite de propiedad; mientras que hacia el borde externo de la calzada oeste, separador lateral tipo berma de tierra y talud de tierra contiguo a la calzada auxiliar oeste, acera y límite de propiedad.', 'Recta y plana', 'Asfalto, pavimentado en buen estado de uso y conservación', 'No se aprecian', 'De norte a sur', 'Natural-artificial postes de alumbrado público, tenue, baja intensidad', 'Buena y profundidad y amplitud', 'Discontinuo', 'Moderada a rápida', NULL, 'En el separador central se observa un corte de la sección de tierra que forma un espacio aplanado que forma una solución de continuidad por uso y costumbre que colinda con una escalera de concreto instalado en el separador lateral oeste que deriva hacia la calzada Auxiliar y Av. San Francisco y hacia el lado este de acceso hacia la calzada principal este., A 350 m. aprox. hacia el norte del lugar del evento  se aprecia interseccion semaforizada que corresponde a la Av. San Felipe, Se ha tomado como punto de referencia P.R. el poste de alumbrado publico Nro. 1114-9-200-150 EDELNOR  ubicado en separador lateral oeste.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Mancha de sangre, localizada en la sección de tierra del separador lateral oeste (berma) en una area de 00.50 x 00.20 m. tipo charco, de bordes irregulares, consistencia seca, ubicado el P.R. al sur a 53.07 m en linea recta.', NULL, NULL),
(4, 25, '2025-08-04', '10:00:00', '----- ACTA DE INTERVENCIÓN POLICIAL - NO ES COPIA CERTIFICADA ----\r\nFecha y Hora Registro	04/08/2025 10:48:05 Hrs.	\r\n\r\nFormalidad	ESCRITA	Fecha y Hora Hecho	04/08/2025 04:30:00 Hrs.\r\nCondición de la Denuncia	[TRAN] ACTA DE INTERVENCION Nro : 259\r\nCódigo QR\r\n\r\nTIPIFICACION\r\nACCIDENTES DE TRANSITO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / ANCON / OTROS CARRETERA PANAMERICANA NORTE KM 40, SENTIDO DE SUR A NORTE, REF. FRONTIS DEL ASENT. HUMANO COX\r\nDETENIDO EN FLAGRANCIA\r\n1) JOSUE ELIAS TORIBIO FELIX(25), CON FECHA DE NACIMIENTO 12/05/2000 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 71389155, DIRECCION : HUANUCO / HUACAYBAMBA / CANCHABAMBA : SAN JUAN DE HUARIPAMPA\r\nFALLECIDO\r\n1) WILDER YOVANY PISCOYA ESCRIBANO(29), CON FECHA DE NACIMIENTO 25/05/1996 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 73579438, DIRECCION : LIMA / LIMA / ANCON : KM.39 1/2 PANAMERICANA NORTE ASOC. DAMNIFICADOS NADINE HEREDIA, TELEFONO : 972447687\r\n\r\nVEHICULO(S)\r\n1) VEHICULO MENOR - MARCA : BAJAJ - MODELO : NO INDICA - PLACA : 3278QC - COLOR : BLANCO ROJO - AÑO FAB : 2024 - SITUACION : ACCIDENTE DE TRANSITO - SERIE : - MOTOR : - OBS : CATEGORIA L3, MODELO PULSAR 200 NS FI ABS\r\nCONTENIDO\r\nACTA DE INTERVENCIÓN POLICIAL.- EN LA CIUDAD DE ANCÓN, DISTRITO DE LA PROVINCIA Y DEPARTAMENTO DE LIMA, SIENDO LAS 06:30 HRS DEL DIA 04 DE AGOSTO DEL 2025, EL SUSCRITO ST3 PNP JORGE ESTEBAN ARAUJO ZEGARRA, IDENTIFICADO CON CIP NRO.31425070, TEL. 989197622, AL MANDO DEL S3 PNP ALTAMIRANO SILVA SEGUNDO, A BORDO DE LA UUMM TMP-6518 ASIGNADO A LA CPNP ANCON, EN CIRCUNSTANCIAS QUE NOS ENCONTRÁBAMOS DE SERVICIO DE TURNO NONA, SIENDO LAS 04:50 APROX., FUIMOS DESPLAZADOS POR EL COMANDANTE DE GUARDIA HACIA EL KM 40 DE LA PANAMERICANA NORTE DE SUR A NORTE A LA ALTURA DEL FRONTIS DEL AA.HH. COX, POR U POSIBLE ACCIDENTE DE TRÁNSITO (ATROPELLO Y FUGA A PERSONA).- SIENDO LAS 05:10 PERSONAL POLICIAL BAJO MI MANDO PRESENTES EN DICHO LUGAR, ANTES MENCIONADO VISUALIZAMOS A UNA PERSONA DE SEXO MASCULINO TENDIDO DE CUBITO SUPINO DE CONTEXTURA GRUESA, VISTE UN PANTALÓN DE TELA ROTO EN UNA DE SUS EXTREMIDADES, TAPADO MEDIO CUERPO CON UNA COLCHA, SANGRANDO POR LA BOCA, AL PARECER CON LESIONES GRAVES, ASIMISMO EN EL LUGAR SE ENCONTRABAN UN GRUPO DE VECINOS QUE AL CONSULTARLES RESPECTO AL ACCIDENTE NO BRINDARON INFORMACIÓN, EL LUGAR PRESENTA ESCASA ILUMINACIÓN Y NO SE APRECIÓ CÁMARAS DE VIDEO VIGILANCIA, ASÍ MISMO EN EL LUGAR NOS ENTREVISTAMOS CON LA SEÑORA BRENDA LIZETH MARCELO SANDOVAL (29) DNI 74090355 S/D/P/V, LA MISMA QUE REFIERE SER LA PAREJA DE LA PERSONA ACCIDENTADA Y QUE POR REFERENCIA DE LAS PERSONAS DEL LUGAR INDICA QUE SU PAREJA FUE VICTIMA DE ATROPELLO Y FUGA, POR PARTE DE VEHÍCULOS QUE TRANSITABAN POR DICHA VÍA, ASIMISMO INDICA QUE AL APARECER EL CONDUCTOR DE LA MOTO LINEAL DE PLACA 3278-QC SE ENCONTRARÍA COMO PARTICIPE DEL ACCIDENTE DE TRÁNSITO, A QUIEN SE LE ENCONTRÓ A LA FRONTIS APROX., DEL LUGAR DE LA INTERVENCIÓN JUNTO A SU MOTO LINEAL CON SENTIDO DE SUR A NORTE EN EL KM 40 DE LA PANAMERICANA NORTE, SIENDO IDENTIFICADO COMO TORIBIO FELIX JOSUE ELIAS (25) SOLTERO, HUÁNUCO, DOMICILIADO EN EL KM 118 PANAMERICANA NORTE ALTURA DE LA GRANJA RIO MAR HUACHO, IDENTIFICADO CON DNI N° 71389155, EL MISMO QUE REFIERE QUE SIENDO LAS 04:30 APROX., EN CIRCUNSTANCIAS QUE SE ENCONTRABA TRANSITANDO A BORDO DE SU MOTO LINEAL DE PLACA 3278-QC, MARCA PULSAR, BLANCO Y NEGRO, POR LE CARRIL DERECHO KM 40 DE LA PANAMERICANA NORTE A LA ALTURA DEL PARADERO DOS POSTES EN SENTIDO DE SUR A NORTE, VISUALIZO QUE UN VEHÍCULO (AUTOMÓVIL) COLOR NEGRO IMPACTO A UNA PERSONA DE SEXO MASCULINO CUANDO ESTE ESTABA CRUZANDO POR LA PISTA CON DIRECCIÓN DE OESTE A ESTE, ES DECIR DESDE EL PARADERO DOS POSTES CON DIRECCIÓN HACIA EL OTRO LADO DE LA PANAMERICANA NORTE COMO REFERENCIA AA.HH. MANUEL COX, LOGRANDO SACARLO DE LA VÍA PRODUCTO DEL IMPACTO, ASIMISMO REFIERE QUE SE ENCONTRABA A TRAS DEL VEHÍCULO EN MENCIÓN, ES EN ESE MOMENTO QUE SINTIÓ UN IMPACTO POR UN SEGUNDO VEHÍCULO QUE CIRCULABA ATRÁS DE EL EN EL MISMO SENTIDO, LOGRANDO IMPACTARLE EN LA LLANTA POSTERIOR DE SU MOTO DESEQUILIBRÁNDOLO Y DESPISTÁNDOLO HACIA EL LUGAR EN CONDE FUE INTERVENIDO POR EL PERSONAL POLICIAL, AMBOS VEHÍCULOS PARTICIPANTES DEL ACCIDENTE SE DIERON A LA FUGA.- AL RESPECTO CON RELACIÓN DE LA PERSONA ACCIDENTADA LOS FAMILIARES VOLUNTARIAMENTE LOS TRASLADARON EN EL VEHÍCULO DE PLACA COS-649 KIA BLANCO HACIA EL HOSPITAL DE PUENTE PIEDRA CONJUNTAMENTE CON EL PERSONAL DE CARRETERA A BORDO DE LA UU. MM. CF-26896 AL MANDO DEL ST1 DAVILA URRUELO HENRY, REFIRIENDO QUE EL DR. JEAN HONORET HUERTA JARA CMP 080270 CERTIFICA SU DECESO (LLEGO CADÁVER) DEL SEÑOR WILDER YOVANY PISCOYA ESCRIBANO (29) DNI 72579438 RNE 049418, SOLTERO, PRIMARIA 5TO. GRADO, FERREÑAFE, PUEBLO NUEVO, EL INTERVENIDO FUE TRASLADADO AL CENTRO MEDICO ANCÓN SIENDO ATENDIDO POR EL DR. CARLOS SEGURA DIAGNOSTICANDO POLICONTUSO POR ACCIDENTE DE TRÁNSITO, SIENDO LAS 07:50 DEL MISMO DIA Y POR LOS HECHOS NARRADOS LA PERSONA DE TORIBIO FELIX JOSUE ELIAS ES PUESTO A DISPOSICIÓN DE LA SECCIÓN DE TRANSITO DE ESTA DEPENDENCIA POLICIAL EN CALIDAD DE DETENIDO POR EL PRESUNTO DELITO S/V/C/S HOMICIDIO CULPOSO, ADJUNTANDO UN ACTA DE REGISTRO PERSONAL, UN ACTA DE LECTURA DE DERECHOS, CONSTANCIA DE BUEN TRATO, ACTA DE DETENCIÓN, ACTA DE SITUACIÓN VEHICULAR, UNA (01) LICENCIA DE CONDUCIR, FIRMANDO A CONTINUACIÓN LA PRESENTE COMO MUESTRA DE CONFORMIDAD LAS PERSONAS INVOLUCRADAS.- FDO. EL INSTRUCTOR PNP ST3 PNP JORGE E. ARAUJO ZEGARRA, SE PNP SEGUNDO ALTAMIRANO SILVA, FDO. EL DETENIDO JOSUÉ ELIAS TORIBIO FELIX.-----\r\n   \r\n\r\n 	 \r\nINTERVINIENTE : SO.TCO.3A. PNP JORGE ESTEBAN ARAUJO ZEGARRA\r\nAUTENTIFICADOR 1 : S1 PNP GONZALO ELTON JACOBO CARDENAS\r\nAUTENTIFICADOR 2 : SO.BRIG. PNP VALENTIN IZAGUIRRE ,GRIMALDO WALTER', 'Constituido en el lugar del evento, personal especializado de la UIAT NORTE no encontró personal policial interviniente de la comisaría PNP Ancón, debido a lo extemporáneo de la intervención y a que, debido a las lesiones causadas en el peatón, fue auxiliado y trasladado al hospital Carlos Lanfranco La Hoz.', '- Motocicleta de placa 3278-QC : Esta unidad no fue encontrada en su posicion final  debido a que a consecuencia del accidente de transito  auxiliaron a la persona lesionada hacia el hospital, - Peaton fallecido : no fue encontrado en su posicion final  debido a que posterior al accidente fue auxiliada y trasladado al hospital de puente piedra', 'Recta', 'Poste de alumbrado público Nro.50/0220012 ubicado en la seccion de tierra este.', '-11.808476, -77.132549', 'Es una vía que consta de dos calzadas de capacidad para dos carriles de circulación cada una, las mismas que se encuentran divididas por un separador central, tipo muro New Jersey, seguido hacia ambos extremos de carril de paradero de transporte público, acera, amplia zona de tierra y límite de propiedad.', 'Recta y plana', 'Asfalto, pavimento seco en buen estado de uso y conservación.', 'Señal vertical \"P-48\", presencia de peatones, señal reguladora de velocidad permitida 80 kph \"R-30\", marcas en la calzada, lineas discontinuas de carril, lineal continua delimitadora de calzada', 'De sur a norte', 'Natural a la hora de la ITP', 'Buena en profundidad y amplitud', 'Discontinua', 'Moderada a rápida', NULL, 'Se puede apreciar en el tramo del evento km 39.500 en el separador central espacios abiertos entre muros de concreto tipo New Jersey  en cantidad de 8 espacios donde es aprovechado por los peatones moradores del lugar  para realizar el cruce de la calzada  de un espacio de 3.92 el más amplio., Se puede apreciar un carril exclusivo de paradero de transporte publico  con marca en la calzada \"BUS\", Se puede apreciar en la superficie de la calzada multiples marcas de huellas de frenada  diferentes tamaños y caracteristicas., Se puede apreciar una area de 10x1 m de restos de autopartes diseminados entre el carril derecho y el carril de paradero de transporte publico, Se puede apreciar una mancha de sangre en la zona de tierra', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Ubicado en la zona de tierra este en un area de 20 cm x 20 cm, ubicando del P.R. a 90 metros hacia el norte', 'Evidencia Fisica (huella de frenada)\r\nDos huellas paralelas de manera longitudinal de 48.00 m ubicando en el carril derecho de la calzada este de la carretera Panamericana Norte  km 39.600 y que se proyecta hacia el centro de la calzada de un ancho de 15 cm y juntas 50 cm\r\n\r\nDos huellas paralelas ubicadas en el carril derecho que se proyectan hacia la zona de berma este de manera longitudinal  de 23.50 m y un ancho de 15 cm.\r\n\r\nHuella de frenado ubicado en el centro de la calzada, de una longitud de 10 m en linea recta de un ancho de 0.15 m\r\n\r\nHuella de melladura en un area de 10x1 m. ubicado en el carril este de paradero de transporte público.', 'Se puede apreciar restos, residuosde autopartes diseminados en el carril de paradero de transporte publico en un area de 10x1 m.'),
(6, 33, '2025-12-09', '23:43:00', 'Fecha y Hora Registro	10/07/2025 08:20:11 Hrs.	\r\n\r\nFormalidad	ESCRITA	Fecha y Hora Hecho	09/07/2025 21:00:00 Hrs.\r\nCondición de la Denuncia	[TRAN] ACTA DE INTERVENCION Nro : 110\r\nCódigo QR\r\n\r\nTIPIFICACION\r\nACCIDENTES DE TRANSITO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / CARABAYLLO / OTROS C. INTEGRACIÓN, CARABAYLLO 15122, PERÚ\r\nAGRAVIADO\r\n1) BETSY ROCIO QUISPE AROSTEGUI(40), CON FECHA DE NACIMIENTO 04/07/1985 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 43097400, DIRECCION : JUNIN / HUANCAYO / EL TAMBO : PSJ. SANTA INES 155\r\n2) CRISTOBAL CLAUDIO CHUCO LEON(44), CON FECHA DE NACIMIENTO 01/05/1981 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 41951786, OCUPACION : COMERCIANTE, DIRECCION : LIMA / LIMA / PACHACAMAC : AV SANTA CRUZ MZ O LTE 7 HUERTO DE LURIN, TELEFONO : 949327126\r\n3) CIELO CRISTEL CHUCO QUISPE(2), CON FECHA DE NACIMIENTO 08/01/2023 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 93211619\r\nPARTICIPANTE\r\n1) PROSPERO FLORES VEGA(66), CON FECHA DE NACIMIENTO 25/06/1959 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 01010505, DIRECCION : LIMA / LIMA / CARABAYLLO : ASENT.H.SAN BENITO MZ.Q1 LT.30 CALLE LOS EDITORES\r\n\r\nVEHICULO(S)\r\n1) VEHICULO MENOR - MARCA : BAJAJ - MODELO : NO INDICA - PLACA : 7858DB - COLOR : AZUL - AÑO FAB : 2020 - SITUACION : ACCIDENTE DE TRANSITO - SERIE : - MOTOR : - OBS :\r\nCONTENIDO\r\nSIENDO LAS 23:30 HRS. DEL DIA 09/07/2025, SE RECEPCIONO EL ACTA DE INTERVENCION POLICIAL FORMULADO EL S3 PNP PAUL CHUQUIHUANCA JOCOPE, CUYO TENOR LITERAL ES EL SIGUIENTE: \"EN EL DISTRITO DE CARABAYLLO, SIENDO LAS 22:10 HORAS., DEL DIA 09JUL2025, PRESENTES EN UNA DE LAS OFICINAS DE LA COMISARIA DE CARABAYLLO; EL SUSCRITO S2 PNP PAUL CHUQUIHUANCA JOCOPE, EN COMPAÑÍA DEL S1 PNP GELSER SANTILLAN SANTILLAN, AMBOS PERTENECIENTES A LA COMISARIA DE CARABAYLLO, SE PROCEDE A FORMULAR LA PRESENTE ACTA: EN CIRCUNSTANCIAS QUE EL SUSCRITO SE ENCONTRABA REALIZANDO LABORES DE PATRULLAJE PREVENTIVO A BORDO DE KL 27892, A HORAS 21:10 DEL PRESENTE DIA., FUIMOS DESPLAZADOS POR EL COMANDANTE DE GUARDIA DE LA CIA CARABAYLLO Y LA CENTRAL DE EMERGENCIA 105, A LA DIRECCIÓN DENOMINADA LOMAS DE CARABAYLLO PARTE CERRO, COMO REFERENCIA EL AA. HH SAN BENITO ETAPA 8 CALLE INTEGRACIÓN MZ B3- LOTE 7- DISTRITO DE CARABAYLLO, CON LA FINALIDAD DE VERIFICAR UN PRESUNTO ACCIDENTE DE TRÁNSITO- VOLCADURA DE VEHÍCULO, SEGUIDO DE LESIONES DE PERSONAS. PRESENTES EN EL LUGAR A HORAS 21:35 APROX., UBICÁNDONOS EN LA PARTE ALTA DEL CERRO DE LOMAS DE CARABAYLLO EN LAS COORDENADAS 11°4831.2\"S 77°0256.1 W, SIENDO UNA VÍA CARROZABLE DE DIFICIL ACCESO Y AGRESTE, TENIENDO FACTORES CLIMATOLÓGICOS COMO LLOVIZNA Y NEBLINA, ADEMÁS DE CARECER DE ALUMBRADO PÚBLICO, ASIMISMO, EN DICHO LUGAR SE ENCONTRÓ A UN GRUPO DE MORADORES, LOS MISMOS QUE ESTABAN TRASLADANDO POR SUS PROPIOS MEDIOS A UNA PERSONA DE SEXO MASCULINO, EL CUAL PRESENTABA A SIMPLE VISTA, LESIONES EN LA CABEZA Y CARA, REFIRIENDO LOS MORADORES, QUE MINUTOS ANTES HABÍA SUCEDIDO UN ACCIDENTE DE TRÁNSITO- DESPISTE DE VEHÍCULO MENOR, EN LA CUAL TAMBIÉN HABÍAN PASAJEROS; EN ESE SENTIDO SE PROCEDIÓ A BRINDAR LOS PRIMEROS AUXILIOS Y A ESTABILIZAR AL HERIDO, SEGUIDAMENTE FUE SUBIDO AL VEHÍCULO POLICIAL PARA SER EL TRANSBORDO HACIA LA AMBULANCIA DEL SAMU DE PLACA EUD-083 AL MANDO DE LA MEDICO EMERGENCIAS ROSA DAVALOS GONZALES, LA MISMA QUE ESTABA ESPERANDO EN LA AV. NORTE SUR DE LA 4TA. ETAPA DEL AA. HH SAN BENITO- DISTRITO DE CARABAYLLO, PARA TRASLADAR AL HERIDO AL HOSPITAL SERGIO BERNALES DE COLLIQUE PARA SU ATENCIÓN MÉDICA. --ASIMISMO, NOS ENTREVISTAMOS CON EL SEÑOR REINALDO FLORES RAMOS (40), IDENTIFICADO CON DNI N°43335692, INDICANDO SER HIJO DEL HERIDO, EL CUAL MEDIANTE SU REFERENCIA, INDICO QUE EL HERIDO Y A SU VEZ CONDUCTOR DEL VEHÍCULO MENOR DE PLACA DE RODAJE N° 7858-DB, ES SU SEÑOR PADRE DE NOMBRE PROSPERO FLORES VEGA, EL CUAL TIENE COMO DNI N°01010505, ASÍ COMO DICHO ACCIDENTE DE TRÁNSITO HABRÍA OCURRIDO A HORAS 21:00 APROX., DEL PRESENTE DÍA, CUANDO SU SEÑOR PADRE SE DESPLAZABA A BORDO DEL VEHÍCULO MENOR DE PLACA 7858-DB, CON DOS OCUPANTES COMO PASAJERO POR TROCHA CARROZABLE ANTES INDICADA LÍNEAS ARRIBA. POR OTRO LADO, EL SUSCRITO SE COMUNICÓ S3 PNP JHON CORAS LOPEZ, PERTENECIENTE A LA COMISARIA DE PUENTE PIEDRA, EL MISMO QUE REALIZA SERVICIO POLICIAL EN EL HOSPITAL DE CARLOS LANFRANCO LA HOZ, ASÍ COMO DIO LA SIGUIENTE INFORMACIÓN; QUE, A HORAS 22:10 DEL PRESENTE DÍA, HIZO EL INGRESO POR EMERGENCIA LAS PERSONA DE BETSY ROCIO QUISPE AROSTEGUI, IDENTIFICADA CON DNI N°43097400, TENIENDO DIAGNOSTICO PRELIMINAR POLITRAUMATISMO POR ACCIDENTE DE TRANSITO, ATENDIDO POR EL MEDICO CARLOS PERALTA BASURCO, CON CMP N°71129; PARA CRISTOBAL CLAUDIO CHUCO LEON, IDENTIFICADO CON DNI N°41951786 Y EL MENOR DE EDAD DE NOMBRE CIELO CRISTEL CHUCO QUISPE (02), IDENTIFICADO CON DNI N°93211619, AMBOS TENIENDO DIAGNOSTICO PRELIMINAR POLICONTUSO POR AATT Y ATENDIDOS POR EL MEDICO SANTIAGO MERINO GIRAO, CON CMP N°097359. TODOS ELLOS QUEDÁNDOSE EN OBSERVACIÓN DE DICHO NOSOCOMIO SE MENCIÓN QUE DEBIDO AL DESPISTE DEL VEHÍCULO DE PLACA DE RODAJE N° 7858-DB, ESTE SE HABRÍA QUEDADO AL PARECER EN LA PENDIENTE DEL CERRO Y POR LO ACCIDENTADO DEL LUGAR, FACTOR CLIMATOLÓGICO (LLOVIZNA Y NEBLINA) Y POR LA FALTA DE ALUMBRADO PÚBLICO, NO SE HA PODO ENCONTRAR EN EL LUGAR EL VEHÍCULO MENOR, YA QUE SE PRIORIZO EL TRASLADO DEL HERIDO. SIENDO 23:33 HORAS DEL PRESENTE DÍA DE LA FECHA, SE DA POR CONCLUIDA LA PRESENTE ACTA PASANDO A FIRMAR EL SUSCRITO Y PERSONAL PNP, DEJANDO CONSTANCIA QUE EL CONDUCTOR NO CERTIFICA LA PRESENTE PORQUE SE ENCUENTRA RECIBIENDO ATENCIÓN MEDICA URGENTE.\"\r\n   \r\n\r\nAMPLIACION 1 	\r\nINSTRUCTOR : SO.1RA. PNP VALVERDE ALVINO ,ERIK ANTONY FECHA AMPLIACION : 10/07/2025---17:20:27\r\n\r\nACTA DE OCURRENCIA POLICIAL EN EL DISTRITO DE CARABAYLLO, SITO EN EL INTERIOR DE LAS INSTALACIONES DE LA CPNP CARABAYLLO, SIENDO LAS 12:45 HORAS DEL DÍA 10JUL2025, EL SUSCRITO S1 PNP ERIK ANTONY VALVERDE ALVINO, PROCEDE A FORMULAR LA PRESENTE ACTA CONFORME AL SIGUIENTE DETALLE: 1.EL DÍA DE LA FECHA A HORAS 06:00, EL SUSCRITO SE CONSTITUYO AL HOSPITAL CARLOS LANFRANCO LA HOZ DE PUENTE PIEDRA, CON LA FINALIDAD DE VERIFICAR EL ESTADO DE SALUD DE LAS PERSONAS BETSY ROCIÓ QUISPE AROSTEGUI, IDENTIFICADA CON DNI 43097400, CRISTOBAL CLAUDIO CHUCO LEON, IDENTIFICADO CON DNI 41951786 Y LA MENOR DE EDAD CIELO CRISTEL CHUCO QUISPE, IDENTIFICADA CON DNI 93211619, QUIENES EN SU CALIDAD DE OCUPANTES (PASAJEROS) DEL VEH. AUT. MENOR DE PLACA DE RODAJE 7858-DB, SUFRIERON LESIONES CUANDO ESTE VEHÍCULO PARTICIPO DE UN ACCIDENTE DE TRANSITO (DESPISTE SEGUIDO DE VOLCADURA), CUANDO ERA CONDUCIDO POR LA PERSONA DE PROSPERO FLORES VEGA, IDENTIFICADO CON DNI 01010505, HECHO OCURRIDO EL DÍA 09JUL2025, EN JURISDICCIÓN DE ESTA CPNP CARABAYLLO. 2.EN EL LUGAR EL SUSCRITO SE ENTREVISTO CON EL DR. CARLOS PERALTA BAZURCO, CON CMP 71129, QUIEN INDICO QUE LA PERSONA DE CRISTOBAL CLAUDIO CHUCO LEON, FUE DADO DE ALTA CON DIAGNOSTICO POLICONTUSO POR ACCIDENTE DE TRÁNSITO, LA MENOR CIELO CRISTEL CHUCO QUISPE, POSTERIOR A SU ATENCIÓN MEDICA PERMANECE EN OBSERVACIÓN CON DIAGNOSTICO POLICONTUSO, PROGRAMANDO MAS EXÁMENES MÉDICOS, LA PERSONA DE BETSY ROCIÓ QUISPE AROSTEGUI, A HORAS 01:00 DEL DÍA 10JUL2025, FUE TRASLADADA AL HOSPITAL ARZOBISPO LOAYZA, CON DIAGNOSTICO TRASTORNO DE CONCIENCIA. 3.CON LA INFORMACIÓN OBTENIDA EL SUSCRITO PROCEDIÓ A COMUNICARSE TELEFÓNICAMENTE CON EL S3 PNP JEYSON CONDORI SALVADOR, ENCARGADO DE LA SECCIÓN DE TRANSITO DE LA COMISARIA PNP DE CHACRA COLORADA, SOLICITANDO INFORMACIÓN DEL INGRESO DE LA PERSONA BETSY ROCIÓ QUISPE AROSTEGUI, AL HOSPITAL MENCIONADO TODA VEZ QUE ESTE NOSOCOMIO PERTENECE A SU JURISDICCIÓN, EL MISMO QUE INDICO QUE LA PERSONA INGRESO A LAS 01:19 DEL DÍA 10JUL2025, DONDE FUE ATENDIDA POR EL DR. WALTER TERAN ROBLES, CON CMP 71903, QUIEN A LAS 03:30 HORAS DEL MISMO DÍA CONFIRMO EL DECESO DE LA AGRAVIADA EN MENCIÓN, CON DIAGNOSTICO TEC SEVERO, TRAUMA TORÁCICO, TRAUMA ABDOMINAL. 4.EL SUSCRITO PROCEDIÓ A DIRIGIRSE AL HOSPITAL SERGIO BERNALES DE COLLIQUE COMAS, LUGAR DONDE EL CONDUCTOR PROSPERO FLORES VEGA, SE ENCONTRABA RECIBIENDO ATENCIÓN MÉDICA, PROCEDIENDO A SU DETENCIÓN POR LA PRESUNTA COMISIÓN DEL DELITO CONTRA LA VIDA EL CUERPO Y LA SALUD (HOMICIDIO CULPOSO), EN AGRAVIO DE LA PERSONA QUIEN EN VIDA FUE BETSY ROCIÓ QUISPE AROSTEGUI (40), A CONSECUENCIA DE UN ACCIDENTE DE TRÁNSITO (DESPISTE SEGUIDO DE VOLCADURA). ASIMISMO, A LAS 08:30 DEL DÍA 10JUL2025, DICHO CONDUCTOR FUE DADO DE ALTA POR MEDICO DE TURNO IVÁN SUELDO MORALES, IDENTIFICADO CON CMP 180108, CON DIAGNÓSTICO POLICONTUSO POR ACCIDENTE DE TRÁNSITO. SIN EMBARGO, SU ALTA MÉDICA FUE AUTORIZADA A LAS 12:30 HORAS DEL PRESENTE DÍA, PROCEDIENDO A SU TRASLADO A', 'Consituido en el lulcar del accidente personal especializado de la UIAT NORTE, se encontró en el lugar presencia de personal policial de la Comisaria PNP Carabayllo a cargo de la protección y aislamiento del lugar de los hechos.', NULL, 'Curva, cerrada, pendiente en subida', 'Poste de alumbrado público Nro. 9-300-2 150-285 2021', '-11.811302, -77.048620', 'Es una vía trocha carrozable que cuenta con una calzada de circulación con capacidad para un vehículo, bordeado hacia el extremo oeste de talud rocoso de corte natural y hacia el lado derecho extremo este acantilado.', 'Curva cerrada, pendiente en subida', 'Trocha carrozable, superficie de tierra afirmada, terreno irregular, presencia de piedras sueltas.', 'No se aprecian', 'De norte a sur', 'Natural, al momento de la intervencíon.', 'Restringida por curva cerrada en amplitud derecho.', 'Esporádica', 'Lenta', NULL, 'Se puede apreciar la superficie de tierra lodosa  piedras sueltas  baches  irregular en el tramo del evento tipo monticulo de tierra  superficie elevado con inclinación hacia el abismo., Se aprecia en el tramo del despiste tierra removida que coincide con la trayectoria de volcadura y caida  precipitación a una altura de 15 metros., Se puede apreciar clima lluvioso y neblina., Se evidencia  el punto de caida de la trimoto de pasajeros  sobre una vivienda de material de madera.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 34, '2024-12-30', '01:00:00', 'Fecha y Hora Registro	31/12/2024 02:12:31 Hrs.	\r\n\r\nFormalidad	ESCRITA	Fecha y Hora Hecho	30/12/2024 21:00:00 Hrs.\r\nCondición de la Denuncia	[DEINPOL] ACTA DE INTERVENCION Nro : 858\r\nCódigo QR\r\n\r\nTIPIFICACION\r\nACCIDENTES DE TRANSITO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / CARABAYLLO / OTROS CARRETERA LIMA A CANTA KM. 32.5\r\n\r\nCONTENIDO\r\n--- EN EL DISTRITO DE CARABAYLLO, SIENDO LAS 21:00 HORAS DEL 30 DE DICIEMBRE DEL 2024, ENCONTRÁNDOSE EL SUSCRITO A BORDO DE LA UU.MM. TMP-3202, POR ORDEN DEL COMANDANTE DE GUARDIA Y A MÉRITO DE UNA LLAMADA RADIAL DE LA CENTRAL 105 DONDE COMUNICAN QUE EN EL KM. 32.5 DE LA CARRETERA LIMA CANTA SE HABÍA PRODUCIDO UN ACCIDENTE DE TRÁNSITO CON CONSECUENCIAS FATALES., EL SUSCRITO SE CONSTITUYÓ AL LUGAR EN MENCIÓN, DONDE AL LLEGAR, HALLÓ A UNA PERSONA DE SEXO MASCULINO N/N TENDIDO EN EL PAVIMENTO A UN COSTADO DE LA VÍA (KM. 32.5 DE LA CARRETERA LIMA CANTA), CON SENTIDO DE SUR A NORTE, EN POSICIÓN DECUBITO VENTRAL CON LAS EXTREMIDADES INFERIORES EXTENDIDAS Y EXTREMIDADES SUPERIORES SEMI FLEXIONADAS, TENIENDO COMO VESTIMENTA UN POLO DE COLOR ROJO, UN SHORT DE COLOR AZUL CLARO Y ZAPATILLAS DE COLOR NEGRO; SE HACE MENCIÓN QUE EN EL LUGAR NO SE APRECIA NINGÚN VEHÍCULO PARTICIPANTE EN EL ACCIDENTE DE TRÁNSITO, DANDO A ENTENDER QUE EL VEHÍCULO SE HABRÍA DADO A LA FUGA CON RUMBO DESCONOCIDO. ASÍ MISMO, SE HACE MENCIÓN QUE SE COMUNICÓ A LA FISCALÍA DE TURNO, SIENDO ATENDIDO POR EL DR. JOSE CORNELIO CASTILLA CISNEROS, FISCAL PROVINCIAL DEL CUARTO DESPACHO DE LA FISCALÍA PROVINCIAL CORPORATIVA DE TRANSITO Y SEGURIDAD VIAL DE LIMA NORTE Y A LA UNIDAD ESPECIALIZADA DEPIAT PNP NORTE, SIENDO ATENDIDO POR EL ST3 PNP MANUEL MONTENEGRO PERALES. --- SIENDO LAS 22:20 HORAS DEL MISMO DÍA SE DA POR CONCLUIDA LA PRESENTE ACTA FIRMANDO EN SEÑAL DE CONFORMIDAD EL INSTRUCTOR PNP.\r\n   \r\n\r\nAMPLIACION 1 	\r\nINSTRUCTOR : SO.2DA. PNP NEYRA ZAPATA ,CRISTHIAN ADRIAN FECHA AMPLIACION : 07/01/2025---11:18:44\r\n\r\nSIENDO LAS 10.45 HORAS DEL DÍA 07ENE2025, SE HIZO PRESENTE A LA OFICINA DE LA SECCIÓN DE INVESTIGACIÓN DE ACCIDENTES DE TRÁNSITO DE LA COMISARIA EL PROGRESO LA CIUDADANA LUCIA MAÑUICO MORALES (31) LIMA, SOLTERA, SU CASA, DOMICILIADA EN EL CMTE. 10 DEL A.H. VILLA ESPERANZA MZ. 55 E LT. 5 DISTRITO DE CARABAYLLO, TELÉFONO: 916125938, QUIEN REFIERE QUE SU SEÑOR PADRE QUIEN EN VIDA FUE: JOSÉ ANTONIO MAÑUICO DURAND (52) FUE VICTIMA DE UN ATROPELLO Y FUGA CON CONSECUENCIA FAYAL; HECHO OCURRIDO EL DÍA 30DICEMBRE2024 A LAS 21:00 HORAS APROXIMADAMENTE, EN LA CARRETERA LIMA A CANTA ALTUTA DEL KM. 32.5 REF: ANTES DE LLEGAR AL CENTRO RECREACIONAL EL TUMI, MISMO QUE FUE REGISTRADO EN EL SISTEMA DE DENUNCIAS SIDPOL COMO N.N., SOLICITANDO QUE SE AMPLIE LA DENUNCIA YA REGISTRADA POR ATROPELLO FATAL CON FUGA CON EL NOMBRE COMPLETO DE SU SEÑOR PADRE, QUIEN PERDIERA LA VIDA A CONSECUENCIA DE DICHO EVENTO DE TRANSITO. SIENDO LAS 10.55 HORAS DEL MISMO DIA SE DA POR CONCLUIDA LA PRESENTE ACTA.\r\n 	 \r\nINTERVINIENTE : SOT1 PNP PERCY HUAYCOCHEA RIVERA\r\nAUTENTIFICADOR 1 : SO2.PNP CRISTHIAN ADRIAN NEYRA ZAPATA', 'Constituido al lugar del evento, se constató presencia de personal policial de la Comisaría PNP El Progreso a cargo de la proteccion y aislamiento del lugar de los hechos.', '- Peatón : Se ubico en su posicion final  decubito dorsal  sobre la superfice del carril este de la calzada de la Carretera Lima-Canta km 32.5  con su cabeza orientada al norte y sus extremidades inferiores al sur  ubicando su punto medio del P.R. al', 'Recta', NULL, '-11.777460, -76.982604', 'Es una vía que consta de una calzada con capacidad para dos carriles de doble sentido de circulación, el cual se encuentra dividido  por lineas longitudinales discontinuas de color blanco, se puede apreciar a los bordes de la calzada linea continua delimitadora de calzada, seguido de berma, amplia zona de tierra y limite de propiedad.', 'Recta y ligera pendiente en subida', 'Pavimento, asfalto en buen estado de uso y conservación', 'Marcas en la calzada, linea continua delimitadora de calzada y lineas discontinuas delimitadoras de carril', 'De sur a norte', 'Artificial, postes de alumbrado público de intensidad tenue.', 'En profundidad', 'Discontinua', 'Moderada a rápida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Mancha de sangre, ubicada en la zona de berma lateral este', NULL, NULL),
(8, 35, '2026-01-11', '05:10:00', 'Fecha y Hora Registro\r\n11/01/2026 14:54:47 Hrs.\r\nFormalidad\r\nESCRITA\r\nFecha y Hora Hecho\r\n11/01/2026 03:15:00 Hrs.\r\nCondición de la Denuncia\r\n[TRAN] ACTA DE INTERVENCION Nro : 6\r\nCódigo QR\r\nTIPIFICACION\r\nACCIDENTES DE TRANSITO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / LIMA / OTROS AUXILIAR PANAMERICANA NORTE 152, PUENTE PIEDRA15121, PERÚ\r\nDETENIDO\r\n1.\r\nJUAN FRANCISCO RAMIREZ BERNABE(22), CON FECHA DE NACIMIENTO 25/05/2003 , ESTADO CIVIL :SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 71272672, DIRECCION : LIMA / LIMA / PUENTE PIEDRA: URB. LAS LOMAS DE ZAPALLAL MZ. L LT. 19, TELEFONO : 906861037\r\nFALLECIDO\r\n1.\r\nSEGUNDO JULIO DELGADO BANDA(57), CON FECHA DE NACIMIENTO 30/01/1968 , ESTADO CIVIL : SOLTERO(A),CON DOCUMENTO DE IDENTIDAD DNI NRO : 27281411, OCUPACION : AGRICULTOR, DIRECCION : CAJAMARCA /CUTERVO / CUTERVO : CASERIO VALLE EL REJO, TELEFONO : 944288278\r\nVEHICULO(S)\r\n1) OMNIBUS - MARCA : NO INDICA - MODELO : NO INDICA - PLACA : A8E798 - COLOR : BLANCO CON LINEASAMARILLAS Y NARANJA - AÑO FAB : - SITUACION : ACCIDENTE DE TRANSITO - SERIE : - MOTOR : - OBS :RUTA C VITARTE JAVIER PRADO, SITUACION CAUSANTE POR EFECTUAR UNA MALA MANIOBRA\r\nCONTENIDO\r\nEN EL DISTRITO DE PUENTE PIEDRA LOCALIDAD DE ZAPALLAL, SIENDO LAS 03 35 HORAS DEL DÍA 11ENE2026EL SUSCRITO ENCONTRANDOSE DE SERVICIO DE PATRULLAJE MOTORIZADO A BORDO DE LA UU.MM TMP-3378EN COMPAÑÍA DEL S3 PNP VERA CARDENAS KEVIN (CONDUCTOR), FUIMOS DESPLAZADOS POR ORDEN DELCOMANDANTE GUARDIA NOS DESPLAZAMOS A LA ALTURA DEL MERCADO TRES REGIONES AV PANAMERICANANORTE KM. SENTIDO DE SUR A NORTE PARA VERIFICAR UN ACCIDENTE DE TRANSITO. EL SUSCRITO ALLLEGAR AL LUGAR VISUALIZA UNA PERSONA DE SEXO MASCULINO TENDIDO DECUBITO DORSAL EN EL CARRILDERECHO DE LA AV PANAMERICANA NORTE KM AL FRONTIS DE LA TIENDA MARTIN RODEADO POR PERSONALDE SERENAZGO Y MORADORES DE LA ZONA EL MISMO QUE HABIA SUFRIDO AL PARECER UN ACCIDENTE DETRANSITO ATROPELLO LOGRANDO VISUALIZAR SU DOCUMENTO NACIONAL DE IDENTIDAD DE NOMBRESEGUNDO JULIO DELGADO BANDA CON DNI NRO 27281411 , ASIMISMO SEGUN LOS MORADORES DE LA ZONAREFIRIERON QUE A LAS 03 15 HORAS DEL PRESENTE DIA SE HABRIA SUSCITADO LPS HECHOS Y QUE DICHOVEHICULO PARTICIPANTE DEL ACCIDENTE DE TRANSITO ATROPELLO HABRIA SIDO UNA COASTER QUE ALMOMENTO DEL HECHO SE DIO A LA FUGA CON RUMBO DESCONOCIDO NO LOGRANDO IDENTIFICAR LA PLACADE RODAJE , SE HACE MENCION QUE POSTERIOR A ELLO SE DIO CONOCIMIENTO QUE DICHO CONDUCTOR YVEHICULO AUTOR DEL HECHO SE HABRIA APERSONADO POR VOLUNTAD PROPIA A LA CIA PNP ZAPALLALSIENDO IDENTIFICADO EL CONDUCTOR DE NOMBRES JUAN FRANCISCO RAMIREZ BERNABE 23 LIMASECUNDARIA COMPLETA CONDUCTOR CON DNI 71272672 DOMICILIO URB LAS LOMAS DE ZAPALLAL LT 19ZAPALLAL VEHICULO DE PLACA A8E 798 MARCA TOYOTA MODELO COASTER COLOR BLANCO AMARILLO. ESTODO LO QUE SE DA CUENTA A LA SUPERIORIDAD PARA LAS DILIGENCIAS CORRESPONDIENTES. SIENDO LAS04 10 HORAS DEL PRESENTE DIA SE DA POR CONCLUIDA LA PRESENTE ACTA FIRMANDO A CONTINUACION LOSPARTICIPANTES EN SEÑAL DE COMFORMIDAD\r\nINTERVINIENTE : SO.2DA. PNP LUSBIN JOEL RUIZ RODRIGUEZ\r\nAUTENTIFICADOR 1 : S2 PNP WILLMAN APOLINARIO IBIAS', 'Constituido en el lugar del evento se pudo constatar presencia de personal policial de la Comisaria PNP Zapallal, a cargo de la protección y aislamimiento del lugar de los hechos, procediendo a abordar la escena del lugar de los hechos.', '- Peatón : fue localizado sobre la superficie del carril derecho de la CPN km 33.500  con la cabeza orientada hacia el norte y sus miembros inferiores hacia el sur  apegado a la linea discontinua del centro de la calzada, - Microbus : no fue encontrado en su posicion final  debido a que posterior al accidente de transito habria continuado su recorrido retirandose del lugar del accidente', 'Recta y plana', 'Poste de alumbrado público N° ESCARSA 05 2018 9 200 2 150 285, ubicado en la zona de tierra lado este de la Carretera Panamericana Norte km 33.500-Puente Piedra', '-11.848601, -77.094645', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 20, '2025-10-14', '15:40:00', 'Fecha y Hora Registro	14/10/2025 01:40:36 Hrs.	\r\n\r\nFormalidad	ESCRITA	Fecha y Hora Hecho	13/10/2025 17:15:00 Hrs.\r\nCondición de la Denuncia	[TRAN] ACTA DE INTERVENCION Nro : 962\r\nCódigo QR\r\n\r\nTIPIFICACION\r\nACCIDENTES DE TRANSITO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / LOS OLIVOS / OTROS SAN MARTIN DE PORRES\r\nAGRAVIADO\r\nARTURO PATROCINIO LA ROSA MEDINA(76), CON FECHA DE NACIMIENTO 14/11/1948 , ESTADO CIVIL : NO INDICA, CON DOCUMENTO DE IDENTIDAD DNI NRO : 09040053, OCUPACION : INDEPENDIENTE\r\nALEXANDRA ISABELLA MORALES PASCO(29), CON FECHA DE NACIMIENTO 18/07/1996 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 75932112, DIRECCION : LIMA / LIMA / SAN MARTIN DE PORRES : JR. TURIN 248 URB. FIORI\r\nPARTICIPANTE\r\nDIEGO ALBERTO FLORES HUINCHA(27), CON FECHA DE NACIMIENTO 28/03/1998 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 75393977, OCUPACION : ESTUDIANTE, DIRECCION : LIMA / LIMA / SAN MARTIN DE PORRES : ASOC. DE PROPIETARIOS LAS MERCEDES, TELEFONO : 5746539\r\n\r\nVEHICULO(S)\r\n1) VEHICULO MENOR - MARCA : YAMAHA - MODELO : NO INDICA - PLACA : 1211QC - COLOR : AZUL - AÑO FAB : 2024 - SITUACION : ACCIDENTE DE TRANSITO - SERIE : - MOTOR : - OBS :\r\nCONTENIDO\r\nACTA DE INTERVENCIÓN POLICIAL. EN LA CIUDAD DE LIMA, DISTRITO DE SAN MARTÍN DE PORRES, SIENDO LAS 21.30 DEL 130CT2025, EL SUSCRITO S2 PNP BRUNO CAMPOS ALVARO PERTENECIENTE A LA UNISEBAN PNP (AGUILAS NEGRAS), ENCONTRÁNDOSE DE SERVICIO A BORDO DE LA UU.MM PL-25834, EN COMPAÑÍA DEL ST3 PNP RAMIREZ TORRES JUAN (CONDUCTOR), AL ENCONTRARNOS REALIZANDO PATRULLAJE PREVENTIVO POR LA AV. HUANDOY, SAN MARTIN DE PORRES, FUIMOS ALERTADOS POR TRANSEUNTES QUE A 100 METROS EN LA CUADRA 13 DE LA AV. LOS ALISOS SE HABRIA PRODUCIDO UN ACCIDENTE DE TRANSITO CON DAÑOS MATERIALES Y LESIONES (ATROPELLO). CONSTITUIDO EN EL LUGAR FRONTIS DE LA AV. LOS ALISOS 1320, URB. ROSARIO DEL NORTE, SAN MARTIN DE PORRES, SE CONSTATO UN ACCIDENTE DE TRANSITO ENTRE UN VEHÍCULO MENOR Y UN PEATÓN, CAUSANDO DAÑOS MATERIALES Y LESIONES (ATROPELLO), HECHO OCURRIDO A HORAS 17.15 APROXIMADAMENTE DEL PRESENTE DIA, DONDE EL PEATÓN DE SEXO MASCULINO SE ENCUENTRA TENDIDO SOBRE LA BERMA CENTRAL EN POSICIÓN DECUBITO LATERAL IZQUIERDO QUIEN SE LE BRINDO LOS PRIMEROS AUXILIOS MANTENIENDOLO INMÓVIL HASTA LA LLEGADA DE LA AMBULANCIA, SIENDO IDENTIFICADO CON EL NOMBRE DE ARTURO PATROCINIO LA ROSA MEDINA (76) CON DNI N. 09040053, TAMBIÉN SE VISUALIZA UN VEHÍCULO MENOR DE PLACA 1211-QC, MARCA YAMAHA, COLOR AZUL, ESTACIONADO EN EL CARRIL IZQUIERDO EN DIRECCIÓN DE OESTE A ESTE, CON SUS OCUPANTES SENTADOS SOBRE LA BERMA LATERAL, EL CONDUCTOR SIENDO IDENTIFICADO CON EL NOMBRE DE DIEGO ALBERTO FLORES HUINCHA (27), LIMA, SOLTERO, CON DNI N 75393977, DOMICILIADO MZ. C LT. 17 ASOCIACIÓN DE PROPIETARIOS LAS MERCEDES, SAN MARTIN DE PORRES, LIMA, QUIEN REFIERE QUE SE ENCONTRABA CIRCULANDO POR EL CARRIL IZQUIERDO DE LA AV. LOS ALISOS EN EL SENTIDO DE OESTE A ESTE EN COMPAÑÍA DE LA PERSONA DE ALEXANDRA ISABELLA MORALES PASCO (29), LIMA, SOLTERA, 75932112, DOMICILIO JIRÓN TURIN 248 URB. FIORI, SAN MARTÍN DE PORRES, LIMA, INDICANDO QUE EL PEATÓN CRUZÓ LA PISTA DE MANERA SORPRESIVA, NO PUDIENDO DARLE EL TIEMPO DE REACCIÓN NECESARIA PARA EVITAR EL ACCIDENTE, POR TAL MOTIVO SE REALIZÓ UNA LLAMADA TELEFÓNICA A LA LÍNEA 106 SAMU (SISTEMA DE ATENCIÓN MÉDICA DE URGENCIAS), PARA EL APOYO MÉDICO Y TRASLADO DE LOS HERIDOS, SIENDO LAS 17.35 HORAS DEL PRESENTE DIA, SE PRESENTÓ LA AMBULANCIA DEL SAMU DE PLACA EUJ-767, A CARGO DEL MÉDICO RUDY VERA RIOFRIO, BRINDANDO EL APOYO MÉDICO A LA PERSONA ATROPELLADA Y TRASLADARLO A LA CLÍNICA JESÚS DEL NORTE POR EMERGENCIA, UBICADA EN AV. CARLOS IZAGUIRRE 153, INDEPENDENCIA, LIMA, DE IGUAL FORMA DE TRASLADO A LOS AGRAVIADOS DEL VEHÍCULO MENOR HACIA LA CLÍNICA ANTES MENCIONADA A BORDO DE LA PL-25834. CONSTITUIDO EN LA CLINICA JESUS DEL NORTE POR EMERGENCIA, EL PRIMERO EN MENCIÓN ARTURO PATROCINIO LA ROSA MEDINA (76), SIENDO ATENDIENDO POR EL MÉDICO DE TURNO JENNIFER LARES CMP N 83256, DIAGNOSTICANDO POLITRAUMATIZADO, TRAUMA TORÁXICO CERRADO, TRAUMA ABDOMINAL CERRADO, FRACTURA DE PIERNA DERECHA, FRACTURA DE PELVIS A DESCARTAR, TRAUMA ENCEFALICO LEVE, INDICANDO QUE SE QUEDARA INTERNADO. SEGUNDO EN MENCIÓN DIEGO ALBERTO FLORES HUINCHA (27), SIENDO ATENDIENDO POR EL DOCTOR DE TURNO JACKELINE VILLAREAL NIETO COMO NRO 82171, DIAGNOSTICANDO POLICONTUSO, FRACTURA DE CLAVÍCULA DERECHA, INDICANDO QUE SE QUEDARÁ INTERNADO, TERCERO EN MENCIÓN ALEXANDRA ISABELLA MORALES PASCO (29), SIENDO ATENDIENDO POR DANIEL PALOMINO LOPEZ CÓMO NRO. 56624. DIAGNOSTICANDO POLICONTUSO, TRAUMATISMO DE CADERA, CABE MENCIONAR QUE SE ADJUNTA UNA (01) ACTA DE LECTURA DE DERECHO Y CONSTANCIA DE BUEN TRATO, UN (01) ACTA DE REGISTRO PERSONAL UN ACTA (01) DE REGISTRO VEHICULAR, UN ACTA DE NOTIFICACIÓN DE DETENCIÓN, UN (01) ACTA DE SITUACIÓN VEHICULAR, UNA (01) LICENCIA DE CONDUCIR, UNA (01) LLAVE DE VEHÍCULO MENOR. ASIMISMO, SE MENCIONA QUE EL S3 PNP CARHUAPOMA YANCE PETER EN CALIDAD OPERADOR DE LA PL-27601 SE ENCUENTRA EN CALIDAD DE CUSTODIA DEL DETENIDO DIEGO ALBERTO FLORES HUINCHA (27). SIENDO LAS 22.20 HORAS DEL PRESENTE DIA, SE DAN POR CONCLUIDO LA PRESENTE ACTA, FIRMANDO EN SEÑAL DE CONFORMIDAD EL PERSONAL PNP, AGRAVIADOS Y DETENIDO.\r\n   \r\n\r\nAMPLIACION 1 	\r\nINSTRUCTOR : SO.SUP. PNP SAAVEDRA VALERIO ,WILFREDO EDDY FECHA AMPLIACION : 14/10/2025---11:16:33\r\n\r\nSE HIZO PRESENTE DOÑA ALESSANDRA MICAELA RAMOS VASQUEZ (25), CON DNI N. 74356380, REFIERE SER SOBRINA DEL SEÑOR ARTURO PATROCINIO LA ROSA MEDINA, EL MISMO QUE EN LA ACTUALIDAD CUENTA CON (76) AÑOS DE EDAD, POR HABER NACIDO EL 14NOV1948.', 'Constituido en el lugar personal especializado de la UIAT NORTE', '- Motocicleta : No se encontro en su posicion final, - Peatón : No se encontró en su posición final', 'Recta y plana', 'Se ha tomado como punto de referencia', '-11.983127, -77.080976', 'Es una vía que consta de dos calzadas con capacidad para dos carriles de circulacion cada una, las cuales se encuentran divididas por un separador central perimetrado con bordes de sardinel  peraltado a mayor nivel de la calzada, en cuyo interior se observa secciones de tierra y plantaciones  de arboles de copa frondosa y tallo regular y arbustos, seguido hacia el borde externo sur de zona de estacionamiento, acera y limite de propiedad.', 'Recta y plana', 'Pavimento, asfalto, limpio y seco, en buen estado de uso y conservación', 'No se aprecian.', 'De oeste a este', 'Natural', 'Buena en profundidad y limitada en amplitud izquierda (presencia de arboles)', 'Discontinua', 'Moderada', NULL, 'Intersección conformada con la calle los Mercurios-SMP el cual no se describe al no verse comprometido su eje longitudinal en la producción del presente evento., Se aprecia en el separador central secciones de acera de concreto  el cual es usado por peatones como solución de continuidad por uso y costumbre   una de ellas presenta barra metálica de manera transversal., Se aprecia camaras de video vigilancia en viviendas aledañas., Se ha tomado como punto de referencia el vértice noroeste del inmueble Av. Los Alisos 1320-SMP, Se ubicaron huellas de melladura y tiznadura en cara externa de sardinel.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Melladura\r\nTiznadura', NULL);
INSERT INTO `itp` (`id`, `accidente_id`, `fecha_itp`, `hora_itp`, `ocurrencia_policial`, `llegada_lugar`, `localizacion_unidades`, `forma_via`, `punto_referencia`, `ubicacion_gps`, `descripcion_via1`, `configuracion_via1`, `material_via1`, `señalizacion_via1`, `ordenamiento_via1`, `iluminacion_via1`, `visibilidad_via1`, `intensidad_via1`, `fluidez_via1`, `medidas_via1`, `observaciones_via1`, `descripcion_via2`, `configuracion_via2`, `material_via2`, `señalizacion_via2`, `ordenamiento_via2`, `iluminacion_via2`, `visibilidad_via2`, `intensidad_via2`, `fluidez_via2`, `medidas_via2`, `observaciones_via2`, `evidencia_biologica`, `evidencia_fisica`, `evidencia_material`) VALUES
(11, 22, '2025-10-12', '04:00:00', 'Fecha y Hora Registro	12/10/2025 05:14:13 Hrs.	\r\n\r\nFormalidad	ESCRITA	Fecha y Hora Hecho	12/10/2025 01:30:00 Hrs.\r\nCondición de la Denuncia	[DEINPOL] ACTA DE INTERVENCION Nro : 483\r\nCódigo QR\r\n\r\nTIPIFICACION\r\nACCIDENTES DE TRANSITO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / ANCON / OTROS KM 38+750 DE LA CARRETERA PANAMERICANA NORTE SENTIDO DE SUR A NORTE ANCON\r\nVICTIMA\r\nNNN NNNN NNN , ESTADO CIVIL : NO INDICA\r\n\r\nCONTENIDO\r\nACTA DE INTERVENCIÓN POLICIAL ---EN EL DISTRITO DE ANCÓN PROVINCIA Y DEPARTAMENTO DE LIMA, SIENDO LAS 03:10 HORAS DEL DÍA 12 DE OCTUBRE DEL 2025, ENCONTRÁNDOSE EN LAS INSTALACIONES DE LA COMISARIA DE ANCÓN, UBICADO EN LA AV. LAS COLINAS CRUCE CON AV. JOSÉ CARLOS MARIÁTEGUI DEL DISTRITO DE ANCÓN, ÉL SUSCRITO S3 PNP ÁLVAREZ ALARCÓN JESÚS CON CIP NRO: 32218011, PROCEDE A REDACTAR EL DOCUMENTO CON EL SIGUIENTE DETALLE. ---SIENDO LAS 01:35 HORAS DEL 12 DE OCTUBRE DEL 2025,EL SUSCRITO ENCONTRÁNDOSE DE SERVICIO DE PATRULLAJE MOTORIZADO DE LA UNIDAD MÓVIL POLICIAL TMP-3357EN COMPAÑÍA DEL S2 PNP RÍOS FELIX LIMER, REALIZANDO PATRULLAJE PREVENTIVO POR NUESTRA ZONA DE RESPONSABILIDAD EN LA AVENIDA 11 DE ENERO VILLAS DE ANCÓN , RECIBIMOS UNA LLAMADA TELEFÓNICA POR ORDEN DEL COMANDANTE DE GUARDIA DE LA COMISARÍA DE ANCÒN, PARA DESPLAZARNOS POR EL KM 39 VILLA ESTELA EN SENTIDO DE SUR A NORTE POR UN PRESUNTO ATROPELLO, ASÍ MISMO EL SUSCRITO AL LLEGAR A DICHO LUGAR ANTES MENCIONADO EN EL KM 38 +750 DE LA CARRETERA PANAMERICANA NORTE EN SENTIDO DE SUR A NORTE, VISUALIZAMOS UNA CUERPO HUMANO DE SEXO MASCULINO (N/N) TENDIDO CON LOS BRAZOS ABIERTO, EN EL PAVIMENTO SE ENCONTRABA TENDIDO EL CUERPO EN CUBITO DORSAL SIN SIGNOS DE VIDA, VESTIDO DE UNA (01) CASACA COLOR NEGRA ROTA, UNA (01) PANTALÓN JEAN COLOR NEGRO (ROTO), UNA PAR DE MEDIAS COLOR NEGRO PUESTOS EN LOS PIES, PUESTO UN ZAPATO COLOR NEGRO PUESTO Y EL OTRO SUELTO TENDIDO EN LA PISTA, TAMBIÉN SE PUDO APRECIAR SIGNOS DE MANCHAS DE SANGRE POR LA PISTA, HUELLAS DE FRENADO POR EL LUGAR DEL ACCIDENTE. ASÍ MISMO SE COMUNICÓ AL ST3 PNP MONTENEGRO PERALES MANUEL CON CEL. NRO: 980121336 A FIN QUE SE HAGA A CARGO DE LA PRESENTE INVESTIGACIÓN POR SER LA UNIDAD ESPECIALIZADA ---SIENDO LAS 03:43 HORAS DEL MISMO DÍA SE DA POR CULMINADA DICHA ACTA FIRMANDO EN SEÑAL DE CONFORMIDAD LOS PARTICIPANTE.\r\n   \r\n\r\nAMPLIACION 1 	\r\nINSTRUCTOR : SO.2DA. PNP ESTRADA PASTOR ,JUAN ROMULO FECHA AMPLIACION : 17/10/2025---13:19:43\r\n\r\nEN ESTE ACTO SE HACE PRESENTE LA PERSONA DE SILVIA ARANGO GODOY (41), IDENTIFICADA CON DNI N° 42984799, MANIFESTANDO QUE LA PERSONA QUE FALLECIO EN EL PRESENTE ACCIDENTE DE TRANSITO FUE SU ESPOSO QUIEN EN VIDA FUE FREDDY JHERSON ROJAS PAZ (34), IDENTIFICADO CON DNI N° 47156997, PRESENTANDO EL CERTIFICADO DE NECROPSIA N° 2025010101002981, DONDE SE ESTABLECE COMO CAUSA DEL FALLECIMIENTO: LACERACION Y CONTUSION ENCEFALICA. FRACTURA ABIERTA DESPLAZADA OCCIPITO ATLOIDEA. TRAUMATISMO CORPORAL MULTIPLE; DEJANDOSE CONSTANCIA QUE EL OCCISO AL MOMENTO DE LA INTERVENCION POLICIAL SE ENCONTRABA COMO \"NN\".\r\n 	 \r\nINTERVINIENTE : SO.3RA. PNP JESUS VITALIANO ALVAREZ ALARCON\r\nAUTENTIFICADOR 1 : S3 PNP JUAN ROMULO ESTRADA PASTOR\r\nAUTENTIFICADOR 2 : SO.3RA. PNP ALVAREZ ALARCON ,JESUS VITALIANO', 'Constituido en el lugar del evento personas especializado de la UIAT NORTE , encontró personal policial de la comisaría PNP Ancón a cargo de la protección y asilamiento de la zona lugar del evento, debidamente con cinta retroflectantes color amarillo, así como persona de la concesionaria vial de rutas de Lima.', '- Peatón : Se ubicó en su posición final en el carril derecho de la calzada este de la Carretera Panamericana Norte km. 38.750-Ancón  en posición decúbito dorsal con la cabeza orientada hacia el norte y sus extremidades inferiores hacia el sur.', 'Recta y pendiente en bajada', NULL, NULL, 'Es una vía que consta de dos calzadas con capacidad para dos carriles de circulación, divididas por un separador central tipo muro NewJersey altura de 1.80 m. seguido hacie el borde externo este de valla de seguridad tipo guardavía metálico , seguido de talud de tierra y limite de propiedad, mientras que hacia el borde externo oeste seguido de elevacion de terreno (cerro).', 'Recta, pendiente en bajada', 'PAvimento, asfalto en buen estado de uso y conservación.', 'Marcas en la calzada , señal indicativo de semaforo vehicular cercano, señal reguladora de velocidad 40 km/h, cartel informativo.', 'De sur a norte', 'Artificial , postes de alumbrado público, operativos, baja intensidad tenue.', 'Buena en profundidad y amplitud', 'Discontinua', 'Moderada a rápida', NULL, 'A 200 metros aprox. se encuentra la intersección regulada por circuito semafórico de la Av. Los Arquitectos con sus ciclos operativos  normales  asimismo se aprecia puente peatonal de concreto  como cruceros peatonales debidamente demarcados., Se ubicó la posición del cuerpo de la persona fallecida \"NN\" en el carril derecho de la calzada este de la CPN km 38.80- Ancón  con su cabeza orientada al norte y extremidades inferiores al sur., Se ubican huellas de frenada de una longitud de 10 m. duales de manera paralela.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Mancha de sangre', 'Huellas de frenada, duales', NULL),
(12, 26, '2025-12-18', '10:20:00', 'Fecha y Hora Registro	11/07/2025 20:54:29 Hrs.	\r\n\r\nFormalidad	ESCRITA	Fecha y Hora Hecho	11/07/2025 19:00:00 Hrs.\r\nCondición de la Denuncia	[TRAN] ACTA DE INTERVENCION Nro : 640\r\nCódigo QR\r\n\r\nTIPIFICACION\r\nACCIDENTES DE TRANSITO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / SAN MARTIN DE PORRES / OTROS AV. HONORIO DELGADO 262, SAN MARTÍN DE PORRES 15102, PERÚ\r\nDENUNCIADO\r\nJOHN RICHARD TITTO VALENCIA(52), CON FECHA DE NACIMIENTO 10/09/1972 , ESTADO CIVIL : CASADO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 10191431, DIRECCION : LIMA / LIMA / COMAS : PROLONG.FAUSTINO SANCHEZ CARRION 179 URB. EL RETABLO\r\nFALLECIDO\r\nHECTOR HORACIO BARRERA PAUCAR(50), CON FECHA DE NACIMIENTO 24/12/1974 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 10159943, DIRECCION : HUANCAVELICA / TAYACAJA / DANIEL HERNANDEZ : AV. HUANCAVELICA S.N.\r\nRECURRENTE\r\nLUIS ANTONIO BARRERA BARRETO(30), CON FECHA DE NACIMIENTO 01/12/1994 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 77243359, OCUPACION : TAXISTA, DIRECCION : LIMA / LIMA / INDEPENDENCIA : PSJ. ALFONSO UGARTE ASENT. H. 30 DE ENERO, TELEFONO : 924022335\r\n\r\nVEHICULO(S)\r\n1) VEHICULO MENOR - MARCA : NO INDICA - MODELO : NO INDICA - PLACA : 0717CA - COLOR : VERDE - AÑO FAB : 2017 - SITUACION : ACCIDENTE DE TRANSITO - SERIE : - MOTOR : - OBS :\r\n2) CAMIONETA - MARCA : NO INDICA - MODELO : NO INDICA - PLACA : BKS508 - COLOR : NEGRO - AÑO FAB : 2019 - SITUACION : DENUNCIADO - SERIE : - MOTOR : - OBS : MARCA:DODGE,MODELO:DURANGO.\r\nCONTENIDO\r\nEN EL DISTRITO DE SAN MARTÍN DE PORRES, SIENDO LAS 19.00 HORAS DEL DÍA 11 DE JULIO DEL 2025 EN CIRCUNSTANCIAS QUE, EL SUSCRITO SE ENCONTRABA DE SERVICIO EN LA CASETA POLICIAL DE EMERGENCIA DEL HOSPITAL CAYETANO HEREDIA-SMP, A HORAS 18.45 DEL MISMO DÍA SE PRESENTÓ LA ENFERMERA DE TURNO LIC. AUREA PINTO VILLAR CON C.E.P. NRO. 36054, QUIEN LABORA EN EL ÁREA DE NEUROCIRUGIA, DEL HOSPITAL NACIONAL CAYETANO HEREDIA, LA MISMA QUIEN HACE CONOCER MEDIANTE LA HOJA DE EPICRISIS Y DE FALLECIMIENTO DE LA PERSONA QUIEN EN VIDA FUE; HÉCTOR HORACIO BARRERA PAUCAR (50) DNI 10159943, SIENDO ATENDIDO POR EL MÉDICO DE TURNO DR. JOSÉ LUIS LEON PALACIOS CON C.M.P. 74801, QUIEN CERTIFICÓ EL FALLECIMIENTO A HORAS 18.45 APROX. DEL DÍA 11 DE JULIO DEL 2025, CON EL DIAGNOSTICO \"SINDROME DE HIPERTENCION ENDOCRANEANO POR TEC SEVERO - PACIENTE FALLECIDO\", DICHO OCCISO SE ENCUENTRA EN EL MORTUORIO DE ESTE NOSOCOMIO, ASIMISMO SE HACE DE CONOCIMIENTO EN ESTE ACTO SE PRESENTÓ A ESTE PUESTO POLICIAL EL CIUDADANO; LUIS ANTONIO BARRERA BARRETO (30), NATURAL DE LIMA, SOLTERO, OCUPACIÓN CONDUCTOR, CON DOMICILIO EN EL JR. PRIMERO DE MAYO MZ. 65 LOTE 18, AA HH 30 DE ENERO DISTRITO DE INDEPENDENCIA LIMA, DNI NRO. 77243359, TELF. 926784741, EL MISMO QUIEN SOLICITA FORMULAR DENUNCIA EN ESTE ACTO POR EL FALLECIMIENTO DE SU PADRE Q.E.V.F. HÉCTOR HORACIO BARRERA PAUCAR (50) POR ACCIDENTE DE TRÁNSITO (CHOQUE)) HECHO OCURRIDO EL MIÉRCOLES 09 DE JULIO DEL 2025 A HORAS 08.30 APROX., A LA ALTURA DE LA CALLE LOS TAMARINDOS (NO REFIERE NUMERACIÓN EXACTA) URB. LOS JARDINES SAN MARTIN DE PORRES, JURISDICCIÓN DE LA COMISARIA DE SAN MARTIN DE PORRES, Y QUE LOS HECHOS SE SUSCITARON CUANDO SU PADRE CONDUCÍA EL VEHÍCULO MENOR DE PLACA DE RODAJE 0717-CA MARCA BAJAJ, MODELO AUTORIKSHA TORITO AÑO 2017 COLOR VERDE CON RAYAS BLANCAS, Y FUE IMPACTADO POR EL VEHÍCULO DE PLACA DE RODAJE BKS-508 MARCA DODGE, MODELO DURANGO 2019, COLOR NEGRO, DONDE ESTE VEHÍCULO IMPACTA CON SU PARTE DELANTERA AL LADO LATERAL IZQUIERDO DEL VEHÍCULO MENOR QUE CONDUCÍA SU PROGENITOR Y QUE A CONSECUENCIA DEL IMPACTO SU PADRE ES LANZADO POR LA PUERTA DERECHA CAYENDO A LA VÍA DONDE SUFRIÓ LESIONES FÍSICAS Y QUE INICIALMENTE EL CONDUCTOR DEL VEHÍCULO QUE HABRÍA OCASIONADO EL ACCIDENTE LO TRASLADA A LA CLÍNICA CAYETANO HEREDIA Y POSTERIORMENTE LO TRASLADA A ESTE HOSPITAL DONDE INDICO AL PERSONAL MÉDICO QUE SE TRATABA DE UNA CAÍDA, ASIMISMO REFIERE QUE NO DENUNCIO ESTOS HECHOS POR DESCONOCIMIENTO DE LOS PROCEDIMIENTOS A SEGUIR Y POR LA PREOCUPACIÓN EN EL CUAL SE ENCONTRABA, INDICA QUE EL VEHÍCULO QUE CONDUCÍA SU PROGENITOR SE ENCUENTRA EN SU DOMICILIO INOPERATIVO, LO QUE DENUNCIA ANTE LA PNP PARA LOS FINES DEL CASO, ASIMISMO SE ADJUNTA AL PRESENTE UNA (01) HOJA DE EPICRISIS, UNA (01) HOJA DE FALLECIMIENTO, LO QUE SE DA CUENTA A LA PNP PARA LOS FINES DEL CASO. --SIENDO LAS 19.40 HORAS DEL MISMO DÍA SE DIO POR CONCLUIDO LA PRESENTE DILIGENCIA, FIRMANDO A CONTINUACIÓN LOS PARTICIPANTES EN SEÑAL DE CONFORMIDAD.\r\n   \r\n\r\nAMPLIACION 1 	\r\nINSTRUCTOR : SO.2DA. PNP DIAZ BARDALES ,JOSE WILIAN FECHA AMPLIACION : 11/07/2025---20:58:35\r\n\r\nSE REALIZA LA PRESENTE AMPLIACION DE LA DENUNCIA EL MISMO QUE EL RECURRENTE EL SR. LUIS ANTONIO BARRERA BARRETO (30), IDENTIFICADO CON DNI.N 77243359, IDENTIFICO AL SEÑOR CAUSANTE Y PARTICIPANTE DEL ACCIDENTE DE TRANSITO-CHOQUE CON LESIONES FATALES, COMO EL SR. JOHN RICHARD TITTO VALENCIA(52), CON FECHA DE NACIMIENTO 10/09/1972 , ESTADO CIVIL : CASADO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 10191431, DIRECCION : LIMA / LIMA / COMAS : PROLONG.FAUSTINO SANCHEZ CARRION 179 URB. EL RETABLO, QUIEN TIENE NUMERO DE CELULAR 996412755.', 'Constituido en el lugar personal especializado de la UIAT NORTE, con la finalidad de realizar la Inspección Técnico Policial de manera extemporanea en el lugar de los hechos, de acuerdo a la carpeta fiscal N° 606015901-2025-2918-0 remitido por la Fiscalia Provincial Corporativa de Tránsito y Seguridad Vial -Distrito Fiscal de Lima Norte-Tercer Despacho.', NULL, 'Intersección', NULL, NULL, 'Es una intersección en forma de \"+\" conformada por el Jr. Los Tamarindos: El cual consta de una calzada de circulacion con capacidad para dos carriles de circulación, con capacidad para dos carriles de circulación, seguido a sus extremos de zonas de jardin y secciones de estacionamiento, acera y limite de propiedad.', 'Recta y plana', 'Asfalto, pavimento en mal estado de uso y conservacion, con presencia de grietas y baches', 'No se aprecian', 'De oeste a este', 'Natural', 'Buena en profundidad y amplitud.', 'Discontinua', 'Moderada', NULL, '- Se aprecian camaras de video vigilancia en la zonas aledañas al lugar del accidente., -En la Calle Los Tamarindos se puede apreciar vehiculos detenidos ocupando en el carril derecho., -Se puede apreciar en la Calle Los Tamarindos a una distancia aproximada de 130 metros aproximadamente zona escolar.', 'La Calle Los Jacintos, consta de una calzada con capacidad para dos carriles de circulación, seguido de borde de sardinel a mayor nivel de la calzada seguido de secciones de jardin, acera y limite de propiedad.', 'Recta y plana', 'Pavimento, asfalto en regular estado de uso y conservación', 'No se aprecian', 'De sur a norte', 'Natural', NULL, NULL, NULL, NULL, '-Se puede apreciar en la seccion de jardin este  arbustos y arboles de copa frondosa.', NULL, NULL, NULL),
(13, 40, '2026-04-13', '17:00:00', 'Tipo	OCURRENCIA	Fecha y Hora Registro	07/07/2025 01:44:49 Hrs.	\r\n\r\nFormalidad	ESCRITA	Fecha y Hora Hecho	06/07/2025 18:40:00 Hrs.\r\nCondición de la Denuncia	[TRAN] OCURRENCIA DE CALLE - COMUN Nro : 746\r\n\r\nTIPIFICACION\r\nTRANSITO/ACCIDENTES DE TRANSITO/ATROPELLO/ATROPELLO\r\nLUGAR DEL HECHO\r\nLIMA / LIMA / LOS OLIVOS / OTROS AVENIDA NARANJAL SENTIDO DE ESTE A OESTE A 100 METRO DE EL OVALO DE NARANJAL\r\nAGRAVIADO\r\nNARCISO QUISPE RUPA(84), CON FECHA DE NACIMIENTO 29/10/1940 , ESTADO CIVIL : CASADO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 08588722, DIRECCION : LIMA / LIMA / LOS OLIVOS : JR. PIRA 607 URB.EL PARQUE NARANJAL\r\nPARTICIPANTE\r\nMISAEL LOPEZ BERRU(29), CON FECHA DE NACIMIENTO 29/11/1995 , ESTADO CIVIL : SOLTERO(A), CON DOCUMENTO DE IDENTIDAD DNI NRO : 74739871, OCUPACION : POLICIA, DIRECCION : LIMA / LIMA / SAN MARTIN DE PORRES : DPTO. 5 URB. VIÑAS DE NARANJAL, TELEFONO : 967545056\r\n\r\nVEHICULO(S)\r\n1) VEHICULO MENOR - MARCA : NO INDICA - MODELO : NO INDICA - PLACA : 1279YB - COLOR : NEGRA - AÑO FAB : - SITUACION : ACCIDENTE DE TRANSITO - SERIE : - MOTOR : - OBS :\r\nCONTENIDO\r\nEN LA CIUDAD DE LIMA, DISTRITO DE INDEPENDENCIA, SIENDO LAS 19:45 HRS. DEL DÍA 06JUL2025, EN UNA DE LAS OFICINAS DE LA COMISARIA PNP INDEPENDENCIA, PRESENTE EL SUSCRITO S3 PNP HUAYTA QUISPE JUNIOR CIP N° 32429755 DNI N° 70281510 Y EN COMPAÑÍA DEL S3. PNP DILMER GUSTAVO CAMPOS TERRONES (CONDUCTOR), CON CIP-32168879, DNI-70837206, CONDUCTOR LOPEZ BERRU MISAEL (29) NATURAL DE PIURA, SOLTERO, ESTUDIANTE, IDENTIFICADO CON DNI N° 74739871, CEL N° 967545056, PLACA VEHICULAR 1279-YB, MARCA HAOJUE, COLOR NEGRO Y DOMICILIADO EN DPTO 5 URB. VIÑAS DE NARANJA ETAPA 2DA MZ C LT 21 SAN MARTIN DE PORRES Y NARCISO QUISPE RUPA (84) NATURAL DE CUZCO, CASADO, INDEPENDIENTE, IDENTIFICADO CON DNI N° 08588722, CEL. N° 987497603 Y DOMICILIADO EN JIRÓN PIRA 607 URB. PARQUE NARANJAL - LOS OLIVOS, CON QUIENES SE PROCEDE A REALIZAR LA PRESENTE ACTA CONFORME AL DETALLE SIGUIENTE: 1.- PERSONAL PNP MIENTRAS REALIZABA PATRULLAJE PREVENTIVO POR LA JURISDICCIÓN DE INDUSTRIAL - INDEPENDENCIA, EN ARAS DE COMBATIR, PREVENIR Y ERRADICAR LOS ÍNDICES DE CRIMINALIDAD, A BORDO DE LA UU.MM TMP-3384, A HORAS 18:50 APROXIMADAMENTE DEL PRESENTE DÍA, EN CIRCUNSTANCIAS QUE NOS ENCONTRÁBAMOS PATRULLANDO POR LA AV. TUPAC AMARU Y AV. CARLOS IZAGUIRRE FUIMOS DESPLAZADOS POR EL CMTE DE GUARDIA A LA AVENIDA NARANJAL SENTIDO DE ESTE A OESTE ALTURA DEL PUENTE PEATONAL JURIDICCION POLICIAL DE LA COMISARIA DE LAURA CALLER PARA LA VERIFICACIÓN DE UN ACCIDENTE DE TRÁNSITO. EL SUSCRITO EN EL LUGAR LOGRO ENTREVISTARSE CON LA PERSONA LÓPEZ BERRU MISAEL (29), EN EL SISTEMA LICENCIA DE CONDUCIR VM 74739871, INDICO SER CONDUCTOR DEL VEHÍCULO 1279-YB, EN EL CUAL INDICO QUE EL HECHO OCURRIDO FUE A LAS 18:40 HORAS DEL MISMO DÍA, EN EL MOMENTO QUE SE ENCONTRABA DESPLAZANDOSE POR LA AVENIDA NARANJAL SENTIDO ESTE A OESTE PASANDO EL OVALO NARANJAL, (ALTURA DEL PUENTE PEATONAL), EN ESE MOMENTO ES DONDE NO VISUALIZA A UNA PERSONA, CAUSANDO UN ATROPELLO A DICHA PERSONA, PRESTÁNDOLE LOS PRIMEROS AUXILIOS SIENDO AUXILIADO POR UNA AMBULANCIA SAMU Y LLEVÁNDOLE A LA CLÍNICA JESÚS DEL NORTE, SIENDO ATENDIDO POR EL DR. LORIEN ROMERO DAVID CON CMP 104493 INDICANDO EL DIAGNOSTICO TRAUMATISMO EN CABEZA Y POLICONTUSO. SIENDO LAS 21:00 HORAS DEL PRESENTE DÍA, SE PROCEDE A DAR POR CULMINADA LA PRESENTE ACTA, FIRMADO A CONTINUACIÓN EL PRESENTE EN SEÑAL DE CONFORMIDAD\r\n   \r\n\r\nAMPLIACION 1 	\r\nINSTRUCTOR : SO.BRIG. PNP SOTO BARRIENTOS ,JUAN CARLOS FECHA AMPLIACION : 07/07/2025---02:06:10\r\n\r\nEN LA CIUDAD DE LIMA, DISTRITO DE INDEPENDENCIA, SIENDO LAS 23:40 HRS. DEL DÍA 06JUL2025, EN UNA DE LAS OFICINAS DE LA COMISARIA PNP INDEPENDENCIA, PRESENTE EL SUSCRITO SB PNP JUAN CARLOS SOTO BARRIENTOS ENCARGADO DE EL AREA DE INVESTIGACION DE ACCIDENTE DE TRANSITO, LUEGO DE VERIFICAR EL ESTADO DE SALUD DE LA PERSONA DE NARCISO QUISPE RUPA (84), EL MISMO QUIEN SE LE DIAGNOSTICO EN LA CLINICA JESUS DEL NORTE CON FRACTURA DE PELVIS, ASI MISMO SE IDENTIFICO A LA PERSONA COMO MIEMBRO DE LA POLICIA NACIONAL DEL PERU SIENDO EL S2 PNP MISAEL LOPEZ BERRU (29), QUIEN PRESTA SERVICO EN LA DIVIAT- PNP, POR TAL MOTIVO SE LE REALIZA LOS SIGUIENTES DOCUMENTOS : ACTA DE DETENCION, ACTA DE BUEN TRATO, ACTA DE REGISTRO PERSONAL, ACTA DE DERECHO DEL DETENIDO, ACTA DE COMUNICACIÓN FISCAL. SIENDO LAS 00:30 DEL 07 DE JULIO DEL 2025, SE PROCEDE A CULMINAR CON LA PRESENTE ACTA DE INTERVENCION POLICIAL\r\nAMPLIACION 2	\r\nINSTRUCTOR : SO.BRIG. PNP SOTO BARRIENTOS ,JUAN CARLOS FECHA AMPLIACION : 07/07/2025---05:02:53\r\n\r\nN EL DISTRITO DE INDEPENDENCIA, SIENDO LAS 03:40 HRS DEL DÍA 07JUL25, EL SUSCRITO DE SERVICIO DE OPERADOR DE LA TMP 3218, FUI COMISIONADO PARA DIRIGIRME A LA COMISARÍA LAURA CALLER Y A MÉRITO DEL OFICIO 578-2025 R-P-LIMA-DIVPOL NORTE2-CI-SIAT CON LA FINALIDAD DE TRANSCRIBIR UN ACCIDENTE DE TRANSITO ATROPELLO CON LESIONES GRAVES CON LA PARTICIPACIÓN DEL VEHÍCULO MENOR SEGUN INDICA DICHO DOCUMENTO DE PLACA DE RODAJE 1279-YB , CONDUCIDO POR EL S2 PNP MISAEL LÓPEZ BERRU(29) EN AGRAVIO DE NARCISO QUISPE RUPA(84). PRESENTE EN EL LUGAR EN EXTERIORES DE LA COMISARÍA LAURA CALLER SE PUDO VERIFICAR QUE DICHO VEHÍCULO MENOR NO CONTABA CON PLACA DE RODAJE EN LA PARTE TRASERA, VERIFICANDO QUE EN LA PARTE LATERAL IZQUIERDO ALTURA DEL TANQUE DE COMBUSTIBLE, PRESENTABA UN STICKER HOLOGRAMA DE SEGUNDA PLACA; CON NÚMERO DE PLACA: 9128-7D, CABE INDICAR QUE DICHO VEHÍCULO ES UN VEHÍCULO MARCA PULSAR NS200 COLOR BLANCO NEGRO TIMÓN LADO DERECHO ROTO Y ESPEJO LADO DERECHO ROTO., SE FORMULA LA PRESENTE ACTA DE SITUACIÓN PARA PONER A DISPOSICIÓN EL VEHICULO A LA COMISARIA LAURA CALLER, CABE PRECISAR QUE EN EL OFICIO QUE SE REMITE LA TRANSCRIPCIÓN N°578, NO COINCIDE LA PLACA, LO QUE SE DA CUENTA A LA SUPERIORIDAD PARA LOS FINES PERTINENTE. SIENDO LAS 03:56 HRS DEL MISMO DÍA SE DA POR CONCLUIDA LA PRESENTE DILIGENCIA FIRMANDO A CONTINUACIÓN EL S3 PNP GERSON GHUAPAYA ROMAN\r\n 	 \r\nINTERVINIENTE : SO.3RA. PNP DILMER GUSTAVO CAMPOS TERRONES\r\nAUTENTIFICADOR 1 : SO.BRIG.PNP JUAN CARLOS SOTO BARRIENTOS', 'Constituido personal especializado de la UIAT NORTE en el lugar de los hechos Av. Naranjal cdra. 05- Los Olivos, a mérito de las diligencias de investigación que se realizan a consecuencia del accidente de tránsito, suscitado el 06JUL2025, se realiza la presente inspección técnico policial.', '- Motocicleta : No se encontro en su posición final  debido a la extemporaneo de la intervención, - Peaton : No se encontro en su posición final  debido que posterior al accidente fue auxiliado y trasladado  a la clínica Jesús del Norte', 'Recta y plana', NULL, '-11.978151, -77.070339', 'Es una vía que consta de dos calzadas principales con capacidad para tres carriles de circulación cada una, las mismas que se encuentran divididas por un separador central con bordes de sardinel peraltado a mayor nivel de la calzada en cuyo interior se aprecia sección de tierra y plantaciones de arboles de copa frondosa y tallo regular, asi como arbustos tipo sabila, asimismo hacia ambos lados se aprecia berma lateral, calzada auxiliar, zona de estacionamiento acera y limite de propiedad.', 'Recta y plana', 'Asfalto, pavimento en regular estado de uso y conservación', NULL, 'De este a oeste', 'Natural', 'Buena en profundidad y amplitud', 'Discontinua', 'Moderada', NULL, 'A 110 m. se aprecia puente peatonal metálico altura del inicio de la cuadra 5 Av. Naranjal -Los Olivos, Hacia la zona oeste se puede apreciar la proxima intersección con la Av. Palmeras  semaforizada., A la altura lugar del accidente se puede apreciar un pasaje que colinda con el local \"Parrilla y punto\", Por la parte agraviada se precisa que el accidente se tránsito se produce en el final de la cuadra 5 inicio de la cuadra 6  siendo que a esa altura no hay plantas que dificulten la visibilidad., Por la parte investigada no precisa el lugar del accidente  debido al shock posterior al accidente  solo precisa que el peatón cruzana con sentido de sur a norte', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'No se aprecian', 'No se aprecian', 'No se aprecian');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Manifestacion`
--

CREATE TABLE `Manifestacion` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `persona_id` int NOT NULL,
  `fecha` date NOT NULL,
  `horario_inicio` time NOT NULL,
  `hora_termino` time NOT NULL,
  `modalidad` varchar(50) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `Manifestacion`
--

INSERT INTO `Manifestacion` (`id`, `accidente_id`, `persona_id`, `fecha`, `horario_inicio`, `hora_termino`, `modalidad`) VALUES
(2, 18, 15, '2025-04-30', '10:25:00', '11:25:00', 'Presencial'),
(3, 19, 24, '2023-07-16', '10:00:00', '11:00:00', 'Presencial'),
(4, 20, 32, '2025-10-15', '17:00:00', '18:00:00', 'Virtual'),
(5, 26, 44, '2025-09-16', '10:00:00', '11:30:00', 'Virtual'),
(6, 27, 49, '2025-04-18', '15:00:00', '16:00:00', 'Presencial'),
(7, 27, 52, '2025-04-17', '14:50:00', '16:00:00', 'Presencial'),
(8, 29, 54, '2025-05-28', '10:00:00', '11:00:00', 'Presencial'),
(9, 29, 56, '2025-05-27', '11:50:00', '12:40:00', 'Presencial'),
(10, 25, 42, '2025-08-05', '14:00:00', '15:30:00', 'Presencial'),
(11, 30, 65, '2025-01-31', '10:50:00', '11:30:00', 'Presencial'),
(12, 24, 40, '2025-11-09', '18:30:00', '19:30:00', 'Presencial'),
(13, 32, 79, '2025-12-02', '12:00:00', '13:30:00', 'Presencial'),
(14, 33, 83, '2025-07-11', '01:00:00', '02:30:00', 'Virtual'),
(15, 33, 85, '2025-07-11', '17:10:00', '18:00:00', 'Virtual'),
(16, 20, 29, '2025-10-31', '09:30:00', '10:30:00', 'Presencial'),
(17, 23, 37, '2025-09-26', '14:00:00', '15:15:00', 'Presencial'),
(18, 23, 141, '2025-09-26', '10:50:00', '12:00:00', 'Virtual'),
(19, 23, 66, '2025-11-24', '18:00:00', '18:40:00', 'Virtual'),
(20, 23, 142, '2025-09-26', '12:00:00', '13:30:00', 'Presencial'),
(21, 23, 143, '2025-09-26', '09:34:00', '10:30:00', 'Virtual'),
(22, 35, 93, '2026-03-12', '10:00:00', '11:40:00', 'Presencial'),
(24, 40, 132, '2026-05-11', '11:20:00', '13:00:00', 'Presencial');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marcas_vehiculo`
--

CREATE TABLE `marcas_vehiculo` (
  `id` int NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pais_origen` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `marcas_vehiculo`
--

INSERT INTO `marcas_vehiculo` (`id`, `nombre`, `pais_origen`, `creado_en`) VALUES
(1, 'Toyota', 'Japón', '2025-09-17 05:42:51'),
(2, 'Nissan', 'Japón', '2025-09-17 05:42:51'),
(3, 'Hyundai', 'Corea del Sur', '2025-09-17 05:42:51'),
(4, 'Kia', 'Corea del Sur', '2025-09-17 05:42:51'),
(5, 'Suzuki', 'Japón', '2025-09-17 05:42:51'),
(6, 'Chevrolet', 'EE.UU.', '2025-09-17 05:42:51'),
(7, 'Ford', 'EE.UU.', '2025-09-17 05:42:51'),
(8, 'Volkswagen', 'Alemania', '2025-09-17 05:42:51'),
(9, 'Mercedes-Benz', 'Alemania', '2025-09-17 05:42:51'),
(10, 'BMW', 'Alemania', '2025-09-17 05:42:51'),
(11, 'Mazda', 'Japón', '2025-09-17 05:42:51'),
(12, 'Honda', 'Japón', '2025-09-17 05:42:51'),
(13, 'Mitsubishi', 'Japón', '2025-09-17 05:42:51'),
(14, 'Great Wall', 'China', '2025-09-17 05:42:51'),
(15, 'Changan', 'China', '2025-09-17 05:42:51'),
(16, 'BYD', 'China', '2025-09-17 05:42:51'),
(17, 'Geely', 'China', '2025-09-17 05:42:51'),
(18, 'Volvo', 'Suecia', '2025-09-17 05:42:51'),
(19, 'Renault', 'Francia', '2025-09-17 05:42:51'),
(20, 'Peugeot', 'Francia', '2025-09-17 05:42:51'),
(21, 'Fiat', 'Italia', '2025-09-17 05:42:51'),
(22, 'Jeep', 'EE.UU.', '2025-09-17 05:42:51'),
(23, 'Bajaj', NULL, '2025-09-18 05:48:11'),
(24, 'Daewoo', NULL, '2025-09-29 04:33:04'),
(25, 'Yamaha', '', '2025-10-14 23:42:38'),
(26, 'Wanxin', 'China', '2025-10-16 23:05:33'),
(27, 'Max Metal', '', '2025-10-20 04:22:45'),
(28, 'SITRAK', NULL, '2025-10-20 04:37:44'),
(29, 'Dodge', '', '2025-10-26 17:39:58'),
(30, 'Sumo', '', '2025-11-01 02:31:15'),
(31, 'JCH', '', '2025-11-24 21:45:55'),
(32, 'Kawasaki', '', '2025-11-29 06:41:44'),
(33, 'DFSK', '', '2025-11-29 06:48:22'),
(34, 'Scania', '', '2026-02-05 06:35:59'),
(35, 'Reconcisa', '', '2026-02-05 06:39:40'),
(37, 'HAVAL', NULL, '2026-03-05 08:01:47');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modalidad_accidente`
--

CREATE TABLE `modalidad_accidente` (
  `id` int NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `modalidad_accidente`
--

INSERT INTO `modalidad_accidente` (`id`, `nombre`, `descripcion`, `creado_en`) VALUES
(1, 'Choque', 'Impacto entre dos o más vehículos', '2025-09-17 06:46:37'),
(2, 'Atropello', 'Vehículo impacta a un peatón', '2025-09-17 06:46:37'),
(3, 'Volcadura', 'Vehículo pierde estabilidad y vuelca', '2025-09-17 06:46:37'),
(4, 'Despiste', 'Vehículo sale de la vía', '2025-09-17 06:46:37'),
(5, 'Caída de pasajero', 'Persona cae desde un vehículo en movimiento', '2025-09-17 06:46:37'),
(6, 'Incendio', 'Vehículo se incendia tras el accidente', '2025-09-17 06:46:37'),
(7, 'Otro', 'Otra modalidad no especificada', '2025-09-17 06:46:37'),
(8, 'Caida', NULL, '2025-09-18 07:52:11'),
(9, 'Fuga', NULL, '2025-10-18 15:00:28');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `modelos_vehiculo`
--

CREATE TABLE `modelos_vehiculo` (
  `id` int NOT NULL,
  `marca_id` int NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `modelos_vehiculo`
--

INSERT INTO `modelos_vehiculo` (`id`, `marca_id`, `nombre`, `creado_en`) VALUES
(1, 1, 'Corolla', '2025-09-17 05:46:32'),
(2, 1, 'Hilux', '2025-09-17 05:46:32'),
(3, 1, 'Yaris', '2025-09-17 05:46:32'),
(4, 1, 'Fortuner', '2025-09-17 05:46:32'),
(5, 2, 'Sentra', '2025-09-17 05:46:32'),
(6, 2, 'Versa', '2025-09-17 05:46:32'),
(7, 2, 'X-Trail', '2025-09-17 05:46:32'),
(8, 2, 'Navara', '2025-09-17 05:46:32'),
(9, 3, 'Accent', '2025-09-17 05:46:32'),
(10, 3, 'Elantra', '2025-09-17 05:46:32'),
(11, 3, 'Tucson', '2025-09-17 05:46:32'),
(12, 3, 'Santa Fe', '2025-09-17 05:46:32'),
(13, 4, 'Rio', '2025-09-17 05:46:32'),
(14, 4, 'Sportage', '2025-09-17 05:46:32'),
(15, 4, 'Sorento', '2025-09-17 05:46:32'),
(16, 4, 'Picanto', '2025-09-17 05:46:32'),
(17, 6, 'Aveo', '2025-09-17 05:46:32'),
(18, 6, 'Spark', '2025-09-17 05:46:32'),
(19, 6, 'Tracker', '2025-09-17 05:46:32'),
(20, 6, 'Captiva', '2025-09-17 05:46:32'),
(21, 8, 'Gol', '2025-09-17 05:46:32'),
(22, 8, 'Jetta', '2025-09-17 05:46:32'),
(23, 8, 'Tiguan', '2025-09-17 05:46:32'),
(24, 8, 'Amarok', '2025-09-17 05:46:32'),
(25, 23, 'Autoriksha', '2025-09-18 05:48:46'),
(26, 8, 'Bolochito', '2025-09-21 06:52:21'),
(27, 23, 'Pulsar', '2025-09-21 07:02:48'),
(28, 2, 'Kick', '2025-09-25 06:38:03'),
(29, 24, 'Racer GTE 3109', '2025-09-29 04:33:23'),
(30, 9, 'ATEGO 3030/63', '2025-09-29 04:37:26'),
(31, 25, 'XTZ690 TENERE 700', '2025-10-14 23:45:01'),
(32, 26, 'Cobra 200', '2025-10-16 23:07:12'),
(33, 27, 'Max/SRP-03', '2025-10-20 04:36:16'),
(34, 28, 'ZZ3316N286ME1', '2025-10-20 04:38:40'),
(35, 6, 'SAIL', '2025-10-20 06:03:55'),
(36, 23, 'Re Autoriksha Torito', '2025-10-26 17:36:32'),
(37, 31, 'Workman', '2025-11-24 21:47:54'),
(38, 32, 'Z900 ABS', '2025-11-29 06:42:58'),
(39, 6, 'Onix Joy', '2025-11-29 06:45:26'),
(40, 33, 'Glory', '2025-11-29 06:49:14'),
(41, 2, 'AD DX', '2025-11-29 06:56:13'),
(42, 9, 'Urbanuss Mod. 1994', '2025-12-02 04:01:45'),
(43, 23, 'Re Autoriksha Torito 4T LPG R', '2025-12-10 01:35:27'),
(44, 30, 'Braho2-200', '2026-01-07 20:26:26'),
(45, 1, 'Coaster', '2026-01-11 21:02:20'),
(46, 16, 'F3', '2026-02-01 04:30:54'),
(47, 34, 'LA6X4', '2026-02-05 06:37:05'),
(48, 35, 'R-01-07-F', '2026-02-05 06:44:27'),
(49, 37, 'NEW H2', '2026-03-05 08:01:47'),
(50, 15, 'M90', '2026-03-05 08:38:12'),
(51, 1, 'Corolla DX', '2026-03-08 16:30:57'),
(52, 23, 'Pulsar 200NS', '2026-03-13 06:50:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `oficios`
--

CREATE TABLE `oficios` (
  `id` int NOT NULL,
  `accidente_id` int DEFAULT NULL,
  `involucrado_persona_id` int DEFAULT NULL,
  `involucrado_vehiculo_id` int DEFAULT NULL,
  `numero` int NOT NULL,
  `anio` year NOT NULL,
  `fecha_emision` date NOT NULL,
  `entidad_id_destino` int NOT NULL,
  `subentidad_destino_id` int DEFAULT NULL,
  `persona_destino_id` int DEFAULT NULL,
  `persona_destino_manual` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `grado_cargo_id` int DEFAULT NULL,
  `asunto_id` int NOT NULL,
  `motivo` text COLLATE utf8mb4_general_ci NOT NULL,
  `referencia_texto` varchar(300) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `oficial_ano_id` int NOT NULL,
  `estado` enum('BORRADOR','FIRMADO','ENVIADO','ANULADO') COLLATE utf8mb4_general_ci DEFAULT 'BORRADOR',
  `archivo_pdf` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `creado_por` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `oficios`
--

INSERT INTO `oficios` (`id`, `accidente_id`, `involucrado_persona_id`, `involucrado_vehiculo_id`, `numero`, `anio`, `fecha_emision`, `entidad_id_destino`, `subentidad_destino_id`, `persona_destino_id`, `persona_destino_manual`, `grado_cargo_id`, `asunto_id`, `motivo`, `referencia_texto`, `oficial_ano_id`, `estado`, `archivo_pdf`, `creado_por`, `creado_en`, `actualizado_en`) VALUES
(10, 20, 26, NULL, 883, '2025', '2025-10-16', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'ENVIADO', NULL, NULL, '2025-10-16 06:27:56', '2026-03-12 04:58:27'),
(11, 19, 23, NULL, 888, '2025', '2025-10-18', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'BORRADOR', NULL, NULL, '2025-10-18 05:26:05', '2025-10-18 05:26:05'),
(12, 23, NULL, NULL, 839, '2025', '2025-10-19', 1, NULL, 3, NULL, 5, 1, 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', '', 1, 'FIRMADO', NULL, NULL, '2025-10-20 04:54:49', '2025-10-20 05:16:30'),
(13, 23, NULL, NULL, 840, '2025', '2025-09-26', 1, NULL, 3, NULL, 5, 1, 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', '', 1, 'FIRMADO', NULL, NULL, '2025-10-20 04:55:46', '2025-10-20 05:16:26'),
(14, 23, NULL, 21, 896, '2025', '2025-11-24', 1, NULL, 3, NULL, 5, 1, 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', '', 1, 'ENVIADO', NULL, NULL, '2025-10-20 04:58:19', '2025-11-24 14:22:42'),
(15, 23, 31, NULL, 897, '2025', '2025-10-20', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'ENVIADO', NULL, NULL, '2025-10-20 05:27:26', '2025-10-20 05:40:55'),
(16, 24, 32, NULL, 898, '2025', '2025-10-20', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'ANULADO', NULL, NULL, '2025-10-20 06:14:24', '2025-10-20 06:26:27'),
(17, 22, 29, NULL, 899, '2025', '2025-10-20', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'ENVIADO', NULL, NULL, '2025-10-20 06:29:07', '2025-10-22 22:43:31'),
(19, 22, NULL, NULL, 916, '2025', '2025-10-22', 3, NULL, NULL, NULL, 6, 10, 'Solicitar grabación de cámaras de videovigilancia, conforme se detalla.', '', 1, 'FIRMADO', NULL, NULL, '2025-10-22 17:24:05', '2025-10-22 22:42:39'),
(20, 26, 37, NULL, 924, '2025', '2025-10-26', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'BORRADOR', NULL, NULL, '2025-10-27 03:15:14', '2025-10-27 03:15:14'),
(21, 20, NULL, 16, 936, '2025', '2025-10-31', 1, NULL, 3, NULL, 5, 1, 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', '', 1, 'ENVIADO', NULL, NULL, '2025-10-31 15:44:26', '2026-04-05 01:52:16'),
(22, 29, 42, NULL, 938, '2025', '2025-10-31', 2, NULL, 2, NULL, 5, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'FIRMADO', NULL, NULL, '2025-11-01 03:38:02', '2025-11-01 03:44:03'),
(23, 30, 45, NULL, 1001, '2025', '2025-11-25', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'BORRADOR', NULL, NULL, '2025-11-25 06:10:36', '2025-11-25 06:10:36'),
(24, 31, NULL, NULL, 1019, '2025', '2025-11-29', 4, NULL, 4, NULL, 7, 11, 'Diligencias realizadas en torno a la investigación por accidente de tránsito, conforme se detalla.', 'Oficio N° 737-2025-COMOPPOL-DIRNOS-PNP/DIRTTSV-DIVPIAT-UIAT-NORTE', 1, 'BORRADOR', NULL, NULL, '2025-11-29 07:16:54', '2025-11-29 07:16:54'),
(25, 32, NULL, 36, 1030, '2025', '2025-12-02', 1, NULL, 3, NULL, 5, 1, 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', '', 1, 'BORRADOR', NULL, NULL, '2025-12-02 16:09:56', '2025-12-02 16:09:56'),
(26, 24, NULL, NULL, 1035, '2025', '2025-12-04', 5, NULL, 5, NULL, 7, 13, 'Informe de resultado de investigacion, conforme se detalla.', '', 1, 'ENVIADO', NULL, NULL, '2025-12-04 20:24:10', '2025-12-05 04:58:45'),
(27, 33, NULL, NULL, 1050, '2025', '2025-12-10', 6, NULL, NULL, NULL, 8, 14, 'Certificado de resultado cuantitativo de examen de dosaje etílico', '', 1, 'BORRADOR', NULL, NULL, '2025-12-10 16:52:44', '2025-12-10 16:52:44'),
(28, 34, 59, NULL, 1053, '2025', '2025-12-10', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', 'Informe Pericial Nro. 2024010101004093', 1, 'BORRADOR', NULL, NULL, '2025-12-11 03:51:23', '2025-12-11 03:51:23'),
(29, 22, 29, NULL, 1063, '2025', '2025-12-15', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', 'Informe Pericial de Necropsia Médico Legal N° 002981-2025', 1, 'ENVIADO', NULL, NULL, '2025-12-15 06:10:21', '2025-12-15 06:31:15'),
(31, 22, NULL, NULL, 1064, '2025', '2025-12-15', 7, NULL, NULL, NULL, 6, 16, 'Copia de “Vídeo Fílmico”, por motivo que se indica', 'SIDPOL Nro. 33512124', 1, 'BORRADOR', NULL, NULL, '2025-12-15 06:41:27', '2025-12-15 06:41:27'),
(32, 18, NULL, NULL, 1065, '2025', '2025-12-15', 8, NULL, NULL, NULL, 7, 18, 'Diligencias realizadas en torno a la investigación por accidente de tránsito, conforme se detalla', 'Carpeta fiscal Nro. 606015901-2025-4922-0', 1, 'BORRADOR', NULL, NULL, '2025-12-15 07:42:28', '2025-12-15 07:42:28'),
(33, 34, NULL, NULL, 1099, '2025', '2025-12-29', 5, NULL, 6, NULL, 7, 13, 'Informe de resultado de investigacion, conforme se detalla.', '', 1, 'ENVIADO', NULL, NULL, '2025-12-29 13:49:32', '2025-12-29 23:26:15'),
(34, 25, NULL, NULL, 1100, '2025', '2025-12-31', 9, NULL, 7, NULL, 7, 19, 'Remite informe policial con resultados de investigación', 'Caso N° 4006034501-2025-466-0', 1, 'BORRADOR', NULL, NULL, '2025-12-31 08:58:42', '2025-12-31 08:58:42'),
(35, 35, NULL, 38, 26, '2026', '2026-01-12', 1, NULL, 3, NULL, 5, 1, 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', '', 1, 'BORRADOR', NULL, NULL, '2026-01-12 05:22:28', '2026-01-12 05:22:28'),
(36, 35, NULL, 38, 27, '2025', '2026-01-12', 1, NULL, 3, NULL, 5, 1, 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', '', 1, 'BORRADOR', NULL, NULL, '2026-01-12 06:08:22', '2026-01-12 06:08:22'),
(37, 35, NULL, NULL, 27, '2026', '2026-01-12', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 1, 'BORRADOR', NULL, NULL, '2026-01-12 06:09:06', '2026-01-12 06:09:06'),
(38, 29, NULL, NULL, 58, '2026', '2026-01-20', 9, NULL, 8, NULL, 7, 19, 'Remite informe policial con resultados de investigación', 'SIDPOL N° 32464625', 1, 'BORRADOR', NULL, NULL, '2026-01-20 13:41:07', '2026-01-20 13:41:07'),
(40, 36, NULL, NULL, 88, '2026', '2026-02-03', 10, NULL, NULL, NULL, NULL, 23, 'Solicitar copia de la grabación de camaras de video vigilancia', 'SIDPOL N° 34373525', 1, 'BORRADOR', NULL, NULL, '2026-02-03 15:37:52', '2026-02-03 15:37:52'),
(41, 22, NULL, NULL, 125, '2026', '2026-02-13', 11, NULL, 9, NULL, 7, 24, 'Remite Informe Policia de resultado de investigación, conforme se detalla', 'Carpeta Fiscal N° 4006034501-2025-593-0', 5, 'ENVIADO', NULL, NULL, '2026-02-13 09:54:21', '2026-02-13 09:58:29'),
(42, 22, NULL, NULL, 126, '2026', '2026-02-19', 12, NULL, NULL, NULL, 10, 26, 'Actuados realizados de investigación policial, conforme se detalla.', 'SIDPOL Nro. 33512124', 5, 'BORRADOR', NULL, NULL, '2026-02-19 16:24:44', '2026-02-19 16:24:44'),
(43, 36, 66, NULL, 187, '2026', '2026-03-03', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', 'Informe Pericial N° 2026010101000343', 5, 'BORRADOR', NULL, NULL, '2026-03-04 04:58:21', '2026-03-04 04:58:21'),
(44, 36, 65, NULL, 188, '2026', '2026-03-04', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 5, 'BORRADOR', NULL, NULL, '2026-03-04 05:05:29', '2026-03-04 05:05:29'),
(45, 36, 64, NULL, 189, '2026', '2026-03-04', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 5, 'BORRADOR', NULL, NULL, '2026-03-04 05:16:52', '2026-03-04 05:16:52'),
(46, 36, 63, NULL, 190, '2026', '2026-03-04', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 5, 'BORRADOR', NULL, NULL, '2026-03-04 05:20:05', '2026-03-04 05:20:05'),
(47, 38, NULL, NULL, 205, '2026', '2026-03-08', 13, NULL, NULL, NULL, 11, 27, 'Grabación de las camaras de video vigilancia, conforme se detalla.', '', 5, 'BORRADOR', NULL, NULL, '2026-03-08 17:18:55', '2026-03-08 17:18:55'),
(48, 38, NULL, NULL, 206, '2026', '2026-03-08', 14, NULL, NULL, NULL, 11, 28, 'Camaras de video vigilancia aledañas al lugar del accidente de tránsito', '', 5, 'ENVIADO', NULL, NULL, '2026-03-08 17:38:43', '2026-03-12 04:58:38'),
(49, 32, 54, NULL, 213, '2026', '2026-03-09', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 5, 'BORRADOR', NULL, NULL, '2026-03-09 06:59:28', '2026-03-09 06:59:28'),
(50, 38, NULL, 42, 204, '2026', '2026-03-09', 1, NULL, 3, NULL, 5, 1, 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', '', 5, 'BORRADOR', NULL, NULL, '2026-03-10 04:33:17', '2026-03-10 04:33:17'),
(51, 20, 26, NULL, 229, '2026', '2026-03-11', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 5, 'ENVIADO', NULL, NULL, '2026-03-12 04:46:18', '2026-03-12 04:58:19'),
(52, 39, NULL, NULL, 450, '2025', '2025-06-23', 3, NULL, NULL, NULL, 6, 10, 'Solicitar grabación de cámaras de videovigilancia, conforme se detalla.', '', 1, 'BORRADOR', NULL, NULL, '2026-03-12 06:48:33', '2026-03-12 06:48:33'),
(53, 26, 37, NULL, 235, '2026', '2026-03-12', 2, NULL, NULL, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 5, 'BORRADOR', NULL, NULL, '2026-03-12 23:57:01', '2026-03-12 23:57:01'),
(54, 40, 74, NULL, 243, '2026', '2026-03-16', 2, NULL, 2, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', '', 5, 'BORRADOR', NULL, NULL, '2026-03-16 15:41:19', '2026-03-16 15:41:19'),
(55, 40, NULL, NULL, 247, '2026', '2026-03-17', 15, NULL, NULL, NULL, 12, 29, 'Solicita informe médico de persona, por motivo que se indica', '', 5, 'ENVIADO', NULL, NULL, '2026-03-17 05:16:17', '2026-03-17 05:18:31'),
(56, 40, NULL, NULL, 248, '2026', '2026-03-17', 16, NULL, NULL, NULL, 12, 30, 'Solicitar informe medico de persona, por motivos que se indica', '', 5, 'ENVIADO', NULL, NULL, '2026-03-17 05:18:14', '2026-03-17 05:18:27'),
(57, 20, 26, NULL, 89, '2026', '2026-02-03', 2, NULL, 10, NULL, 4, 8, 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', NULL, 5, 'ENVIADO', NULL, NULL, '2026-04-05 01:52:06', '2026-04-05 01:52:10'),
(58, 20, NULL, NULL, 315, '2026', '2026-04-06', 4, NULL, 11, NULL, 7, 31, 'Resultado de investigación, conforme se detalla.', NULL, 5, 'BORRADOR', NULL, NULL, '2026-04-06 13:04:30', '2026-04-06 13:04:30');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `oficio_asunto`
--

CREATE TABLE `oficio_asunto` (
  `id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `tipo` enum('SOLICITAR','REMITIR') COLLATE utf8mb4_general_ci NOT NULL,
  `nombre` varchar(160) COLLATE utf8mb4_general_ci NOT NULL,
  `detalle` text COLLATE utf8mb4_general_ci,
  `orden` int DEFAULT '0',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `oficio_asunto`
--

INSERT INTO `oficio_asunto` (`id`, `entidad_id`, `tipo`, `nombre`, `detalle`, `orden`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'SOLICITAR', 'Peritaje de Constatación de daños', 'Solicita se realice peritaje de constatacion de daños en vehiculo, por motivo que se indica.', 0, 1, '2025-10-10 00:47:35', '2025-10-10 00:47:35'),
(8, 2, 'SOLICITAR', 'Protocolo de Necropsia', 'Solicita protocolo de necropsia integral y fluidos; por motivo que se indica', 0, 1, '2025-10-11 17:16:39', '2025-10-11 17:16:39'),
(9, 3, 'SOLICITAR', 'Copia de grabaciones de cámaras de video vigilancia', '', 0, 1, '2025-10-22 14:36:30', '2025-10-22 14:36:30'),
(10, 3, 'SOLICITAR', 'Camaras de video vigilancia', 'Solicitar grabación de cámaras de videovigilancia, conforme se detalla.', 0, 1, '2025-10-22 17:23:38', '2025-10-22 17:23:38'),
(11, 4, 'REMITIR', 'Diligencias realizadas en torno a la investigación por accidente de tránsito, conforme se detalla.', '', 0, 1, '2025-11-29 07:14:32', '2025-11-29 07:14:32'),
(12, 4, 'REMITIR', 'Remitir diligencias', 'Diligencias realizadas en torno a la investigación por accidente de tránsito, conforme se detalla', 0, 1, '2025-11-29 07:24:30', '2025-11-29 07:24:30'),
(13, 5, 'REMITIR', 'Remitir diligencia', 'Informe de resultado de investigacion, conforme se detalla.', 0, 1, '2025-12-04 20:24:01', '2025-12-04 20:24:01'),
(14, 6, 'SOLICITAR', 'Resultado dosaje etílico', 'Certificado de resultado cuantitativo de examen de dosaje etílico', 0, 1, '2025-12-10 15:06:04', '2025-12-10 15:06:04'),
(15, 7, 'SOLICITAR', 'Camaras de video', 'Copia de “Vídeo Fílmico”, por motivo que se indica', 0, 1, '2025-12-15 06:38:11', '2025-12-15 06:38:11'),
(16, 7, 'SOLICITAR', 'Camaras de video vigilancia', 'Copia de “Vídeo Fílmico”, por motivo que se indica', 0, 1, '2025-12-15 06:40:59', '2025-12-15 06:40:59'),
(17, 8, 'SOLICITAR', 'Remitir diligencia', 'Diligencias realizadas en torno a la investigación por accidente de tránsito, conforme se detalla', 0, 1, '2025-12-15 07:40:57', '2025-12-15 07:40:57'),
(18, 8, 'REMITIR', 'Remitir diligencias', 'Diligencias realizadas en torno a la investigación por accidente de tránsito, conforme se detalla', 0, 1, '2025-12-15 07:41:26', '2025-12-15 07:41:26'),
(19, 9, 'REMITIR', 'Remitir diligencia', 'Remite informe policial con resultados de investigación', 0, 1, '2025-12-31 08:57:59', '2025-12-31 08:57:59'),
(23, 10, 'SOLICITAR', 'Camaras de video vigilancia', 'Solicitar copia de la grabación de camaras de video vigilancia', 0, 1, '2026-02-03 15:37:34', '2026-02-03 15:37:34'),
(24, 11, 'REMITIR', 'Remitir diligencia', 'Remite Informe Policia de resultado de investigación, conforme se detalla', 0, 1, '2026-02-13 09:53:30', '2026-02-13 09:53:30'),
(25, 12, 'SOLICITAR', 'Remitir DIligencia', 'Actuados de diligencias de investigación, conforme se detalla.', 0, 1, '2026-02-19 16:23:03', '2026-02-19 16:23:03'),
(26, 12, 'REMITIR', 'Remitir Diligencias', 'Actuados realizados de investigación policial, conforme se detalla.', 0, 1, '2026-02-19 16:24:16', '2026-02-19 16:24:16'),
(27, 13, 'SOLICITAR', 'Camaras de video vigilancia', 'Grabación de las camaras de video vigilancia, conforme se detalla.', 0, 1, '2026-03-08 17:18:27', '2026-03-08 17:18:27'),
(28, 14, 'SOLICITAR', 'Camaras de video vigilancia', '', 0, 1, '2026-03-08 17:37:58', '2026-03-08 17:37:58'),
(29, 15, 'SOLICITAR', 'Informe Médico', 'Solicita informe médico de persona, por motivo que se indica', 0, 1, '2026-03-17 05:16:08', '2026-03-17 05:16:08'),
(30, 16, 'SOLICITAR', 'Informe Médico', 'Solicitar informe medico de persona, por motivos que se indica', 0, 1, '2026-03-17 05:18:05', '2026-03-17 05:18:05'),
(31, 4, 'REMITIR', 'Resultado de investigación', NULL, 0, 1, '2026-04-06 13:03:13', '2026-04-06 13:03:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `oficio_entidad`
--

CREATE TABLE `oficio_entidad` (
  `id` int NOT NULL,
  `tipo` enum('PUBLICA','PRIVADA','PERSONA_NATURAL','OTRA') COLLATE utf8mb4_general_ci DEFAULT 'PUBLICA',
  `categoria` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `siglas` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono_fijo` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono_movil` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pagina_web` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `oficio_entidad`
--

INSERT INTO `oficio_entidad` (`id`, `tipo`, `categoria`, `nombre`, `siglas`, `direccion`, `telefono`, `telefono_fijo`, `telefono_movil`, `correo`, `pagina_web`, `creado_en`, `actualizado_en`) VALUES
(1, 'PUBLICA', NULL, 'Unidad de Peritajes PNP', 'UPER PNP', 'Calle Francisco Bolognesi S/N - Santa Anita', '', NULL, NULL, '', '', '2025-10-10 00:45:35', '2025-10-10 00:45:35'),
(2, 'PRIVADA', NULL, 'Unidad de Tanatología Forense', 'UTANFOR', 'Av. Cangallo', '', NULL, NULL, '', '', '2025-10-10 04:50:48', '2025-10-10 04:50:48'),
(3, 'PUBLICA', NULL, 'Concesionaria vial Rutas de Lima', 'Rutas de Lima', 'Peaje de Chorrillos', '', NULL, NULL, '', 'https://recepcionvirtual.rutasdelima.pe/', '2025-10-22 14:34:21', '2025-10-22 17:49:22'),
(4, 'PUBLICA', NULL, 'Físcalia Corporativa de Tránsito y Seguridad Vial-Primer Despacho-Lima Norte', 'FCTYSV-1°D-LN', '', '', NULL, NULL, '', '', '2025-11-29 07:11:13', '2025-11-29 07:11:13'),
(5, 'PUBLICA', NULL, 'Fiscalía Corporativa de Tránsito y Seguridad Vial-Cuarto Despacho-Lima Norte', 'FCTYSV-4D-LN', '', '', NULL, NULL, '', '', '2025-12-04 20:17:57', '2025-12-04 20:17:57'),
(6, 'PUBLICA', 'COMISARIA', 'Comisaria PNP Carabayllo', 'COM PNP CARABAYLLO', NULL, NULL, NULL, NULL, NULL, NULL, '2025-12-10 14:23:48', '2026-04-24 16:08:33'),
(7, 'PUBLICA', NULL, 'Concesionario Vial de NORVIAL', '', '', '', NULL, NULL, '', '', '2025-12-15 06:36:34', '2025-12-15 06:36:34'),
(8, 'PUBLICA', NULL, 'Tercera Fiscalía Provincial Penal Corporativa de Santa Rosa-Primer Despacho-Distrito Fiscal de Lima Noroeste', 'FPPC-SR-3F-1D-NO', '', '', NULL, NULL, '', '', '2025-12-15 07:36:15', '2025-12-15 07:36:15'),
(9, 'PUBLICA', NULL, 'Primera Fiscalía Penal Corporativa de Santa Rosa-Segundo Despacho-Distrito Fiscal de Lima Noroeste', '', '', '', NULL, NULL, '', '', '2025-12-31 08:55:49', '2025-12-31 08:55:49'),
(10, 'PRIVADA', NULL, 'PROPIETARIO INMUEBLE', '', '', '', NULL, NULL, '', '', '2026-02-03 15:17:40', '2026-02-03 15:17:40'),
(11, 'PUBLICA', NULL, 'Primera Fiscalía Provincial Penal Corporativa de Santa Rosa-Tercer Despacho-Distrito Fiscal de Lima Noroeste', '', '', '', NULL, NULL, '', '', '2026-02-13 09:52:05', '2026-02-13 09:52:05'),
(12, 'PUBLICA', NULL, 'JEFATURA DEL SERVICIO DE POLICIA NAVAL', '', '', '', NULL, NULL, '', '', '2026-02-19 16:19:05', '2026-02-19 16:19:05'),
(13, 'PUBLICA', NULL, 'EMPRESA PRIVADA', '', '', '', NULL, NULL, '', '', '2026-03-08 17:14:49', '2026-03-08 17:14:49'),
(14, 'PUBLICA', NULL, 'Municipalidad de Carabayllo', '', '', '', NULL, NULL, '', '', '2026-03-08 17:36:59', '2026-03-08 17:36:59'),
(15, 'PUBLICA', NULL, 'Hospital \"Alberto Sabogal Sologuren\"', '', '', '', NULL, NULL, '', '', '2026-03-17 05:15:07', '2026-03-17 05:15:07'),
(16, 'PUBLICA', NULL, 'Hospital Nacional \"Edgardo Rebagliati Martins\"', '', '', '', NULL, NULL, '', '', '2026-03-17 05:17:20', '2026-03-17 05:17:20'),
(17, 'PUBLICA', 'MUNICIPALIDAD', 'Municipalidad de Comas', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-20 00:01:18', '2026-04-20 00:01:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `oficio_oficial_ano`
--

CREATE TABLE `oficio_oficial_ano` (
  `id` int NOT NULL,
  `anio` year NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `decreto` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `vigente` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `oficio_oficial_ano`
--

INSERT INTO `oficio_oficial_ano` (`id`, `anio`, `nombre`, `decreto`, `vigente`, `creado_en`, `actualizado_en`) VALUES
(1, '2025', '\"Año de la recuperación y consolidación de la economía peruana\"', '', 0, '2025-10-10 00:20:53', '2025-10-10 00:29:36'),
(4, '2024', '\"Año del Bicentenario, de la consolidación de nuestra Independencia, y de la conmemoración de las heroicas batallas de Junín y Ayacucho\"', '', 0, '2025-10-10 00:32:41', '2025-10-10 00:32:41'),
(5, '2026', 'Año de la Esperanza y el Fortalecimiento de la Democracia', '', 1, '2026-02-03 16:34:24', '2026-02-03 16:34:24');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `oficio_persona_entidad`
--

CREATE TABLE `oficio_persona_entidad` (
  `id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `nombres` varchar(120) COLLATE utf8mb4_general_ci NOT NULL,
  `apellido_paterno` varchar(80) COLLATE utf8mb4_general_ci NOT NULL,
  `apellido_materno` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pagina_web` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `observacion` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `oficio_persona_entidad`
--

INSERT INTO `oficio_persona_entidad` (`id`, `entidad_id`, `nombres`, `apellido_paterno`, `apellido_materno`, `telefono`, `direccion`, `pagina_web`, `correo`, `observacion`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 1, 'Carlos', 'VELASQUEZ', 'QUESQUEN', '', '', '', '', '', 1, '2025-10-10 00:49:24', '2025-10-10 00:49:24'),
(2, 2, 'Félix', 'BRICEÑO', 'ITURRI', '', '', '', '', '', 1, '2025-10-10 04:56:35', '2025-10-10 04:56:35'),
(3, 1, 'Daniel Alfredo', 'VELASQUEZ', 'SANCHEZ', '', '', '', '', '', 1, '2025-10-16 06:12:08', '2025-10-16 06:12:08'),
(4, 4, 'Jonatan Vicente', 'GONZALES', 'WONG', '', '', '', '', '', 1, '2025-11-29 07:12:30', '2025-11-29 07:12:30'),
(5, 5, 'Jorge Luís', 'RODRIGUEZ', 'LOARTE', '', '', '', '', '', 1, '2025-12-04 20:20:17', '2025-12-04 20:20:17'),
(6, 5, 'Jhosselinne', 'YOVERA', 'CERNA', '', '', '', '', '', 1, '2025-12-29 13:49:17', '2025-12-29 13:49:17'),
(7, 9, 'María Esperanza', 'POLO', 'ZAPATA', '', '', '', '', '', 1, '2025-12-31 08:57:06', '2025-12-31 08:57:06'),
(8, 9, 'Edwin G.', 'TOLENTINO', 'GABANCHO', '', '', '', '', '', 1, '2026-01-20 13:39:10', '2026-01-20 13:39:10'),
(9, 11, 'Rosalia', 'TORRES', 'CALENI', '', '', '', '', '', 1, '2026-02-13 09:52:40', '2026-02-13 09:52:40'),
(10, 2, 'Felix Antonio', 'BRISEÑO', 'ITURRI', NULL, NULL, NULL, NULL, NULL, 1, '2026-04-05 01:51:53', '2026-04-05 01:51:53'),
(11, 4, 'Flavio Nazario', 'SEGOVIA', 'QUISPE', NULL, NULL, NULL, NULL, NULL, 1, '2026-04-06 13:02:31', '2026-04-06 13:02:31'),
(12, 17, 'SEGURIDAD', 'CIUDADANA', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-04-20 00:05:15', '2026-04-20 00:05:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `oficio_subentidad`
--

CREATE TABLE `oficio_subentidad` (
  `id` int NOT NULL,
  `entidad_id` int NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `siglas` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tipo` enum('SEDE','GERENCIA','DIRECCION','OFICINA','UNIDAD','DEPARTAMENTO','AREA','OTRA') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'OFICINA',
  `codigo` varchar(60) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `pagina_web` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `direccion` varchar(250) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correo` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `participacion_persona`
--

CREATE TABLE `participacion_persona` (
  `Id` tinyint UNSIGNED NOT NULL,
  `Nombre` varchar(40) COLLATE utf8mb4_general_ci NOT NULL,
  `RequiereVehiculo` tinyint(1) NOT NULL DEFAULT '0',
  `Orden` smallint UNSIGNED DEFAULT '0',
  `Activo` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `participacion_persona`
--

INSERT INTO `participacion_persona` (`Id`, `Nombre`, `RequiereVehiculo`, `Orden`, `Activo`) VALUES
(1, 'Conductor', 1, 1, 1),
(2, 'Peatón', 0, 2, 1),
(3, 'Pasajero', 1, 3, 1),
(4, 'Ocupante', 1, 4, 1),
(5, 'Testigo presencial', 0, 5, 1),
(6, 'Testigo referencial', 0, 6, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `personas`
--

CREATE TABLE `personas` (
  `id` int NOT NULL,
  `tipo_doc` enum('DNI','CE','PAS','OTRO') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DNI',
  `num_doc` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_paterno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido_materno` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sexo` enum('M','F') COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `edad` tinyint UNSIGNED DEFAULT NULL,
  `estado_civil` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nacionalidad` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT 'PERUANA',
  `departamento_nac` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provincia_nac` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `distrito_nac` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domicilio` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ocupacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grado_instruccion` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_padre` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_madre` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `celular` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `foto_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_fuente` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_ref` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domicilio_departamento` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domicilio_provincia` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domicilio_distrito` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `personas`
--

INSERT INTO `personas` (`id`, `tipo_doc`, `num_doc`, `apellido_paterno`, `apellido_materno`, `nombres`, `sexo`, `fecha_nacimiento`, `edad`, `estado_civil`, `nacionalidad`, `departamento_nac`, `provincia_nac`, `distrito_nac`, `domicilio`, `ocupacion`, `grado_instruccion`, `nombre_padre`, `nombre_madre`, `celular`, `email`, `notas`, `creado_en`, `foto_path`, `api_fuente`, `api_ref`, `domicilio_departamento`, `domicilio_provincia`, `domicilio_distrito`) VALUES
(15, 'DNI', '45201865', 'ROJAS', 'CASTRO', 'Leonardo Joaquin', 'M', '1985-07-04', 39, 'Soltero', 'PERUANA', 'Pasco', 'Oxapampa', 'Villa Rica', 'Jr. San Carlos 00-Oxapampa-Villa Rica -Pasco', 'Chofer', 'Secundaria completa', 'Joaquín', 'María', '948713289', 'milex_leo@icloud.com', NULL, '2025-09-29 04:53:50', NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'DNI', '25576045', 'HERNANDEZ', 'COBBA', 'Carlos Alberto', 'M', '1968-04-18', 57, 'Casado', 'PERUANA', 'Lambayeque', 'Chiclayo', 'Chiclayo', 'Mz J6 lote 15 Sector Villa Estela-Ancón', 'Independiente', 'Secundaria completa', 'Segundo Lucas', 'Martha Timotea', NULL, NULL, NULL, '2025-09-29 04:59:46', NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'DNI', '45019102', 'BERROCAL', 'PALACIOS', 'Sergio', 'M', '1985-12-13', 39, 'Casado', 'PERUANA', 'Lima', 'Lima', 'Lima', 'Mz X lote 5 AA.HH Los Cedros II-Callao Ventanilla', NULL, 'Secundaria completa', 'Javier Rafael', 'Flor de María', '986571975', NULL, NULL, '2025-09-29 06:02:23', NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'DNI', '09047324', 'MANRIQUE', 'WONG DE SALAZAR', 'Luz Maria', 'F', '1964-02-29', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'CA. ENRIQUE FERMI 374 MZ. P LT. 36 URB. FIORI 1RA ET.', NULL, 'SECUNDARIA COMPLETA', 'SANTOS', 'FELICITA', NULL, NULL, NULL, '2025-10-09 20:57:47', NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'DNI', '45867229', 'MERINO', 'SANCHO', 'Giancarlo Jorge', 'M', '1989-07-13', 35, NULL, 'PERUANA', NULL, NULL, NULL, 'AV. LOS PRECURSORES P.J.SAN CAMILO MZ. N LT. 1', NULL, 'SECUNDARIA COMPLETA', 'CARLOS JORGE', 'MARIA ISABEL', '986571975', '31486778@policia.gob.pe', NULL, '2025-10-09 21:38:11', 'uploads/reniec/45867229.jpg', NULL, NULL, 'LIMA', 'LIMA', 'INDEPENDENCIA'),
(22, 'DNI', '07599624', 'CANTO', 'CABRERA', 'Carmen Rosa', 'F', '1946-08-17', 76, 'Casado', 'PERUANA', 'Lima', 'Lima', 'Comas', 'CALLE GRAL JACINTO LARA 238 URB. HUAQUILLAY ETAPA 2', 'su casa', 'Secundaria completa', 'JOSE', 'MARIA', '958832555', 'rretetec@gmail.com', NULL, '2025-10-11 06:09:33', NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'DNI', '45262864', 'SALAZAR', 'MANRIQUE', 'Shirley Ines', 'F', '1988-08-10', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'CALLE ENRIQUE FERMI 374 URB. FIORI ETAPA 1RA.', NULL, 'SECUNDARIA COMPLETA', 'LUIS MIGUEL', 'LUZ MARIA', NULL, NULL, NULL, '2025-10-11 06:10:43', 'uploads/reniec/45262864.jpg', NULL, NULL, 'LIMA', 'LIMA', 'SAN MARTIN DE PORRES'),
(24, 'DNI', '06874413', 'MANDA', 'CORIMANYA', 'Francisco', 'M', '1942-10-04', 80, 'Viudo', 'PERUANA', 'Lima', 'Lima', 'Comas', 'JR FRANCISCO DE ZELA 266 URB SAN AGUSTIN KM 14', 'chofer', 'Superior completa', 'MARIANO', 'AIDA', '995196153', '', NULL, '2025-10-11 06:11:20', 'uploads/reniec/06874413.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(25, 'DNI', '08011916', 'CANCINO', 'VILLANUEVA', 'Clemente', 'M', '1941-05-15', 82, NULL, 'PERUANA', NULL, NULL, NULL, 'CALLE GRAL JACINTO LARA 215 URB HUAQUILLAY ETAPA 2', NULL, 'SECUNDARIA COMPLETA', 'LEONCIO', 'MARIA', NULL, NULL, NULL, '2025-10-11 06:12:00', 'uploads/reniec/08011916.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(26, 'DNI', '10186473', 'COBBA', 'TERRONES', 'Dora Genoveva', 'F', '1954-10-14', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'ASENT.H.VILLA ESTELA MZ.J-6 LT.18', NULL, 'SECUNDARIA COMPLETA', 'EVEREGISTO', 'JOVITA', '933383650', '', NULL, '2025-10-11 06:22:25', 'uploads/reniec/10186473.jpg', NULL, NULL, 'LIMA', 'LIMA', 'ANCON'),
(27, 'DNI', '08036209', 'BERROCAL', 'PAREDES', 'Alejandro', 'M', '1950-03-10', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'BALCON DEL RIMAC MZ.F LT.15', NULL, 'PRIMARIA COMPLETA', 'SATURNINO', 'GRACIELA', '922023190', '', NULL, '2025-10-11 06:47:14', 'uploads/reniec/08036209.jpg', NULL, NULL, 'LIMA', 'LIMA', 'RIMAC'),
(28, 'DNI', '22474709', 'CANCINO', 'SANCHEZ', 'Rolando', 'M', '1967-01-10', NULL, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Comas', 'JR. GRAL JACINTO LARA 215 URB HUAQUILLAY 2DA ET.', 'docente', 'SUPERIOR COMPLETA', 'CLEMENTE', 'EDITH', '912424787', 'cancinosonco@gmail.com', NULL, '2025-10-12 08:51:03', 'uploads/reniec/22474709.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(29, 'DNI', '75393977', 'FLORES', 'HUINCHA', 'Diego Alberto', 'M', '1998-03-28', 28, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Miraflores', 'ASOC. DE PROPIETARIOS LAS MERCEDES MZ. C LT. 17', 'Independiente', 'SECUNDARIA-1ER AÑO', 'FLORES CIRIACO ANGEL', 'HUINCHA LEON  JULIA IRMA', '944422210', 'diego122898@gmail.com', NULL, '2025-10-15 17:13:57', 'uploads/reniec/75393977.jpg', NULL, NULL, 'LIMA', 'LIMA', 'SAN MARTIN DE PORRES'),
(30, 'DNI', '09040053', 'LA ROSA', 'MEDINA', 'Arturo Patrocinio', 'M', '1948-11-14', 77, NULL, 'PERUANA', 'Lima', 'Lima', 'Lima', 'LAS NUECES 197 EL ERMITAÑO', NULL, 'SECUNDARIA COMPLETA', 'Pedro', 'Josefina', NULL, NULL, NULL, '2025-10-15 17:15:24', 'uploads/reniec/09040053.jpg', NULL, NULL, 'LIMA', 'LIMA', 'INDEPENDENCIA'),
(31, 'DNI', '75932112', 'MORALES', 'PASCO', 'Alexandra Isabella', 'F', '1996-07-18', 29, NULL, 'PERUANA', 'Lima', 'Lima', 'Lima', 'JR. TURIN 248 URB. FIORI', NULL, 'SECUNDARIA COMPLETA', 'MORALES REZZA JESUS', 'PASCO FALCON  ADA GLADIS', NULL, NULL, NULL, '2025-10-15 17:16:34', 'uploads/reniec/75932112.jpg', NULL, NULL, 'LIMA', 'LIMA', 'SAN MARTIN DE PORRES'),
(32, 'DNI', '10681315', 'LA ROSA', 'VASQUEZ', 'Shella Johanna', 'F', '1977-04-07', 48, 'Casado', 'PERUANA', 'Lima', 'Lima', 'San Martín de Porres', 'CALLE LOS NOGALES ASENT.H. JAZMINES DEL NARANJAL MZ. S1 LT. 41', NULL, 'SECUNDARIA COMPLETA', 'ARTURO', 'LUCIA', '976040301', 'notiene@gmail.com', NULL, '2025-10-15 17:49:06', 'uploads/reniec/10681315.jpg', NULL, NULL, 'LIMA', 'LIMA', 'SAN MARTIN DE PORRES'),
(33, 'DNI', '46830105', 'COLAN', 'RIVAROLA', 'Victor Alfonso', 'M', '1991-03-05', 34, 'Soltero', 'PERUANA', 'Callao', 'Callao', 'Callao', 'Av. Argentina 6140 Dpto.16', NULL, 'Secundaria completa', 'Victor Manuel', 'Yvonne', NULL, NULL, NULL, '2025-10-17 15:56:09', NULL, NULL, NULL, 'Callao', 'Callao', 'Callao'),
(34, 'DNI', '47156997', 'ROJAS', 'PAZ', 'Freddy Jherson', 'M', '1991-05-29', 34, 'Casado', 'PERUANA', 'Lima', 'Lima', 'Miraflores', 'PSJ. S/N ASENT.H. ANGAMOS ETAPA 3 MZ. P15 LT. 25', 'OM1 MGP', 'SECUNDARIA COMPLETA', 'FRIDOLINO JUSTINIANO', 'JUDITH KARINA', NULL, NULL, NULL, '2025-10-18 15:10:00', 'uploads/reniec/47156997.jpg', NULL, NULL, 'LIMA', 'CALLAO', 'VENTANILLA'),
(35, 'DNI', '42984799', 'ARANGO', 'GODOY', 'Silvia', 'F', '1984-06-13', NULL, 'Casado', 'PERUANA', 'Ayacucho', 'Huamanga', 'Vinchos', 'URB. SAN JUAN MASIAS SECTOR I MZ.K1 LT.52', 'Técnico de la FAP', 'Técnico Superior completa', 'SEGUNDO', 'SABINA', '947504402', 'ajheje04@gmail.com', NULL, '2025-10-18 15:12:47', 'uploads/reniec/42984799.jpg', NULL, NULL, 'AYACUCHO', 'CALLAO', 'CALLAO'),
(36, 'DNI', '47072942', 'GUTIERREZ', 'RUA', 'Edgar Mauricio', 'M', '1991-06-01', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'ASOC. LAS PRADERAS DE LA MOLINA MZ. A  LT. 3', NULL, 'SECUNDARIA COMPLETA', 'EDGAR WILIAM', 'CARMEN', '', '', NULL, '2025-10-19 07:47:25', 'uploads/reniec/47072942.jpg', NULL, NULL, 'LIMA', 'LIMA', 'LA MOLINA'),
(37, 'DNI', '45782193', 'ROJAS', 'VELI', 'Yoel Eduardo', 'M', '1988-12-14', 36, 'Soltero', 'PERUANA', 'Huancavelica', 'Angaraes', 'Cochacasa', 'AV. LAS AZUCENAS ASOC. SANTA CRUZ DE CAJAMARQUILLA MZ. L LT. 3', 'Chofer', 'SECUNDARIA COMPLETA', 'DEMETRIO', 'ROSA', '974603991', 'eduardo.death.14@gmail.com', NULL, '2025-10-20 04:43:24', 'uploads/reniec/45782193.jpg', NULL, NULL, 'LIMA', 'LIMA', 'LURIGANCHO'),
(38, 'DNI', '10015491', 'CRUZ', 'GARCIA', 'David', 'M', '1973-12-14', 51, 'Viudo', 'PERUANA', 'Amazonas', 'Luya', 'Tingo', 'CALLE 1 URB. PRO LIMA ETAPA 2 MZ. A LT. 01', 'Conductor', 'SECUNDARIA COMPLETA', 'PEDRO', 'ELIZABET', NULL, NULL, NULL, '2025-10-20 04:45:50', 'uploads/reniec/10015491.jpg', NULL, NULL, 'LIMA', 'LIMA', 'LOS OLIVOS'),
(39, 'DNI', '22660353', 'NIEVES', 'ALDABA', 'Antonia', 'F', '1968-01-18', 57, 'Soltero', 'PERUANA', 'Huanuco', 'Ambo', 'Ambo', 'MZ. G LT. 7 ASENT. H. SAN JUAN BAUTISTA', NULL, 'PRIMARIA COMPLETA', 'VICENTE', 'TEODOSIA', NULL, NULL, NULL, '2025-10-20 06:08:56', NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'DNI', '43165106', 'CARRILLO', 'NIEVES', 'Luis Agusto', 'M', '1985-08-04', 40, 'Casado', 'PERUANA', 'Piura', 'Talara', 'Pariñas', 'Mz G lote 17 Prop. Virgen de las Mercedes', 'Conductor', 'Técnico Superior incompleta', 'Jorge', 'Edita', '972529356', 'canieves22@gmail.com', 'Hermano JORGE CARRILLO 903086458', '2025-10-20 06:12:24', NULL, NULL, NULL, 'Lima', 'Lima', 'San Martin de Porres'),
(41, 'DNI', '73579438', 'PISCOYA', 'ESCRIBANO', 'Wilder Yovany', 'M', '1996-05-25', 29, 'Soltero', 'PERUANA', 'Lambayeque', 'Ferreñafe', 'Pueblo Nuevo', 'KM.39 1/2 PANAMERICANA NORTE ASOC. DAMNIFICADOS NADINE HEREDIA MZ. D LT. 16', 'Independiente', 'PRIMARIA-5TO GRADO', 'PISCOYA TIGRE PABLO', 'ESCRIBANO SIANCAS  ANGELA', NULL, NULL, NULL, '2025-10-20 14:58:56', 'uploads/reniec/73579438.jpg', NULL, NULL, 'LIMA', 'LIMA', 'ANCON'),
(42, 'DNI', '71389155', 'TORIBIO', 'FELIX', 'Josue Elias', 'M', '2000-05-12', 25, 'Soltero', 'PERUANA', NULL, NULL, NULL, 'SAN JUAN DE HUARIPAMPA', NULL, 'PRIMARIA-2DO GRADO', 'TORIBIO PINEDO JEREMIAS', 'FELIX CAMPOS  CARMELITA', '993702532', 'toribiofelixjosue@gmail.com', NULL, '2025-10-20 15:20:59', 'uploads/reniec/71389155.jpg', NULL, NULL, 'HUANUCO', 'HUACAYBAMBA', 'CANCHABAMBA'),
(43, 'DNI', '43589515', 'CHAVEZ', 'HUARACA', 'Ericson Ivan', 'M', '1983-02-28', NULL, 'Soltero', 'PERUANA', 'Ancash', 'Bolognesi', 'Colquioc', 'CALLE MANUEL SCORZA ASOC. LOS FRUTALES DEL NORTE MZ. G LT. 04', 'Suboficial de Policía', 'Superior Técnico completo', 'DEMETRIO HONORA', 'JUANA', '', '', NULL, '2025-10-23 16:51:09', 'uploads/reniec/43589515.jpg', NULL, NULL, 'LIMA', 'LIMA', 'PUENTE PIEDRA'),
(44, 'DNI', '10191431', 'TITTO', 'VALENCIA', 'John Richard', 'M', '1972-09-10', 52, NULL, 'PERUANA', 'Lima', 'Lima', 'Jesus María', 'PROLONG.FAUSTINO SANCHEZ CARRION 179 URB. EL RETABLO ETAPA I', NULL, 'SECUNDARIA COMPLETA', 'JOSE MARTIN', 'MARIA CRISTINA', NULL, NULL, NULL, '2025-10-26 17:41:51', 'uploads/reniec/10191431.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(45, 'DNI', '10159943', 'BARRERA', 'PAUCAR', 'Hector Horacio', 'M', '1974-12-24', 51, 'Soltero', 'PERUANA', 'Huancavelica', 'Tayacaja', 'Pampas', 'CALLE 1 DE MAYO MZ. A LT. 5', 'CONDUCTOR', 'SECUNDARIA-5TO AÑO', 'PABLO', 'FAUSTINA', NULL, NULL, NULL, '2025-10-26 17:44:07', 'uploads/reniec/10159943.jpg', NULL, NULL, 'LIMA', 'LIMA', 'INDEPENDENCIA'),
(46, 'DNI', '09405138', 'PARIZACA', 'AMANQUI', 'Teofilo Alfredo', 'M', '1967-02-05', 59, 'Casado', 'PERUANA', 'Puno', 'Azangaro', 'Arapa', 'MZ.G LT. 6 BARRIO 1 SECT. 1 IV ETAPA URB. PACHACAMAC', NULL, 'TECNICA COMPLETA', 'TEOFILO', 'AMALIA', '922407144', NULL, NULL, '2025-10-27 01:17:03', 'uploads/reniec/09405138.jpg', NULL, NULL, 'LIMA', 'LIMA', 'SAN JUAN DE MIRAFLORES'),
(47, 'DNI', '77243359', 'BARRERA', 'BARRETO', 'Luis Antonio', 'M', '1994-12-01', NULL, 'Soltero', 'PERUANA', 'LIma', 'Lima', 'Independencia', 'PSJ. ALFONSO UGARTE ASENT.H. 30 DE ENERO MZ. A LT. 05', 'Conductor', 'SECUNDARIA COMPLETA', 'BARRERA PAUCAR HECTOR HORACIO', 'BARRETO GUTIERREZ  TEODORA', '926784741', 'antoniobarrera2894@gmail.com', NULL, '2025-10-27 01:28:38', 'uploads/reniec/77243359.jpg', NULL, NULL, 'LIMA', 'LIMA', 'INDEPENDENCIA'),
(48, 'DNI', '80116863', 'BARRETO', 'GUTIERREZ', 'Teodora', 'F', '1975-01-05', 51, NULL, 'PERUANA', NULL, NULL, NULL, 'AV. HUANCAVELICA S/N', NULL, 'ILETRADO/SIN INSTRUCCION', 'TEOFILO', 'VICTORIA', NULL, NULL, NULL, '2025-10-27 01:33:05', 'uploads/reniec/80116863.jpg', NULL, NULL, 'HUANCAVELICA', 'TAYACAJA', 'DANIEL HERNANDEZ'),
(49, 'DNI', '43080686', 'QUICAÑA', 'GUEVARA', 'Wilder', 'M', '1985-05-20', 39, 'Soltero', 'PERUANA', 'LIma', 'Lima', 'Puente Piedra', 'ASENT.H.SAN PEDRO DE CHOQUE MZ.D LT.3', 'Suboficial PNP', 'Técnico Superior Completo', 'EUSEBIO', 'BERTHA', '968776604', 'quicanaguevara@gmail.com', NULL, '2025-10-27 04:07:44', 'uploads/reniec/43080686.jpg', NULL, NULL, 'LIMA', 'LIMA', 'PUENTE PIEDRA'),
(50, 'DNI', '48238427', 'LEYVA', 'SUDARIO', 'Robert Valentin', 'M', '1979-05-05', 45, 'Soltero', 'PERUANA', 'Ancash', 'Antonio Raimondi', 'Aczo', 'ASOC. MICAELA BASTIDAS MZ. C LT. 11', NULL, 'PRIMARIA-4TO GRADO', 'HERMINIO', 'MARIA', NULL, NULL, NULL, '2025-10-27 04:12:06', 'uploads/reniec/48238427.jpg', NULL, NULL, 'LIMA', 'LIMA', 'PUENTE PIEDRA'),
(51, 'DNI', '72916574', 'HUAMAN', 'CACHAY', 'Segundo Luis Antonio', 'M', '1996-02-19', NULL, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Cercado de Lima', 'ASENT.H. NUEVO PROGRESO MZ. A LT. 02', 'Suboficial PNP', 'SECUNDARIA COMPLETA', 'HUAMAN POQUIOMA LUIS ANTONIO', 'CACHAY GUELAC  ASUNTA', '972775999', '', NULL, '2025-10-27 04:14:05', 'uploads/reniec/72916574.jpg', NULL, NULL, 'LIMA', 'LIMA', 'CARABAYLLO'),
(52, 'DNI', '71708182', 'TENORIO', 'CARRERO', 'Jose Lizardo', 'M', '1995-02-24', 30, 'Soltero', 'PERUANA', 'Cajamarca', 'La Capilla', 'Cutervo', 'COMUNIDAD NARANJOS', 'Suboficial PNP', 'SECUNDARIA COMPLETA', 'TENORIO OLIVERA HERMES', 'CARRERO MONSALVE  FELICITA', '982133613', NULL, NULL, '2025-10-27 04:17:16', 'uploads/reniec/71708182.jpg', NULL, NULL, 'CAJAMARCA', 'CUTERVO', 'SANTO DOMINGO DE LA CAPILLA'),
(53, 'DNI', '09778749', 'PONCE', 'SOLIS', 'Charito Rosario', 'F', '1968-01-16', NULL, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'El Agustino', 'PSJ.AMARILIS NRO.157', 'Empleada', 'PRIMARIA COMPLETA', 'MAURO', 'ESTILITA', '900230983', '', NULL, '2025-10-27 04:19:31', 'uploads/reniec/09778749.jpg', NULL, NULL, 'LIMA', 'LIMA', 'EL AGUSTINO'),
(54, 'DNI', '40348826', 'TARAZONA', 'ORTEGA', 'Edgardo', 'M', '1978-05-02', 47, 'Casado', 'PERUANA', 'Huanuco', 'Huamalies', 'Llata', 'ASENT.H. SAN MARTIN II ETAPA MZ. P LT. 10', 'Chofer', 'Secundaria completa', 'MARCELINO', 'EUSEBIA', '924115049', '', NULL, '2025-11-01 02:41:52', 'uploads/reniec/40348826.jpg', NULL, NULL, 'LIMA', 'HUARAL', 'CHANCAY'),
(55, 'DNI', '43838633', 'LITANO', 'BERECHE', 'Faustino', 'M', '1967-06-10', 57, 'Soltero', 'PERUANA', 'PIura', 'Morropon', 'Chulucanas', 'CALLE GRAU 271 CENT. SAN CLEMENTE', NULL, 'PRIMARIA COMPLETA', 'JOSE MERCEDES', 'VENEDA', NULL, NULL, NULL, '2025-11-01 02:55:37', 'uploads/reniec/43838633.jpg', NULL, NULL, 'PIURA', 'SECHURA', 'BELLAVISTA DE LA UNION'),
(56, 'DNI', '16015424', 'AQUINO', 'SUSANIBAR', 'Angelica', 'F', '1976-10-12', 48, 'Casado', 'PERUANA', 'Lima', 'Huaral', 'Chancay', 'ASENT. H. SAN MARTIN II ETAPA MZ.F LT.10', 'Asistente RR.HH.', 'Superior universitario completo', 'AUGUSTO', 'GERARDA', NULL, 'angelica.aquinos@hotmail.com', NULL, '2025-11-01 03:01:53', 'uploads/reniec/16015424.jpg', NULL, NULL, 'LIMA', 'HUARAL', 'CHANCAY'),
(57, 'DNI', '80667630', 'TORRES', 'TALLEDO', 'Elsa', 'F', '1979-09-29', NULL, 'Casado', 'PERUANA', 'Piura', 'Sechura', 'Bella Vista de la Unión', 'CENTRO POBLADO SAN CLEMENTE PASAJE DON GOLLO S/N', 'Su casa', 'SECUNDARIA COMPLETA', 'JOSE SIXTO', 'OLGA', '951085307', '', NULL, '2025-11-01 03:09:39', 'uploads/reniec/80667630.jpg', NULL, NULL, 'PIURA', 'SECHURA', 'BELLAVISTA DE LA UNION'),
(58, 'DNI', '41529305', 'RAMOS', 'MONTALVO', 'Andres Anival', 'M', '1981-10-04', NULL, 'Casado', 'PERUANA', 'PASCO', 'OXAPAMPA', 'OXAPAMPA', 'MZ F LT 24 A.H. BALNEARIOS DE PACHACUTEC I SECTOR', NULL, 'TECNICA COMPLETA', 'OCTAVIO', 'PRIMITIVA', '', '', NULL, '2025-11-01 03:26:07', 'uploads/reniec/41529305.jpg', NULL, NULL, 'PASCO', 'CALLAO', 'VENTANILLA'),
(59, 'DNI', '06434000', 'TICLAVILCA', 'MONTES', 'Moraima Soledad', 'F', '1966-02-23', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'ASOC.1RO.DE NOVIEMBRE MZ.G LT.7 HUASCATA', NULL, 'SECUNDARIA COMPLETA', 'DARIO', 'ESPERANZA', '', '', NULL, '2025-11-01 03:30:57', 'uploads/reniec/06434000.jpg', NULL, NULL, 'LIMA', 'LIMA', 'CHACLACAYO'),
(60, 'DNI', '46714098', 'QUILCA', 'ANAHUI', 'Roni William', 'M', '1991-07-18', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'JR. BREÑA 100 P.J. MIGUEL GRAU ZN-A ETAPA III', NULL, 'Técnico superior completo', 'NICANOR WILFREDO', 'BERNA', '+51 910 952 673', '', NULL, '2025-11-15 23:24:53', 'uploads/reniec/46714098.jpg', NULL, NULL, 'AREQUIPA', 'AREQUIPA', 'PAUCARPATA'),
(61, 'DNI', '44858773', 'ESPINOZA', 'NIEVES', 'Lidia', 'F', '1987-11-28', NULL, 'Soltero', 'PERUANA', 'Huánuco', 'Ambo', 'Ambo', 'AV. MONTEVERDE COOP. ASOC. VIVIENDA ALFA Y OMEGA MZ. M LT. 6', 'Costurera', 'Secundaria completa', 'ROLO', 'ANTONIA', '924385905', 'lidiasergio28@gmail.com', NULL, '2025-11-15 23:37:17', 'uploads/reniec/44858773.jpg', NULL, NULL, 'LIMA', 'LIMA', 'ATE'),
(62, 'DNI', '42212296', 'ARAUJO', 'ZEGARRA', 'Jorge Esteban', 'M', '1982-07-04', NULL, 'Soltero', 'PERUANA', 'La Libertad', 'Bolivar', 'Huchumarca', 'JR. LAS MONJAS 150 URB. SANTA FELICIA 1ERA.ETAPA', NULL, 'Técnico Completa', 'ESTEBAN', 'CLEMENTINA', '989197622', 'jojuries@gmail.com', NULL, '2025-11-21 05:09:55', 'uploads/reniec/42212296.jpg', NULL, NULL, 'LIMA', 'LIMA', 'LA MOLINA'),
(64, 'DNI', '74090355', 'MARCELO', 'SANDOVAL', 'Brenda Lizeth', 'F', '1999-03-08', NULL, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Los Olivos', 'ASOC. DE DAMNIFICADOS NADINE HEREDIA MZ. D LT. 16', 'Su casa', 'SECUNDARIA COMPLETA', 'MARCELO GUERRERO MARCO ANTONIO', 'SANDOVAL YAUCE  MARIA ANGELA', '935048300', 'brendalizethmarcelosandoval@gmail.com', NULL, '2025-11-21 05:25:59', 'uploads/reniec/74090355.jpg', NULL, NULL, 'LIMA', 'LIMA', 'ANCON'),
(65, 'DNI', '10749424', 'OCHAVANO', 'HIDALGO', 'Lidia', 'F', '1978-04-21', 46, 'Soltero', 'PERUANA', NULL, NULL, NULL, 'CALLE J URB. EL ALAMO MZ. C LT. 10', NULL, 'SECUNDARIA COMPLETA', 'RAMON', 'LIDIA', NULL, NULL, NULL, '2025-11-24 22:36:14', 'uploads/reniec/10749424.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(66, 'DNI', '09910030', 'PALMA', 'ROJAS', 'Fredy Aldo', 'M', '1975-01-18', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'COOP. RESIDENCIAL LA ENSENADA MZ. N\' LT. 26', NULL, 'TECNICA COMPLETA', 'CIPRIANO', 'NATIVIDAD', '', '', NULL, '2025-11-24 22:57:39', 'uploads/reniec/09910030.jpg', NULL, NULL, 'LIMA', 'LIMA', 'PUENTE PIEDRA'),
(67, 'DNI', '75837967', 'CRISOSTOMO', 'BOLAÑOS', 'Bruno Alexander', 'M', '2000-03-28', 24, 'Soltero', 'Peruano', 'Lima', 'Lima', 'Lima', 'URB. EL ALAMO II ET. VIPOL MZ. W1 LT. 07', NULL, 'SECUNDARIA COMPLETA', 'CRISOSTOMO MALLMA ENRIQUE', 'BOLAÑOS REYES  MARIA ISABEL ALEXANDRA', '', '', NULL, '2025-11-25 03:54:28', 'uploads/reniec/75837967.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(68, 'DNI', '71263875', 'BOBADILLA', 'ROJAS', 'Rosa Ines', 'F', '1994-12-04', 30, 'Soltero', 'PERUANA', 'Loreto', 'Maynas', 'Iquitos', 'MZ F LT 2 PJ COLLIQUE 5TA ZONA', NULL, 'SECUNDARIA-5TO AÑO', 'BOBADILLA CARRANZA JOSE GUILLERMO', 'ROJAS CHAVEZ  ENEYDA', NULL, NULL, 'ocupante de motocicleta, lesiones leves', '2025-11-25 04:01:15', 'uploads/reniec/71263875.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(69, 'DNI', '42689616', 'MESIA', 'TORRES', 'Johnny', 'M', '1984-09-03', NULL, 'Soltero', 'PERUANA', 'Amazonas', 'Rodriguez de Mendoza', 'Limabamba', 'JR. JUAN CASTRO 174 ETAPA 1 PISO 3 URB. SANTA LUZMILA', 'Suboficial PNP', 'Técnico superior completa', 'MIGUEL ANGEL', 'ANA CREMILDA', '907058643', '', NULL, '2025-11-25 05:26:24', 'uploads/reniec/42689616.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(70, 'DNI', '06736087', 'BOLAÑOS', 'REYES', 'Maria Isabel Alexandra', 'F', '1958-08-11', NULL, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Breña', 'CALLE 30 URB. EL ALAMO II - VIPOL MZ. W1 LT. 07', NULL, 'SUPERIOR COMPLETA', 'ADRIAN', 'ISABEL', '', '', NULL, '2025-11-25 05:35:00', 'uploads/reniec/06736087.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(71, 'DNI', '06238219', 'HARADA', 'GAMARRA', 'Duffe', 'F', '1947-12-13', NULL, NULL, 'PERUANA', NULL, NULL, NULL, 'JR. YARAVI 246 INT. 402 4TO PISO', NULL, 'SUPERIOR COMPLETA', 'HISAJI', 'EFIGENIA', '', '', NULL, '2025-11-25 05:58:40', 'uploads/reniec/06238219.jpg', NULL, NULL, 'LIMA', 'LIMA', 'BREÑA'),
(72, 'DNI', '75551732', 'ALVAREZ', 'ALARCON', 'Jesus Vitaliano', 'M', '1996-12-15', NULL, 'Soltero', 'PERUANA', NULL, NULL, NULL, 'ASENT.H. JOSE CARLOS MARIATEGUI ETAPA 5 MZ. U9 LT. 2', NULL, 'SECUNDARIA COMPLETA', 'ALVAREZ JIHUALLANCA VITALIANO', 'ALARCON ORTIZ  MARCELINA', '918774946', '', NULL, '2025-11-26 18:33:25', 'uploads/reniec/75551732.jpg', NULL, NULL, 'LIMA', 'LIMA', 'SAN JUAN DE LURIGANCHO'),
(73, 'DNI', '78001942', 'SANCHEZ', 'BORJA', 'Jofrre Alexander', 'M', '2005-09-25', 19, NULL, 'PERUANA', NULL, NULL, NULL, 'AH COLLIQUE IV SCT AMPLIAC MZ. Q3 LT. 22', NULL, 'SECUNDARIA COMPLETA', 'SANCHEZ DIAZ CHRISTIAN ALEXANDER', 'BORJA RAMOS  DIANA CAROL', NULL, NULL, NULL, '2025-11-29 06:57:19', 'uploads/reniec/78001942.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(74, 'DNI', '71264393', 'TEJADA', 'NAMUCHE', 'Ericcson Jhoan', 'M', '2000-08-05', 24, NULL, 'PERUANA', NULL, NULL, NULL, 'ASENT.H. TIWINZA MZ .B LT.06', NULL, 'SECUNDARIA COMPLETA', 'TEJADA MENDOZA WALTER', 'NAMUCHE CASTILLO  SANTOS CAROLINA', NULL, NULL, NULL, '2025-11-29 07:00:05', 'uploads/reniec/71264393.jpg', NULL, NULL, 'LIMA', 'LIMA', 'PUENTE PIEDRA'),
(75, 'DNI', '40654960', 'LUNA', 'APARICIO', 'Marciano Sosimo', 'M', '1979-01-14', 46, NULL, 'PERUANA', NULL, NULL, NULL, 'ASENT.H. SAN ANTONIO DE PADUA MZ. M LT. 02', NULL, 'TECNICA COMPLETA', 'BONIFACIO', 'VICTORIA', NULL, NULL, NULL, '2025-11-29 07:00:58', 'uploads/reniec/40654960.jpg', NULL, NULL, 'LIMA', 'LIMA', 'SAN JUAN DE MIRAFLORES'),
(76, 'DNI', '43547401', 'VELASQUEZ', 'ORTIZ', 'Jornan Hilton', 'M', '1986-05-09', 39, NULL, 'PERUANA', NULL, NULL, NULL, 'MZ B LT 20 ASENT. H. 4 DE ENERO', NULL, 'SECUNDARIA COMPLETA', 'MARTIN OSCAR', 'ELENA', NULL, NULL, NULL, '2025-11-29 07:02:05', 'uploads/reniec/43547401.jpg', NULL, NULL, 'LIMA', 'LIMA', 'LIMA'),
(77, 'DNI', '47264300', 'GUTIERREZ', 'HUACHACA', 'Jose Williams', 'M', '1992-05-18', 33, NULL, 'PERUANA', NULL, NULL, NULL, 'JR. LIBERTAD 624 ASENT.H. 22 HECTAREAS MZ. 28 LT. 6', NULL, 'SECUNDARIA COMPLETA', 'AUGUSTO JESUS', 'OLGA LIDIA', NULL, NULL, NULL, '2025-11-29 07:03:01', 'uploads/reniec/47264300.jpg', NULL, NULL, 'LIMA', 'CALLAO', 'CARMEN DE LA LEGUA-REYNOSO'),
(78, 'DNI', '77295897', 'LIÑAN', 'SARANGO', 'Arlette Masiel', 'F', '2005-02-09', 20, NULL, 'PERUANA', NULL, NULL, NULL, 'JR. MANUEL DE LARA 367 VILLA SOL', NULL, 'PRIMARIA-1ER GRADO', 'LIÑAN SALAZAR ENRIQUE ALIPIO', 'SARANGO CHIRINOS  JOHANA ARACELY', NULL, NULL, NULL, '2025-11-29 07:04:44', 'uploads/reniec/77295897.jpg', NULL, NULL, 'LIMA', 'LIMA', 'LOS OLIVOS'),
(79, 'DNI', '10389997', 'ARCE', 'PEÑA', 'Carlos Gilbert', 'M', '1969-12-15', 55, NULL, 'PERUANA', 'Lima', 'Lima', 'Cercado de Lima', 'CALLE FEDERICO BARRETO 117 URB. SAN AGUSTIN 2DA. ETAPA', 'Chofer', 'SECUNDARIA-5TO AÑO', 'TEOBALDO', 'LUCILA', '916887587', NULL, NULL, '2025-12-02 04:07:57', 'uploads/reniec/10389997.jpg', NULL, NULL, 'LIMA', 'LIMA', 'COMAS'),
(80, 'DNI', '48953337', 'CASTELLANO', 'MINAYA', 'Miguel Angel', 'M', '1999-07-12', 26, 'Soltero', 'PERUANA', 'Ancash', 'Asunción', 'Chacas', 'AV. LA PAZ 1725 URB. MIRAMAR', NULL, 'ILETRADO/SIN INSTRUCCION', NULL, 'MARTINA ALEJANDRINA', NULL, NULL, NULL, '2025-12-02 04:25:02', 'uploads/reniec/48953337.jpg', NULL, NULL, 'LIMA', 'LIMA', 'SAN MIGUEL'),
(81, 'DNI', '44579792', 'MANRRIQUE', 'CASTELLANO', 'Ly Hernan', 'M', '1979-04-18', NULL, 'Soltero', 'PERUANA', 'Ancash', 'Asunción', 'Chacas', 'Calle Los Alhelies Urb. Los Lirios Mz. D lote 16', NULL, 'Superior completa', 'NARCISO', 'MARTINA ALEJAND', '916903842', NULL, NULL, '2025-12-02 05:33:59', 'uploads/reniec/44579792.jpg', NULL, NULL, 'ANCASH', 'CALLAO', 'CALLAO'),
(82, 'DNI', '74771679', 'MAMANI', 'DEZA', 'Amilcar David', 'M', '1999-04-28', NULL, 'Soltero', 'PERUANA', 'Puno', 'Puno', 'Puno', 'AV. INDUSTRIAL URB. LAS AMERICAS MZ. D LT. 04', 'Suboficial PNP', 'Técnico superior completo', 'MAMANI TURPO DAVID SEBASTIAN', 'DEZA MULLISACA  BERNARDINA', '926869150', '', NULL, '2025-12-02 06:05:34', 'uploads/reniec/74771679.jpg', NULL, NULL, 'PUNO', 'SAN ROMAN', 'JULIACA'),
(83, 'DNI', '01010505', 'FLORES', 'VEGA', 'Prospero', 'M', '1959-06-25', 66, 'Soltero', 'PERUANA', 'San Martín', 'Tocache', 'Uchiza', 'ASENT.H.SAN BENITO MZ.Q1 LT.30 CALLE LOS EDITORES', 'Chofer', 'Secundaria completa', 'FRANCISCO', 'MARIA', '934318416', NULL, NULL, '2025-12-10 02:01:59', 'uploads/reniec/01010505.jpg', NULL, NULL, 'LIMA', 'LIMA', 'CARABAYLLO'),
(84, 'DNI', '43097400', 'QUISPE', 'AROSTEGUI', 'Betsy Rocio', 'F', '1985-07-04', 40, 'Soltero', 'PERUANA', 'Apurimac', 'Abancay', 'Abancay', 'PSJ. SANTA INES 155', NULL, 'SECUNDARIA COMPLETA', 'JUAN', 'CANDELARIA', NULL, NULL, NULL, '2025-12-10 02:05:53', 'uploads/reniec/43097400.jpg', NULL, NULL, 'JUNIN', 'HUANCAYO', 'EL TAMBO'),
(85, 'DNI', '41951786', 'CHUCO', 'LEON', 'Cristobal Claudio', 'M', '1981-05-01', 44, 'Soltero', 'PERUANA', 'Junín', 'Tarma', 'San Pedro de Cajas', 'JR.TARMA S/N', NULL, 'SECUNDARIA-2DO AÑO', 'ARTEMIO', 'ISABEL', '901341421', NULL, NULL, '2025-12-10 02:47:18', 'uploads/reniec/41951786.jpg', NULL, NULL, 'JUNIN', 'TARMA', 'SAN PEDRO DE CAJAS'),
(86, 'DNI', '93211619', 'CHUCO', 'QUISPE', 'Cielo Cristel', 'F', '2023-01-08', 2, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Puente Piedra', 'PSJ. SANTA INES 155', NULL, 'ILETRADO/SIN INSTRUCCION', 'CHUCO LEON CRISTOBAL CLAUDIO', 'QUISPE AROSTEGUI  BETSY ROCIO', NULL, NULL, NULL, '2025-12-10 02:49:11', 'uploads/reniec/93211619.jpg', NULL, NULL, 'JUNIN', 'HUANCAYO', 'EL TAMBO'),
(87, 'DNI', '45081401', 'QUISPE', 'AROSTEGUI', 'Nicolasa Dorila', 'F', '1980-04-26', NULL, 'Soltero', 'PERUANA', 'Apurímac', 'Abancay', 'Abancay', 'ASENT.H. SAN BENITO MZ. E2 LT. 18', 'Ama de casa', 'SECUNDARIA COMPLETA', 'JUAN', 'CANDELARIA', '918437626', 'silvera.a.c.12.23@gmail.com', NULL, '2025-12-10 04:11:09', 'uploads/reniec/45081401.jpg', NULL, NULL, 'LIMA', 'LIMA', 'CARABAYLLO'),
(88, 'DNI', '40632130', 'OLORTEGUI', 'RAMOS', 'Luis Angel', 'M', '1980-08-17', NULL, 'Soltero', 'PERUANA', 'San Martín', 'Tocache', 'Uchiza', 'MZ.Q1 LT.30 A.H.SAN BENITO', NULL, 'SECUNDARIA COMPLETA', 'GLICERIO', 'SABINA', '', '', NULL, '2025-12-10 04:15:08', 'uploads/reniec/40632130.jpg', NULL, NULL, 'LIMA', 'LIMA', 'CARABAYLLO'),
(89, 'DNI', '74245705', 'CHUQUIHUANCA', 'JOCOPE', 'Paul Darly', 'M', '1996-11-04', NULL, 'Soltero', 'PERUANA', 'Piura', 'Ayabaca', 'Sapillica', 'JR. PIURA SN SAPILLICA', 'S2.PNP', 'SECUNDARIA COMPLETA', 'CHUQUIHUANCA LIMA JUAN CARLOS', 'JOCOPE UMBO  SARA', '981043857', '32207482@policia.gob.pe', NULL, '2025-12-10 04:19:13', 'uploads/reniec/74245705.jpg', NULL, NULL, 'PIURA', 'AYABACA', 'SAPILLICA'),
(90, 'DNI', '09552338', 'MAÑUICO', 'DURAND', 'Jose Antonio', 'M', '1972-12-09', 53, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Comas', 'AV.MIRAFLORES 610 P.JOVEN EL PROGRESO 1ER SECTOR', NULL, 'SECUNDARIA COMPLETA', 'VICTOR', 'SUSANA', NULL, NULL, NULL, '2025-12-11 03:46:53', 'uploads/reniec/09552338.jpg', NULL, NULL, 'LIMA', 'LIMA', 'CARABAYLLO'),
(91, 'DNI', '48086823', 'MAÑUICO', 'MORALES', 'Lucia', 'F', '1993-12-19', NULL, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Carabayllo', 'COMITE 10 ASENT.H. VILLA ESPERANZA MZ. 55E LT. 5', NULL, 'SECUNDARIA COMPLETA', 'JOSE ANTONIO', 'ISABEL', '963387689', '', NULL, '2025-12-15 08:26:15', 'uploads/reniec/48086823.jpg', NULL, NULL, 'LIMA', 'LIMA', 'CARABAYLLO'),
(92, 'DNI', '43956495', 'HUAYCOCHEA', 'RIVERA', 'Percy', 'M', '1972-08-24', NULL, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'El Agustino', 'SECTOR 1 GRUPO 8 MZ.L LT.19', 'Suboficial PNP', 'TECNICA COMPLETA', 'GILBERTO', 'ISABEL', '918104131', '', NULL, '2025-12-15 08:31:14', NULL, NULL, NULL, 'Lima', 'Lima', 'Villa El Salvador'),
(93, 'DNI', '27281411', 'DELGADO', 'BANDA', 'Segundo Julio', 'M', '1968-01-30', 57, 'Soltero', 'PERUANA', 'Cajamarca', 'Cutervo', 'Cutervo', 'Caserio Valle El Rejo', NULL, 'Primaria completa', 'Cesar', 'Jovita', NULL, NULL, NULL, '2026-01-11 20:46:08', NULL, NULL, NULL, 'Cajamarca', 'Cutervo', 'Cutervo'),
(94, 'DNI', '71272672', 'RAMIREZ', 'BERNABE', 'Juan Francisco', 'M', '2003-05-25', 22, 'Soltero', 'PERUANA', 'Calllao', 'Callao', 'Bellavista', 'Urb. Las Lomas de Zapallal Mz L lote 19-Puente Piedra', 'Estudiante', 'Secundaria completa', 'Alejandro', 'Patricia', '912585569', 'juanraa2517@gmail.com', NULL, '2026-01-11 20:49:51', NULL, NULL, NULL, 'Lima', 'Lima', 'Puente Piedra'),
(95, 'DNI', '41923982', 'ROJAS', 'SANCHEZ', 'Kely Yhuse', 'F', '1983-08-07', NULL, 'Soltero', 'PERUANA', 'La Libertad', 'Otuzco', 'Salpo', 'Urb. El Sol del Chacarero', NULL, 'Secundaria completa', 'José', 'Amada', '', '', NULL, '2026-01-11 21:10:10', NULL, NULL, NULL, 'La Libertad', 'Trujillo', 'Trujillo'),
(96, 'DNI', '48888610', 'RUIZ', 'RODRIGUEZ', 'Lusbin Joel', 'M', '1994-06-05', NULL, 'Soltero', 'PERUANA', 'Piura', 'Sullana', 'Marcavelica', NULL, NULL, 'Técnico superior completo', 'Jorge Hermelindo', 'Perfecta', '', '', NULL, '2026-01-11 21:21:31', NULL, NULL, NULL, NULL, NULL, NULL),
(97, 'DNI', '46617325', 'VASQUEZ', 'DELGADO', 'Eiler Keiner', 'M', '1990-07-08', NULL, 'Soltero', 'Peruana', 'Cajamarca', 'Cutervo', 'Cutervo', 'Calle Los Alcanfores Urb. Las Praderas Mz F8 lote 28-Carabayllo', 'Albañil', 'Secundaria completa', 'Ernesto', 'Zoila', '970227901', '', NULL, '2026-01-12 02:05:47', NULL, NULL, NULL, NULL, NULL, NULL),
(98, 'DNI', '75869389', 'GARCIA', 'BELLO', 'Joseph Eduardo', 'M', '1999-06-29', 26, 'Soltero', 'PERUANA', 'Lima', 'Lima', 'Lima', 'Calle Los Naranjales 493 Urb. Pando', 'Chofer', 'Secundaria completa', 'José Antonio', 'Norma Bety', NULL, NULL, NULL, '2026-02-03 14:41:46', NULL, NULL, NULL, 'Lima', 'Lima', 'San Miguel'),
(99, 'CE', '000986758', 'TILLERO', 'PEREZ', 'Jocelym', 'F', '2002-04-09', 23, 'Soltero', 'Venezolana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 14:45:19', NULL, NULL, NULL, NULL, NULL, NULL),
(100, 'CE', '008216988', 'RODRIGUEZ', 'RAMOS', 'Yohandri Alberto', 'M', '1997-01-18', 29, 'Soltero', 'Venezolana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 14:48:13', NULL, NULL, NULL, NULL, NULL, NULL),
(101, 'OTRO', '1234', 'PACHECO', 'PEÑA', 'Janierkis Carolina', 'F', '2026-01-01', 0, 'Soltero', 'Venezolana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 14:54:29', NULL, NULL, NULL, NULL, NULL, NULL),
(102, 'OTRO', '123456', 'BELLO', 'RODRIGUEZ', 'Thiago Josue', 'M', '2026-01-01', 0, 'Soltero', 'Venezolana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 14:55:57', NULL, NULL, NULL, NULL, NULL, NULL),
(103, 'PAS', '186926976', 'RODRIGUEZ', 'RAMOS', 'Celeste De Las Nieves', 'F', '2006-09-19', 19, 'Soltero', 'Venezolana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-03 15:12:20', NULL, NULL, NULL, NULL, NULL, NULL),
(104, 'CE', '008261832', 'BELLO', 'GOMEZ', 'Cristian Enrique', 'M', '2001-01-05', NULL, 'Casado', 'Venezolana', NULL, NULL, NULL, 'Calle Ansalmo Andia 865- Santa Luzmila', NULL, NULL, NULL, NULL, '903446963', '', NULL, '2026-02-04 23:50:01', NULL, NULL, NULL, NULL, NULL, NULL),
(105, 'PAS', '196130572', 'PACHECO', 'PEÑA', 'Mariela Josefina', 'F', '1981-02-25', NULL, 'Casado', 'Venezolana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '920668793', '', NULL, '2026-02-05 04:39:12', NULL, NULL, NULL, NULL, NULL, NULL),
(106, 'CE', '003794143', 'SALAZAR', 'SALAZAR', 'Sophie De Los Angeles', 'F', '2005-02-01', NULL, 'Soltero', 'Venezolana', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '925783696', '', NULL, '2026-02-05 04:46:45', NULL, NULL, NULL, NULL, NULL, NULL),
(107, 'DNI', '46506905', 'ANGELES', 'ANGELES', 'Enzo Giovanni', 'M', '1990-07-08', 35, 'Soltero', 'Peruano', 'Ancash', 'Huaylas', 'Caraz', 'Urb. Santa Teresita Mz D-7-Ancash-Huaylas-Caraz', NULL, NULL, NULL, NULL, '975727177', NULL, NULL, '2026-02-05 04:58:43', NULL, NULL, NULL, NULL, NULL, NULL),
(108, 'DNI', '44383706', 'ALBORNOS', 'MORENO', 'Victor Elias', 'M', '1986-08-30', 37, 'Soltero', 'PERUANA', 'Ancash', 'Huaylas', 'Caraz', 'Agrupación Familiar Nuevo Amanecer II- San Juan de Lurigancho', 'Chofer', 'Secundaria completa', 'Paulino', 'Mabel', '949444663', 'valbornosm@gmail.com', NULL, '2026-02-05 06:52:29', NULL, NULL, NULL, 'Lima', 'Lima', 'San Juan de Lurigancho'),
(109, 'DNI', '33563564', 'DAVILA', 'MONDRAGON', 'Manuel Arturo', 'M', '1945-09-27', 78, 'Casado', 'PERUANA', 'Cajamarca', 'San Pablo', 'San Pablo', 'Via Evitamiento 415', 'Comerciante', 'Prima completa', 'Manuel', 'Alfoncina', NULL, NULL, NULL, '2026-02-05 07:27:55', NULL, NULL, NULL, 'Amazonas', 'Bagua', 'Bagua'),
(110, 'DNI', '77329523', 'BRUNO', 'CAMPOS', 'Alvaro Vilardo', 'M', '1995-12-16', NULL, 'Soltero', 'Peruana', 'Lima', 'Lima', 'San Juan de Lurigancho', 'Los Proceres 107 Daniel Alcides Carrión', 'Suboficial PNP', 'Técnico superior completo', 'Domingo', 'Martha', '922659941', 'alvarovilardo123@gmail.com', NULL, '2026-02-07 14:45:47', NULL, NULL, NULL, 'Lima', 'Lima', 'San Juan de Lurigancho'),
(111, 'DNI', '40802557', 'CORDOVA', 'GONZALES', 'Danny Ronald', 'M', '1980-05-29', NULL, 'Soltero', 'PERUANA', 'Arequipa', 'Arequipa', 'Arequipa', 'Laura Caller Mz 34 P1 lote 9- Los Olivos', NULL, 'Superior', 'Luís', 'Olga', '933617406', 'dronald.cg@gmail.com', NULL, '2026-03-04 15:08:40', NULL, NULL, NULL, 'Lima', 'Lima', 'Los Olivos'),
(114, 'DNI', '42395162', 'MONTENEGRO', 'PERALES', 'Eber Manuel', 'M', '1984-06-06', 41, 'CASADO', 'PERUANA', NULL, NULL, NULL, 'ASENT.H. HIROSHIMA MZ. L LT. 07', NULL, 'SECUNDARIA COMPLETA', 'SEGUNDO ELIAS', 'VILMA HILDA', NULL, NULL, NULL, '2026-03-04 22:56:34', 'uploads/reniec/42395162.jpg', 'RENIEC_SEEKER', '42395162', '', 'CALLAO', 'VENTANILLA'),
(116, 'DNI', '10548513', 'RAMOS', 'SALAZAR', 'Luis Enrique', 'M', '1977-05-12', 48, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'RIMAC ASENT.H. SAN JUAN DE AMANCAES MZ. I1 LT. 4', NULL, 'SECUNDARIA COMPLETA', 'MIGUEL', 'ROSA', NULL, NULL, NULL, '2026-03-05 01:23:15', 'uploads/reniec/10548513.jpg', 'RENIEC_SEEKER', '10548513', 'LIMA', 'LIMA', 'RIMAC'),
(118, 'DNI', '72990351', 'GARCIA', 'GRANADOS', 'George Shandell', 'M', '2003-09-14', 22, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'ASOCIACION LOS TULIPANES-LOS OLIVOS', NULL, 'SECUNDARIA-1ER AÑO', 'GARCIA MALDONADO JULIO CESAR', 'GRANADOS CLAROS  LEONID PLACENTINA', NULL, NULL, NULL, '2026-03-05 03:24:57', 'uploads/reniec/72990351.jpg', 'RENIEC_SEEKER', '72990351', 'LIMA', 'LIMA', 'LOS OLIVOS'),
(120, 'DNI', '60987915', 'MEGO', 'SANCHO', 'Soledad Isabel', 'F', '2007-02-12', 19, ' ', 'PERUANA', NULL, NULL, NULL, 'PJ.SAN CAMILO MZ J LT 5', NULL, 'PRIMARIA-6TO GRADO', 'MEGO GOICOCHEA EDINSON ALBERTO', 'SANCHO AYALA  YENNY MERCEDES', NULL, NULL, NULL, '2026-03-07 00:34:22', 'uploads/reniec/60987915.jpg', 'RENIEC_SEEKER', '60987915', 'LIMA', 'LIMA', 'INDEPENDENCIA'),
(122, 'DNI', '42610431', 'BRONCANO', 'ZAMORA', 'Carlos Augusto', 'M', '1984-09-17', 41, 'Soltero', 'PERUANA', 'Lima', 'Huaura', 'Huacho', 'CALLE JOSE BERNARDO ALCEDO URB. LOS LIBERTADORES MZ. B LT. 603', NULL, 'SECUNDARIA COMPLETA', 'ISIDORO ZENOBIO', 'MIRTA', NULL, NULL, NULL, '2026-03-08 16:34:18', 'uploads/reniec/42610431.jpg', 'RENIEC_SEEKER', '42610431', 'LIMA', 'LIMA', 'SAN MARTIN DE PORRES'),
(124, 'DNI', '08179210', 'LIZANA', 'DE LA CRUZ', 'Iraida', 'F', '1976-12-19', 49, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'URB.RES. LAS AMERICAS MZ. B LT. 10', NULL, 'SECUNDARIA COMPLETA', 'GUILLERMO', 'MARGARITA', NULL, NULL, NULL, '2026-03-08 16:39:40', 'uploads/reniec/08179210.jpg', 'RENIEC_SEEKER', '08179210', 'LIMA', 'LIMA', 'CARABAYLLO'),
(126, 'DNI', '44582265', 'MARTINEZ', 'CORONADO', 'Edar Edinson', 'M', '1987-10-18', 38, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'ASENT.H. VILLA CANTA MZ. 74 LT. 26A', NULL, 'SECUNDARIA-5TO AÑO', 'JOSE MANUEL', 'ROSA', '972516671', '', NULL, '2026-03-08 19:28:03', 'uploads/reniec/44582265.jpg', 'RENIEC_SEEKER', '44582265', 'LIMA', 'LIMA', 'INDEPENDENCIA'),
(127, 'DNI', '76766081', 'ARCE', 'LIZANA', 'Jose Enrique', 'M', '1996-02-04', 30, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'PROGRESO URB. LAS MAERICAS MZ. B LT. 10', NULL, 'SECUNDARIA COMPLETA', 'ARCE ARCE HILTON JANMS', 'LIZANA DE LA CRUZ  IRAIDA', '948311606', '', NULL, '2026-03-08 20:59:57', 'uploads/reniec/76766081.jpg', 'RENIEC_SEEKER', '76766081', 'LIMA', 'LIMA', 'CARABAYLLO'),
(128, 'DNI', '10159282', 'PEREZ', 'URETA', 'Angel Silverio', 'M', '1973-02-08', 52, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'LOS GUAYABOS 191 ERMITAÑO', NULL, 'SECUNDARIA COMPLETA', 'SILVERIO', 'CLAUDIA', NULL, NULL, NULL, '2026-03-12 06:29:53', 'uploads/reniec/10159282.jpg', 'RENIEC_SEEKER', '10159282', 'LIMA', 'LIMA', 'INDEPENDENCIA'),
(130, 'DNI', '71389579', 'VASQUEZ', 'VEGA', 'Yony', 'M', '2000-10-03', 25, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'M S1 L 5 A.A.H.H PPN PACHACUTEC SCT A GRUP RES A 3VENTANILLA', NULL, 'SECUNDARIA-5TO AÑO', 'VASQUEZ PRADAS CELESTINO', 'VEGA MELGAREJO  MANZUETA', '946389117', '32436989@policia.gob.pe', NULL, '2026-03-12 06:45:12', 'uploads/reniec/71389579.jpg', 'RENIEC_SEEKER', '71389579', '', 'CALLAO', 'VENTANILLA'),
(131, 'DNI', '09521666', 'SOLLER', 'URETA', 'Ema Antonia', 'F', '1971-02-14', 55, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'MZ.E LT.24 VIRGEN DE LAS MERCEDES', NULL, 'SECUNDARIA COMPLETA', 'JUAN', 'CLAUDIA', '985883197', '', NULL, '2026-03-12 16:14:32', 'uploads/reniec/09521666.jpg', 'RENIEC_SEEKER', '09521666', 'LIMA', 'LIMA', 'SAN MARTIN DE PORRES'),
(132, 'DNI', '74739871', 'LOPEZ', 'BERRU', 'Misael', 'M', '1995-11-29', 29, 'Soltero', 'PERUANA', 'Piura', 'Morropon', 'Santo Domingo', 'Dpto. 5 Urb. Viñas de Naranjal-SMP', 'Suboficial PNP', 'Técnico superior completo', 'Luis Alberto', 'Angelica', '967545056', 'lopezberrumisael@gmail.com', NULL, '2026-03-13 07:35:51', NULL, NULL, NULL, 'Lima', 'Lima', 'San Martín de Porres'),
(133, 'DNI', '08588722', 'QUISPE', 'RUPA', 'Narciso', 'M', '1940-10-29', 84, 'Casado', 'PERUANA', 'Cusco', 'Quispicanchi', 'Oropesa', 'Jr. Pira 607 Urb. El Parque Naranjal-Los Olivos', NULL, 'Secundaria', 'Remigio', 'Maximiliana', NULL, NULL, NULL, '2026-03-13 07:39:13', NULL, NULL, NULL, 'Lima', 'Lima', 'Los Olivos'),
(134, 'DNI', '72379810', 'MIREZ', 'MARCANI', 'Cindy Lourdes', 'F', '1992-09-22', 33, 'SOLTERO', 'PERUANA', NULL, NULL, NULL, 'JR. 9 DE DICIEMBRE 209', NULL, 'SECUNDARIA COMPLETA', 'MIREZ TRUJILLO FELIPE CESAR', 'MARCANI LOPEZ  LURDES TEODULA', NULL, NULL, NULL, '2026-03-13 19:33:52', 'uploads/reniec/72379810.jpg', 'RENIEC_SEEKER', '72379810', 'LIMA', 'LIMA', 'COMAS'),
(135, 'DNI', '09623726', 'QUISPE', 'TRUJILLANO', 'Amparo', 'F', '1971-06-04', 54, NULL, 'PERUANA', NULL, NULL, NULL, 'Jr. Pira 607 Urb. El Parque Naranjal', NULL, 'SECUNDARIA COMPLETA', 'NARCISO', 'BRENILDA', '51987497603', 'quispetrujillanoa@gmail.com', NULL, '2026-03-17 01:16:09', 'uploads/reniec/09623726.jpg', 'RENIEC_SEEKER', '09623726', 'LIMA', 'LIMA', 'LOS OLIVOS'),
(137, 'DNI', '74380102', 'ALZAMORA', 'CONGA', 'Maritza Lucero', 'F', '1994-03-10', 32, NULL, 'PERUANA', NULL, NULL, NULL, 'AV. ERNESTO MALINOWSKY 338 MZ. G LT. 11', NULL, 'SECUNDARIA-5TO AÑO', 'ALZAMORA FLORES ROMAN', 'CONGA RICO  ROSA ERNESTINA', '999921134', NULL, NULL, '2026-03-17 01:32:39', 'uploads/reniec/74380102.jpg', 'RENIEC_SEEKER', '74380102', 'LIMA', 'LIMA', 'LIMA'),
(138, 'DNI', '71035771', 'NEYRA', 'SALDANA', 'Gianella Rubela', 'F', '1996-04-21', 29, NULL, 'PERUANA', 'LIMA', 'LIMA', 'LIMA', 'URB. SANTA HILDA MZ. G LT. 01', NULL, 'TECNICA COMPLETA', 'NEYRA RUIZ RUBEN CARLOS', 'SALDAÑA AHUANARI  ENITH', NULL, NULL, NULL, '2026-03-24 06:37:35', 'uploads/reniec/71035771.jpg', 'RENIEC_SEEKER', '71035771', 'LIMA', 'HUARAL', 'HUARAL'),
(139, 'DNI', '06925609', 'PEREZ', 'MORALES', 'Fausto', 'M', '1944-11-19', 81, 'Casado', 'PERUANA', 'Ancash', 'Carlos Fermin Fitzcarrald', 'Yauya', 'JR PACHACUTEC 237 3ERA ZONA COLLIQUE', NULL, 'PRIMARIA COMPLETA', 'JUAN', 'CELESTINA', NULL, NULL, NULL, '2026-04-09 20:35:28', 'uploads/reniec/06925609.jpg', 'RENIEC_SEEKER', '06925609', 'LIMA', 'LIMA', 'COMAS'),
(140, 'DNI', '45829332', 'PEREZ', 'RAVELO', 'Giovanna', 'F', '1987-06-02', 38, 'Casado', 'PERUANA', 'Lima', 'Lima', 'Carabayllo', 'JR. PACHACUTEC 237 COLLIQUE 3ERA ZONA', 'Empleada', 'Secundaria completa', 'Fausto', 'Juana', '931498594', NULL, NULL, '2026-04-09 20:42:02', 'uploads/reniec/45829332.jpg', 'RENIEC_SEEKER', '45829332', 'LIMA', 'LIMA', 'COMAS'),
(141, 'DNI', '74480151', 'LEIVA', 'ALBARRAN', 'Jose Ebert', 'M', '1998-06-06', 27, 'Soltero', 'Peruana', 'Cajamarca', 'San Marcos', 'Gregorio Pita', 'Jr. Rio Ucayali 5362', NULL, 'Técnico superior completo', 'LEIVA MARIN SANTOS', 'ALBARRAN TERRONES  EUFEMIA', '918803500', 'ebertleiva9@gmail.com', NULL, '2026-04-13 07:58:31', 'uploads/reniec/74480151.jpg', 'RENIEC_SEEKER', '74480151', 'Lima', 'Lima', 'Los Olivos'),
(142, 'DNI', '06613782', 'LUNA', 'ARIAS', 'William', 'M', '1963-03-06', 63, 'Soltero', 'PERUANA', 'Apurímac', 'Andahuaylas', 'Andahuaylas', 'Urb. San Antonio de Carapongo Mz R1 lote 38', 'Gerente', 'SECUNDARIA COMPLETA', 'ROBERTO', 'EVANGELINA', '955512402', NULL, NULL, '2026-04-13 08:49:32', 'uploads/reniec/06613782.jpg', 'RENIEC_SEEKER', '06613782', 'LIMA', 'LIMA', 'Lurigancho'),
(143, 'DNI', '09708158', 'CRUZ', 'GARCIA', 'Ana Mercedes', 'F', '1969-10-21', 56, 'Soltero', 'Peruana', 'Amazonas', 'Luya', 'Tingo', 'AA.HH. Fortaleza Kuelap Mz A lote 5', 'Su casa', 'Secundaria completa', 'PEDRO', 'ELISA', '973982090', NULL, NULL, '2026-04-13 08:55:26', 'uploads/reniec/09708158.jpg', 'RENIEC_SEEKER', '09708158', 'LIMA', 'LIMA', 'PUENTE PIEDRA');

--
-- Disparadores `personas`
--
DELIMITER $$
CREATE TRIGGER `bi_personas_norm` BEFORE INSERT ON `personas` FOR EACH ROW BEGIN
  SET NEW.apellido_paterno = UPPER(TRIM(REGEXP_REPLACE(COALESCE(NEW.apellido_paterno, ''), '[[:space:]]+', ' ')));
  SET NEW.apellido_materno = UPPER(TRIM(REGEXP_REPLACE(COALESCE(NEW.apellido_materno, ''), '[[:space:]]+', ' ')));
  SET NEW.nombres = initcap_words(NEW.nombres);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `bu_personas_norm` BEFORE UPDATE ON `personas` FOR EACH ROW BEGIN
  SET NEW.apellido_paterno = UPPER(TRIM(REGEXP_REPLACE(COALESCE(NEW.apellido_paterno, ''), '[[:space:]]+', ' ')));
  SET NEW.apellido_materno = UPPER(TRIM(REGEXP_REPLACE(COALESCE(NEW.apellido_materno, ''), '[[:space:]]+', ' ')));
  SET NEW.nombres = initcap_words(NEW.nombres);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `policial_interviniente`
--

CREATE TABLE `policial_interviniente` (
  `id` int NOT NULL,
  `accidente_id` int NOT NULL,
  `persona_id` int NOT NULL,
  `grado_policial` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cip` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dependencia_policial` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol_funcion` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'INTERVINIENTE',
  `observaciones` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `policial_interviniente`
--

INSERT INTO `policial_interviniente` (`id`, `accidente_id`, `persona_id`, `grado_policial`, `cip`, `dependencia_policial`, `rol_funcion`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(1, 18, 21, 'ST3.PNP', '31486778', 'UIAT NORTE', 'INTERVINIENTE', '', '2025-10-11 05:12:12', '2025-10-11 05:12:12'),
(2, 19, 43, 'ST3. PNP', '12345678', 'Comisaria PNP Santa Isabel', 'INTERVINIENTE', '', '2025-10-23 16:52:25', '2025-10-23 16:52:25'),
(3, 26, 46, 'ST1.PNP', '30864138', 'Comisaria PNP San Martín de Porres', 'INTERVINIENTE', '', '2025-10-27 01:24:03', '2025-10-27 01:24:03'),
(4, 27, 51, 'S2.PNP', '32138138', 'Comisaria PNP Ancón', 'INTERVINIENTE', '', '2025-10-27 04:15:32', '2025-10-27 04:15:32'),
(5, 29, 58, 'ST2.PNP', '31359818', 'DEPROCAR SANTA ROSA', 'INTERVINIENTE', '', '2025-11-01 03:28:46', '2025-11-01 03:28:46'),
(6, 24, 60, 'S3.PNP', '46714098', 'Comisaria PNP Santa Isabel', 'INTERVINIENTE', '', '2025-11-15 23:29:36', '2025-11-15 23:29:36'),
(7, 25, 62, 'ST3.PNP', '42212296', 'Comiria PNP Ancón', 'INTERVINIENTE', '', '2025-11-21 05:19:16', '2025-11-21 05:19:16'),
(8, 30, 69, 'S1.PNP', '31393181', 'Comisaria Santa Luzmila', 'INTERVINIENTE', '', '2025-11-25 05:28:01', '2025-11-25 05:28:01'),
(9, 22, 72, 'S3.PNP', '32218011', 'Comisaria PNP Ancón', 'INTERVINIENTE', '', '2025-11-26 18:34:21', '2025-11-26 18:34:21'),
(10, 32, 82, 'S3.PNP', '32430175', 'Comisaría PNP Puente Piedra', 'INTERVINIENTE', '', '2025-12-02 06:06:23', '2025-12-02 06:06:23'),
(11, 32, 21, 'ST3.PNP', '31486778', 'UIAT NORTE', 'INTERVINIENTE', '', '2025-12-03 02:27:19', '2025-12-03 02:27:19'),
(12, 33, 89, 'S2.PNP', '32207482', 'Comisaria PNP Carabayllo', 'INTERVINIENTE', '', '2025-12-10 04:19:57', '2025-12-10 04:19:57'),
(13, 34, 92, 'ST1.PNP', '30953165', 'Comisaria PNP El Progreso', 'INTERVINIENTE', '', '2025-12-15 08:31:54', '2025-12-15 08:31:54'),
(14, 35, 96, 'S2.PNP', '32426822', 'Comisaría PNP Zapallal', 'INTERVINIENTE', '', '2026-01-11 21:27:06', '2026-01-11 21:27:06'),
(15, 36, 107, 'ST3.PNP', '46506905', 'Comisaría PNP Laura Caller Ibérico', 'INTERVINIENTE', NULL, '2026-02-05 05:58:44', '2026-04-09 15:42:52'),
(16, 20, 110, 'S2.PNP', '77329523', 'UNISEBAN PNP', 'INTERVINIENTE', '', '2026-02-07 14:46:22', '2026-02-07 14:46:22'),
(17, 38, 126, 'S1.PNP', '31519825', 'Carretera Yangas', 'INTERVINIENTE', '', '2026-03-08 19:29:17', '2026-03-08 19:29:17'),
(18, 39, 130, 'S3.PNP', '32436989', 'UNEMEMOT-HALCONES', 'INTERVINIENTE', '', '2026-03-12 06:47:23', '2026-03-12 06:47:23'),
(19, 23, 141, 'S3.PNP', '74480151', 'UCT-Norte 2', 'INTERVINIENTE', NULL, '2026-04-13 08:00:07', '2026-04-13 08:00:07');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `propietario_vehiculo`
--

CREATE TABLE `propietario_vehiculo` (
  `id` int UNSIGNED NOT NULL,
  `accidente_id` int UNSIGNED NOT NULL,
  `vehiculo_inv_id` int UNSIGNED NOT NULL,
  `tipo_propietario` enum('NATURAL','JURIDICA') COLLATE utf8mb4_general_ci NOT NULL,
  `propietario_persona_id` int UNSIGNED NOT NULL DEFAULT '0',
  `ruc` varchar(11) COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `razon_social` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `domicilio_fiscal` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rol_legal` varchar(80) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `representante_persona_id` int UNSIGNED DEFAULT NULL,
  `observaciones` text COLLATE utf8mb4_general_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `propietario_vehiculo`
--

INSERT INTO `propietario_vehiculo` (`id`, `accidente_id`, `vehiculo_inv_id`, `tipo_propietario`, `propietario_persona_id`, `ruc`, `razon_social`, `domicilio_fiscal`, `rol_legal`, `representante_persona_id`, `observaciones`, `creado_en`, `actualizado_en`) VALUES
(1, 18, 13, 'NATURAL', 27, '', '', '', 'Representante legal', 0, '', '2025-10-11 06:47:53', NULL),
(3, 19, 15, 'NATURAL', 24, '', '', '', 'Representante legal', 0, '', '2025-10-20 19:31:36', NULL),
(4, 26, 24, 'NATURAL', 48, '', '', '', 'Representante legal', 0, '', '2025-10-27 01:33:28', NULL),
(5, 26, 25, 'NATURAL', 44, '', '', '', 'Representante legal', 0, '', '2025-10-27 01:37:10', NULL),
(6, 29, 27, 'NATURAL', 54, '', '', '', 'Representante legal', 0, '', '2025-11-01 03:29:38', NULL),
(7, 29, 28, 'NATURAL', 59, '', '', '', 'Representante legal', 0, '', '2025-11-01 03:31:05', NULL),
(8, 24, 22, 'NATURAL', 40, '', '', '', 'Representante legal', 0, '', '2025-11-16 00:14:54', NULL),
(9, 25, 23, 'NATURAL', 42, '', '', '', 'Representante legal', 0, '', '2025-11-21 05:36:06', NULL),
(10, 23, 21, 'NATURAL', 66, '', '', '', 'Representante legal', 0, '', '2025-11-24 22:59:55', NULL),
(11, 30, 29, 'NATURAL', 71, '', '', '', 'Representante legal', 0, '', '2025-11-25 05:59:02', NULL),
(12, 30, 30, 'NATURAL', 67, '', '', '', 'Representante legal', 0, '', '2025-11-25 05:59:35', NULL),
(13, 32, 36, 'NATURAL', 79, '', '', '', 'Representante legal', 0, '', '2025-12-02 05:35:07', NULL),
(14, 33, 37, 'NATURAL', 88, '', '', '', 'Representante legal', 0, '', '2025-12-10 04:15:24', NULL),
(16, 35, 38, 'JURIDICA', 0, '20602467717', 'EMPRESA DE TRANSPORTES Y SERVICIOSGENERALES N & R S.A.C.', 'MZA. F LOTE. 14 URB. EL SOL DEL CHACARERO I ETAPA LA LIBERTAD -TRUJILLO - TRUJILLO', 'Representante legal', 95, '', '2026-01-11 21:14:00', NULL),
(17, 20, 16, 'NATURAL', 29, '', '', '', 'Representante legal', 0, '', '2026-02-07 18:40:58', NULL),
(18, 36, 39, 'NATURAL', 111, '', '', '', 'Representante legal', 0, '', '2026-03-04 15:08:54', NULL),
(19, 40, 44, 'NATURAL', 137, '', '', '', 'Representante legal', 0, '', '2026-03-17 01:33:04', NULL),
(20, 23, 46, 'JURIDICA', 0, '20550195411', 'Consorcio WLA S.A.C.', 'MZA. R1 LOTE. 38 URB. SAN ANTONIO DE CARAPONGO LIMA - LIMA - LURIGANCHO', 'Representante legal', 142, NULL, '2026-04-13 08:49:45', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_vehiculo`
--

CREATE TABLE `tipos_vehiculo` (
  `id` int NOT NULL,
  `categoria_id` int NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipos_vehiculo`
--

INSERT INTO `tipos_vehiculo` (`id`, `categoria_id`, `codigo`, `nombre`, `descripcion`, `creado_en`) VALUES
(1, 1, 'M1-AUTO', 'Automóvil', 'Transporte de personas, hasta 8 asientos', '2025-09-17 05:33:25'),
(2, 1, 'M1-SUV', 'Camioneta SUV', 'Vehículo multipropósito, hasta 8 asientos', '2025-09-17 05:33:25'),
(3, 1, 'M1-RURAL', 'Camioneta Rural', 'Tipo station wagon, hasta 8 asientos', '2025-09-17 05:33:25'),
(4, 15, 'L5', 'Trimoto de psajeros', NULL, '2025-09-18 05:47:20'),
(5, 13, 'L3', 'Motocicleta', 'Vehiculo de dos ruedas', '2025-09-21 07:02:16'),
(6, 6, 'N3', 'Camión', NULL, '2025-09-29 04:36:47'),
(8, 1, 'M1', 'Multipropósito', '', '2025-10-19 07:44:53'),
(9, 10, 'O4', 'Remolque', '', '2025-10-20 04:19:40'),
(10, 4, 'N1', 'Camioneta Pickup', '', '2025-10-27 04:03:31'),
(11, 3, 'M3', 'Ómnibus', '', '2025-10-30 20:52:57'),
(15, 15, 'L9', 'Trimoto de pasajeros', '', '2025-11-24 21:36:15'),
(20, 3, 'M03', 'Minibús', '', '2026-01-11 21:01:28'),
(23, 6, 'N3r', 'Remolcador', '', '2026-02-05 06:34:37'),
(24, 6, 'O4s', 'Semirremolque', '', '2026-02-05 06:39:03'),
(26, 10, 'O4s2', 'Semirremolque', '', '2026-02-05 06:41:20'),
(28, 1, 'M1-Station Wagon', 'Station Wagon', NULL, '2026-03-08 16:29:31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_diligencia`
--

CREATE TABLE `tipo_diligencia` (
  `id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipo_diligencia`
--

INSERT INTO `tipo_diligencia` (`id`, `nombre`, `descripcion`, `creado_en`) VALUES
(1, 'Protocolo de Necropsia', NULL, '2025-11-25 08:06:24'),
(2, 'Peritaje de constatación de daños', NULL, '2025-11-25 08:06:43'),
(3, 'Camaras de video', NULL, '2025-11-25 08:07:03'),
(4, 'Manifestación', NULL, '2025-11-25 08:07:18'),
(5, 'Informe Policial', NULL, '2025-11-25 08:07:31'),
(6, 'Identificacion de cadaver', NULL, '2025-11-25 08:08:38'),
(7, 'Resultado de dosaje etilico', 'Solicitar el resultado cuantitativo de certificado de dosaje etilico', '2025-12-10 23:12:39'),
(8, 'Inspección Técnico Policial', 'Descripción de la configuración de la via', '2025-12-15 05:18:31'),
(9, 'Visualización de Video', NULL, '2026-02-02 04:15:03'),
(10, 'Croquis demostrativo', NULL, '2026-03-16 15:29:39'),
(11, 'Oficio Solicitar', NULL, '2026-03-16 15:31:28'),
(12, 'Oficio Remitir', NULL, '2026-03-16 15:31:36');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubigeo_departamento`
--

CREATE TABLE `ubigeo_departamento` (
  `cod_dep` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ubigeo_departamento`
--

INSERT INTO `ubigeo_departamento` (`cod_dep`, `nombre`) VALUES
('01', 'Amazonas'),
('02', 'Áncash'),
('03', 'Apurímac'),
('04', 'Arequipa'),
('05', 'Ayacucho'),
('06', 'Cajamarca'),
('07', 'Callao'),
('08', 'Cusco'),
('09', 'Huancavelica'),
('10', 'Huánuco'),
('11', 'Ica'),
('12', 'Junín'),
('13', 'La Libertad'),
('14', 'Lambayeque'),
('15', 'Lima'),
('16', 'Loreto'),
('17', 'Madre de Dios'),
('18', 'Moquegua'),
('19', 'Pasco'),
('20', 'Piura'),
('21', 'Puno'),
('22', 'San Martín'),
('23', 'Tacna'),
('24', 'Tumbes'),
('25', 'Ucayali');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubigeo_distrito`
--

CREATE TABLE `ubigeo_distrito` (
  `cod_dep` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_prov` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_dist` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubigeo6` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lat` decimal(10,7) DEFAULT NULL,
  `lon` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ubigeo_distrito`
--

INSERT INTO `ubigeo_distrito` (`cod_dep`, `cod_prov`, `cod_dist`, `ubigeo6`, `nombre`, `lat`, `lon`) VALUES
('15', '01', '01', '150101', 'Lima', NULL, NULL),
('15', '01', '02', '150102', 'Ancón', NULL, NULL),
('15', '01', '03', '150103', 'Ate', NULL, NULL),
('15', '01', '04', '150104', 'Barranco', NULL, NULL),
('15', '01', '05', '150105', 'Breña', NULL, NULL),
('15', '01', '06', '150106', 'Carabayllo', NULL, NULL),
('15', '01', '07', '150107', 'Chaclacayo', NULL, NULL),
('15', '01', '08', '150108', 'Chorrillos', NULL, NULL),
('15', '01', '09', '150109', 'Cieneguilla', NULL, NULL),
('15', '01', '10', '150110', 'Comas', NULL, NULL),
('15', '01', '11', '150111', 'El Agustino', NULL, NULL),
('15', '01', '12', '150112', 'Independencia', NULL, NULL),
('15', '01', '13', '150113', 'Jesús María', NULL, NULL),
('15', '01', '14', '150114', 'La Molina', NULL, NULL),
('15', '01', '15', '150115', 'La Victoria', NULL, NULL),
('15', '01', '16', '150116', 'Lince', NULL, NULL),
('15', '01', '17', '150117', 'Los Olivos', NULL, NULL),
('15', '01', '18', '150118', 'Lurigancho', NULL, NULL),
('15', '01', '19', '150119', 'Lurín', NULL, NULL),
('15', '01', '20', '150120', 'Magdalena del Mar', NULL, NULL),
('15', '01', '21', '150121', 'Pueblo Libre', NULL, NULL),
('15', '01', '22', '150122', 'Miraflores', NULL, NULL),
('15', '01', '23', '150123', 'Pachacámac', NULL, NULL),
('15', '01', '24', '150124', 'Pucusana', NULL, NULL),
('15', '01', '25', '150125', 'Puente Piedra', NULL, NULL),
('15', '01', '26', '150126', 'Punta Hermosa', NULL, NULL),
('15', '01', '27', '150127', 'Punta Negra', NULL, NULL),
('15', '01', '28', '150128', 'Rímac', NULL, NULL),
('15', '01', '29', '150129', 'San Bartolo', NULL, NULL),
('15', '01', '30', '150130', 'San Borja', NULL, NULL),
('15', '01', '31', '150131', 'San Isidro', NULL, NULL),
('15', '01', '32', '150132', 'San Juan de Lurigancho', NULL, NULL),
('15', '01', '33', '150133', 'San Juan de Miraflores', NULL, NULL),
('15', '01', '34', '150134', 'San Luis', NULL, NULL),
('15', '01', '35', '150135', 'San Martín de Porres', NULL, NULL),
('15', '01', '36', '150136', 'San Miguel', NULL, NULL),
('15', '01', '37', '150137', 'Santa Anita', NULL, NULL),
('15', '01', '38', '150138', 'Santa María del Mar', NULL, NULL),
('15', '01', '39', '150139', 'Santa Rosa', NULL, NULL),
('15', '01', '40', '150140', 'Santiago de Surco', NULL, NULL),
('15', '01', '41', '150141', 'Surquillo', NULL, NULL),
('15', '01', '42', '150142', 'Villa El Salvador', NULL, NULL),
('15', '01', '43', '150143', 'Villa María del Triunfo', NULL, NULL),
('15', '02', '01', '150201', 'Barranca', NULL, NULL),
('15', '02', '02', '150202', 'Paramonga', NULL, NULL),
('15', '02', '03', '150203', 'Pativilca', NULL, NULL),
('15', '02', '04', '150204', 'Supe', NULL, NULL),
('15', '02', '05', '150205', 'Supe Puerto', NULL, NULL),
('15', '03', '01', '150301', 'Cajatambo', NULL, NULL),
('15', '03', '02', '150302', 'Copa', NULL, NULL),
('15', '03', '03', '150303', 'Gorgor', NULL, NULL),
('15', '03', '04', '150304', 'Huancapón', NULL, NULL),
('15', '03', '05', '150305', 'Manás', NULL, NULL),
('15', '04', '01', '150401', 'Canta', NULL, NULL),
('15', '04', '02', '150402', 'Arahuay', NULL, NULL),
('15', '04', '03', '150403', 'Huamantanga', NULL, NULL),
('15', '04', '04', '150404', 'Huaros', NULL, NULL),
('15', '04', '05', '150405', 'Lachaqui', NULL, NULL),
('15', '04', '06', '150406', 'San Buenaventura', NULL, NULL),
('15', '04', '07', '150407', 'Santa Rosa de Quives', NULL, NULL),
('15', '05', '01', '150501', 'San Vicente de Cañete', NULL, NULL),
('15', '05', '02', '150502', 'Asia', NULL, NULL),
('15', '05', '03', '150503', 'Calango', NULL, NULL),
('15', '05', '04', '150504', 'Cerro Azul', NULL, NULL),
('15', '05', '05', '150505', 'Chilca', NULL, NULL),
('15', '05', '06', '150506', 'Coayllo', NULL, NULL),
('15', '05', '07', '150507', 'Imperial', NULL, NULL),
('15', '05', '08', '150508', 'Lunahuaná', NULL, NULL),
('15', '05', '09', '150509', 'Mala', NULL, NULL),
('15', '05', '10', '150510', 'Nuevo Imperial', NULL, NULL),
('15', '05', '11', '150511', 'Pacarán', NULL, NULL),
('15', '05', '12', '150512', 'Quilmana', NULL, NULL),
('15', '05', '13', '150513', 'San Antonio', NULL, NULL),
('15', '05', '14', '150514', 'San Luis', NULL, NULL),
('15', '05', '15', '150515', 'Santa Cruz de Flores', NULL, NULL),
('15', '05', '16', '150516', 'Zúñiga', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ubigeo_provincia`
--

CREATE TABLE `ubigeo_provincia` (
  `cod_dep` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cod_prov` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ubigeo_provincia`
--

INSERT INTO `ubigeo_provincia` (`cod_dep`, `cod_prov`, `nombre`) VALUES
('15', '01', 'Lima'),
('15', '02', 'Barranca'),
('15', '03', 'Cajatambo'),
('15', '04', 'Canta'),
('15', '05', 'Cañete'),
('15', '06', 'Huaral'),
('15', '07', 'Huarochirí'),
('15', '08', 'Huaura'),
('15', '09', 'Oyón'),
('15', '10', 'Yauyos');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('kayiosama','admin','editor','viewer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'viewer',
  `pass_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `email`, `nombre`, `rol`, `pass_hash`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 'admin@uiat', 'Administrador', 'admin', '$2y$10$3a1ieLpffpxnvrphfouAUOMWyiX1AmqG.3qchbopfZB9t/wrT0Sj.', 1, '2025-09-17 17:25:39', '2025-09-17 19:13:37'),
(2, 'gmerinos@uiatnorte.com', 'Giancarlo Merino Sancho', 'viewer', '$2y$10$D3eqCgFGadHAgGHknAxQnuvF28JwTssK1b9JDClT46L.3NkpMfd/K', 1, '2025-09-18 18:25:23', '2025-09-18 18:25:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int NOT NULL,
  `placa` varchar(12) NOT NULL,
  `serie_vin` varchar(25) DEFAULT NULL,
  `nro_motor` varchar(30) DEFAULT NULL,
  `categoria_id` int NOT NULL,
  `tipo_id` int NOT NULL,
  `carroceria_id` int DEFAULT NULL,
  `marca_id` int DEFAULT NULL,
  `modelo_id` int DEFAULT NULL,
  `anio` smallint UNSIGNED DEFAULT NULL,
  `color` varchar(40) DEFAULT NULL,
  `largo_mm` decimal(5,2) DEFAULT NULL,
  `ancho_mm` decimal(5,2) DEFAULT NULL,
  `alto_mm` decimal(5,2) DEFAULT NULL,
  `notas` text,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `vehiculos`
--

INSERT INTO `vehiculos` (`id`, `placa`, `serie_vin`, `nro_motor`, `categoria_id`, `tipo_id`, `carroceria_id`, `marca_id`, `modelo_id`, `anio`, `color`, `largo_mm`, `ancho_mm`, `alto_mm`, `notas`, `creado_en`, `actualizado_en`) VALUES
(7, 'CCO-633', NULL, NULL, 1, 1, 1, 24, 29, 1998, 'Guinda', 4.39, 1.66, 1.36, NULL, '2025-09-29 04:33:51', '2025-09-29 04:33:51'),
(8, 'BWC-778', NULL, NULL, 6, 6, 7, 9, 30, 2023, 'Blanco, azul', 12.50, 2.60, 4.10, NULL, '2025-09-29 04:38:54', '2025-09-29 04:38:54'),
(10, 'A3F-410', NULL, NULL, 1, 1, 1, 1, 3, 2010, 'azul claro metalico', 4.30, 1.69, 1.46, '', '2025-10-12 08:14:41', '2025-10-12 08:14:41'),
(11, '1211-QC', NULL, NULL, 13, 5, 6, 25, 31, 2024, 'Azul', 2.37, 0.91, 1.45, NULL, '2025-10-14 23:43:24', '2025-10-15 14:09:34'),
(12, '9332-KB', NULL, NULL, 13, 5, 6, 26, NULL, 2021, 'Negro Anaranjado', NULL, NULL, NULL, '', '2025-10-16 23:05:57', '2025-10-16 23:05:57'),
(13, 'BWI180', NULL, NULL, 1, 8, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '', '2025-10-19 07:45:45', '2025-10-19 07:45:45'),
(14, 'BJC-846', NULL, NULL, 6, 6, 10, 28, 34, 2019, 'Blanco Anaranjado', 9.10, 2.53, 3.50, NULL, '2025-10-20 04:12:07', '2025-10-20 04:39:12'),
(15, 'ASU-997', NULL, NULL, 10, 9, 8, 27, 33, 2020, 'Anaranjado', 8.60, 2.43, 3.60, NULL, '2025-10-20 04:23:27', '2025-10-20 04:36:44'),
(16, 'AJQ-206', NULL, NULL, 1, 1, 1, 1, 3, 2015, 'Negro Mica', 4.41, 1.70, 1.47, '', '2025-10-20 04:25:50', '2025-10-20 04:25:50'),
(17, 'BLA-148', NULL, NULL, 1, 1, 1, 6, 35, 2020, 'Negro Meet', 4.30, 1.73, 1.50, '', '2025-10-20 06:04:21', '2025-10-20 06:04:21'),
(18, '3278-QC', NULL, NULL, 13, 5, 6, 23, 27, 2024, 'Blanco, rojo', 2.01, 0.80, 1.07, '', '2025-10-20 14:54:35', '2025-10-20 14:54:35'),
(19, '0717-CA', NULL, NULL, 15, 4, 5, 23, 36, 2017, 'Verde', 2.63, 1.30, 1.70, '', '2025-10-26 17:37:02', '2025-10-26 17:37:02'),
(20, 'BKS-508', NULL, NULL, 1, 3, 11, 29, NULL, 2018, 'Negro', 5.08, 1.92, 1.76, '', '2025-10-26 17:40:42', '2025-10-26 17:40:42'),
(21, 'BXG-831', NULL, NULL, 4, 10, 12, 1, 2, 2024, 'Gris oscuro metalico', 5.32, 1.85, 1.81, NULL, '2025-10-27 04:04:25', '2025-10-27 04:05:34'),
(22, 'F8M-819', NULL, NULL, 3, 11, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, '', '2025-10-30 20:53:07', '2025-10-30 20:53:07'),
(23, 'AWP-291', NULL, NULL, 1, 1, 1, 2, 6, 2016, 'Gris Oscuro', 4.49, 1.69, 1.50, '', '2025-11-01 02:19:27', '2025-11-01 02:19:27'),
(24, 'B3-4339', NULL, NULL, 13, 5, 6, 30, 44, 2011, 'ANARANJADO', 2.06, 0.73, 1.18, NULL, '2025-11-01 02:32:47', '2026-01-07 20:26:30'),
(26, '4509-LA', NULL, NULL, 15, 4, 5, 23, 36, 2018, 'Azul', 2.63, 1.30, 1.70, '', '2025-11-24 21:44:05', '2025-11-24 21:44:05'),
(27, '7164-XC', NULL, NULL, 13, 5, 6, 31, 37, 2024, 'Negro, rojo', 1.98, 0.78, 1.42, NULL, '2025-11-24 21:46:08', '2025-11-24 21:48:15'),
(29, '5162-JC', NULL, NULL, 13, 5, 6, 32, 38, 2023, 'Plata', 2.07, 0.82, 1.08, '', '2025-11-29 06:43:25', '2025-11-29 06:43:25'),
(30, 'CDJ-357', NULL, NULL, 1, 1, 1, 6, 39, 2023, 'Negro meet', 4.27, 1.96, 1.47, '', '2025-11-29 06:45:50', '2025-11-29 06:45:50'),
(31, 'BYJ-257', NULL, NULL, 1, 2, 13, 33, 40, 2022, 'Blanco', 4.38, 1.85, 1.64, '', '2025-11-29 06:49:36', '2025-11-29 06:49:36'),
(32, 'F3Q-329', NULL, NULL, 1, 1, 1, 6, 35, 2013, 'Negro', 3.64, 1.59, 1.52, '', '2025-11-29 06:51:46', '2025-11-29 06:51:46'),
(33, 'ABX-053', NULL, NULL, 1, 1, 4, 2, 41, 1995, 'Blanco', 4.30, 1.50, 1.52, '', '2025-11-29 06:56:30', '2025-11-29 06:56:30'),
(34, 'A6B-706', NULL, NULL, 3, 11, 14, 9, 42, 1993, 'Dorado, amarillo, verde', 10.50, 2.50, 3.19, '', '2025-12-02 04:06:23', '2025-12-02 04:06:23'),
(35, '7858-DB', NULL, NULL, 15, 4, 5, 23, 43, 2020, 'Azul', 2.63, 1.30, 1.70, '', '2025-12-10 01:49:43', '2025-12-10 01:49:43'),
(36, 'A8E-798', NULL, NULL, 3, 20, 15, 1, 45, 1997, 'Blanco, amarillo, rojo, azul', 6.99, 2.02, 2.50, '', '2026-01-11 21:02:38', '2026-01-11 21:02:38'),
(37, 'F4P-020', NULL, NULL, 1, 1, 1, 16, 46, 2013, 'Plata', 4.53, 1.70, 1.49, '', '2026-02-01 04:31:22', '2026-02-01 04:31:22'),
(38, 'A8X-844', NULL, NULL, 6, 23, 16, 34, 47, 2007, 'Azul', 6.93, 2.60, 3.29, '', '2026-02-05 06:37:35', '2026-02-05 06:37:35'),
(39, 'C7Q-975', NULL, NULL, 10, 26, 18, 35, 48, 2013, 'Azul', 6.93, 2.60, 3.29, '', '2026-02-05 06:44:59', '2026-02-05 06:44:59'),
(40, 'ANN697', '3N1CM7AD20K399945', 'HR16833613K', 1, 1, 1, 2, 6, 2015, 'NEGRO', 4.47, 1.70, 1.51, '{\"coCateg\":\"Categoria M\",\"descTipoCarr\":\"SEDAN\",\"descTipoUso\":\"Vehiculos Particulares\",\"descTipoComb\":\"GASOLINA\",\"pesoBruto\":\"1.425 tn\",\"fecIns\":\"10\\/03\\/2016 09:04\",\"numPartida\":\"53311414\",\"noVin\":\"3N1CN7AD2GK399945\"}', '2026-03-05 07:01:23', '2026-03-05 07:11:36'),
(54, 'BUZ362', 'LGWEE4A40NH910229', 'GW4G15B2025013851', 1, 1, 3, 37, 49, NULL, 'NEGRO', 4.37, 1.81, 1.71, '{\"coCateg\":\"M1\",\"descTipoCarr\":\"SUV\",\"descTipoUso\":\"Vehiculos Particulares\",\"descTipoComb\":\"BI-COMBUSTIBLE GLP\",\"pesoBruto\":\"2.025 tn\",\"fecIns\":\"13\\/05\\/2021 19:05\",\"numPartida\":\"54490230\",\"noVin\":\"LGWEE4A40NH910229\"}', '2026-03-05 08:01:47', '2026-03-05 08:02:15'),
(56, 'BJY598', 'LS4AFU3A8KG611033', '4G948KTD2000465', 1, 8, 19, 15, 50, 2019, 'MARRON', 5.18, 1.74, 2.20, 'Marca API: CHANGAN | Modelo API: M90 | Carrocería API: MULTIPRÓSITO | Combustible API: BI-COMBUSTIBLE GNV | Fec. Inscripción API: 26/04/2019 21:10 | Acto API: Cambio de Tipo de Uso', '2026-03-05 08:38:42', '2026-03-05 08:38:42'),
(57, 'D6E666', 'CE1066014205', '2C3499571', 1, 28, 20, 1, 51, NULL, 'Blanco', 4.35, 1.67, 1.65, 'Marca API: TOYOTA | Modelo API: COROLLA DX | Carrocería API: SEDAN | Combustible API: PETROLEO | Fec. Inscripción API: 28/11/2002 16:53 | Acto API: Cambio de Color', '2026-03-08 16:31:07', '2026-03-08 16:31:07'),
(58, 'SP-10159282', NULL, NULL, 13, 5, 21, 23, 27, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-12 06:35:41', '2026-03-12 06:35:41'),
(59, '9128-7D', 'MD2A36F28FCB95192', 'JLZCFB93457', 13, 5, 6, 23, 52, 2015, 'BLANCO NEGRO', 2.02, 0.80, 1.20, 'Marca API: BAJAJ | Modelo API: PULSAR 200 NS DECAL | Carrocería API: MOTOCICLETA | Combustible API: GASOLINA | Fec. Inscripción API: 29/08/2016 11:13', '2026-03-13 06:50:23', '2026-03-13 06:50:23');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_export_accidente_involucrados_full`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_export_accidente_involucrados_full` (
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_accidente_personas_vinculadas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_accidente_personas_vinculadas` (
`fuente` varchar(3)
,`fuente_id` bigint
,`accidente_id` bigint
,`tipo_doc` varchar(4)
,`num_doc` varchar(15)
,`nombres` varchar(100)
,`apellido_paterno` varchar(50)
,`apellido_materno` varchar(50)
,`domicilio` varchar(200)
,`fecha_nacimiento` date
,`relacion` varchar(80)
,`extra` varchar(186)
);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `abogados`
--
ALTER TABLE `abogados`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_abogado_persona` (`persona_id`),
  ADD KEY `fk_abogado_accidente` (`accidente_id`);

--
-- Indices de la tabla `accidentes`
--
ALTER TABLE `accidentes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sidpol` (`sidpol`),
  ADD UNIQUE KEY `uq_accidentes_sidpol` (`sidpol`),
  ADD UNIQUE KEY `ux_accidentes_registro_sidpol` (`registro_sidpol`),
  ADD KEY `idx_sidpol` (`sidpol`),
  ADD KEY `idx_fecha` (`fecha_accidente`),
  ADD KEY `idx_distrito` (`cod_dep`,`cod_prov`,`cod_dist`),
  ADD KEY `fk_accidente_comisaria` (`comisaria_id`),
  ADD KEY `idx_fiscalia` (`fiscalia_id`),
  ADD KEY `idx_fiscal` (`fiscal_id`),
  ADD KEY `idx_informe` (`nro_informe_policial`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indices de la tabla `accidente_analisis_imagenes`
--
ALTER TABLE `accidente_analisis_imagenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_accidente_analisis_imagenes_accidente` (`accidente_id`),
  ADD KEY `idx_accidente_analisis_imagenes_section_order` (`accidente_id`,`seccion`,`sort_order`);

--
-- Indices de la tabla `accidente_consecuencia`
--
ALTER TABLE `accidente_consecuencia`
  ADD PRIMARY KEY (`accidente_id`,`consecuencia_id`),
  ADD UNIQUE KEY `uq_acc_con` (`accidente_id`,`consecuencia_id`),
  ADD KEY `idx_ac_consecuencia` (`consecuencia_id`);

--
-- Indices de la tabla `accidente_modalidad`
--
ALTER TABLE `accidente_modalidad`
  ADD PRIMARY KEY (`accidente_id`,`modalidad_id`),
  ADD UNIQUE KEY `uq_acc_mod` (`accidente_id`,`modalidad_id`),
  ADD KEY `idx_am_modalidad` (`modalidad_id`);

--
-- Indices de la tabla `api_persona_cache`
--
ALTER TABLE `api_persona_cache`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD KEY `dni_2` (`dni`);

--
-- Indices de la tabla `carroceria_vehiculo`
--
ALTER TABLE `carroceria_vehiculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipo_nombre` (`tipo_id`,`nombre`),
  ADD KEY `idx_tipo` (`tipo_id`);

--
-- Indices de la tabla `categoria_vehiculos`
--
ALTER TABLE `categoria_vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `citacion`
--
ALTER TABLE `citacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_cit_acc` (`accidente_id`),
  ADD KEY `ix_cit_fuente` (`fuente`,`fuente_id`),
  ADD KEY `ix_cit_fecha` (`fecha`,`hora`);

--
-- Indices de la tabla `comisarias`
--
ALTER TABLE `comisarias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nombre_tipo` (`nombre`,`tipo`);

--
-- Indices de la tabla `comisaria_distrito`
--
ALTER TABLE `comisaria_distrito`
  ADD PRIMARY KEY (`comisaria_id`,`cod_dep`,`cod_prov`,`cod_dist`),
  ADD UNIQUE KEY `uq_comisaria_ubigeo` (`comisaria_id`,`cod_dep`,`cod_prov`,`cod_dist`),
  ADD KEY `idx_distrito` (`cod_dep`,`cod_prov`,`cod_dist`),
  ADD KEY `idx_comisaria` (`comisaria_id`);

--
-- Indices de la tabla `consecuencia_accidente`
--
ALTER TABLE `consecuencia_accidente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `uq_consecuencia_nombre` (`nombre`);

--
-- Indices de la tabla `diligencias_pendientes`
--
ALTER TABLE `diligencias_pendientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dp_accidente` (`accidente_id`),
  ADD KEY `fk_dp_citacion` (`citacion_id`),
  ADD KEY `fk_dp_oficio` (`oficio_id`),
  ADD KEY `fk_dp_tipo_diligencia` (`tipo_diligencia_id`);

--
-- Indices de la tabla `documentos_recibidos`
--
ALTER TABLE `documentos_recibidos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doc_accidente` (`accidente_id`),
  ADD KEY `idx_doc_oficio` (`referencia_oficio_id`);

--
-- Indices de la tabla `documento_dosaje`
--
ALTER TABLE `documento_dosaje`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dosaje_persona` (`persona_id`);

--
-- Indices de la tabla `documento_lc`
--
ALTER TABLE `documento_lc`
  ADD PRIMARY KEY (`id`),
  ADD KEY `persona_id` (`persona_id`);

--
-- Indices de la tabla `documento_occiso`
--
ALTER TABLE `documento_occiso`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_occiso_persona` (`persona_id`),
  ADD KEY `fk_occiso_accidente` (`accidente_id`);

--
-- Indices de la tabla `documento_rml`
--
ALTER TABLE `documento_rml`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_rml_persona_numero` (`persona_id`,`numero`),
  ADD KEY `idx_rml_numero` (`numero`),
  ADD KEY `idx_rml_persona` (`persona_id`),
  ADD KEY `idx_rml_accidente` (`accidente_id`);

--
-- Indices de la tabla `documento_vehiculo`
--
ALTER TABLE `documento_vehiculo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_docveh_invol` (`involucrado_vehiculo_id`),
  ADD KEY `fk_docveh_vehiculo` (`vehiculo_id`);

--
-- Indices de la tabla `enlace_interes`
--
ALTER TABLE `enlace_interes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_enlace_interes_categoria` (`categoria`),
  ADD KEY `idx_enlace_interes_activo` (`activo`),
  ADD KEY `idx_enlace_interes_orden` (`orden`);

--
-- Indices de la tabla `familiar_fallecido`
--
ALTER TABLE `familiar_fallecido`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rel` (`accidente_id`,`fallecido_inv_id`,`familiar_persona_id`),
  ADD KEY `idx_acc` (`accidente_id`),
  ADD KEY `idx_fall` (`fallecido_inv_id`),
  ADD KEY `idx_fam` (`familiar_persona_id`);

--
-- Indices de la tabla `fiscales`
--
ALTER TABLE `fiscales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_fiscalia` (`fiscalia_id`);

--
-- Indices de la tabla `fiscalia`
--
ALTER TABLE `fiscalia`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `grado_cargo`
--
ALTER TABLE `grado_cargo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tipo_nombre` (`tipo`,`nombre`);

--
-- Indices de la tabla `involucrados_personas`
--
ALTER TABLE `involucrados_personas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_acc_per_rol` (`accidente_id`,`persona_id`,`rol_id`),
  ADD KEY `idx_ip_acc` (`accidente_id`),
  ADD KEY `idx_ip_per` (`persona_id`),
  ADD KEY `idx_ip_veh` (`vehiculo_id`),
  ADD KEY `fk_ip_rol` (`rol_id`),
  ADD KEY `idx_ip_orden` (`accidente_id`,`vehiculo_id`,`orden_persona`);

--
-- Indices de la tabla `involucrados_vehiculos`
--
ALTER TABLE `involucrados_vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_acc_veh` (`accidente_id`,`vehiculo_id`),
  ADD KEY `idx_iv_acc` (`accidente_id`),
  ADD KEY `idx_iv_veh` (`vehiculo_id`);

--
-- Indices de la tabla `itp`
--
ALTER TABLE `itp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `accidente_id` (`accidente_id`);

--
-- Indices de la tabla `Manifestacion`
--
ALTER TABLE `Manifestacion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_manifest_accidente` (`accidente_id`),
  ADD KEY `fk_manifest_persona` (`persona_id`);

--
-- Indices de la tabla `marcas_vehiculo`
--
ALTER TABLE `marcas_vehiculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `modalidad_accidente`
--
ALTER TABLE `modalidad_accidente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD UNIQUE KEY `uq_modalidad_nombre` (`nombre`);

--
-- Indices de la tabla `modelos_vehiculo`
--
ALTER TABLE `modelos_vehiculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_marca_modelo` (`marca_id`,`nombre`),
  ADD KEY `idx_marca` (`marca_id`);

--
-- Indices de la tabla `oficios`
--
ALTER TABLE `oficios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_oficios_anio_numero` (`anio`,`numero`),
  ADD KEY `fk_ofi_dest_subent` (`subentidad_destino_id`),
  ADD KEY `fk_ofi_dest_persona` (`persona_destino_id`),
  ADD KEY `fk_ofi_oficial_ano` (`oficial_ano_id`),
  ADD KEY `idx_ofi_entidad` (`entidad_id_destino`),
  ADD KEY `idx_ofi_asunto` (`asunto_id`),
  ADD KEY `idx_ofi_estado` (`estado`),
  ADD KEY `idx_ofi_fecha` (`fecha_emision`),
  ADD KEY `fk_ofi_accidente` (`accidente_id`),
  ADD KEY `fk_oficio_involpersona` (`involucrado_persona_id`),
  ADD KEY `fk_oficio_involvehiculo` (`involucrado_vehiculo_id`),
  ADD KEY `idx_oficios_grado` (`grado_cargo_id`);

--
-- Indices de la tabla `oficio_asunto`
--
ALTER TABLE `oficio_asunto`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_ofas_ent_tipo_nombre` (`entidad_id`,`tipo`,`nombre`),
  ADD KEY `idx_ofas_ent_tipo` (`entidad_id`,`tipo`);

--
-- Indices de la tabla `oficio_entidad`
--
ALTER TABLE `oficio_entidad`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `oficio_oficial_ano`
--
ALTER TABLE `oficio_oficial_ano`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `anio` (`anio`);

--
-- Indices de la tabla `oficio_persona_entidad`
--
ALTER TABLE `oficio_persona_entidad`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ope_entidad` (`entidad_id`);

--
-- Indices de la tabla `oficio_subentidad`
--
ALTER TABLE `oficio_subentidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_os_ent_nom` (`entidad_id`,`nombre`),
  ADD KEY `fk_os_parent` (`parent_id`),
  ADD KEY `idx_os_ent_tipo` (`entidad_id`,`tipo`);

--
-- Indices de la tabla `participacion_persona`
--
ALTER TABLE `participacion_persona`
  ADD PRIMARY KEY (`Id`),
  ADD UNIQUE KEY `Nombre` (`Nombre`);

--
-- Indices de la tabla `personas`
--
ALTER TABLE `personas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipo_numdoc` (`tipo_doc`,`num_doc`),
  ADD UNIQUE KEY `uq_persona_doc` (`tipo_doc`,`num_doc`),
  ADD KEY `idx_apellidos` (`apellido_paterno`,`apellido_materno`,`nombres`);

--
-- Indices de la tabla `policial_interviniente`
--
ALTER TABLE `policial_interviniente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_acc_per` (`accidente_id`,`persona_id`),
  ADD KEY `idx_acc` (`accidente_id`),
  ADD KEY `idx_per` (`persona_id`),
  ADD KEY `idx_cip` (`cip`);

--
-- Indices de la tabla `propietario_vehiculo`
--
ALTER TABLE `propietario_vehiculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_prop` (`accidente_id`,`vehiculo_inv_id`,`tipo_propietario`,`propietario_persona_id`,`ruc`),
  ADD KEY `idx_acc` (`accidente_id`),
  ADD KEY `idx_inv` (`vehiculo_inv_id`),
  ADD KEY `idx_nat` (`propietario_persona_id`),
  ADD KEY `idx_rep` (`representante_persona_id`),
  ADD KEY `idx_ruc` (`ruc`);

--
-- Indices de la tabla `tipos_vehiculo`
--
ALTER TABLE `tipos_vehiculo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD UNIQUE KEY `uq_cat_nombre` (`categoria_id`,`nombre`),
  ADD KEY `idx_categoria` (`categoria_id`);

--
-- Indices de la tabla `tipo_diligencia`
--
ALTER TABLE `tipo_diligencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipo_diligencia_nombre` (`nombre`);

--
-- Indices de la tabla `ubigeo_departamento`
--
ALTER TABLE `ubigeo_departamento`
  ADD PRIMARY KEY (`cod_dep`);

--
-- Indices de la tabla `ubigeo_distrito`
--
ALTER TABLE `ubigeo_distrito`
  ADD PRIMARY KEY (`cod_dep`,`cod_prov`,`cod_dist`),
  ADD UNIQUE KEY `uq_ubigeo6` (`ubigeo6`);

--
-- Indices de la tabla `ubigeo_provincia`
--
ALTER TABLE `ubigeo_provincia`
  ADD PRIMARY KEY (`cod_dep`,`cod_prov`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `placa` (`placa`),
  ADD UNIQUE KEY `uk_vehiculos_placa` (`placa`),
  ADD KEY `idx_busqueda` (`placa`,`marca_id`,`modelo_id`),
  ADD KEY `idx_catalogo` (`categoria_id`,`tipo_id`,`carroceria_id`),
  ADD KEY `fk_veh_tipo` (`tipo_id`),
  ADD KEY `fk_veh_carroceria` (`carroceria_id`),
  ADD KEY `fk_veh_marca` (`marca_id`),
  ADD KEY `fk_veh_modelo` (`modelo_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `abogados`
--
ALTER TABLE `abogados`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `accidentes`
--
ALTER TABLE `accidentes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT de la tabla `accidente_analisis_imagenes`
--
ALTER TABLE `accidente_analisis_imagenes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `api_persona_cache`
--
ALTER TABLE `api_persona_cache`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `carroceria_vehiculo`
--
ALTER TABLE `carroceria_vehiculo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `categoria_vehiculos`
--
ALTER TABLE `categoria_vehiculos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT de la tabla `citacion`
--
ALTER TABLE `citacion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT de la tabla `comisarias`
--
ALTER TABLE `comisarias`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `consecuencia_accidente`
--
ALTER TABLE `consecuencia_accidente`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `diligencias_pendientes`
--
ALTER TABLE `diligencias_pendientes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `documentos_recibidos`
--
ALTER TABLE `documentos_recibidos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `documento_dosaje`
--
ALTER TABLE `documento_dosaje`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `documento_lc`
--
ALTER TABLE `documento_lc`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `documento_occiso`
--
ALTER TABLE `documento_occiso`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `documento_rml`
--
ALTER TABLE `documento_rml`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `documento_vehiculo`
--
ALTER TABLE `documento_vehiculo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `enlace_interes`
--
ALTER TABLE `enlace_interes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `familiar_fallecido`
--
ALTER TABLE `familiar_fallecido`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `fiscales`
--
ALTER TABLE `fiscales`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `fiscalia`
--
ALTER TABLE `fiscalia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `grado_cargo`
--
ALTER TABLE `grado_cargo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `involucrados_personas`
--
ALTER TABLE `involucrados_personas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT de la tabla `involucrados_vehiculos`
--
ALTER TABLE `involucrados_vehiculos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT de la tabla `itp`
--
ALTER TABLE `itp`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `Manifestacion`
--
ALTER TABLE `Manifestacion`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT de la tabla `marcas_vehiculo`
--
ALTER TABLE `marcas_vehiculo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT de la tabla `modalidad_accidente`
--
ALTER TABLE `modalidad_accidente`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `modelos_vehiculo`
--
ALTER TABLE `modelos_vehiculo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de la tabla `oficios`
--
ALTER TABLE `oficios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT de la tabla `oficio_asunto`
--
ALTER TABLE `oficio_asunto`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `oficio_entidad`
--
ALTER TABLE `oficio_entidad`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `oficio_oficial_ano`
--
ALTER TABLE `oficio_oficial_ano`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `oficio_persona_entidad`
--
ALTER TABLE `oficio_persona_entidad`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `oficio_subentidad`
--
ALTER TABLE `oficio_subentidad`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `participacion_persona`
--
ALTER TABLE `participacion_persona`
  MODIFY `Id` tinyint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `personas`
--
ALTER TABLE `personas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT de la tabla `policial_interviniente`
--
ALTER TABLE `policial_interviniente`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT de la tabla `propietario_vehiculo`
--
ALTER TABLE `propietario_vehiculo`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `tipos_vehiculo`
--
ALTER TABLE `tipos_vehiculo`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `tipo_diligencia`
--
ALTER TABLE `tipo_diligencia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_export_accidente_involucrados_full`
--
DROP TABLE IF EXISTS `vw_export_accidente_involucrados_full`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cpses_kowihbr8d6`@`localhost` SQL SECURITY DEFINER VIEW `vw_export_accidente_involucrados_full`  AS SELECT `a`.`id` AS `accidente_id`, `a`.`sidpol` AS `sidpol`, `a`.`registro_sidpol` AS `registro_sidpol`, `a`.`lugar` AS `lugar`, `a`.`referencia` AS `referencia`, `ud`.`nombre` AS `cod_dep`, `up`.`nombre` AS `cod_prov`, `udi`.`nombre` AS `cod_dist`, `a`.`comisaria_id` AS `comisaria_id`, `a`.`fecha_accidente` AS `fecha_accidente`, `a`.`estado` AS `estado`, `a`.`fecha_comunicacion` AS `fecha_comunicacion`, `a`.`fecha_intervencion` AS `fecha_intervencion`, `a`.`comunicante_nombre` AS `comunicante_nombre`, `a`.`comunicante_telefono` AS `comunicante_telefono`, `a`.`fiscalia_id` AS `fiscalia_id`, `a`.`fiscal_id` AS `fiscal_id`, `a`.`nro_informe_policial` AS `nro_informe_policial`, `a`.`folder` AS `folder`, `a`.`sentido` AS `sentido`, `a`.`secuencia` AS `secuencia`, `a`.`creado_en` AS `accidente_creado_en`, `a`.`actualizado_en` AS `accidente_actualizado_en`, `a`.`priority` AS `accidente_priority`, `ip`.`id` AS `inv_persona_id`, `ip`.`persona_id` AS `persona_id`, `pp`.`Nombre` AS `rol_id`, `ip`.`orden_persona` AS `orden_persona`, `ip`.`vehiculo_id` AS `vehiculo_id`, `ip`.`lesion` AS `lesion`, `ip`.`observaciones` AS `observaciones`, `ip`.`creado_en` AS `inv_creado_en`, `ip`.`actualizado_en` AS `inv_actualizado_en`, concat_ws(' ',`p`.`apellido_paterno`,`p`.`apellido_materno`,`p`.`nombres`) AS `persona_nombre_completo`, `p`.`tipo_doc` AS `tipo_doc`, `p`.`num_doc` AS `num_doc`, `p`.`sexo` AS `sexo`, `p`.`fecha_nacimiento` AS `fecha_nacimiento`, `p`.`edad` AS `edad`, `p`.`estado_civil` AS `estado_civil`, `p`.`nacionalidad` AS `nacionalidad`, `p`.`departamento_nac` AS `departamento_nac`, `p`.`provincia_nac` AS `provincia_nac`, `p`.`distrito_nac` AS `distrito_nac`, `p`.`domicilio` AS `domicilio`, `p`.`ocupacion` AS `ocupacion`, `p`.`grado_instruccion` AS `grado_instruccion`, `p`.`nombre_padre` AS `nombre_padre`, `p`.`nombre_madre` AS `nombre_madre`, `p`.`celular` AS `celular`, `p`.`email` AS `email`, `p`.`notas` AS `persona_notas`, `v`.`placa` AS `veh_placa`, `v`.`serie_vin` AS `veh_serie_vin`, `v`.`nro_motor` AS `veh_nro_motor`, `v`.`anio` AS `veh_anio`, `v`.`color` AS `veh_color` FROM (((((((`accidentes` `a` left join `involucrados_personas` `ip` on((`ip`.`accidente_id` = `a`.`id`))) left join `personas` `p` on((`p`.`id` = `ip`.`persona_id`))) left join `vehiculos` `v` on((`v`.`id` = `ip`.`vehiculo_id`))) left join `participacion_persona` `pp` on((`pp`.`Id` = `ip`.`rol_id`))) left join `ubigeo_departamento` `ud` on((`ud`.`cod_dep` = `a`.`cod_dep`))) left join `ubigeo_provincia` `up` on(((`up`.`cod_dep` = `a`.`cod_dep`) and (`up`.`cod_prov` = `a`.`cod_prov`)))) left join `ubigeo_distrito` `udi` on(((`udi`.`cod_dep` = `a`.`cod_dep`) and (`udi`.`cod_prov` = `a`.`cod_prov`) and (`udi`.`cod_dist` = `a`.`cod_dist`)))) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_accidente_personas_vinculadas`
--
DROP TABLE IF EXISTS `v_accidente_personas_vinculadas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `v_accidente_personas_vinculadas`  AS SELECT cast('INV' as char charset utf8mb4) AS `fuente`, `ip`.`id` AS `fuente_id`, `ip`.`accidente_id` AS `accidente_id`, (`p`.`tipo_doc` collate utf8mb4_general_ci) AS `tipo_doc`, (`p`.`num_doc` collate utf8mb4_general_ci) AS `num_doc`, (`p`.`nombres` collate utf8mb4_general_ci) AS `nombres`, (`p`.`apellido_paterno` collate utf8mb4_general_ci) AS `apellido_paterno`, (coalesce(`p`.`apellido_materno`,'') collate utf8mb4_general_ci) AS `apellido_materno`, (`p`.`domicilio` collate utf8mb4_general_ci) AS `domicilio`, `p`.`fecha_nacimiento` AS `fecha_nacimiento`, (coalesce(`pr`.`Nombre`,'Relacionado') collate utf8mb4_general_ci) AS `relacion`, (cast('' as char charset utf8mb4) collate utf8mb4_general_ci) AS `extra` FROM ((`involucrados_personas` `ip` join `personas` `p` on((`p`.`id` = `ip`.`persona_id`))) left join `participacion_persona` `pr` on((`pr`.`Id` = `ip`.`rol_id`)))union all select cast('PNP' as char charset utf8mb4) AS `fuente`,`pi`.`id` AS `fuente_id`,`pi`.`accidente_id` AS `accidente_id`,(`p`.`tipo_doc` collate utf8mb4_general_ci) AS `tipo_doc`,(`p`.`num_doc` collate utf8mb4_general_ci) AS `num_doc`,(`p`.`nombres` collate utf8mb4_general_ci) AS `nombres`,(`p`.`apellido_paterno` collate utf8mb4_general_ci) AS `apellido_paterno`,(coalesce(`p`.`apellido_materno`,'') collate utf8mb4_general_ci) AS `apellido_materno`,(`p`.`domicilio` collate utf8mb4_general_ci) AS `domicilio`,NULL AS `fecha_nacimiento`,(cast('Efectivo policial' as char charset utf8mb4) collate utf8mb4_general_ci) AS `relacion`,(concat('Grado: ',coalesce(`pi`.`grado_policial`,''),' - Dep.: ',coalesce(`pi`.`dependencia_policial`,'')) collate utf8mb4_general_ci) AS `extra` from (`policial_interviniente` `pi` join `personas` `p` on((`p`.`id` = `pi`.`persona_id`))) union all select cast('PRO' as char charset utf8mb4) AS `fuente`,`pv`.`id` AS `fuente_id`,`pv`.`accidente_id` AS `accidente_id`,(`p`.`tipo_doc` collate utf8mb4_general_ci) AS `tipo_doc`,(`p`.`num_doc` collate utf8mb4_general_ci) AS `num_doc`,(`p`.`nombres` collate utf8mb4_general_ci) AS `nombres`,(`p`.`apellido_paterno` collate utf8mb4_general_ci) AS `apellido_paterno`,(coalesce(`p`.`apellido_materno`,'') collate utf8mb4_general_ci) AS `apellido_materno`,(`p`.`domicilio` collate utf8mb4_general_ci) AS `domicilio`,NULL AS `fecha_nacimiento`,(cast('Propietario del vehiculo' as char charset utf8mb4) collate utf8mb4_general_ci) AS `relacion`,(coalesce(`pv`.`rol_legal`,'') collate utf8mb4_general_ci) AS `extra` from (`propietario_vehiculo` `pv` join `personas` `p` on((`p`.`id` = `pv`.`propietario_persona_id`))) union all select cast('FAM' as char charset utf8mb4) AS `fuente`,`ff`.`id` AS `fuente_id`,`ff`.`accidente_id` AS `accidente_id`,(`p`.`tipo_doc` collate utf8mb4_general_ci) AS `tipo_doc`,(`p`.`num_doc` collate utf8mb4_general_ci) AS `num_doc`,(`p`.`nombres` collate utf8mb4_general_ci) AS `nombres`,(`p`.`apellido_paterno` collate utf8mb4_general_ci) AS `apellido_paterno`,(coalesce(`p`.`apellido_materno`,'') collate utf8mb4_general_ci) AS `apellido_materno`,(`p`.`domicilio` collate utf8mb4_general_ci) AS `domicilio`,NULL AS `fecha_nacimiento`,(coalesce(`ff`.`parentesco`,'Familiar mas cercano') collate utf8mb4_general_ci) AS `relacion`,(cast('' as char charset utf8mb4) collate utf8mb4_general_ci) AS `extra` from (`familiar_fallecido` `ff` join `personas` `p` on((`p`.`id` = `ff`.`familiar_persona_id`)))  ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
