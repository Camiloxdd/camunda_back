-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: req_camunda
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctrine_migration_versions`
--

LOCK TABLES `doctrine_migration_versions` WRITE;
/*!40000 ALTER TABLE `doctrine_migration_versions` DISABLE KEYS */;
INSERT INTO `doctrine_migration_versions` VALUES ('DoctrineMigrations\\Version20251112144951','2025-11-12 14:50:04',516);
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messenger_messages`
--

DROP TABLE IF EXISTS `messenger_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messenger_messages` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `headers` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue_name` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `available_at` datetime NOT NULL COMMENT '(DC2Type:datetime_immutable)',
  `delivered_at` datetime DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
  PRIMARY KEY (`id`),
  KEY `IDX_75EA56E0FB7336F0` (`queue_name`),
  KEY `IDX_75EA56E0E3BD61CE` (`available_at`),
  KEY `IDX_75EA56E016BA31DB` (`delivered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messenger_messages`
--

LOCK TABLES `messenger_messages` WRITE;
/*!40000 ALTER TABLE `messenger_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messenger_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `cuenta_contable` varchar(50) DEFAULT NULL,
  `centro_costo` varchar(50) DEFAULT NULL,
  `grupo` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'Laptop Dell XPS 13','Portátil para trabajo de oficina y desarrollo','1101','1101','tec'),(2,'Monitor LG 27\"','Monitor LED 27 pulgadas Full HD','1102','1102','tec'),(3,'Teclado Mecánico Logitech','Teclado mecánico retroiluminado','1103','1103','tec'),(4,'Mouse Inalámbrico Microsoft','Mouse ergonómico inalámbrico','1104','1104','tec'),(5,'Laptop Dell XPS 13','Portátil para trabajo de oficina y desarrollo','1101','1101','tec'),(6,'Monitor LG 27\"','Monitor LED 27 pulgadas Full HD','1102','1102','tec'),(7,'Teclado Mecánico Logitech','Teclado mecánico retroiluminado','1103','1103','tec'),(8,'Mouse Inalámbrico Microsoft','Mouse ergonómico inalámbrico','1104','1104','tec'),(9,'Silla Ergonómica Ergosupport','Silla con soporte lumbar ajustable para postura saludable','2101','2101','erg'),(10,'Reposapiés Ergonómico Ajustable','Reposapiés con inclinación regulable para mejorar circulación','2102','2102','erg'),(11,'Soporte de Monitor Ajustable','Elevador de monitor para mejorar la postura del cuello','2103','2102','erg'),(12,'Almohadilla Ergonómica para Muñeca','Almohadilla de gel para apoyo de muñeca en teclado o mouse','2104','2104','erg'),(13,'Laptop Dell XPS 13','Laptop para trabajo operativo y desarrollo','1101','1101','tec'),(14,'Monitor LG 27\"','Monitor LED 27 pulgadas Full HD','1102','1102','tec'),(15,'Mouse Inalámbrico Logitech','Mouse ergonómico inalámbrico','1104','1104','tec'),(16,'Teclado Mecánico Logitech','Teclado mecánico retroiluminado','1103','1103','tec'),(17,'Silla Ergonómica Ergosupport','Silla para soporte lumbar regulable','2101','2101','erg'),(18,'Reposapiés Ajustable','Reposapiés con inclinación regulable','2102','2102','erg'),(19,'Soporte de Monitor Ajustable','Elevador de monitor para mejorar postura','2103','2103','erg'),(20,'Resma Papel Carta 500 hojas','Papel tamaño carta 75g','3101','3101','pap'),(21,'Bolígrafo Azul x12','Caja de bolígrafos punta fina','3102','3102','pap'),(22,'Cuaderno Profesional 100 hojas','Cuaderno argollado profesional','3103','3103','pap'),(23,'Café en grano 1kg','Café para máquina de oficina','4101','4101','caf'),(24,'Azúcar refinada 1kg','Azúcar para cafetería','4102','4102','caf'),(25,'Vasos térmicos x50','Vasos desechables térmicos','4103','4103','caf');
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requisicion_aprobaciones`
--

DROP TABLE IF EXISTS `requisicion_aprobaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisicion_aprobaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `requisicion_id` int NOT NULL,
  `area` varchar(100) DEFAULT NULL,
  `rol_aprobador` varchar(100) DEFAULT NULL,
  `nombre_aprobador` varchar(150) DEFAULT NULL,
  `aprobado` tinyint(1) DEFAULT '0',
  `fecha_aprobacion` datetime DEFAULT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `aprob_dic_typ` tinyint(1) DEFAULT '0',
  `aprob_dic_sst` tinyint(1) DEFAULT '0',
  `aprob_ger_typ` tinyint(1) DEFAULT '0',
  `aprob_ger_sst` tinyint(1) DEFAULT '0',
  `aprob_ger_admin` tinyint(1) DEFAULT '0',
  `aprob_ger_gral` tinyint(1) DEFAULT '0',
  `orden` int DEFAULT '0',
  `visible` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `requisicion_id` (`requisicion_id`),
  CONSTRAINT `requisicion_aprobaciones_ibfk_1` FOREIGN KEY (`requisicion_id`) REFERENCES `requisiciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=618 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisicion_aprobaciones`
--

LOCK TABLES `requisicion_aprobaciones` WRITE;
/*!40000 ALTER TABLE `requisicion_aprobaciones` DISABLE KEYS */;
INSERT INTO `requisicion_aprobaciones` VALUES (614,189,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,NULL,'pendiente',0,0,0,0,0,0,1,1),(615,189,'TyP','gerTyC','Wilson Ricardo Marulanda',0,NULL,'pendiente',0,0,0,0,0,0,2,0),(616,190,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,'2025-12-11 08:18:39','aprobada',0,0,0,0,0,0,1,0),(617,190,'TyP','gerTyC','Wilson Ricardo Marulanda',0,'2025-12-11 08:22:07','aprobada',0,0,0,0,0,0,2,0);
/*!40000 ALTER TABLE `requisicion_aprobaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requisicion_productos`
--

DROP TABLE IF EXISTS `requisicion_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisicion_productos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `requisicion_id` int NOT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `cantidad` int DEFAULT '1',
  `fecha_aprobado` varchar(100) DEFAULT NULL,
  `descripcion` text,
  `compra_tecnologica` tinyint(1) DEFAULT '0',
  `ergonomico` tinyint(1) DEFAULT '0',
  `visible` tinyint DEFAULT NULL,
  `valor_estimado` decimal(12,2) DEFAULT NULL,
  `centro_costo` varchar(100) DEFAULT NULL,
  `cuenta_contable` varchar(100) DEFAULT NULL,
  `aprobado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  KEY `requisicion_id` (`requisicion_id`),
  CONSTRAINT `requisicion_productos_ibfk_1` FOREIGN KEY (`requisicion_id`) REFERENCES `requisiciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=416 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisicion_productos`
--

LOCK TABLES `requisicion_productos` WRITE;
/*!40000 ALTER TABLE `requisicion_productos` DISABLE KEYS */;
INSERT INTO `requisicion_productos` VALUES (413,189,'Laptop Dell XPS 13',1,NULL,'',1,0,NULL,2150000.00,'1101','1101',NULL),(414,189,'Teclado Mecánico Logitech',1,NULL,'',1,0,NULL,1500000.00,'1103','1103',NULL),(415,190,'Laptop Dell XPS 13',1,'2025-12-11T13:21:54.532Z','',1,0,NULL,500000.00,'1101','1101','aprobado');
/*!40000 ALTER TABLE `requisicion_productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `requisiciones`
--

DROP TABLE IF EXISTS `requisiciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `requisiciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_requisicion` varchar(255) DEFAULT NULL,
  `nombre_solicitante` varchar(150) NOT NULL,
  `fecha` date NOT NULL,
  `fecha_requerido_entrega` date DEFAULT NULL,
  `tiempoAproximadoGestion` varchar(60) DEFAULT NULL,
  `justificacion` text,
  `area` varchar(100) DEFAULT NULL,
  `sede` varchar(100) DEFAULT NULL,
  `urgencia` varchar(50) DEFAULT NULL,
  `presupuestada` tinyint(1) DEFAULT '0',
  `valor_total` int DEFAULT NULL,
  `process_instance_key` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pendiente',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=191 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisiciones`
--

LOCK TABLES `requisiciones` WRITE;
/*!40000 ALTER TABLE `requisiciones` DISABLE KEYS */;
INSERT INTO `requisiciones` VALUES (189,NULL,'Juan Camilo Bello Roa','2025-12-11','2025-12-12','1','','TyP','cota','Baja',0,3650000,'2251799813686778','pendiente','2025-12-11 13:09:24'),(190,NULL,'Juan Camilo Bello Roa','2025-12-11','2025-12-13','1','','TyP','cota','Baja',0,500000,'2251799813689425','Aprobada','2025-12-11 13:16:37');
/*!40000 ALTER TABLE `requisiciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  `correo` varchar(255) DEFAULT NULL,
  `contraseña` varchar(255) DEFAULT NULL,
  `cargo` varchar(255) DEFAULT NULL,
  `telefono` varchar(255) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `sede` varchar(255) DEFAULT NULL,
  `super_admin` tinyint(1) DEFAULT '0',
  `aprobador` tinyint(1) DEFAULT '0',
  `solicitante` tinyint(1) DEFAULT '0',
  `comprador` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (3,'Nelly Paola Coca Sierra','n.coca@coopidrogas.com.co','$2b$10$42fHo1pc3yXs2W470SqGL.GXLhj85p/12B0OpCV3s6tYjfyngIvxS','dicSST','','SST','cota',0,1,0,0),(5,'Daniel Alejandro Quiros Bertocchi','gerenciaGeneralTesting@coopidrogas.com.co','$2b$10$y5XPDf1quHo0C6R3e0/c8ulhRh1qLk6vddZr4h8WR5QXynQztKxpG','gerGeneral','3224399893','GerenciaGeneral','cota',0,1,0,0),(6,'Diego Alfonso Diaz Devia','d.diaz@coopidrogas.com.co','$2b$10$7gDyQfoc.HzNG/z.9jLIzuEsFS/7WFFxN0RpyYvJ8a2hZu9gQb6Tu','dicTYP','3125874818','TyP','cota',0,1,0,0),(7,'Wilson Ricardo Marulanda','w.marulanda@coopidrogas.com.co','$2b$10$RPL79cMse7ZT5UeDFUSukOlb3p7AnzKX3ZkIF86pXAvU0q5Ik1wXe','gerTyC','123456789','TyP','cota',0,1,0,0),(8,'Juan Camilo Bello Roa','pract7.desarrollo@coopidrogas.com.co','$2b$10$HYbiZaqdninuxTJScI0ceOP1gs0MTWlvY9jV7TMiMBXMLhEz6Pqza','analistaQA','','TyP','cota',1,0,1,0),(9,'Carlos Alfonso López Vargas','gerenciaAdminTesting@coopidrogas.com.co','$2b$10$amRejwiMpAQwoSUZHz208OrITxycmf1kh8/1C452W6EUxuPfT4LW.','gerAdmin','ASDASD','GerenciaAdmin','cota',0,1,0,0),(12,'Santiago Barinas','pract6.desarrollo@coopidrogas.com.co','$2b$10$ZZulhbPzhN0wVT91iva4yuoOb8jpXueLejQtpecHV2CHb0Mufa5x6','analistaQA','3214961814','TyP','cota',0,0,1,0),(13,'Edison Kenneth Campos Avila','k.campos@coopidrogas.com.co','$2b$10$iNmYe5pnKJg.YxPk0bU7vOE77vNqJGPCat5oWFawJutnG.8kuyTxm','dicSST','3224399893','SST','cota',0,1,0,0),(14,'Armando Puertas','elpepe@gmail.com','$2y$13$svXDaZYXAOoSo5rFvNHB/.xaULUxvmuGvcC1YB83xC1Ms9EwzXVX2','analistaQA','3224399893','TyP','cota',0,0,0,1);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-12-12 15:44:36
