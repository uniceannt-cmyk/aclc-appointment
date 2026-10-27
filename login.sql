DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `student_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(25) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'student',
  `education_level` varchar(20) DEFAULT NULL,
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


LOCK TABLES `users` WRITE;

INSERT INTO `users` (`student_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `created_at`, `role`, `education_level`) VALUES ('ADMIN-001','Registrar','Admin','admin@aclc.com','','$2y$12$zp/PkCzjRqOWTkj2NeaxO.rgRWLe3rKzcsLy9Q1/YrvwifYPxGKYu','2026-08-29 02:06:20','registrar',NULL),('24001321100','Unice Ann','Tubal','unixiann@gmail.com','09202527586','$2y$10$bBQWup5BTa0TUF/w1fWT.O/ATibnmCLhNTMdaBr1yTn4eEawNvLiS','2026-09-20 05:36:31','student','College');

UNLOCK TABLES;
