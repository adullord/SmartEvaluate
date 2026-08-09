-- ระบบประเมินสมรรถนะบุคลากรสาธารณสุข
-- Production fresh-install schema (UTF-8)
-- ไม่มีรายชื่อบุคลากรจำลองและผลการประเมิน

SET NAMES utf8mb4;
SET time_zone = '+07:00';


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_code` varchar(10) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `short_name` varchar(255) DEFAULT NULL,
  `type` enum('SSO','RPST','ADMIN') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_departments_service_code` (`service_code`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ranks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ranks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(13) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `role` enum('admin','ss_amphoe','director','staff') NOT NULL,
  `department_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `rank_id` int(11) NOT NULL,
  `expected_level` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `department_id` (`department_id`),
  KEY `position_id` (`position_id`),
  KEY `rank_id` (`rank_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`),
  CONSTRAINT `users_ibfk_3` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=275 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `evaluation_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_cycles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year` varchar(4) NOT NULL,
  `round_name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','closed') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competencies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('core','functional') NOT NULL,
  `order_seq` int(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `competency_levels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `competency_levels` (
  `competency_id` int(11) NOT NULL,
  `expected_level` int(11) NOT NULL,
  `level_description` text NOT NULL,
  PRIMARY KEY (`competency_id`,`expected_level`),
  CONSTRAINT `competency_levels_ibfk_1` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `indicators`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `indicators` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `competency_id` int(11) NOT NULL,
  `expected_level` int(11) DEFAULT NULL,
  `position_id` int(11) DEFAULT NULL,
  `indicator_text` text NOT NULL,
  `order_seq` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `competency_id` (`competency_id`),
  KEY `position_id` (`position_id`),
  CONSTRAINT `indicators_ibfk_1` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indicators_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `evaluation_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `position_id` int(11) NOT NULL,
  `expected_level` int(11) NOT NULL,
  `competency_id` int(11) NOT NULL,
  `weight` decimal(5,2) NOT NULL,
  `level_description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `position_id` (`position_id`),
  KEY `competency_id` (`competency_id`),
  CONSTRAINT `evaluation_templates_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluation_templates_ibfk_2` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=153 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `evaluator_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluator_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `evaluatee_id` int(11) NOT NULL,
  `evaluator_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `cycle_id` (`cycle_id`),
  KEY `evaluatee_id` (`evaluatee_id`),
  KEY `evaluator_id` (`evaluator_id`),
  CONSTRAINT `evaluator_mapping_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluator_mapping_ibfk_2` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluator_mapping_ibfk_3` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=469 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cycle_id` int(11) NOT NULL,
  `evaluatee_id` int(11) NOT NULL,
  `evaluator_id` int(11) NOT NULL,
  `status` enum('draft','submitted','acknowledged','returned') DEFAULT 'draft',
  `total_score_base5` decimal(10,4) DEFAULT 0.0000,
  `total_score_base100` decimal(10,2) DEFAULT 0.00,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cycle_id` (`cycle_id`),
  KEY `evaluatee_id` (`evaluatee_id`),
  KEY `evaluator_id` (`evaluator_id`),
  CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`),
  CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`),
  CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `evaluation_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `indicator_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `evaluation_id` (`evaluation_id`),
  KEY `indicator_id` (`indicator_id`),
  CONSTRAINT `evaluation_scores_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluation_scores_ibfk_2` FOREIGN KEY (`indicator_id`) REFERENCES `indicators` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `evaluation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluation_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `evaluation_id` (`evaluation_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `evaluation_logs_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluation_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ข้อมูลตั้งต้น: หน่วยบริการ ตำแหน่ง บทบาท และสมรรถนะ

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` (`id`, `service_code`, `name`, `short_name`, `type`, `created_at`) VALUES (5,'00914','สำนักงานสาธารณสุขอำเภอบันนังสตา','สสอ.บันนังสตา','SSO','2026-08-06 15:16:15'),(6,'10039','บ้านบางลาง','รพ.สต.บ้านบางลาง','RPST','2026-08-06 15:16:15'),(7,'10041','บ้านทำนบ','รพ.สต.บ้านทำนบ','RPST','2026-08-06 15:16:15'),(8,'10042','บ้านบันนังบูโบ','รพ.สต.บ้านบันนังบูโบ','RPST','2026-08-06 15:16:15'),(9,'10043','บ้านตะบิงติงงี','รพ.สต.บ้านตะบิงติงงี','RPST','2026-08-06 15:16:15'),(14,'10045','บ้านสายตาเอียด','รพ.สต.บ้านสายตาเอียด','RPST','2026-08-07 06:44:03'),(15,'10046','บ้านกือลอง','รพ.สต.บ้านกือลอง','RPST','2026-08-07 06:44:03'),(16,'10047','บ้านสันติ1','รพ.สต.บ้านสันติ1','RPST','2026-08-07 06:44:03'),(17,'14992','บือซู','รพ.สต.บือซู','RPST','2026-08-07 06:44:03'),(18,'10040','บ้าน กม.26','รพ.สต.บ้าน กม.26','RPST','2026-08-07 06:44:03');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `positions` WRITE;
/*!40000 ALTER TABLE `positions` DISABLE KEYS */;
INSERT INTO `positions` (`id`, `name`, `created_at`) VALUES (2,'สาธารณสุขอำเภอ','2026-08-06 08:22:31'),(3,'ผู้อำนวยการ รพ.สต.','2026-08-06 08:22:31'),(4,'นักวิชาการสาธารณสุข','2026-08-06 08:22:31'),(5,'นักสาธารณสุข','2026-08-06 08:22:31'),(6,'พยาบาลวิชาชีพ','2026-08-06 08:22:31'),(7,'นักวิชาการคอมพิวเตอร์','2026-08-06 08:22:31'),(8,'แพทย์แผนไทย','2026-08-06 08:22:31'),(9,'เจ้าพนักงานสาธารณสุข','2026-08-06 08:22:31'),(10,'เจ้าพนักงานทันตสาธารณสุข','2026-08-06 08:22:31'),(11,'เจ้าพนักงานการเงินและบัญชี','2026-08-06 08:22:31');
/*!40000 ALTER TABLE `positions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `ranks` WRITE;
/*!40000 ALTER TABLE `ranks` DISABLE KEYS */;
INSERT INTO `ranks` (`id`, `name`, `created_at`) VALUES (2,'อำนวยการระดับสูง','2026-08-06 08:22:31'),(3,'ผู้อำนวยการ','2026-08-06 08:22:31'),(4,'ปฏิบัติการ','2026-08-06 08:22:31'),(5,'ชำนาญการ','2026-08-06 08:22:31'),(6,'ชำนาญการพิเศษ','2026-08-06 08:22:31'),(7,'ปฏิบัติงาน','2026-08-06 08:22:31'),(8,'อาวุโส','2026-08-06 08:22:31');
/*!40000 ALTER TABLE `ranks` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `code`, `name`, `created_at`) VALUES (1,'ss_amphoe','สสอ.','2026-08-07 04:02:42'),(2,'director','ผอ.รพ.สต.','2026-08-07 04:02:42'),(3,'staff','บุคลากร','2026-08-07 04:02:42'),(4,'admin','admin','2026-08-07 04:02:42');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `competencies` WRITE;
/*!40000 ALTER TABLE `competencies` DISABLE KEYS */;
INSERT INTO `competencies` (`id`, `name`, `description`, `type`, `order_seq`) VALUES (1,'การทำงานที่เป็นเลิศ','หมายถึง การปฏิบัติตนด้วยความเป็นมืออาชีพและน่าเชื่อถือ มีความมุ่งมั่นทุ่มเทที่จะปฏิบัติให้สำเร็จ สามารถกำหนดเป้าหมาย และแนวทางการทำงานที่ชัดเจน มีการรวบรวมและวิเคราะห์ข้อมูลเพื่อประกอบการตัดสินใจ หรือแก้ปัญหาได้อย่ามีประสิทธิภาพ อีกทั้งมีการสั่งสมความเชี่ยวชาญในงานอาชีพและสามารถประยุกต์ใช้ในการพัฒนางานได้อย่างต่อเนื่อง','core',1),(2,'การยึดมั่นในความถูกต้องและมีจิตบริการสาธารณะ','หมายถึง การปฏิบัติหน้าที่อย่างถูกต้องเหมาะสมบนพื้นฐานของหลักมาตรฐานทางจริยธรรม หลักกฎหมาย และจรรยาบรรณ อีกทั้งมีความเป็นมิตร ทัศนคติทางบวก และความพยายามที่จะเข้าใจความคิดและความรู้สึกของผู้อื่น พร้อมแก้ไขปัญหา สร้างความเข้าใจและการยอมรับแก่ประชาชนหรือผู้รับบริการ รวมถึงมีจิตบริการสาธาธารณะ','core',2),(3,'การประสานความร่วมมือร่วมใจ','หมายถึง การสร้างความร่วมมือร่วมใจและการทำงานร่วมกับผู้อื่นเพื่อให้บรรลุเป้าหมายร่วมกันโดย สามารถสื่อสารประสานสัมพันธ์และรักษาสัมพันธภาพอันดีกับผู้ที่เกี่ยวข้องตลอดจนเป็นที่ไว้วางใจได้มองเห็นคุณค่าของบุคคล และเคารพความแตกต่างระหว่างบุคคลและปฏิบัติต่อผู้อื่นด้วยความสุภาพ','core',3),(4,'ความยืดหยุ่น คล่องตัว ริเริ่มสร้างสรรค์ (Being Agile and Innovative)','หมายถึง การตอบสนองต่อความเปลี่ยนแปลงต่างๆ ได้อย่างเหมาะสม โดยใช้มุมมองเชิงสร้างสรรค์ในการปรับตัวและพัฒนาวิธีการทำงาน สามารถประเมินและพร้อมรับมือกับความเสี่ยงที่อาจจะเกิดขึ้น ทำความเข้าใจสาเหตุและผลกระทบของปัญหา พร้อมทั้งแก้ไขหรือหาทางป้องกันได้อย่างสร้างสรรค์ มีความยืดหยุ่นทางจิตใจ เพื่อให้สามารถฝ่าฟันอุปสรรคได้ พร้อมทั้งมองหาโอกาสในการพัฒนาอย่างต่อเนื่อง','core',4),(5,'การตรวจสอบความถูกต้องตามกระบวนงาน','หมายถึง ความใส่ใจที่จะปฏิบัติงานให้ถูกต้อง ครบถ้วน มุ่งเน้นความชัดเจนของบทบาทหน้าที่ และลดข้อบกพร่องที่อาจเกิดจากสภาพแวดล้อม โดยติดตามและตรวจสอบการทำงานหรือข้อมูล ตลอดจนพัฒนาระบบการตรวจสอบเพื่อให้กระบวนงานมีความถูกต้อง','functional',5),(6,'การแสดงความรับผิดชอบตามอำนาจหน้าที่','หมายถึง การกำกับดูแลให้ผู้อื่นปฏิบัติตามมาตรฐาน กฎระเบียบ ข้อบังคับ โดยอาศัยอำนาจตามกฎหมาย หรือการออกคำสั่งโดยปกติทั่วไป จนถึงการใช้อำนาจตามกฎหมายกับผู้ฝ่าฝืน','functional',6),(7,'การสืบเสาะหาข้อมูล','หมายถึง ความใฝ่รู้เชิงลึกที่จะแสวงหาข้อมูลเกี่ยวกับสถานการณ์ ที่มาของประเด็นปัญหา หรือเรื่องราวต่างๆ ที่เกี่ยวข้อง หรือจะเป็นประโยชน์ในการปฏิบัติงาน','functional',7),(8,'การคิดวิเคราะห์','หมายถึง การทำความเข้าใจและวิเคราะห์สถานการณ์ ข้อมูล ประเด็นปัญหา หรือแนวคิด โดยแยกแยะประเด็นออกเป็นส่วนย่อยหรือเป็นขั้นตอน รวมถึงการจัดหมวดหมู่อย่างเป็นระบบ เปรียบเทียบข้อมูลและหลักฐานในแง่มุมต่าง ๆ ตั้งสมมติฐาน วิเคราะห์ตรรกะ ลำดับความสำคัญ ช่วงเวลา เหตุและผล ตลอดจนที่มาที่ไปของกรณีต่าง ๆ','functional',8),(9,'การดำเนินการเชิงรุก','หมายถึง การเล็งเห็นปัญหาหรือโอกาส พร้อมทั้งจัดการเชิงรุกกับปัญหานั้นโดยอาจไม่มีใครร้องขอ และอย่างไม่ย่อท้อหรือใช้โอกาสนั้นให้เกิดประโยชน์ต่องาน ตลอดจนการคิดริเริ่มสร้างสรรค์ใหม่ๆ เกี่ยวกับงาน เพื่อแก้ปัญหา ป้องกันปัญหา หรือสร้างโอกาสด้วย','functional',9),(10,'การมองภาพองค์รวม','หมายถึง การคิดในเชิงสังเคราะห์ มองภาพองค์รวม โดยการจับประเด็น สรุปรูปแบบเชื่อมโยงหรือประยุกต์แนวทางจากสถานการณ์ ข้อมูล หรือทัศนะต่าง ๆ จนได้เป็นกรอบความคิดหรือแนวคิดใหม่','functional',10),(11,'การคาดการณ์เชิงยุทธศาสตร์','หมายถึง การคิดแบบมองภาพใหญ่ที่สามารถระบุรูปแบบและแนวโน้ม เพื่อทำความเข้าใจในตัวขับเคลื่อนเชิงกลยุทธ์ของการเปลี่ยนแปลงที่มีโอกาสเกิดขึ้นในระยะกลาง และระยะยาว เพื่อใช้เป็นข้อมูลประกอบในการสร้างนโยบายและยุทธศาสตร์ให้สามารถรองรับการเปลี่ยนแปลงที่อาจเกิดขึ้นในอนาคตในรูปแบบต่าง ๆ ได้อย่างเป็นระบบ','functional',11),(12,'ความเข้าใจความหลากหลายทางวัฒนธรรม','หมายถึง การรับรู้ถึงความหลากหลายทางวัฒนธรรม และสามารถประยุกต์ความเข้าใจ และความหลากหลายเพื่อสร้างสัมพันธภาพระหว่างกันได้','functional',12),(13,'การควบคุมการแสดงออกทางอารมณ์ภายใต้สถานการณ์ตึงเครียด','หมายถึง ความสามารถในการจัดการความเครียดได้อย่างเหมาะสม ควบคุมอารมณ์ ความคิด และพฤติกรรมของตนเองภายใต้สถานการณ์ที่กดดันหรือแรงต้าน เพื่อให้สามารถคงความมุ่งมั่นและยังทำงานได้อย่างมีคุณภาพและประสิทธิภาพ','functional',13),(14,'การคิดสร้างสรรค์','หมายถึง การสร้างและส่งเสริมแนวคิดใหม่ ๆ หรือแนวคิดที่แตกต่างจากเดิม เพื่อพัฒนากระบวนการ วิธีการ ระบบ ผลิตภัณฑ์ หรือบริการให้เกิดขึ้นใหม่หรือปรับปรุงให้ดีขึ้น รวมถึงการพยายามค้นหาวิธีการใหม่เพื่อจัดการกับปัญหาหรือตอบสนองต่อโอกาสที่เกิดขึ้น','functional',14);
/*!40000 ALTER TABLE `competencies` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `competency_levels` WRITE;
/*!40000 ALTER TABLE `competency_levels` DISABLE KEYS */;
INSERT INTO `competency_levels` (`competency_id`, `expected_level`, `level_description`) VALUES (1,1,'วางตนได้อย่างเหมาะสมและตั้งใจทำหน้าที่ของตนเองให้ดี สนใจใฝ่รู้และพัฒนาตนเองอยู่เสมอ รวมทั้งวางแผนการทำงานและตัดสินใจแก้ไขปัญหาที่ไม่ชับช้อนได้'),(1,2,'วางตนและปฏิบัติงานได้อย่างน่าเชื่อถือ มุ่งมั่นทุ่มเทที่จะทำงานให้สำเร็จและดียิ่งขึ้นอยู่เสมอ สามารถระบุเป้าหมายสำคัญของงานและกำหนดแนวทางที่ชัดเจนเพื่อให้บรรลุเป้าหมายนั้น รวมทั้งตัดสินใจบนฐานของข้อมูลในประเด็นที่มีความซับซ้อนได้'),(2,1,'พยายามเข้าอกเข้าใจผู้อื่น ทำหน้าที่อย่างถูกต้อง และใส่ใจความต้องการของส่วนรวม'),(2,2,'พยายามทำความเข้าใจมุมมองและบริบทของผู้อื่น ช่วยเหลือแก้ปัญหา พร้อมทั้งปฏิบัติต่อผู้ที่เกี่ยวข้องด้วยความเป็นมิตร มีน้ำใจ และไม่เพิกเฉยต่อปัญหาสาธารณะ'),(3,1,'เข้าใจความแตกต่างและปฏิบัติต่อผู้อื่นด้วยความเท่าเทียม รับฟังและสื่อสารได้อย่างมั่นใจ รับผิดชอบและมีส่วนร่วมในการทำงานเป็นทีมพร้อมสร้างและรักษาความสัมพันธ์เชิงบวก'),(3,2,'สนับสนุนให้ผู้อื่นเห็นคุณค่าของความแตกต่างระหว่างบุคคล แสวงหามุมมองที่หลากหลาย รับฟังและปรับการสื่อสารได้อย่างเหมาะสม สามารถใช้ศักยภาพของทีมมาใช้ในการทำงาน สามารถประสานความสัมพันธ์ และเป็นที่ไว้วางใจได้ของผู้ที่เกี่ยวข้อง'),(4,1,'สนใจความเปลี่ยนแปลงที่เกิดขึ้นรอบตัวเปิดกว้างและปรับตัวต่อ สถานการณ์ใหม่ๆ พร้อมทั้งสามารถรักษาระดับการทำงานที่มีประสิทธิภาพได้แม้เผชิญปัญหาหรืออยู่ภายใต้แรงกดดัน ริเริ่มปรับเปลี่ยนวิธีการทำงานให้เหมาะสมกับสถานการณ์และมีการพัฒนาปรับปรุงตนอย่างต่อเนื่อง'),(4,2,'ก้าวทันความเปลี่ยนแปลงที่เกิดขึ้น พยายามนำกระบวนการใหม่ หรือนวัตกรรมมาปรับใช้ในการทำงาน ตอบสนองต่อความท้าทาย หรือปัญหาด้วยหลักการและเหตุผล พร้อมทั้งสามารถเสนอแนะแนวทางการพัฒนา ตนเองให้กับผู้อื่นได้อย่างเหมาะสม'),(5,1,'รักษากฎ ระเบียบ และตรวจทานความถูกต้องของงานที่ตนรับผิดชอบ'),(6,1,'กำกับดูแลมาตรฐาน กฎ ระเบียบ ข้อบังคับและกำหนดขอบเขตข้อจำกัดในการกระทำการใด ๆ'),(6,2,'มอบหมายงาน ติดตาม ควบคุม และสั่งให้ปรับมาตรฐานหรือปรับปรุงการปฏิบัติงานให้ดีขึ้น'),(7,1,'ค้นหาข้อมูลจากแหล่งข้อมูลหรือบุคคลที่เกี่ยวข้อง พร้อมทั้งตรวจสอบความน่าเชื่อถือของข้อมูลเพื่อนำมาใช้ในการปฏิบัติงาน'),(8,1,'แยกแยะประเด็นปัญหาหรืองานออกเป็นส่วนย่อยๆ และเข้าใจความสัมพันธ์ขั้นพื้นฐานของปัญหาหรืองาน'),(8,2,'เข้าใจความสัมพันธ์ที่ซับซ้อนของปัญหาหรืองาน สามารถจัดลำดับความสำคัญและความเร่งด่วนของปัญหาได้ และเสนอแนะแนวทางการแก้ไขปัญหาที่หลากหลาย'),(9,1,'เห็นปัญหาหรือโอกาสระยะสั้นและลงมือดำเนินการ และจัดการปัญหาเฉพาะหน้าหรือเหตุวิกฤติ'),(9,2,'เตรียมการล่วงหน้า เพื่อสร้างโอกาส หรือหลีกเลี่ยงปัญหาระยะสั้น'),(10,1,'ใช้ทฤษฎีหรือแนวคิดพื้นฐาน และประยุกต์ใช้ประสบการณ์ในการทำงาน'),(10,2,'ประยุกต์ทฤษฎีหรือแนวคิดซับซ้อน และอธิบายให้ผู้อื่นเข้าใจ'),(11,1,'ตระหนักถึงความไม่แน่นอนและวิเคราะห์ หาปัจจัยขับเคลื่อนการเปลี่ยนแปลงได้'),(11,2,'ค้นหาและเข้าใจสัญญาณของการเปลี่ยนแปลงรวมทั้งคาดการณ์สถานการณ์ต่าง ๆ ที่อาจเกิดขึ้นได้'),(12,1,'เห็นคุณค่าของวัฒนธรรมไทย รวมถึงแสดงออกให้สอดคล้องกับวัฒนธรรมได้'),(13,1,'ควบคุมการแสดงออกทางอารมณ์ภายใต้สถานการณ์ตึงเครียดได้'),(14,1,'เข้าใจและวิเคราะห์แนวทางเดิมได้อย่างสร้างสรรค์ เพื่อค้นหาวิธีการใหม่');
/*!40000 ALTER TABLE `competency_levels` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `indicators` WRITE;
/*!40000 ALTER TABLE `indicators` DISABLE KEYS */;
INSERT INTO `indicators` (`id`, `competency_id`, `expected_level`, `position_id`, `indicator_text`, `order_seq`) VALUES (1,1,1,NULL,'พยายามรับผิดชอบงานในหน้าที่ให้สำเร็จได้อย่างถูกต้อง มีคุณภาพ และภายในระยะเวลาที่กำหนด',1),(2,1,1,NULL,'มีการทบทวนและพัฒนาผลการทำงานของตนเองอย่างสม่ำเสมอ',2),(3,1,1,NULL,'สามารถรวบรวมข้อมูลที่เกี่ยวข้อง เพื่อนำมาประกอบการตัดสินใจในเรื่องทั่วไปที่มีแนวทางดำเนินการอยู่แล้วได้อย่างสมเหตุสมผล',3),(4,1,1,NULL,'ระบุเป้าหมายของการทำงานที่ไม่ชับช้อนได้ รวมทั้งเข้าใจขั้นตอนและวิธีการที่จะบรรลุเป้าหมาย',4),(5,1,1,NULL,'มีความรู้ความเข้าใจและสามารถประยุกต์หลักวิชาการ กฎระเบียบ หรือนโยบายที่เกี่ยวข้องมาใช้ในการปฏิบัติงาน ได้อย่างถูกต้องและเหมาะสม',5),(6,1,1,NULL,'มีความมั่นใจและวางตัวได้อย่างเหมาะสมกับสถานการณ์',6),(7,1,2,NULL,'รับผิดชอบงานตามหน้าที่ รวมทั้งกำกับดูแล ให้คำแนะนำทีมงานให้ปฏิบัติงานได้สำเร็จอย่างถูกต้อง มีคุณภาพ และภายในระยะเวลาที่กำหนด',1),(8,1,2,NULL,'มีการทบทวนและพัฒนาการทำงานของทีมงานอย่างสม่ำเสม่ำเสมอ เพื่อปรับปรุงกระบวนงานและผลงานให้มีประสิทธิภาพมากขึ้น',2),(9,1,2,NULL,'สามารถวิเคราะห์ข้อมูลที่ครบถ้วนรอบด้าน เพื่อการตัดสินใจในประเด็นที่มีความขับซ้อนหรือคลุมเครือได้อย่างมีประสิทธิภาพ',3),(10,1,2,NULL,'ระบุเป้าหมายสำคัญของงาน เข้าใจภาพรวม ขั้นตอน และวิธีการ ที่จะบรรลุเป้าหมาย รวมทั้งจัดลำดับความสำคัญของงานได้',4),(11,1,2,NULL,'มีความรู้ความเข้าใจและสามารถประยุกต์หลักวิชาการ กฎระเบียบ หรือนโยบายที่หลากหลายมาใช้ในการปฏิบัติงานได้อย่างถูกต้องและเหมาะสม',5),(12,1,2,NULL,'สามารถแสดงความเห็นได้อย่างน่าเชื่อถือ กล้าแสดงจุดยืนของตน ตามหลักวิชาการหรือวิชาชีพ เป็นที่ยอมรับภายในกลุ่มงาน',6),(13,2,1,NULL,'เคารพความรู้สึกนึกคิดของผู้อื่น เปิดใจรับฟังปัญหาหรือความต้องการ และพร้อมจะช่วยแก้ไขปัญหาให้ผู้อื่น',1),(14,2,1,NULL,'ปฏิบัติหน้าที่ด้วยความถูกต้องตามมาตรฐานทางจริยธรรม หลักกฎหมาย และจรรยาบรรณ',2),(15,2,1,NULL,'ไม่เพิกเฉยต่อข้อเสนอแนะหรือข้อร้องเรียนที่เกิดขึ้นจากการทำงาน',3),(16,2,1,NULL,'ตระหนักถึงผลของการกระทำของตน และรับผิดชอบในภาระหน้าที่ของตนที่มีต่อสาธารณชน',4),(17,2,2,NULL,'พยายามเข้าใจมุมมองและบริบทของผู้อื่น พร้อมจะช่วยเหลือแก้ปัญหา ปฏิบัติต่อผู้อื่นด้วยความเป็นมิตรและมีน้ำใจ',1),(18,2,2,NULL,'ยึดมั่นและกำกับให้ทีมงานปฏิบัติตามมาตรฐานทางจริยธรรม หลักกฎหมายและจรรยาบรรณ',2),(19,2,2,NULL,'แสดงทัศนคติทางบวกและพยายามสร้างความเข้าใจและการยอมรับให้แก่ประชาชนหรือผู้รับบริการ',3),(20,2,2,NULL,'กระตุ้นให้บุคคลรอบข้างให้ความสำคัญกับประเด็นทางด้านสังคม หรือสิ่งที่เกิดขึ้นรอบตัว รวมทั้งภาระหน้าที่ที่มีต่อสาธารณชน',4),(21,3,1,NULL,'ปฏิบัติต่อผู้อื่นอย่างเท่าเทียม เข้าใจและยอมรับความแตกต่างระหว่างบุคคล เช่น อายุ เพศสภาพ ศาสนา วัฒนธรรม ฐานะทางเศรษฐกิจหรือสังคม และสภาพทางกาย เป็นต้น',1),(22,3,1,NULL,'สามารถจับใจความและสื่อสารได้อย่างมั่นใจ ชัดเจน เลือกใช้สื่อและภาษาที่เหมาะสม',2),(23,3,1,NULL,'รับผิดชอบงานในส่วนที่ตนได้รับมอบหมาย และมีส่วนร่วมในการทำงานเป็นทีม',3),(24,3,1,NULL,'รับรู้อารมณ์ความรู้สึกของตนเองและผู้อื่น รวมทั้งสามารถตอบสนองได้อย่างเหมาะสม',4),(25,3,1,NULL,'มีสัจจะเชื่อถือได้ และไม่บิดเบือน หรืออ้างข้อยกเว้นให้ตนเอง',5),(26,3,2,NULL,'สนับสนุนให้ทีมงานเกิดความเข้าใจ ยอมรับและเห็นคุณค่าของความแตกต่างระหว่างบุคคล',1),(27,3,2,NULL,'สามารถจับใจความสำคัญ และสื่อสารได้อย่างกระชับตรงประเด็น รวมทั้งปรับรูปแบบการสื่อสารได้อย่างเหมาะสม',2),(28,3,2,NULL,'สามารถใช้ศักยภาพของทีมหรือเปิดโอกาสให้ผู้ที่เกี่ยวข้องเข้ามามีส่วนร่วม เพื่อบรรลุภารกิจหรือเป้าหมายร่วมกัน',3),(29,3,2,NULL,'รับรู้อารมณ์ความรู้สึกของผู้อื่น พร้อมสร้างและรักษาความสัมพันธ์ที่ดี',4),(30,3,2,NULL,'ปฏิบัติหน้าที่อย่างตรงไปตรงมา ไม่เบี่ยงเบนด้วยอคติหรือผลประโยชน์ส่วนตน แม้ต้องกระทบกับบุคคลที่มีอำนาจหน้าที่ที่สูงกว่า',5),(31,4,1,NULL,'วิเคราะห์ศักยภาพของตนเอง ยอมรับและพร้อมนำความเห็นต่างมาใช้ประกอบการพัฒนาปรับปรุงตนเอง',1),(32,4,1,NULL,'แสดงความคิดเห็นเพื่อพัฒนาแนวทางการทำงานอย่างสร้างสรรค์',2),(33,4,1,NULL,'พร้อมปรับตัวเข้ากับสถานการณ์ใหม่ ๆ',3),(34,4,1,NULL,'ระบุและประเมินผลกระทบจากปัจจัยเสี่ยงที่อาจส่งผลต่อการดำเนินงาน และเสนอแนะแนวทางป้องกันความเสี่ยงที่อาจเกิดขึ้น',4),(35,4,1,NULL,'สามารถรักษาระดับการทำงานที่มีประสิทธิภาพได้แม้เผชิญปัญหาหรืออยู่ภายใต้แรงกดดัน',5),(36,4,2,NULL,'สามารถวิเคราะห์จุดเด่นและจุดที่ควรพัฒนาของตนเองและผู้อื่นได้อย่างชัดเจน พร้อมเสนอแนะแนวทางในการพัฒนาตนเองและผู้อื่นได้อย่างเหมาะสม',1),(37,4,2,NULL,'นำแนวคิดใหม่ ๆ และนวัตกรรม มาปรับปรุงการทำงานและสนับสนุนทีมงานให้มีความคิดสร้างสรรค์',2),(38,4,2,NULL,'ตอบสนองกับสถานการณ์ต่าง ๆ อย่างรวดเร็ว และทันต่อเวลา',3),(39,4,2,NULL,'คาดการณ์แนวโน้มและวิเคราะห์ความเชื่อมโยงของปัจจัยเสี่ยงทั้งที่เกี่ยวข้องโดยตรงและโดยอ้อมกับหน่วยงาน และกำหนดมาตรการเบื้องต้นเพื่อรับมือผลกระทบที่อาจเกิดขึ้น',4),(40,4,2,NULL,'รักษาระดับของความพยายามและความมุ่งมั่นแม้ต้องเผชิญกับปัญหาหรือความผิดพลาดด้วยหลักการและเหตุผล',5),(41,5,1,NULL,'ดูแลให้เกิดความเป็นระเบียบในสภาพแวดล้อมของการทำงาน',1),(42,5,1,NULL,'ปฏิบัติตามกฎ ระเบียบ และขั้นตอน ที่กำหนดอย่างเคร่งครัด',2),(43,5,1,NULL,'ตรวจสอบความถูกต้องของงานในหน้าที่ความรับผิดชอบของตนเอง',3),(44,6,1,NULL,'กำหนดแนวทางปฏิบัติ เพื่อให้ผู้อื่นปฏิบัติตามมาตรฐาน กฎ ระเบียบ ข้อบังคับ',1),(45,6,1,NULL,'กำกับดูแลงานให้เป็นไปตามมาตรฐาน กฎระเบียบ ข้อบังคับ และปฏิเสธคำขอของผู้อื่นที่ไม่สมเหตุสมผล',2),(46,6,2,NULL,'มอบหมายงานในรายละเอียดบางส่วนให้ผู้อื่นดำเนินการแทนได้ รวมถึงติดตามและควบคุมผู้ที่อยู่ใต้การกำกับดูแลให้ปฏิบัติตามมาตรฐาน กฎระเบียบ ข้อบังคับ',1),(47,6,2,NULL,'กำหนดมาตรฐานในการปฏิบัติงานให้สูงขึ้นหรือมีประสิทธิภาพมากขึ้นกว่าเดิม',2),(48,6,2,NULL,'สั่งให้ปรับปรุงการปฏิบัติงานให้เป็นไปตามมาตรฐาน กฎ ระเบียบ ข้อบังคับ',3),(49,7,1,NULL,'ใช้ข้อมูลที่มีอยู่ หรือค้นหาจากแหล่งข้อมูลที่มีอยู่แล้ว',1),(50,7,1,NULL,'สอบถามผู้ที่เกี่ยวข้องโดยตรง หรือผู้ที่ใกล้ชิดกับเหตุการณ์ หรือเรื่องราวมากที่สุดเพื่อให้ได้ข้อมูล',2),(51,7,1,NULL,'ตรวจสอบความน่าเชื่อถือของข้อมูลซึ่งเผยแพร่ทั่วไปก่อนนำมาใช้ในการปฏิบัติงาน',3),(52,8,1,NULL,'เข้าใจที่มาที่ไปของปัญหา และแยกแยะปัญหา หรือสถานการณ์ออกเป็นรายการได้ โดยเรียงลำดับตามความสำคัญและความเร่งด่วน',1),(53,8,1,NULL,'วางแผนงานที่ตนรับผิดชอบโดยแตกประเด็นปัญหาออกเป็นส่วนๆ หรือเป็นกิจกรรมต่างๆ ได้',2),(54,8,1,NULL,'ระบุเหตุและผล ข้อดีข้อเสียของประเด็นปัญหา หรือในแต่ละสถานการณ์ได้',3),(55,8,2,NULL,'เข้าใจประเด็นปัญหาหรือสถานการณ์ในรายละเอียด และแยกแยะปัญหาหรือสถานการณ์ที่ซับซ้อนออกเป็นรายการ โดยเรียงลำดับตามความสำคัญและเร่งด่วน',1),(56,8,2,NULL,'วางแผนงานโดยกำหนดกิจกรรม ขั้นตอนการดำเนินงานต่างๆ ที่มีผู้เกี่ยวข้องหลายฝ่ายได้อย่างมีประสิทธิภาพ และสามารถคาดการณ์เกี่ยวกับปัญหา หรืออุปสรรคที่อาจจะเกิดขึ้นได้',2),(57,8,2,NULL,'ระบุเหตุและผล รวมถึงข้อดีข้อเสียของประเด็นปัญหา หรือในแต่ละสถานการณ์ได้อย่างครบถ้วนและรอบด้าน พร้อมทั้งสามารถเสนอแนะแนวทางการแก้ไขปัญหาที่หลากหลาย',3),(58,9,1,NULL,'เล็งเห็นปัญหา อุปสรรค และหาวิธีแก้ไข โดยไม่รอช้า โดยอาจไม่มีใครร้องขอ',1),(59,9,1,NULL,'เล็งเห็นโอกาสและไม่รีรอที่จะนำโอกาสนั้นมาใช้ประโยชน์ในงาน',2),(60,9,2,NULL,'คาดการณ์และเตรียมการล่วงหน้า เพื่อสร้างโอกาส หรือหลีกเลี่ยงปัญหาที่อาจเกิดขึ้นในระยะสั้น',1),(61,9,2,NULL,'ทดลองใช้วิธีการแปลกใหม่ในการแก้ไขปัญหาหรือสร้างสรรค์สิ่งใหม่ให้เกิดขึ้น',2),(62,10,1,NULL,'ใช้ทฤษฎีหรือแนวคิดพื้นฐาน หลักเกณฑ์ สามัญสำนึก หรือประสบการณ์ของตนในการระบุประเด็นปัญหาหรือแก้ปัญหาในงาน',1),(63,10,1,NULL,'ระบุถึงความเชื่อมโยงของข้อมูล แนวโน้มและความไม่ครบถ้วนของข้อมูลได้',2),(64,10,2,NULL,'ประยุกต์ทฤษฎี แนวคิดที่ซับซ้อน หรือแนวโน้มในอดีต รวมถึงประสบการณ์ของตนหรือผู้อื่นในการระบุหรือแก้ปัญหาตามสถานการณ์',1),(65,10,2,NULL,'สามารถสรุปแนวคิด ทฤษฎี องค์ความรู้ทั่วไปและอธิบายให้ผู้อื่นเข้าใจได้โดยง่าย',2),(66,10,2,NULL,'ค้นคว้า เปรียบเทียบ และนำเสนอรูปแบบ วิธีการหรือองค์ความรู้ใหม่ที่เป็นประโยชน์ต่องาน',3),(67,11,1,NULL,'ตื่นตัวต่อความเปลี่ยนแปลงที่เกิดขึ้นรอบตัวอยู่แสมอ',1),(68,11,1,NULL,'วิเคราะห์ข้อมูลเพื่อให้ทราบถึงแนวโน้มและตัวขับเคลื่อนการเปลี่ยนแปลงในระยะสั้น',2),(69,11,2,NULL,'ใช้เครื่องมือและวิธีการเพื่อค้นหาและทำความเข้าใจสัญญาณของการเปลี่ยนแปลงในปัจจุบัน และผลกระทบที่อาจเกิดขึ้นในอนาคต',1),(70,11,2,NULL,'ตีความข้อมูลและคาดการณ์สถานการณ์ต่าง ๆ ที่อาจเกิดขึ้นได้ในอนาคต โดยผสมผสานกันระหว่างการใช้เทคนิคและแนวปฏิบัติที่มองการณ์ไกล',2),(71,12,1,NULL,'ภาคภูมิใจในวัฒนธรรมไทย ขณะที่เห็นคุณค่าและสนใจที่จะเรียนรู้วัฒนธรรมของผู้อื่น',1),(72,12,1,NULL,'รู้จักมารยาท กาลเทศะ ตลอดจนธรรมเนียมปฏิบัติของวัฒนธรรมที่แตกต่าง และแสดงออกด้วยวิธีการ เนื้อหาและถ้อยคำที่เหมาะสมกับวัฒนธรรมของผู้อื่น',2),(73,13,1,NULL,'ควบคุมการแสดงกิริยาท่าทาง ทั้งด้วยน้ำเสียง ท่าทีหรือความคิดเห็น ได้อย่างมืออาชีพ เมื่อต้องรับมือกับสถานการณ์ตึงเครียดในระยะเวลาสั้นๆ หรืออารมณ์เชิงลบจากผู้อื่น',1),(74,13,1,NULL,'ไม่แสดงออกทางอารมณ์ สีหน้า ท่าทาง และคำพูด เพื่อปกป้อง แก้ตัว หรือแก้ต่างให้กับตนเอง เมื่อต้องเผชิญกับคำวิจารณ์',2),(75,13,1,NULL,'ตระหนักและเข้าใจถึงสิ่งกระตุ้นความเครียดส่วนบุคคลของตนและมีการดำเนินการเพื่อจำกัดผลกระทบที่อาจเกิดขึ้นได้',3),(76,13,1,NULL,'มองปัญหาหรือสถานการณ์ได้อย่างรอบด้านและตอบสนองได้อย่างเหมาะสม',4),(80,14,1,7,'พัฒนาแนวคิดที่อาจอยู่นอกเหนือขอบเขตความรับผิดชอบ แต่เป็นประโยชน์ต่อทีม',1),(81,14,1,7,'วิเคราะห์กระบวนการและขั้นตอนที่มีอยู่เดิมอย่างสร้างสรรค์ เพื่อค้นหาวิธีการที่ดีกว่า',2),(82,14,1,7,'พิจารณาได้ว่าแนวคิดหรือคำแนะนำใดสามารถนำไปปฏิบัติได้จริง หรือสามารถพัฒนาต่อยอดได้',3);
/*!40000 ALTER TABLE `indicators` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `evaluation_templates` WRITE;
/*!40000 ALTER TABLE `evaluation_templates` DISABLE KEYS */;
INSERT INTO `evaluation_templates` (`id`, `position_id`, `expected_level`, `competency_id`, `weight`, `level_description`) VALUES (1,2,1,1,15.00,NULL),(2,2,1,2,15.00,NULL),(3,2,1,3,15.00,NULL),(4,2,1,4,15.00,NULL),(5,2,1,5,15.00,NULL),(6,2,1,6,15.00,NULL),(7,2,1,7,10.00,NULL),(8,2,2,1,15.00,NULL),(9,2,2,2,15.00,NULL),(10,2,2,3,15.00,NULL),(11,2,2,4,15.00,NULL),(12,2,2,5,15.00,NULL),(13,2,2,6,15.00,NULL),(14,2,2,7,10.00,NULL),(15,3,1,1,15.00,NULL),(16,3,1,2,15.00,NULL),(17,3,1,3,15.00,NULL),(18,3,1,4,15.00,NULL),(19,3,1,5,15.00,NULL),(20,3,1,6,15.00,NULL),(21,3,1,7,10.00,NULL),(22,3,2,1,15.00,NULL),(23,3,2,2,15.00,NULL),(24,3,2,3,15.00,NULL),(25,3,2,4,15.00,NULL),(26,3,2,5,15.00,NULL),(27,3,2,6,15.00,NULL),(28,3,2,7,10.00,NULL),(29,4,1,1,15.00,NULL),(30,4,1,2,15.00,NULL),(31,4,1,3,15.00,NULL),(32,4,1,4,15.00,NULL),(33,4,1,10,15.00,NULL),(34,4,1,11,15.00,NULL),(35,4,1,6,10.00,NULL),(36,4,2,1,15.00,NULL),(37,4,2,2,15.00,NULL),(38,4,2,3,15.00,NULL),(39,4,2,4,15.00,NULL),(40,4,2,10,15.00,NULL),(41,4,2,11,15.00,NULL),(42,4,2,6,10.00,NULL),(43,5,1,1,15.00,NULL),(44,5,1,2,15.00,NULL),(45,5,1,3,15.00,NULL),(46,5,1,4,15.00,NULL),(47,5,1,12,15.00,NULL),(48,5,1,11,15.00,NULL),(49,5,1,6,10.00,NULL),(50,5,2,1,15.00,NULL),(51,5,2,2,15.00,NULL),(52,5,2,3,15.00,NULL),(53,5,2,4,15.00,NULL),(54,5,2,12,15.00,NULL),(55,5,2,11,15.00,NULL),(56,5,2,6,10.00,NULL),(57,6,1,1,15.00,NULL),(58,6,1,2,15.00,NULL),(59,6,1,3,15.00,NULL),(60,6,1,4,15.00,NULL),(61,6,1,8,15.00,NULL),(62,6,1,6,15.00,NULL),(63,6,1,13,10.00,NULL),(64,6,2,1,15.00,NULL),(65,6,2,2,15.00,NULL),(66,6,2,3,15.00,NULL),(67,6,2,4,15.00,NULL),(68,6,2,8,15.00,NULL),(69,6,2,6,15.00,NULL),(70,6,2,13,10.00,NULL),(71,7,1,1,15.00,NULL),(72,7,1,2,15.00,NULL),(73,7,1,3,15.00,NULL),(74,7,1,4,15.00,NULL),(78,7,2,1,15.00,NULL),(79,7,2,2,15.00,NULL),(80,7,2,3,15.00,NULL),(81,7,2,4,15.00,NULL),(85,8,1,1,15.00,NULL),(86,8,1,2,15.00,NULL),(87,8,1,3,15.00,NULL),(88,8,1,4,15.00,NULL),(89,8,1,8,15.00,NULL),(90,8,1,10,15.00,NULL),(91,8,1,9,10.00,NULL),(92,8,2,1,15.00,NULL),(93,8,2,2,15.00,NULL),(94,8,2,3,15.00,NULL),(95,8,2,4,15.00,NULL),(96,8,2,8,15.00,NULL),(97,8,2,10,15.00,NULL),(98,8,2,9,10.00,NULL),(99,9,1,1,15.00,NULL),(100,9,1,2,15.00,NULL),(101,9,1,3,15.00,NULL),(102,9,1,4,15.00,NULL),(103,9,1,8,15.00,NULL),(104,9,1,9,15.00,NULL),(105,9,1,6,10.00,NULL),(106,9,2,1,15.00,NULL),(107,9,2,2,15.00,NULL),(108,9,2,3,15.00,NULL),(109,9,2,4,15.00,NULL),(110,9,2,8,15.00,NULL),(111,9,2,9,15.00,NULL),(112,9,2,6,10.00,NULL),(113,10,1,1,15.00,NULL),(114,10,1,2,15.00,NULL),(115,10,1,3,15.00,NULL),(116,10,1,4,15.00,NULL),(117,10,1,5,15.00,NULL),(118,10,1,6,15.00,NULL),(119,10,1,7,10.00,NULL),(120,10,2,1,15.00,NULL),(121,10,2,2,15.00,NULL),(122,10,2,3,15.00,NULL),(123,10,2,4,15.00,NULL),(124,10,2,5,15.00,NULL),(125,10,2,6,15.00,NULL),(126,10,2,7,10.00,NULL),(127,11,1,1,15.00,NULL),(128,11,1,2,15.00,NULL),(129,11,1,3,15.00,NULL),(130,11,1,4,15.00,NULL),(131,11,1,5,15.00,NULL),(132,11,1,6,15.00,NULL),(133,11,1,7,10.00,NULL),(134,11,2,1,15.00,NULL),(135,11,2,2,15.00,NULL),(136,11,2,3,15.00,NULL),(137,11,2,4,15.00,NULL),(138,11,2,5,15.00,NULL),(139,11,2,6,15.00,NULL),(140,11,2,7,10.00,NULL),(147,7,1,8,15.00,'ใช้ทฤษฎีหรือแนวคิดพื้นฐาน และประยุกต์ใช้ประสบการณ์ในการทำงาน'),(148,7,1,5,15.00,NULL),(149,7,1,14,10.00,'เข้าใจและวิเคราะห์แนวทางเดิมได้อย่างสร้างสรรค์ เพื่อค้นหาวิธีการใหม่'),(150,7,2,8,15.00,'ใช้ทฤษฎีหรือแนวคิดพื้นฐาน และประยุกต์ใช้ประสบการณ์ในการทำงาน'),(151,7,2,5,15.00,NULL),(152,7,2,14,10.00,'เข้าใจและวิเคราะห์แนวทางเดิมได้อย่างสร้างสรรค์ เพื่อค้นหาวิธีการใหม่');
/*!40000 ALTER TABLE `evaluation_templates` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


-- ระบบตัวชี้วัดผลสัมฤทธิ์ของงาน
CREATE TABLE IF NOT EXISTS `kpi_indicators` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cycle_id` int NOT NULL,
  `name` text NOT NULL,
  `target_label` varchar(255) DEFAULT NULL,
  `unit` varchar(100) DEFAULT NULL,
  `weight` decimal(8,2) NOT NULL DEFAULT 0,
  `target_value` decimal(14,4) DEFAULT NULL,
  `score_1_threshold` decimal(14,4) NOT NULL,
  `score_2_threshold` decimal(14,4) NOT NULL,
  `score_3_threshold` decimal(14,4) NOT NULL,
  `score_4_threshold` decimal(14,4) NOT NULL,
  `score_5_threshold` decimal(14,4) NOT NULL,
  `scoring_direction` enum('ascending','descending') NOT NULL DEFAULT 'ascending',
  `order_seq` int NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_kpi_indicators_cycle` (`cycle_id`,`order_seq`),
  CONSTRAINT `fk_kpi_indicators_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kpi_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `indicator_id` int NOT NULL,
  `user_id` int NOT NULL,
  `responsibility_type` enum('primary','secondary') NOT NULL DEFAULT 'primary',
  `assigned_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_kpi_assignment` (`indicator_id`,`user_id`), KEY `idx_kpi_assignment_user` (`user_id`),
  CONSTRAINT `fk_kpi_assignment_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `kpi_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kpi_assignment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kpi_assignment_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kpi_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `indicator_id` int NOT NULL,
  `department_id` int NOT NULL,
  `actual_value` decimal(14,4) DEFAULT NULL,
  `percentage` decimal(14,4) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `weighted_score` decimal(12,4) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `entered_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), UNIQUE KEY `uq_kpi_result` (`indicator_id`,`department_id`), KEY `idx_kpi_result_department` (`department_id`),
  CONSTRAINT `fk_kpi_result_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `kpi_indicators` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kpi_result_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_kpi_result_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `kpi_indicators` (`cycle_id`,`name`,`target_label`,`unit`,`weight`,`target_value`,`score_1_threshold`,`score_2_threshold`,`score_3_threshold`,`score_4_threshold`,`score_5_threshold`,`scoring_direction`,`order_seq`)
SELECT c.id,s.name,s.target_label,s.unit,s.weight,s.target_value,s.s1,s.s2,s.s3,s.s4,s.s5,s.direction,s.order_seq
FROM `evaluation_cycles` c JOIN (
 SELECT 1 order_seq,'หน่วยงานส่งข้อมูล 43 แฟ้ม ทันเวลา อย่างน้อยวันละ 1 ครั้ง' name,'ร้อยละ 100' target_label,'ร้อยละ' unit,1.50 weight,100.0000 target_value,80.0000 s1,85.0000 s2,90.0000 s3,95.0000 s4,100.0000 s5,'ascending' direction
 UNION ALL SELECT 2,'การเบิกจ่ายค่าชดเชยบริการสาธารณสุข','100 บาท/ประชากร','บาท/ประชากร',1.50,20.0000,1.0000,5.0000,10.0000,15.0000,20.0000,'ascending'
 UNION ALL SELECT 3,'ประชากรซ้ำซ้อน ไม่เกินร้อยละ 1','น้อยกว่าร้อยละ 1','ร้อยละ',1.50,1.0000,1.2500,1.0000,0.7500,0.5000,0.2500,'descending'
) s WHERE c.fiscal_year='2569' AND (c.round_name='2' OR c.round_name LIKE '%2%')
AND NOT EXISTS (SELECT 1 FROM `kpi_indicators` k WHERE k.cycle_id=c.id);

-- บัญชีผู้ดูแลระบบเริ่มต้นจะถูกสร้างอัตโนมัติเมื่อเปิดเว็บไซต์ครั้งแรก
