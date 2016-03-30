
create database if not exists instructables;
CREATE USER 'instructables'@'localhost' IDENTIFIED BY 'some_pass';
GRANT ALL PRIVILEGES ON instructables.* TO 'instructables'@'localhost' WITH GRANT OPTION;

CREATE TABLE IF NOT EXISTS contests (
cid int unsigned NOT NULL AUTO_INCREMENT,
contestname VARCHAR(500),
contesturl VARCHAR(500) CHARACTER SET utf8 COLLATE utf8_bin not null UNIQUE Key,
contestimg VARCHAR(500),
contestends date,
contestentries int unsigned default 0,
creation_time DATETIME DEFAULT CURRENT_TIMESTAMP,
modification_time DATETIME ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY (cid)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_bin;

CREATE TABLE IF NOT EXISTS posts (
pid int unsigned NOT NULL AUTO_INCREMENT,
piid VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL UNIQUE Key,
url varchar(500),
title varchar(500),
author varchar(255),
postimage varchar(500),
instructableType varchar(25),
featured BOOL default false,
channel varchar(255),
category varchar(255),
publishDate date,
creation_time DATETIME DEFAULT CURRENT_TIMESTAMP,
modification_time DATETIME ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY (pid)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_bin;

CREATE TABLE IF NOT EXISTS stats (
sid int unsigned NOT NULL AUTO_INCREMENT,
pid int unsigned NOT NULL,
views int unsigned default 0,
favs  int unsigned default 0,
creation_time DATETIME DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (sid)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_bin;



CREATE TABLE IF NOT EXISTS entries (
eid int unsigned NOT NULL AUTO_INCREMENT,
contestid int unsigned NOT NULL,
postid int unsigned NOT NULL,
dateadded DATETIME DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (eid)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COLLATE = utf8_bin;
