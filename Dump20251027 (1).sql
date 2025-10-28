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
) ENGINE=InnoDB AUTO_INCREMENT=386 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisicion_aprobaciones`
--

LOCK TABLES `requisicion_aprobaciones` WRITE;
/*!40000 ALTER TABLE `requisicion_aprobaciones` DISABLE KEYS */;
INSERT INTO `requisicion_aprobaciones` VALUES (361,107,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,NULL,'pendiente',0,0,0,0,0,0,1,1),(362,107,'TyP','gerTyC','Wilson Ricardo Marulanda Niño',0,NULL,'pendiente',0,0,0,0,0,0,2,0),(363,108,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,NULL,'pendiente',0,0,0,0,0,0,1,1),(364,108,'TyP','gerTyC','Wilson Ricardo Marulanda Niño',0,NULL,'pendiente',0,0,0,0,0,0,2,0),(365,108,'GerenciaAdmin','gerAdmin','Carlos Alfonso López Vargas',0,NULL,'pendiente',0,0,0,0,0,0,3,0),(366,108,'GerenciaGeneral','gerGeneral','Daniel Alejandro Quiros Bertocchi',0,NULL,'pendiente',0,0,0,0,0,0,4,0),(367,109,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,'2025-10-28 13:34:13','aprobada',0,0,0,0,0,0,1,0),(368,109,'TyP','gerTyC','Wilson Ricardo Marulanda Niño',0,'2025-10-28 13:34:50','aprobada',0,0,0,0,0,0,2,0),(369,109,'SST','dicSST','Nelly Paola Coca Sierra',0,'2025-10-28 13:41:02','aprobada',0,0,0,0,0,0,3,0),(370,110,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,NULL,'pendiente',0,0,0,0,0,0,1,1),(371,110,'TyP','gerTyC','Wilson Ricardo Marulanda Niño',0,NULL,'pendiente',0,0,0,0,0,0,2,0),(372,110,'SST','dicSST','Nelly Paola Coca Sierra',0,NULL,'pendiente',0,0,0,0,0,0,3,0),(373,110,'GerenciaAdmin','gerAdmin','Carlos Alfonso López Vargas',0,NULL,'pendiente',0,0,0,0,0,0,4,0),(374,110,'GerenciaGeneral','gerGeneral','Daniel Alejandro Quiros Bertocchi',0,NULL,'pendiente',0,0,0,0,0,0,5,0),(375,111,'SST','dicSST','Nelly Paola Coca Sierra',0,NULL,'rechazada',0,0,0,0,0,0,1,0),(376,111,'SST','gerSST','Edison Kenneth Campos Avila',0,NULL,'rechazada',0,0,0,0,0,0,2,0),(377,112,'SST','dicSST','Nelly Paola Coca Sierra',0,'2025-10-28 13:42:42','aprobada',0,0,0,0,0,0,1,0),(378,112,'SST','gerSST','Edison Kenneth Campos Avila',0,NULL,'pendiente',0,0,0,0,0,0,2,1),(379,112,'GerenciaAdmin','gerAdmin','Carlos Alfonso López Vargas',0,NULL,'pendiente',0,0,0,0,0,0,3,0),(380,112,'GerenciaGeneral','gerGeneral','Daniel Alejandro Quiros Bertocchi',0,NULL,'pendiente',0,0,0,0,0,0,4,0),(381,113,'TyP','dicTYP','Diego Alfonso Diaz Devia',0,'2025-10-28 12:27:36','aprobada',0,0,0,0,0,0,1,0),(382,113,'TyP','gerTyC','Wilson Ricardo Marulanda Niño',0,'2025-10-28 12:28:04','aprobada',0,0,0,0,0,0,2,0),(383,113,'SST','dicSST','Nelly Paola Coca Sierra',0,'2025-10-28 12:29:10','aprobada',0,0,0,0,0,0,3,0),(384,113,'GerenciaAdmin','gerAdmin','Carlos Alfonso López Vargas',0,'2025-10-28 12:29:25','aprobada',0,0,0,0,0,0,4,0),(385,113,'GerenciaGeneral','gerGeneral','Daniel Alejandro Quiros Bertocchi',0,'2025-10-28 12:29:32','aprobada',0,0,0,0,0,0,5,0);
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
) ENGINE=InnoDB AUTO_INCREMENT=191 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisicion_productos`
--

LOCK TABLES `requisicion_productos` WRITE;
/*!40000 ALTER TABLE `requisicion_productos` DISABLE KEYS */;
INSERT INTO `requisicion_productos` VALUES (170,108,'Computador',1,NULL,'por que si',0,0,NULL,15000000.00,'123456','123456','pendiente'),(171,108,'Mouse',1,NULL,'por que si',0,0,NULL,35000.00,'123456','123456','pendiente'),(172,109,'PC',1,NULL,'por que si',1,0,NULL,15000000.00,'123456','123456','pendiente'),(173,109,'Silla',1,NULL,'por que si',0,1,0,6500000.00,'123456','123456','rechazado'),(174,110,'PC',1,NULL,'por que si',1,0,NULL,15000000.00,'123456','123456','pendiente'),(175,110,'Silla',1,NULL,'123456',0,1,NULL,6500000.00,'123456','123456','pendiente'),(176,111,'Silla',1,NULL,'pormque si',0,1,0,15500000.00,'123456','123456','rechazado'),(177,112,'Silla',1,'2025-10-28T18:42:42.064Z','por que si',0,1,1,15000000.00,'123546','123546','aprobado'),(178,113,'Pantalla',1,'2025-10-28T17:29:32.701Z','pantalla',1,0,1,400000.00,'123456','123456','aprobado'),(179,113,'Brazo para computador',1,'2025-10-28T17:29:32.701Z','Brazo para computador',1,0,1,250000.00,'123456','123456','aprobado'),(180,113,'Base refrigerante',1,'2025-10-28T17:29:32.701Z','Base refrigerante',1,0,1,200000.00,'123456','123456','aprobado'),(181,113,'Licencias Office',1,'2025-10-28T17:29:32.701Z','Licencias Office',1,0,1,100000.00,'123456','123456','aprobado'),(182,113,'Descansa pies',1,'2025-10-28T17:29:32.701Z','Descansa pies',0,1,1,20000000.00,'123456','123456','aprobado'),(183,113,'Teclado ergonomico',1,'2025-10-28T17:29:32.701Z','Teclado ergonomico',0,1,1,40000000.00,'123456','123456','aprobado'),(184,113,'Carro 0 km corvette',1,'2025-10-28T17:29:32.701Z','Carro 0 km corvette',0,0,1,200000000.00,'123456','123456','aprobado'),(189,107,'Computador',1,NULL,'por que si',0,0,NULL,25000000.00,'123456','123456',NULL),(190,107,'Mouse',1,NULL,'por que si',0,0,NULL,35000000.00,'123456','123456',NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `requisiciones`
--

LOCK TABLES `requisiciones` WRITE;
/*!40000 ALTER TABLE `requisiciones` DISABLE KEYS */;
INSERT INTO `requisiciones` VALUES (107,NULL,'Juan Camilo Bello Roa','2025-10-28','2025-10-29','1','','TyP','cota','Baja',1,60000000,'2025-10-28 16:55:08','pendiente'),(108,NULL,'Juan Camilo Bello Roa','2025-10-28','2025-10-29','1 Dia','','TyP','cota','Baja',0,15035000,'2025-10-28 16:56:54','pendiente'),(109,NULL,'Juan Camilo Bello Roa','2025-10-28','2025-10-29','1','','TyP','cota','Baja',1,15000000,'2025-10-28 16:58:45','aprobada'),(110,NULL,'Juan Camilo Bello Roa','2025-10-28','2025-10-29','1 ','','TyP','cota','Baja',0,21500000,'2025-10-28 17:00:37','pendiente'),(111,NULL,'Juan Camilo Bello Roa','2025-10-28','2025-10-29','1','','TyP','cota','Baja',1,0,'2025-10-28 17:03:13','rechazada'),(112,NULL,'Juan Camilo Bello Roa','2025-10-28','2025-10-29','1','','TyP','cota','Baja',0,15000000,'2025-10-28 17:03:57','pendiente'),(113,NULL,'Santiago Barinas','2025-10-28','2025-10-29','1','','TyP','cota','Baja',0,260950000,'2025-10-28 17:26:34','Totalmente Aprobada');
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (2,'Edison Kenneth Campos Avila','k.campos@coopidrogas.com.co','$2b$10$PysxFk8frdIJp3aa/TigL.K3Z9kwgl5IIsyEwPNi74hYZqKJADbW.','gerSST','3224399893','SST','cota',0,1,0,0),(3,'Nelly Paola Coca Sierra','n.coca@coopidrogas.com.co','$2b$10$42fHo1pc3yXs2W470SqGL.GXLhj85p/12B0OpCV3s6tYjfyngIvxS','dicSST','','SST','cota',0,1,0,0),(5,'Daniel Alejandro Quiros Bertocchi','gerenciaGeneralTesting@coopidrogas.com.co','$2b$10$y5XPDf1quHo0C6R3e0/c8ulhRh1qLk6vddZr4h8WR5QXynQztKxpG','gerGeneral','3224399893','GerenciaGeneral','cota',0,1,0,0),(6,'Diego Alfonso Diaz Devia','d.diaz@coopidrogas.com.co','$2b$10$7gDyQfoc.HzNG/z.9jLIzuEsFS/7WFFxN0RpyYvJ8a2hZu9gQb6Tu','dicTYP','3125874818','TyP','cota',0,1,0,0),(7,'Wilson Ricardo Marulanda Niño','w.marulanda@coopidrogas.com.co','$2b$10$RPL79cMse7ZT5UeDFUSukOlb3p7AnzKX3ZkIF86pXAvU0q5Ik1wXe','gerTyC','123456789','TyP','cota',0,1,0,0),(8,'Juan Camilo Bello Roa','pract7.desarrollo@coopidrogas.com.co','$2b$10$HYbiZaqdninuxTJScI0ceOP1gs0MTWlvY9jV7TMiMBXMLhEz6Pqza','analistaQA','','TyP','cota',0,0,1,0),(9,'Carlos Alfonso López Vargas','gerenciaAdminTesting@coopidrogas.com.co','$2b$10$amRejwiMpAQwoSUZHz208OrITxycmf1kh8/1C452W6EUxuPfT4LW.','gerAdmin','ASDASD','GerenciaAdmin','cota',0,1,0,0),(11,'elpepe','elpepe@gmail.com','$2b$10$GGq3wG9o4Grturpl.3Aqg.yQDyeUqhGwbAll7QE3Uc8C2Kp9Fe0xW','analistaQA','5445454242','SST','medellin',1,0,0,1),(12,'Santiago Barinas','pract6.desarrollo@coopidrogas.com.co','$2b$10$ZZulhbPzhN0wVT91iva4yuoOb8jpXueLejQtpecHV2CHb0Mufa5x6','analistaQA','3214961814','TyP','cota',0,0,1,0);
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

-- Dump completed on 2025-10-28 15:52:09
