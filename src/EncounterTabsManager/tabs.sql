CREATE TABLE `tabs_manager` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `category` int(5) DEFAULT NULL,
  `form_list` varchar(255) DEFAULT NULL,
  `age` int(5) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB