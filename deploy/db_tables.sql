drop table if exists network_detail; 
create table network_detail (
    id int(11) NOT NULL auto_increment,
    ip varbinary(16) default NULL,
    username VARCHAR(64) default NULL,
    device_type VARCHAR(64) default NULL,
    device_vendor VARCHAR(64) default NULL,
    latitude FLOAT(10,6) default NULL,
    longitude FLOAT(10,6) default NULL,
	ip_src CHAR(45) NOT NULL,
	ip_dst CHAR(45) NOT NULL,
    packets INT UNSIGNED NOT NULL,
	bytes BIGINT UNSIGNED NOT NULL,
	stamp_inserted DATETIME NOT NULL,
	stamp_updated DATETIME,
	PRIMARY KEY (id),
    INDEX(ip_src),
    INDEX(ip_dst),
    INDEX(ip),
    INDEX(username),
    INDEX(device_type),
    INDEX(device_vendor),
    INDEX(stamp_inserted)
) ENGINE=innodb DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
