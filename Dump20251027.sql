-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: requisiciones
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
-- Table structure for table `formularios`
--

DROP TABLE IF EXISTS `formularios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `formularios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  `fechaSolicitud` date DEFAULT NULL,
  `fechaEntrega` date DEFAULT NULL,
  `justificacion` text,
  `area` varchar(255) DEFAULT NULL,
  `sede` varchar(255) DEFAULT NULL,
  `urgenciaCompra` varchar(100) DEFAULT NULL,
  `tiempoGestion` varchar(100) DEFAULT NULL,
  `anexos` text,
  `nombreSolicitante` varchar(255) DEFAULT NULL,
  `firmaSolicitante` varchar(255) DEFAULT NULL,
  `nombreAdministrativo` varchar(255) DEFAULT NULL,
  `firmaAdministrativo` varchar(255) DEFAULT NULL,
  `nombreGerente` varchar(255) DEFAULT NULL,
  `firmaGerente` varchar(255) DEFAULT NULL,
  `autorizacionGerencia` varchar(255) DEFAULT NULL,
  `firmaCompras` varchar(255) DEFAULT NULL,
  `creadoEn` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `formularios`
--

LOCK TABLES `formularios` WRITE;
/*!40000 ALTER TABLE `formularios` DISABLE KEYS */;
INSERT INTO `formularios` VALUES (14,'tester','2025-10-06','2025-10-07','tester','tester','tester','tester','tester','tester','Camilo Bello','Camilo Bello','fulanito','tester','Kenneth Campos','Kenneth Campos','tester','tester','2025-10-06 15:59:24'),(15,'Juan Camilo Bello Roa','2025-10-06','2025-10-08','Compra de equipo para trabajador nuevo','Tecnologia y Proyectos','Principal','Alta','15 minutos','Si','Kenneth Campos','Kenneth Campos','Wilson Marulanda','Wilson Marulanda','Wilson Marulanda','Wilson Marulanda','Camilo Bello','Wilson Marulanda','2025-10-06 16:48:28'),(16,'Juan Camilo Bello Roa','2025-10-06','2025-10-09','Compra de equipo para trabajador nuevo','Tecnologia y Proyectos','Principal','Alta','15 minutos','Si','tester','tester','testertester','tester','','','','','2025-10-06 17:03:59'),(17,'Edison Kenneth Campos','2025-10-06','2025-10-06','Compra de equipo para trabajador nuevo','Tecnologia y Proyectos','Principal','Alta','15 minutos','Si','Kenneth Campos','Kenneth Campos','Kenneth Campos','Kenneth Campos','','','','','2025-10-06 17:10:11'),(18,'Juan Camilo Bello Roa','2025-10-06','2025-10-07','Compra de equipo para trabajador nuevo','Tecnologia y Proyectos','Principal','Alta','15 minutos','Si','asd','asd','asd','asd','asd','asd','asd','asd','2025-10-06 17:28:31'),(19,'Bello Roa Juan Camilo','2025-10-06','2025-10-06','Compra de equipo para trabajador nuevo','Tecnologia y Proyectos','Principal','Alta','15 minutos','Si','tester','tester','tester','tester','tester','tester','tester','tester','2025-10-06 19:08:25'),(20,'tester','2025-10-06','2025-10-06','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','2025-10-06 19:34:33'),(21,'tester','2025-10-06','2025-10-07','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','','','','','2025-10-06 20:23:05'),(22,'tester','2025-10-07','2025-10-07','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','2025-10-07 20:35:27'),(23,'tester','2025-10-08','2025-10-08','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','2025-10-08 12:54:43'),(24,'tester','2025-10-08','2025-10-08','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','2025-10-08 12:56:26'),(25,'tester','2025-10-08','2025-10-08','tester','tester','tester','tester','tester','tester','Kenneth Campos','Kenneth Campos','Kenneth Campos','Kenneth Campos','Kenneth Campos','Kenneth Campos','Kenneth Campos','Kenneth Campos','2025-10-08 13:51:05'),(26,'tester','2025-10-08','2025-10-08','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','tester','2025-10-08 14:00:17');
/*!40000 ALTER TABLE `formularios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items_formulario`
--

DROP TABLE IF EXISTS `items_formulario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `items_formulario` (
  `id` int NOT NULL AUTO_INCREMENT,
  `formulario_id` int DEFAULT NULL,
  `descripcion` text,
  `cantidad` int DEFAULT NULL,
  `centro` varchar(255) DEFAULT NULL,
  `cuenta` varchar(255) DEFAULT NULL,
  `valor` decimal(15,2) DEFAULT NULL,
  `vobo` tinyint DEFAULT NULL,
  `productoOServicio` varchar(255) DEFAULT NULL,
  `purchaseAprobated` tinyint DEFAULT NULL,
  `siExiste` tinyint DEFAULT NULL,
  `sstAprobacion` tinyint DEFAULT NULL,
  `aprobatedStatus` tinyint DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `formulario_id` (`formulario_id`),
  CONSTRAINT `items_formulario_ibfk_1` FOREIGN KEY (`formulario_id`) REFERENCES `formularios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items_formulario`
--

LOCK TABLES `items_formulario` WRITE;
/*!40000 ALTER TABLE `items_formulario` DISABLE KEYS */;
INSERT INTO `items_formulario` VALUES (49,14,'PC para trabajo',1,'999','87546',7500000.00,1,NULL,NULL,NULL,NULL,NULL),(50,14,'Silla para trabajador',1,'999','84579',25000000.00,0,NULL,NULL,NULL,NULL,NULL),(51,14,'Mouse para trabajo',1,'999','28234',700000.00,1,NULL,NULL,NULL,NULL,NULL),(58,15,'PC para trabajador',1,'854','98645',7500000.00,0,NULL,NULL,NULL,NULL,NULL),(59,15,'pantalla para trabajador',1,'652','53154',15000000.00,0,NULL,NULL,NULL,NULL,NULL),(60,15,'Mouse para trabajador',1,'685','75689',120000.00,0,NULL,NULL,NULL,NULL,NULL),(65,17,'asd',1,'788754','4567',500000.00,0,NULL,NULL,NULL,NULL,NULL),(66,17,'asd',1,'4564','14231',500000.00,0,NULL,NULL,NULL,NULL,NULL),(67,16,'asd',1,'8569','254136',800000.00,0,NULL,NULL,NULL,NULL,NULL),(68,16,'asd',1,'9874','589647',2500000.00,1,NULL,NULL,NULL,NULL,NULL),(69,18,'sad',1,'45123','45234',15000000.00,1,'PC',0,0,0,NULL),(70,18,'asd',1,'4563453','123123',25000000.00,0,'Silla',1,1,1,NULL),(71,19,'asd',1,'7878','7878',1500000.00,1,'PC',0,0,0,0),(72,19,'asd',1,'7878','7878',2500000.00,0,'Silla',1,1,1,0),(73,20,'tester',1,'7845','9865',5000000.00,1,'PC',0,0,0,1),(74,20,'tester',1,'8754','5689',15000000.00,0,'Silla',1,1,1,0),(75,21,'tester',1,'tester','tester',100000.00,0,'tester',1,1,1,0),(76,22,'tester',1,'tester','tester',1800000.00,1,'tester',0,0,0,0),(77,22,'tester',1,'tester','tester',15000000.00,0,'tester',1,1,1,0),(78,23,'tester',1,'tester','tester',1800000.00,1,'tester',0,0,0,1),(79,24,'tester',1,'tester','tester',1800000.00,1,'tester',0,0,0,1),(80,25,'tester',1,'87456','87456',1800000.00,1,'PC',0,0,0,1),(81,25,'tester',1,'87456','87456',25000000.00,0,'Silla',1,1,0,0),(82,25,'tester',1,'87456','87456',700000.00,0,'Mouse',0,0,0,0),(83,25,'tester',1,'87456','87456',1950000.00,0,'Apoya Pies',1,1,0,0),(84,26,'tester',1,'tester','tester',1500000.00,0,'tester',1,1,0,1),(85,26,'tester',1,'tester','tester',1500000.00,1,'tester',0,0,0,1),(86,26,'tester',1,'tester','tester',1500000.00,0,'tester',1,1,1,1),(87,26,'tester',1,'tester','tester',1500000.00,0,'tester',0,0,0,1);
/*!40000 ALTER TABLE `items_formulario` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=321 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisicion_aprobaciones`
--

LOCK TABLES `requisicion_aprobaciones` WRITE;
/*!40000 ALTER TABLE `requisicion_aprobaciones` DISABLE KEYS */;
INSERT INTO `requisicion_aprobaciones` VALUES (309,85,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,'2025-10-27 14:02:19','aprobada',0,0,0,0,0,0,1,0),(310,85,'TyP','gerTyC','Wilson Ricardo Marulanda Niño',0,'2025-10-27 14:02:46','aprobada',0,0,0,0,0,0,2,0),(311,85,'SST','dicSST','Nelly Paola Coca Sierra',0,'2025-10-27 14:03:02','aprobada',0,0,0,0,0,0,3,0),(312,85,'SST','gerSST','Edison Kenneth Campos Avila',0,'2025-10-27 14:03:14','aprobada',0,0,0,0,0,0,4,0),(313,85,'GerenciaAdmin','gerAdmin','Carlos Alfonso López Vargas',0,'2025-10-27 14:03:24','aprobada',0,0,0,0,0,0,5,0),(314,85,'GerenciaGeneral','gerGeneral','Daniel Alejandro Quiros Bertocchi',0,'2025-10-27 14:03:35','aprobada',0,0,0,0,0,0,6,0),(315,86,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,'2025-10-27 15:07:03','aprobada',0,0,0,0,0,0,1,0),(316,86,'TyP','gerTyC','Wilson Ricardo Marulanda Niño',0,'2025-10-27 15:07:32','aprobada',0,0,0,0,0,0,2,0),(317,86,'SST','dicSST','Nelly Paola Coca Sierra',0,'2025-10-27 15:07:48','aprobada',0,0,0,0,0,0,3,0),(318,86,'SST','gerSST','Edison Kenneth Campos Avila',0,'2025-10-27 15:07:55','aprobada',0,0,0,0,0,0,4,0),(319,86,'GerenciaAdmin','gerAdmin','Carlos Alfonso López Vargas',0,'2025-10-27 15:08:21','aprobada',0,0,0,0,0,0,5,0),(320,86,'GerenciaGeneral','gerGeneral','Daniel Alejandro Quiros Bertocchi',0,'2025-10-27 15:08:55','aprobada',0,0,0,0,0,0,6,0);
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
  `valor_estimado` decimal(12,2) DEFAULT NULL,
  `centro_costo` varchar(100) DEFAULT NULL,
  `cuenta_contable` varchar(100) DEFAULT NULL,
  `aprobado` enum('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  KEY `requisicion_id` (`requisicion_id`),
  CONSTRAINT `requisicion_productos_ibfk_1` FOREIGN KEY (`requisicion_id`) REFERENCES `requisiciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisicion_productos`
--

LOCK TABLES `requisicion_productos` WRITE;
/*!40000 ALTER TABLE `requisicion_productos` DISABLE KEYS */;
INSERT INTO `requisicion_productos` VALUES (129,85,'PC',1,'2025-10-27T19:03:35.071Z','PC',1,0,15000000.00,'123456','123456','aprobado'),(130,85,'Silla',1,'2025-10-27T19:03:35.071Z','Silla',0,1,15000000.00,'123456','123456','aprobado'),(131,86,'PC',1,'2025-10-27T20:08:55.323Z','PC',1,0,15000000.00,'asd','asd','aprobado'),(132,86,'Silla',1,'2025-10-27T20:08:55.323Z','Silla',0,1,15000000.00,'asd','asd','aprobado');
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) DEFAULT 'pendiente',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisiciones`
--

LOCK TABLES `requisiciones` WRITE;
/*!40000 ALTER TABLE `requisiciones` DISABLE KEYS */;
INSERT INTO `requisiciones` VALUES (85,NULL,'Juan Camilo Bello Roa','2025-10-27','2025-10-30','3 Dias habiles','','TyP','cota','Baja',0,30000000,'2025-10-27 19:01:52','Totalmente Aprobada'),(86,NULL,'Juan Camilo Bello Roa','2025-10-27','2025-10-29','1 Dia habiles','','SST','cota','Baja',0,30000000,'2025-10-27 20:06:49','Totalmente Aprobada');
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (2,'Edison Kenneth Campos Avila','k.campos@coopidrogas.com.co','$2b$10$PysxFk8frdIJp3aa/TigL.K3Z9kwgl5IIsyEwPNi74hYZqKJADbW.','gerSST','3224399893','SST','cota',0,1,0,0),(3,'Nelly Paola Coca Sierra','n.coca@coopidrogas.com.co','$2b$10$42fHo1pc3yXs2W470SqGL.GXLhj85p/12B0OpCV3s6tYjfyngIvxS','dicSST','','SST','cota',0,1,0,0),(5,'Daniel Alejandro Quiros Bertocchi','gerenciaGeneralTesting@coopidrogas.com.co','$2b$10$y5XPDf1quHo0C6R3e0/c8ulhRh1qLk6vddZr4h8WR5QXynQztKxpG','gerGeneral','3224399893','GerenciaGeneral','cota',0,1,0,0),(6,'Diego Alfonso Diaz Devia','d.diaz@coopidrogas.com.co','$2b$10$7gDyQfoc.HzNG/z.9jLIzuEsFS/7WFFxN0RpyYvJ8a2hZu9gQb6Tu','dicTYP','3125874818','TyP','cota',0,1,0,0),(7,'Wilson Ricardo Marulanda Niño','w.marulanda@coopidrogas.com.co','$2b$10$RPL79cMse7ZT5UeDFUSukOlb3p7AnzKX3ZkIF86pXAvU0q5Ik1wXe','gerTyC','123456789','TyP','cota',0,1,0,0),(8,'Juan Camilo Bello Roa','pract7.desarrollo@coopidrogas.com.co','$2b$10$HYbiZaqdninuxTJScI0ceOP1gs0MTWlvY9jV7TMiMBXMLhEz6Pqza','analistaQA','','SST','cota',0,0,1,0),(9,'Carlos Alfonso López Vargas','gerenciaAdminTesting@coopidrogas.com.co','$2b$10$amRejwiMpAQwoSUZHz208OrITxycmf1kh8/1C452W6EUxuPfT4LW.','gerAdmin','ASDASD','GerenciaAdmin','cota',0,1,0,0),(11,'elpepe','elpepe@gmail.com','$2b$10$GGq3wG9o4Grturpl.3Aqg.yQDyeUqhGwbAll7QE3Uc8C2Kp9Fe0xW','analistaQA','5445454242','TyP','medellin',1,0,0,1);
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

-- Dump completed on 2025-10-27 15:56:50
