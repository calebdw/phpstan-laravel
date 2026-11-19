CREATE TABLE `dump_posts` (
  `id` bigint unsigned NOT NULL,
  `body` int NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `migrations` VALUES (1,'2020_02_02_000000_add_foo_columns_to_posts_table',1);
