CREATE TABLE `dump_posts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `status` enum('draft','published') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` VALUES (1,'2020_01_01_000000_create_posts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2020_01_02_000000_add_status_to_posts_table',1);
