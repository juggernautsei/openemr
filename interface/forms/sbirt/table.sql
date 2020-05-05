CREATE TABLE IF NOT EXISTS `form_sbirt` (
    id bigint(20) NOT NULL auto_increment,
    date datetime default NULL,
    pid bigint(20) default NULL,
    user varchar(255) default NULL,
    answers longtext default NULL,
    score tinyint(3) default NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;
