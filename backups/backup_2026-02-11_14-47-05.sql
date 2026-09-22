-- Sims-Tech Inventory System Database Backup
-- Generated: 2026-02-11 14:47:05
-- Database: simstech_inventory

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `backup_settings`;
CREATE TABLE `backup_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `categories` VALUES ('1', 'Laptops', 'Various laptop brands and models', '2026-01-27 04:02:14');
INSERT INTO `categories` VALUES ('2', 'Power Packs', 'Portable power banks and battery packs', '2026-01-27 04:02:14');
INSERT INTO `categories` VALUES ('3', 'Power Cables', 'Charging cables and power cords', '2026-01-27 04:02:14');
INSERT INTO `categories` VALUES ('4', 'IPhone Chargers', 'Mobile phone chargers and adapters', '2026-01-27 04:02:14');
INSERT INTO `categories` VALUES ('5', 'Headsets', 'Audio headphones and headsets', '2026-01-27 04:02:14');
INSERT INTO `categories` VALUES ('6', 'SSDs', 'Solid State Drives for storage', '2026-01-27 04:02:14');
INSERT INTO `categories` VALUES ('7', 'Hard Drives', 'Traditional hard disk drives', '2026-01-27 04:02:14');
INSERT INTO `categories` VALUES ('8', 'Books', 'Other computer and phone accessories', '2026-01-27 04:02:14');
INSERT INTO `categories` VALUES ('9', 'Laptops', 'Various laptop brands and models', '2026-01-27 17:13:57');
INSERT INTO `categories` VALUES ('10', 'Power Packs', 'Portable power banks and battery packs', '2026-01-27 17:13:57');
INSERT INTO `categories` VALUES ('11', 'Power Cables', 'Charging cables and power cords', '2026-01-27 17:13:57');
INSERT INTO `categories` VALUES ('12', 'Phone Chargers', 'Mobile phone chargers and adapters', '2026-01-27 17:13:57');
INSERT INTO `categories` VALUES ('13', 'Headsets', 'Audio headphones and headsets', '2026-01-27 17:13:57');
INSERT INTO `categories` VALUES ('14', 'SSDs', 'Solid State Drives for storage', '2026-01-27 17:13:57');
INSERT INTO `categories` VALUES ('15', 'Hard Drives', 'Traditional hard disk drives', '2026-01-27 17:13:57');
INSERT INTO `categories` VALUES ('16', 'Accessories', 'Other computer and phone accessories', '2026-01-27 17:13:57');
INSERT INTO `categories` VALUES ('17', 'Laptops', 'Various laptop brands and models', '2026-01-29 14:19:27');
INSERT INTO `categories` VALUES ('18', 'Power Packs', 'Portable power banks and battery packs', '2026-01-29 14:19:27');
INSERT INTO `categories` VALUES ('19', 'Power Cables', 'Charging cables and power cords', '2026-01-29 14:19:27');
INSERT INTO `categories` VALUES ('20', 'Phone Chargers', 'Mobile phone chargers and adapters', '2026-01-29 14:19:27');
INSERT INTO `categories` VALUES ('21', 'Headsets', 'Audio headphones and headsets', '2026-01-29 14:19:27');
INSERT INTO `categories` VALUES ('22', 'SSDs', 'Solid State Drives for storage', '2026-01-29 14:19:27');
INSERT INTO `categories` VALUES ('23', 'Hard Drives', 'Traditional hard disk drives', '2026-01-29 14:19:27');
INSERT INTO `categories` VALUES ('24', 'Accessories', 'Other computer and phone accessories', '2026-01-29 14:19:27');

DROP TABLE IF EXISTS `company_info`;
CREATE TABLE `company_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) DEFAULT 'Sims-Tech Zambia',
  `tagline` varchar(255) DEFAULT NULL,
  `about_us` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `mobile` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `tpin` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `pay_to_sale` varchar(50) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `company_info` VALUES ('1', 'SIMS-TECH ZAMBIA LIMITED', 'SAVINGS THROUGH MAINTENANCE OF YOUR COMPUTERS', 'Sims-Tech Zambia is a leading technology solutions provider offering quality electronics, computer accessories, and professional repair services. We are committed to delivering excellent products and services to our valued customers.', 'UNZA MAIN CAMPUS, NEXT TO THE POST OFFICE', '+260973071800', '+260969362923', 'simstechzambia@gmail.com', '2002530937', 'FNB', 'SIMS-TECH ZAMBIA', '6292984114', '260006', '0973071800', NULL, '', '', '', '2026-01-28 11:32:12');
INSERT INTO `company_info` VALUES ('2', 'SIMS-TECH ZAMBIA LIMITED', 'SAVINGS THROUGH MAINTENANCE OF YOUR COMPUTERS', 'Sims-Tech Zambia is a leading technology solutions provider offering quality electronics, computer accessories, and professional repair services. We are committed to delivering excellent products and services to our valued customers.', 'UNZA MAIN CAMPUS, NEXT TO THE POST OFFICE', '+260973071800', '+260969362923', 'simstechzambia@gmail.com', '2002530937', 'STANBIC', 'SIMS-TECH ZAMBIA', '6292984114', '260006', '0973071800', NULL, NULL, NULL, NULL, '2026-01-27 17:13:57');
INSERT INTO `company_info` VALUES ('3', 'SIMS-TECH ZAMBIA LIMITED', 'SAVINGS THROUGH MAINTENANCE OF YOUR COMPUTERS', 'Sims-Tech Zambia is a leading technology solutions provider offering quality electronics, computer accessories, and professional repair services. We are committed to delivering excellent products and services to our valued customers.', 'UNZA MAIN CAMPUS, NEXT TO THE POST OFFICE', '+260973071800', '+260969362923', 'simstechzambia@gmail.com', '2002530937', 'STANBIC', 'SIMS-TECH ZAMBIA', '6292984114', '260006', '0973071800', NULL, NULL, NULL, NULL, '2026-01-29 14:19:27');

DROP TABLE IF EXISTS `daily_sales_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_sales_summary` AS select cast(`sales`.`sale_date` as date) AS `sale_day`,count(0) AS `total_transactions`,sum(`sales`.`total_amount`) AS `total_revenue`,avg(`sales`.`total_amount`) AS `avg_transaction` from `sales` group by cast(`sales`.`sale_date` as date) order by cast(`sales`.`sale_date` as date) desc;

INSERT INTO `daily_sales_summary` VALUES ('2026-02-11', '1', '7000.00', '7000.000000');
INSERT INTO `daily_sales_summary` VALUES ('2026-02-02', '1', '15000.00', '15000.000000');
INSERT INTO `daily_sales_summary` VALUES ('2026-01-28', '3', '20800.00', '6933.333333');
INSERT INTO `daily_sales_summary` VALUES ('2026-01-27', '2', '650.00', '325.000000');

DROP TABLE IF EXISTS `document_items`;
CREATE TABLE `document_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `document_id` (`document_id`),
  CONSTRAINT `document_items_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `document_items` VALUES ('1', '1', 'Hp elitebook', '1', '7500.00', '7500.00');
INSERT INTO `document_items` VALUES ('2', '2', 'laptop', '1', '500.04', '500.04');
INSERT INTO `document_items` VALUES ('3', '3', 'Dell latitude', '2', '6000.00', '12000.00');
INSERT INTO `document_items` VALUES ('4', '4', 'Laptop Repair', '1', '150.00', '150.00');

DROP TABLE IF EXISTS `document_templates`;
CREATE TABLE `document_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('invoice','quotation','receipt','other') NOT NULL DEFAULT 'other',
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `is_default` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `document_templates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `document_templates` VALUES ('3', '1', 'arnold', 'receipt', 'template_698b3ff4d8c6c_1770733556.docx', 'MR.  Kaonga Receipt.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '246174', 'power', '0', '2026-02-10 16:25:56', '2026-02-10 16:25:56');

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_number` varchar(50) NOT NULL,
  `document_type` enum('invoice','quotation','receipt') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `client_name` varchar(100) NOT NULL,
  `client_phone` varchar(50) DEFAULT NULL,
  `client_email` varchar(100) DEFAULT NULL,
  `client_address` text DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('draft','sent','paid','cancelled') DEFAULT 'draft',
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_number` (`document_number`),
  KEY `user_id` (`user_id`),
  KEY `idx_documents_type` (`document_type`),
  KEY `idx_documents_status` (`status`),
  KEY `idx_documents_date` (`created_at`),
  CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `documents` VALUES ('1', 'INV-20260205-0001', 'invoice', '1', 'Hahsel Simutowe', '0968745131', 'Arnoldchama36@gmail.com', 'lusakawest 1212', '7500.00', '0.00', '0.00', '7500.00', '', 'draft', NULL, '2026-02-05 08:21:44', '2026-02-05 08:21:44');
INSERT INTO `documents` VALUES ('2', 'REC-20260210-0001', 'receipt', '1', 'Hanshel Simutowe', '0968745131', 'Arnoldchama36@gmail.com', 'lusakawest 1212', '500.04', '0.00', '0.00', '500.04', '', 'paid', NULL, '2026-02-10 16:29:35', '2026-02-10 16:29:35');
INSERT INTO `documents` VALUES ('3', 'INV-20260210-0001', 'invoice', '1', 'Hanshel Simutowe', '0968745131', 'Arnoldchama36@gmail.com', 'lusakawest 1212', '12000.00', '0.00', '0.00', '12000.00', '', 'paid', NULL, '2026-02-10 16:41:20', '2026-02-10 16:41:20');
INSERT INTO `documents` VALUES ('4', 'INV-20260210-0002', 'invoice', '1', 'Hanshel Simutowe', '0968745131', 'Arnoldchama36@gmail.com', 'lusakawest 1212', '150.00', '0.00', '0.00', '150.00', '', 'sent', NULL, '2026-02-10 16:53:58', '2026-02-10 16:53:58');

DROP TABLE IF EXISTS `email_settings`;
CREATE TABLE `email_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `email_settings` VALUES ('1', 'smtp_host', 'smtp.gmail.com', '2026-01-29 14:15:08');
INSERT INTO `email_settings` VALUES ('2', 'smtp_port', '587', '2026-01-29 14:15:08');
INSERT INTO `email_settings` VALUES ('3', 'smtp_username', 'Arnoldchama36@gmail.com', '2026-01-29 14:15:08');
INSERT INTO `email_settings` VALUES ('4', 'smtp_password', 'djfvfrvcfmueidsz', '2026-01-29 14:15:08');
INSERT INTO `email_settings` VALUES ('5', 'smtp_from_email', 'Arnoldchama36@gmail.com', '2026-01-29 14:15:08');
INSERT INTO `email_settings` VALUES ('6', 'smtp_from_name', 'Sims-Tech Zambia', '2026-01-29 14:15:08');
INSERT INTO `email_settings` VALUES ('7', 'smtp_encryption', 'tls', '2026-01-29 14:15:08');

DROP TABLE IF EXISTS `low_stock_products`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `low_stock_products` AS select `p`.`id` AS `id`,`p`.`serial_number` AS `serial_number`,`p`.`name` AS `name`,`p`.`description` AS `description`,`p`.`specifications` AS `specifications`,`p`.`category_id` AS `category_id`,`p`.`price` AS `price`,`p`.`cost_price` AS `cost_price`,`p`.`quantity` AS `quantity`,`p`.`min_stock_level` AS `min_stock_level`,`p`.`image` AS `image`,`p`.`status` AS `status`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at`,`c`.`name` AS `category_name` from (`products` `p` left join `categories` `c` on(`p`.`category_id` = `c`.`id`)) where `p`.`quantity` <= `p`.`min_stock_level` and `p`.`status` = 'active';

INSERT INTO `low_stock_products` VALUES ('1', '102901', 'Hp elitebook', 'preowned', '8gb ram, core i5 ,14 inches.', '1', '7500.00', '4000.00', '0', '5', '6978cc14683bb.jpeg', 'active', '2026-01-27 16:30:44', '2026-02-11 15:42:48', 'Laptops');
INSERT INTO `low_stock_products` VALUES ('2', '23445', 'Dell latitude', 'preowned', '8gb ram, core i5', '1', '6000.00', '4000.00', '0', '5', '6979dc2f6bcd5.jpeg', 'active', '2026-01-28 11:51:43', '2026-02-05 09:30:38', 'Laptops');

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `password_resets` VALUES ('1', '5', '32a262b24cca80afc5f867c5351a6745e8f9b96677617afeb59fb38bec8962a5', '2026-02-05 09:21:16', '0', '2026-02-05 09:21:16');
INSERT INTO `password_resets` VALUES ('2', '5', '393a3021d4552c271a2067a33272e896c85f2a508bba88e26498a4cdbfc85a3c', '2026-02-05 09:22:56', '0', '2026-02-05 09:22:56');
INSERT INTO `password_resets` VALUES ('3', '5', 'e66e26e06d4ff67172bea4ccf2bb0618472b4513b39bfd6ddd5ac9d43983d15b', '2026-02-05 11:04:25', '0', '2026-02-05 11:04:25');
INSERT INTO `password_resets` VALUES ('4', '5', '65d62ad0a3aa82dc7fe322fc5c4793c4822f18eaf6717f4b252d1fd6213acbe5', '2026-02-05 11:04:34', '0', '2026-02-05 11:04:34');
INSERT INTO `password_resets` VALUES ('5', '5', '95b5372c23e8b2ee559fc0292d60dab7fd1b19c7236a3dd8b8a466b7759cb1b2', '2026-02-05 11:04:42', '0', '2026-02-05 11:04:42');
INSERT INTO `password_resets` VALUES ('6', '5', '46a241ead700ab9e04db0dfd1d29bd28e79687ff5e612ccea43e26e589c63f88', '2026-02-05 11:04:48', '0', '2026-02-05 11:04:48');
INSERT INTO `password_resets` VALUES ('7', '5', '7dac6a73ecb2ed1749c74b5805b7e0bccf23f23c9175f48a6cc5e11342892002', '2026-02-05 11:04:55', '0', '2026-02-05 11:04:55');
INSERT INTO `password_resets` VALUES ('8', '5', '5d986c5798b6590d59c0de334da10fb2ddf4d5dda8cc0b66e831494bd7f9e17e', '2026-02-05 11:05:03', '0', '2026-02-05 11:05:03');
INSERT INTO `password_resets` VALUES ('9', '5', '9c049f5b1026517567b9f0cbf1d6ab5b03cf90e553dcba0ee11952b2331cab03', '2026-02-05 11:05:11', '0', '2026-02-05 11:05:11');
INSERT INTO `password_resets` VALUES ('10', '5', '40f51c2ffbdd5661d02438a3fe5b3f5bfed1ead39a68b30d37f7d800f1e2aebe', '2026-02-05 11:05:18', '0', '2026-02-05 11:05:18');
INSERT INTO `password_resets` VALUES ('11', '5', '927a13fd9b9c3bdb2f028451dfbb642ba60cc778a346c12268fc021319d1800a', '2026-02-05 11:05:27', '0', '2026-02-05 11:05:27');
INSERT INTO `password_resets` VALUES ('12', '5', '8e2cc3b6e972d6dfa32e60775e62cb51cd8f220664a54a3d2413461d310c6dd4', '2026-02-05 11:05:34', '0', '2026-02-05 11:05:34');
INSERT INTO `password_resets` VALUES ('13', '5', '5c30e08cf22485e0581cfafe7b61797dc87dc9a53d3a8637c6be2415fd046570', '2026-02-05 11:05:40', '0', '2026-02-05 11:05:40');
INSERT INTO `password_resets` VALUES ('14', '5', '2895d3d221cc8b33cee28e1525854cc892b0b319d570a791950bebfebad141f2', '2026-02-05 11:05:48', '0', '2026-02-05 11:05:48');
INSERT INTO `password_resets` VALUES ('15', '5', '6774aa3ac0d76b1a22b50afc9803b5a9e41f6b25a3a8c404e8b3d6c5069d2f9e', '2026-02-05 11:05:54', '0', '2026-02-05 11:05:54');
INSERT INTO `password_resets` VALUES ('16', '5', '2ce5b9b26b2a23d2d6d6aad7dde45717ba4451de1357aff527a91fa2caa71574', '2026-02-05 11:06:06', '0', '2026-02-05 11:06:06');
INSERT INTO `password_resets` VALUES ('17', '5', '9f8d079c57f54fd8101508b2707751139c9571c8bbdbe43a68cca8c75ce6a601', '2026-02-05 11:06:13', '0', '2026-02-05 11:06:13');
INSERT INTO `password_resets` VALUES ('18', '5', '0c28339e648b7a904a07028f4da506dab52b154ea16ea61492014ff79707ee52', '2026-02-05 11:06:21', '0', '2026-02-05 11:06:21');
INSERT INTO `password_resets` VALUES ('19', '5', '897938a0f30fe69083a1925a552032fc1ccaa8814f3776b755b40fa0730b0691', '2026-02-05 11:06:28', '0', '2026-02-05 11:06:28');
INSERT INTO `password_resets` VALUES ('20', '5', '4b8c7790ce447dce8aaf35bcd4521ad7dad8c6ef70678762ab16fc653912c9fd', '2026-02-05 11:06:35', '0', '2026-02-05 11:06:35');
INSERT INTO `password_resets` VALUES ('21', '5', '92f24580f4fe4b37e15b210ed45494de99f5988d98758e1bcab8c2fff47094d7', '2026-02-05 14:14:36', '0', '2026-02-05 14:14:36');
INSERT INTO `password_resets` VALUES ('22', '5', '97427042b1ea642460b06cf20ceec498247fda3c658d356efa08e30994c32c58', '2026-02-05 14:14:42', '0', '2026-02-05 14:14:42');
INSERT INTO `password_resets` VALUES ('23', '5', 'bf02251b03894271a3208736229a4c23f389e4421817f43314eb5d6a99591293', '2026-02-06 18:44:24', '0', '2026-02-06 18:44:24');
INSERT INTO `password_resets` VALUES ('24', '5', '7430b2ffb20f72124f98b70b6e67041b5b7cc03dc0b01391b534276021877e38', '2026-02-06 18:44:31', '0', '2026-02-06 18:44:31');
INSERT INTO `password_resets` VALUES ('25', '5', 'd2d25100f868f0c340ee46ec8bde01c30117616a27113ba549a5a6d9c8f7ab64', '2026-02-06 18:44:38', '0', '2026-02-06 18:44:38');
INSERT INTO `password_resets` VALUES ('26', '5', 'e35362b4ff0d130e8c413af142b0d910747889ae3697deee649c02db9f600bf0', '2026-02-06 18:44:44', '0', '2026-02-06 18:44:44');
INSERT INTO `password_resets` VALUES ('27', '5', '9dd1ca5aa388bebf3dbc9cd50761b9b66eed813f984fb4bc17359d72bbbd38c8', '2026-02-06 18:44:51', '0', '2026-02-06 18:44:51');
INSERT INTO `password_resets` VALUES ('28', '5', '2a2b4d9286111d0b5178b2fd365201463ad4cb025784210fa5b242878abf9983', '2026-02-06 18:44:57', '0', '2026-02-06 18:44:57');
INSERT INTO `password_resets` VALUES ('29', '5', '20f6b93bc9bf687c1f047e8e20edebab0a22a236d224c60469e2575006eb85d4', '2026-02-06 18:45:03', '0', '2026-02-06 18:45:03');
INSERT INTO `password_resets` VALUES ('30', '5', '6c460724a0e89c1001d2946f795dbf85322b3b980c110cdb9322f2707e1df89b', '2026-02-06 18:45:09', '0', '2026-02-06 18:45:09');
INSERT INTO `password_resets` VALUES ('31', '5', 'ca6e3fdfa163e36ef8c06916b9e936152d58d3dbe7f07ec18b1b9d8643a0bb4d', '2026-02-06 18:45:15', '0', '2026-02-06 18:45:15');
INSERT INTO `password_resets` VALUES ('32', '5', 'b9ebd3298517b91c05b7d956ec0a9bdf744cb824e5004d9f964d955503b8cddc', '2026-02-06 18:45:31', '0', '2026-02-06 18:45:31');
INSERT INTO `password_resets` VALUES ('33', '5', 'faabdccb55a3cc77a160230444613434c165a9788535dc4e03b242076f361eb6', '2026-02-06 18:46:18', '0', '2026-02-06 18:46:18');
INSERT INTO `password_resets` VALUES ('34', '5', '507397c182c78700e1487c575351d8490666895d4c49112b22b2fbca19969565', '2026-02-06 18:46:24', '0', '2026-02-06 18:46:24');
INSERT INTO `password_resets` VALUES ('35', '5', 'edebd17ea676949ed4096b41e60e9851fc1018fec8d8aa343929463af99a4aa6', '2026-02-06 18:46:30', '0', '2026-02-06 18:46:30');
INSERT INTO `password_resets` VALUES ('36', '5', '4ae94f9c972b64eca7d683a8bb9d05f8a03095c8af04b42a3011f1ead7b982c4', '2026-02-06 18:46:36', '0', '2026-02-06 18:46:36');
INSERT INTO `password_resets` VALUES ('37', '5', '388eff7c613092bb833d8013fed92bc2e99bfabcdcec47332be4ac2b8f0bae17', '2026-02-06 18:46:42', '0', '2026-02-06 18:46:42');

DROP TABLE IF EXISTS `product_sales_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_sales_summary` AS select `p`.`id` AS `id`,`p`.`name` AS `name`,`p`.`serial_number` AS `serial_number`,coalesce(sum(`si`.`quantity`),0) AS `total_sold`,coalesce(sum(`si`.`total_price`),0) AS `total_revenue` from (`products` `p` left join `sale_items` `si` on(`p`.`id` = `si`.`product_id` and `si`.`item_type` = 'product')) group by `p`.`id` order by coalesce(sum(`si`.`quantity`),0) desc;

INSERT INTO `product_sales_summary` VALUES ('1', 'Hp elitebook', '102901', '5', '36300.00');
INSERT INTO `product_sales_summary` VALUES ('2', 'Dell latitude', '23445', '1', '6500.00');

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `serial_number` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `min_stock_level` int(11) DEFAULT 5,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number` (`serial_number`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_status` (`status`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `products` VALUES ('1', '102901', 'Hp elitebook', 'preowned', '8gb ram, core i5 ,14 inches.', '1', '7500.00', '4000.00', '0', '5', '6978cc14683bb.jpeg', 'active', '2026-01-27 16:30:44', '2026-02-11 15:42:48');
INSERT INTO `products` VALUES ('2', '23445', 'Dell latitude', 'preowned', '8gb ram, core i5', '1', '6000.00', '4000.00', '0', '5', '6979dc2f6bcd5.jpeg', 'active', '2026-01-28 11:51:43', '2026-02-05 09:30:38');

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `item_type` enum('product','service') NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `product_id` (`product_id`),
  KEY `service_id` (`service_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sale_items_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sale_items` VALUES ('1', '1', NULL, NULL, 'service', '1', '500.00', '500.00');
INSERT INTO `sale_items` VALUES ('2', '2', NULL, '5', 'service', '1', '150.00', '150.00');
INSERT INTO `sale_items` VALUES ('3', '3', '1', NULL, 'product', '1', '6500.00', '6500.00');
INSERT INTO `sale_items` VALUES ('4', '4', '1', NULL, 'product', '1', '7800.00', '7800.00');
INSERT INTO `sale_items` VALUES ('5', '5', '2', NULL, 'product', '1', '6500.00', '6500.00');
INSERT INTO `sale_items` VALUES ('8', '8', '1', NULL, 'product', '2', '7500.00', '15000.00');
INSERT INTO `sale_items` VALUES ('9', '9', '1', NULL, 'product', '1', '7000.00', '7000.00');

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','card','mobile_money') DEFAULT 'cash',
  `sale_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `idx_sales_date` (`sale_date`),
  KEY `idx_sales_user` (`user_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `sales` VALUES ('1', 'INV-20260127-0001', '1', 'Arnold', '0958449', '500.00', 'cash', '2026-01-27 17:24:42');
INSERT INTO `sales` VALUES ('2', 'INV-20260127-0002', NULL, 'Joseph', '09739459032', '150.00', 'mobile_money', '2026-01-27 17:26:39');
INSERT INTO `sales` VALUES ('3', 'INV-20260128-0001', NULL, 'Arnold', '09739459032', '6500.00', 'cash', '2026-01-28 09:59:53');
INSERT INTO `sales` VALUES ('4', 'INV-20260128-0002', NULL, 'Mr mweene', '097577483', '7800.00', 'cash', '2026-01-28 11:20:49');
INSERT INTO `sales` VALUES ('5', 'INV-20260128-0003', '1', 'Arnold', '0968745131', '6500.00', 'mobile_money', '2026-01-28 11:52:34');
INSERT INTO `sales` VALUES ('8', 'INV-20260202-0001', '1', 'Hanshel simutowe', '09739459032', '15000.00', 'cash', '2026-02-02 12:31:52');
INSERT INTO `sales` VALUES ('9', 'INV-20260211-0001', '1', 'Nico', '09739459032', '7000.00', 'cash', '2026-02-11 15:42:48');

DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration` varchar(50) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `services` VALUES ('3', 'Passport Photos', 'Professional passport size photo services', '20.00', '30 minutes', NULL, 'active', '2026-01-27 04:02:14', '2026-01-27 04:02:14');
INSERT INTO `services` VALUES ('5', 'Laptop Repair', 'Professional laptop repair and maintenance services', '150.00', '1-3 days', NULL, 'active', '2026-01-27 17:13:57', '2026-01-27 17:13:57');
INSERT INTO `services` VALUES ('6', 'Phone Repair', 'Mobile phone screen replacement and repairs', '100.00', '1-2 days', NULL, 'active', '2026-01-27 17:13:57', '2026-01-27 17:13:57');
INSERT INTO `services` VALUES ('13', 'Printing Services', 'Document printing, scanning and copying', '5.00', 'Immediate', NULL, 'active', '2026-01-29 14:19:27', '2026-01-29 14:19:27');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','employee') DEFAULT 'employee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` VALUES ('1', 'admin', '$2y$10$XQa6.UaAo5m8uRRbpNu15O1hlxLnyXpC0Ye3FsGU/IhrpvTnW/Czq', 'System Administrator', 'admin@simstech.com', 'admin', '2026-01-27 04:02:14', '2026-02-11 15:47:05');
INSERT INTO `users` VALUES ('4', 'Joseph', '$2y$10$w4iyGYgl7Lq/ec4cYa.TneyS.FSPROy2mnvWxGkyzhsDoBEF2jE1u', 'Joseph', 'Josephkamfwa@gmail.com', 'employee', '2026-01-28 13:01:33', '2026-01-28 13:01:33');
INSERT INTO `users` VALUES ('5', 'ArnoldChama', '$2y$10$473H9T1Q4gEohDS3OgDRru9yAun1ypd5aiz64Z1nKj5ISAD.cIW7C', 'Arnold  Chama', 'Arnoldchama36@gmail.com', 'employee', '2026-01-29 13:38:57', '2026-01-29 13:38:57');
INSERT INTO `users` VALUES ('7', 'Arnold', '$2y$10$thqf/BNRAaHdr1llADKSVOzxggOTNxu.trN7SQbzqhfRguT.2xFQG', 'Arnold User', 'arnold@simstech.com', 'employee', '2026-02-11 15:46:33', '2026-02-11 15:47:05');

SET FOREIGN_KEY_CHECKS=1;
