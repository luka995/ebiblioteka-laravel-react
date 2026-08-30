-- Valentina Studio --
-- MySQL dump --
-- ---------------------------------------------------------


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
-- ---------------------------------------------------------


-- CREATE TABLE "access_logs" ----------------------------------
CREATE TABLE `access_logs`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`user_id` Int( 11 ) NULL DEFAULT NULL,
	`_get` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`post` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`_session` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`url` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`actionTime` DateTime NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 4071391;
-- -------------------------------------------------------------


-- CREATE TABLE "authors" --------------------------------------
CREATE TABLE `authors`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 92188;
-- -------------------------------------------------------------


-- CREATE TABLE "authors_central" ------------------------------
CREATE TABLE `authors_central`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 111804;
-- -------------------------------------------------------------


-- CREATE TABLE "banners" --------------------------------------
CREATE TABLE `banners`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`text` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 2;
-- -------------------------------------------------------------


-- CREATE TABLE "bar_code_seq" ---------------------------------
CREATE TABLE `bar_code_seq`( 
	`code` BigInt( 20 ) NOT NULL )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB;
-- -------------------------------------------------------------


-- CREATE TABLE "book_authors" ---------------------------------
CREATE TABLE `book_authors`( 
	`book_id` Int( 11 ) NOT NULL,
	`author_id` Int( 11 ) NOT NULL,
	PRIMARY KEY ( `book_id`, `author_id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB;
-- -------------------------------------------------------------


-- CREATE TABLE "book_central_authors" -------------------------
CREATE TABLE `book_central_authors`( 
	`bookcentral_id` Int( 11 ) NOT NULL,
	`authorcentral_id` Int( 11 ) NOT NULL,
	PRIMARY KEY ( `bookcentral_id`, `authorcentral_id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB;
-- -------------------------------------------------------------


-- CREATE TABLE "book_copies" ----------------------------------
CREATE TABLE `book_copies`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`book_id` Int( 11 ) NULL DEFAULT NULL,
	`barcode` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`dateAdd` Date NULL DEFAULT NULL,
	`deleted` TinyInt( 1 ) NOT NULL,
	`borrowed` TinyInt( 1 ) NOT NULL,
	`reserved` TinyInt( 1 ) NOT NULL,
	`binding` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`origin` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`notice` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`numOfPages` Int( 11 ) NULL DEFAULT NULL,
	`isbn` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`publisher` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`udk` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`dimension` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`part` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`price` Decimal( 10, 0 ) NULL DEFAULT NULL,
	`bookNumber` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`publishYear` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`publishPlace` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`orderNumber` BigInt( 20 ) NULL DEFAULT NULL,
	`issueNumber` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`placeOnShelf` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`outOfDate` TinyInt( 4 ) NULL DEFAULT 0,
	`unusable` TinyInt( 4 ) NULL DEFAULT 0,
	`deleteDate` Date NULL DEFAULT NULL,
	`recError` TinyInt( 1 ) NULL DEFAULT 0,
	`recErrorNotice` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`seq_number` Int( 11 ) NULL DEFAULT NULL,
	PRIMARY KEY ( `id` ),
	CONSTRAINT `order_number` UNIQUE( `library_id`, `orderNumber` ),
	CONSTRAINT `unique_seq_number_library_id` UNIQUE( `seq_number`, `library_id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 387059;
-- -------------------------------------------------------------


-- CREATE TABLE "book_copies_confirmed" ------------------------
CREATE TABLE `book_copies_confirmed`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`book_copy_id` Int( 11 ) NOT NULL,
	`library_id` Int( 11 ) NOT NULL,
	`date` Date NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_general_ci
ENGINE = InnoDB
AUTO_INCREMENT = 13365;
-- -------------------------------------------------------------


-- CREATE TABLE "book_history" ---------------------------------
CREATE TABLE `book_history`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`book_id` Int( 11 ) NULL DEFAULT NULL,
	`book_copy_id` Int( 11 ) NULL DEFAULT NULL,
	`user_id` Int( 11 ) NULL DEFAULT NULL,
	`actionDate` DateTime NOT NULL,
	`action` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 94888;
-- -------------------------------------------------------------


-- CREATE TABLE "books" ----------------------------------------
CREATE TABLE `books`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`category_prim` Int( 11 ) NULL DEFAULT NULL,
	`category_sec` Int( 11 ) NULL DEFAULT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`description` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`image` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`available` Int( 11 ) NOT NULL,
	`total` Int( 11 ) NOT NULL,
	`deleted` TinyInt( 1 ) NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 156006;
-- -------------------------------------------------------------


-- CREATE TABLE "books_central" --------------------------------
CREATE TABLE `books_central`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`description` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`deleted` TinyInt( 1 ) NOT NULL,
	`isbn` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`udk` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`publisher` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`pages` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`dimension` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 202086;
-- -------------------------------------------------------------


-- CREATE TABLE "borrow" ---------------------------------------
CREATE TABLE `borrow`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`user_id` Int( 11 ) NULL DEFAULT NULL,
	`validTill` DateTime NOT NULL,
	`fromDate` DateTime NOT NULL,
	`returnDate` DateTime NULL DEFAULT NULL,
	`returned` TinyInt( 1 ) NOT NULL,
	`book_id` Int( 11 ) NULL DEFAULT NULL,
	`bookcopy_id` Int( 11 ) NULL DEFAULT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 48352;
-- -------------------------------------------------------------


-- CREATE TABLE "category" -------------------------------------
CREATE TABLE `category`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`category_id` Int( 11 ) NULL DEFAULT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 6099;
-- -------------------------------------------------------------


-- CREATE TABLE "city" -----------------------------------------
CREATE TABLE `city`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 99;
-- -------------------------------------------------------------


-- CREATE TABLE "fos_user" -------------------------------------
CREATE TABLE `fos_user`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`username` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`username_canonical` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`email` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`email_canonical` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`enabled` TinyInt( 1 ) NOT NULL,
	`salt` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`password` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`last_login` DateTime NULL DEFAULT NULL,
	`locked` TinyInt( 1 ) NOT NULL,
	`expired` TinyInt( 1 ) NOT NULL,
	`expires_at` DateTime NULL DEFAULT NULL,
	`confirmation_token` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`password_requested_at` DateTime NULL DEFAULT NULL,
	`roles` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT '(DC2Type:array)',
	`credentials_expired` TinyInt( 1 ) NOT NULL,
	`credentials_expire_at` DateTime NULL DEFAULT NULL,
	`firstName` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`lastName` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`jmbg` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`address` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`city` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`postCode` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`school` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`barCode` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`contact` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`validTill` DateTime NULL DEFAULT NULL,
	PRIMARY KEY ( `id` ),
	CONSTRAINT `UNIQ_957A647992FC23A8` UNIQUE( `username_canonical` ),
	CONSTRAINT `UNIQ_957A6479F85E0677` UNIQUE( `username` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 17027;
-- -------------------------------------------------------------


-- CREATE TABLE "inventar_books" -------------------------------
CREATE TABLE `inventar_books`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`date` DateTime NOT NULL,
	`file` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`status` Int( 11 ) NOT NULL,
	`working` Int( 11 ) NOT NULL,
	`locale` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 2056;
-- -------------------------------------------------------------


-- CREATE TABLE "library" --------------------------------------
CREATE TABLE `library`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`region_id` Int( 11 ) NULL DEFAULT NULL,
	`city_id` Int( 11 ) NULL DEFAULT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`address` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`workTime` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`deleted` TinyInt( 1 ) NOT NULL,
	`inv_br_rucno` Int( 11 ) NOT NULL DEFAULT 0,
	`kontrolni_bar_kod` Int( 11 ) NOT NULL DEFAULT 1,
	`bar_kod_rucno` Int( 11 ) NOT NULL DEFAULT 0,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 164;
-- -------------------------------------------------------------


-- CREATE TABLE "library_settings" -----------------------------
CREATE TABLE `library_settings`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`membership` Int( 11 ) NOT NULL,
	`reservationTime` Int( 11 ) NOT NULL,
	`lendTime` Int( 11 ) NOT NULL,
	`overDuePrice` Int( 11 ) NOT NULL,
	`printName` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ),
	CONSTRAINT `UNIQ_C3E651D1FE2541D7` UNIQUE( `library_id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 9;
-- -------------------------------------------------------------


-- CREATE TABLE "membership" -----------------------------------
CREATE TABLE `membership`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`user_id` Int( 11 ) NULL DEFAULT NULL,
	`validTill` DateTime NOT NULL,
	`fromDate` DateTime NOT NULL,
	`description` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`price` Int( 11 ) NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 38;
-- -------------------------------------------------------------


-- CREATE TABLE "migration_versions" ---------------------------
CREATE TABLE `migration_versions`( 
	`version` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `version` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB;
-- -------------------------------------------------------------


-- CREATE TABLE "news" -----------------------------------------
CREATE TABLE `news`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`title` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`smallText` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`full_text` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`date` Date NOT NULL,
	`image` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 19;
-- -------------------------------------------------------------


-- CREATE TABLE "pages" ----------------------------------------
CREATE TABLE `pages`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`description` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	`locale` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 5;
-- -------------------------------------------------------------


-- CREATE TABLE "recomendations" -------------------------------
CREATE TABLE `recomendations`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`title` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`smallText` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`full_text` LongText CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`image` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 20;
-- -------------------------------------------------------------


-- CREATE TABLE "region" ---------------------------------------
CREATE TABLE `region`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 48;
-- -------------------------------------------------------------


-- CREATE TABLE "reservation" ----------------------------------
CREATE TABLE `reservation`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`user_id` Int( 11 ) NULL DEFAULT NULL,
	`validTill` DateTime NOT NULL,
	`fromDate` DateTime NOT NULL,
	`book_id` Int( 11 ) NULL DEFAULT NULL,
	`book_copy_id` Int( 11 ) NULL DEFAULT NULL,
	`history` TinyInt( 1 ) NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 29;
-- -------------------------------------------------------------


-- CREATE TABLE "tags" -----------------------------------------
CREATE TABLE `tags`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`name` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 1172;
-- -------------------------------------------------------------


-- CREATE TABLE "transaction" ----------------------------------
CREATE TABLE `transaction`( 
	`id` Int( 11 ) AUTO_INCREMENT NOT NULL,
	`library_id` Int( 11 ) NULL DEFAULT NULL,
	`amount` Int( 11 ) NOT NULL,
	`description` VarChar( 255 ) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	`tranDate` DateTime NOT NULL,
	PRIMARY KEY ( `id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB
AUTO_INCREMENT = 308801;
-- -------------------------------------------------------------


-- CREATE TABLE "user_tag" -------------------------------------
CREATE TABLE `user_tag`( 
	`user_id` Int( 11 ) NOT NULL,
	`tag_id` Int( 11 ) NOT NULL,
	PRIMARY KEY ( `user_id`, `tag_id` ) )
CHARACTER SET = utf8
COLLATE = utf8_unicode_ci
ENGINE = InnoDB;
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_F08FC65CA76ED395" -------------------------
CREATE INDEX `IDX_F08FC65CA76ED395` USING BTREE ON `access_logs`( `user_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_F08FC65CFE2541D7" -------------------------
CREATE INDEX `IDX_F08FC65CFE2541D7` USING BTREE ON `access_logs`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_8E0C2A51FE2541D7" -------------------------
CREATE INDEX `IDX_8E0C2A51FE2541D7` USING BTREE ON `authors`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_1D2C02C716A2B381" -------------------------
CREATE INDEX `IDX_1D2C02C716A2B381` USING BTREE ON `book_authors`( `book_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_1D2C02C7F675F31B" -------------------------
CREATE INDEX `IDX_1D2C02C7F675F31B` USING BTREE ON `book_authors`( `author_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_665AD1AE479244AA" -------------------------
CREATE INDEX `IDX_665AD1AE479244AA` USING BTREE ON `book_central_authors`( `bookcentral_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_665AD1AEEED885C5" -------------------------
CREATE INDEX `IDX_665AD1AEEED885C5` USING BTREE ON `book_central_authors`( `authorcentral_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_F0A8D81116A2B381" -------------------------
CREATE INDEX `IDX_F0A8D81116A2B381` USING BTREE ON `book_copies`( `book_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_F0A8D811FE2541D7" -------------------------
CREATE INDEX `IDX_F0A8D811FE2541D7` USING BTREE ON `book_copies`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "book_copy_id" ---------------------------------
CREATE INDEX `book_copy_id` USING BTREE ON `book_copies_confirmed`( `book_copy_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "library_id" -----------------------------------
CREATE INDEX `library_id` USING BTREE ON `book_copies_confirmed`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_B49A58DD16A2B381" -------------------------
CREATE INDEX `IDX_B49A58DD16A2B381` USING BTREE ON `book_history`( `book_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_B49A58DD3B550FE4" -------------------------
CREATE INDEX `IDX_B49A58DD3B550FE4` USING BTREE ON `book_history`( `book_copy_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_B49A58DDA76ED395" -------------------------
CREATE INDEX `IDX_B49A58DDA76ED395` USING BTREE ON `book_history`( `user_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_B49A58DDFE2541D7" -------------------------
CREATE INDEX `IDX_B49A58DDFE2541D7` USING BTREE ON `book_history`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_4A1B2A9211A974F2" -------------------------
CREATE INDEX `IDX_4A1B2A9211A974F2` USING BTREE ON `books`( `category_prim` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_4A1B2A9240050D41" -------------------------
CREATE INDEX `IDX_4A1B2A9240050D41` USING BTREE ON `books`( `category_sec` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_4A1B2A92FE2541D7" -------------------------
CREATE INDEX `IDX_4A1B2A92FE2541D7` USING BTREE ON `books`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "FK_55DBA8B016A2B381" --------------------------
CREATE INDEX `FK_55DBA8B016A2B381` USING BTREE ON `borrow`( `book_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "FK_55DBA8B07891CB7C" --------------------------
CREATE INDEX `FK_55DBA8B07891CB7C` USING BTREE ON `borrow`( `bookcopy_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_55DBA8B0A76ED395" -------------------------
CREATE INDEX `IDX_55DBA8B0A76ED395` USING BTREE ON `borrow`( `user_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_55DBA8B0FE2541D7" -------------------------
CREATE INDEX `IDX_55DBA8B0FE2541D7` USING BTREE ON `borrow`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_64C19C112469DE2" --------------------------
CREATE INDEX `IDX_64C19C112469DE2` USING BTREE ON `category`( `category_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_64C19C1FE2541D7" --------------------------
CREATE INDEX `IDX_64C19C1FE2541D7` USING BTREE ON `category`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_957A6479FE2541D7" -------------------------
CREATE INDEX `IDX_957A6479FE2541D7` USING BTREE ON `fos_user`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_99E761BEFE2541D7" -------------------------
CREATE INDEX `IDX_99E761BEFE2541D7` USING BTREE ON `inventar_books`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_A18098BC8BAC62AF" -------------------------
CREATE INDEX `IDX_A18098BC8BAC62AF` USING BTREE ON `library`( `city_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_A18098BC98260155" -------------------------
CREATE INDEX `IDX_A18098BC98260155` USING BTREE ON `library`( `region_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_86FFD285A76ED395" -------------------------
CREATE INDEX `IDX_86FFD285A76ED395` USING BTREE ON `membership`( `user_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_86FFD285FE2541D7" -------------------------
CREATE INDEX `IDX_86FFD285FE2541D7` USING BTREE ON `membership`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_C454C68216A2B381" -------------------------
CREATE INDEX `IDX_C454C68216A2B381` USING BTREE ON `reservation`( `book_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_C454C6823B550FE4" -------------------------
CREATE INDEX `IDX_C454C6823B550FE4` USING BTREE ON `reservation`( `book_copy_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_C454C682A76ED395" -------------------------
CREATE INDEX `IDX_C454C682A76ED395` USING BTREE ON `reservation`( `user_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_C454C682FE2541D7" -------------------------
CREATE INDEX `IDX_C454C682FE2541D7` USING BTREE ON `reservation`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_6FBC9426FE2541D7" -------------------------
CREATE INDEX `IDX_6FBC9426FE2541D7` USING BTREE ON `tags`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_723705D1FE2541D7" -------------------------
CREATE INDEX `IDX_723705D1FE2541D7` USING BTREE ON `transaction`( `library_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_E89FD608A76ED395" -------------------------
CREATE INDEX `IDX_E89FD608A76ED395` USING BTREE ON `user_tag`( `user_id` );
-- -------------------------------------------------------------


-- CREATE INDEX "IDX_E89FD608BAD26311" -------------------------
CREATE INDEX `IDX_E89FD608BAD26311` USING BTREE ON `user_tag`( `tag_id` );
-- -------------------------------------------------------------


-- CREATE LINK "FK_F08FC65CA76ED395" ---------------------------
ALTER TABLE `access_logs`
	ADD CONSTRAINT `FK_F08FC65CA76ED395` FOREIGN KEY ( `user_id` )
	REFERENCES `fos_user`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_F08FC65CFE2541D7" ---------------------------
ALTER TABLE `access_logs`
	ADD CONSTRAINT `FK_F08FC65CFE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_8E0C2A51FE2541D7" ---------------------------
ALTER TABLE `authors`
	ADD CONSTRAINT `FK_8E0C2A51FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_1D2C02C716A2B381" ---------------------------
ALTER TABLE `book_authors`
	ADD CONSTRAINT `FK_1D2C02C716A2B381` FOREIGN KEY ( `book_id` )
	REFERENCES `books`( `id` )
	ON DELETE Cascade
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_1D2C02C7F675F31B" ---------------------------
ALTER TABLE `book_authors`
	ADD CONSTRAINT `FK_1D2C02C7F675F31B` FOREIGN KEY ( `author_id` )
	REFERENCES `authors`( `id` )
	ON DELETE Cascade
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_665AD1AE479244AA" ---------------------------
ALTER TABLE `book_central_authors`
	ADD CONSTRAINT `FK_665AD1AE479244AA` FOREIGN KEY ( `bookcentral_id` )
	REFERENCES `books_central`( `id` )
	ON DELETE Cascade
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_665AD1AEEED885C5" ---------------------------
ALTER TABLE `book_central_authors`
	ADD CONSTRAINT `FK_665AD1AEEED885C5` FOREIGN KEY ( `authorcentral_id` )
	REFERENCES `authors_central`( `id` )
	ON DELETE Cascade
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_F0A8D81116A2B381" ---------------------------
ALTER TABLE `book_copies`
	ADD CONSTRAINT `FK_F0A8D81116A2B381` FOREIGN KEY ( `book_id` )
	REFERENCES `books`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_F0A8D811FE2541D7" ---------------------------
ALTER TABLE `book_copies`
	ADD CONSTRAINT `FK_F0A8D811FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "book_copies_confirmed_ibfk_1" ------------------
ALTER TABLE `book_copies_confirmed`
	ADD CONSTRAINT `book_copies_confirmed_ibfk_1` FOREIGN KEY ( `book_copy_id` )
	REFERENCES `book_copies`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "book_copies_confirmed_ibfk_2" ------------------
ALTER TABLE `book_copies_confirmed`
	ADD CONSTRAINT `book_copies_confirmed_ibfk_2` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_B49A58DD16A2B381" ---------------------------
ALTER TABLE `book_history`
	ADD CONSTRAINT `FK_B49A58DD16A2B381` FOREIGN KEY ( `book_id` )
	REFERENCES `books`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_B49A58DD3B550FE4" ---------------------------
ALTER TABLE `book_history`
	ADD CONSTRAINT `FK_B49A58DD3B550FE4` FOREIGN KEY ( `book_copy_id` )
	REFERENCES `book_copies`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_B49A58DDA76ED395" ---------------------------
ALTER TABLE `book_history`
	ADD CONSTRAINT `FK_B49A58DDA76ED395` FOREIGN KEY ( `user_id` )
	REFERENCES `fos_user`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_B49A58DDFE2541D7" ---------------------------
ALTER TABLE `book_history`
	ADD CONSTRAINT `FK_B49A58DDFE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_4A1B2A9211A974F2" ---------------------------
ALTER TABLE `books`
	ADD CONSTRAINT `FK_4A1B2A9211A974F2` FOREIGN KEY ( `category_prim` )
	REFERENCES `category`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_4A1B2A9240050D41" ---------------------------
ALTER TABLE `books`
	ADD CONSTRAINT `FK_4A1B2A9240050D41` FOREIGN KEY ( `category_sec` )
	REFERENCES `category`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_4A1B2A92FE2541D7" ---------------------------
ALTER TABLE `books`
	ADD CONSTRAINT `FK_4A1B2A92FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_55DBA8B016A2B381" ---------------------------
ALTER TABLE `borrow`
	ADD CONSTRAINT `FK_55DBA8B016A2B381` FOREIGN KEY ( `book_id` )
	REFERENCES `books`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_55DBA8B07891CB7C" ---------------------------
ALTER TABLE `borrow`
	ADD CONSTRAINT `FK_55DBA8B07891CB7C` FOREIGN KEY ( `bookcopy_id` )
	REFERENCES `book_copies`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_55DBA8B0A76ED395" ---------------------------
ALTER TABLE `borrow`
	ADD CONSTRAINT `FK_55DBA8B0A76ED395` FOREIGN KEY ( `user_id` )
	REFERENCES `fos_user`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_55DBA8B0FE2541D7" ---------------------------
ALTER TABLE `borrow`
	ADD CONSTRAINT `FK_55DBA8B0FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_64C19C112469DE2" ----------------------------
ALTER TABLE `category`
	ADD CONSTRAINT `FK_64C19C112469DE2` FOREIGN KEY ( `category_id` )
	REFERENCES `category`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_64C19C1FE2541D7" ----------------------------
ALTER TABLE `category`
	ADD CONSTRAINT `FK_64C19C1FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_957A6479FE2541D7" ---------------------------
ALTER TABLE `fos_user`
	ADD CONSTRAINT `FK_957A6479FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_99E761BEFE2541D7" ---------------------------
ALTER TABLE `inventar_books`
	ADD CONSTRAINT `FK_99E761BEFE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_A18098BC8BAC62AF" ---------------------------
ALTER TABLE `library`
	ADD CONSTRAINT `FK_A18098BC8BAC62AF` FOREIGN KEY ( `city_id` )
	REFERENCES `city`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_A18098BC98260155" ---------------------------
ALTER TABLE `library`
	ADD CONSTRAINT `FK_A18098BC98260155` FOREIGN KEY ( `region_id` )
	REFERENCES `region`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_C3E651D1FE2541D7" ---------------------------
ALTER TABLE `library_settings`
	ADD CONSTRAINT `FK_C3E651D1FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_86FFD285A76ED395" ---------------------------
ALTER TABLE `membership`
	ADD CONSTRAINT `FK_86FFD285A76ED395` FOREIGN KEY ( `user_id` )
	REFERENCES `fos_user`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_86FFD285FE2541D7" ---------------------------
ALTER TABLE `membership`
	ADD CONSTRAINT `FK_86FFD285FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_C454C68216A2B381" ---------------------------
ALTER TABLE `reservation`
	ADD CONSTRAINT `FK_C454C68216A2B381` FOREIGN KEY ( `book_id` )
	REFERENCES `books`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_C454C6823B550FE4" ---------------------------
ALTER TABLE `reservation`
	ADD CONSTRAINT `FK_C454C6823B550FE4` FOREIGN KEY ( `book_copy_id` )
	REFERENCES `book_copies`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_C454C682A76ED395" ---------------------------
ALTER TABLE `reservation`
	ADD CONSTRAINT `FK_C454C682A76ED395` FOREIGN KEY ( `user_id` )
	REFERENCES `fos_user`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_C454C682FE2541D7" ---------------------------
ALTER TABLE `reservation`
	ADD CONSTRAINT `FK_C454C682FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_6FBC9426FE2541D7" ---------------------------
ALTER TABLE `tags`
	ADD CONSTRAINT `FK_6FBC9426FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_723705D1FE2541D7" ---------------------------
ALTER TABLE `transaction`
	ADD CONSTRAINT `FK_723705D1FE2541D7` FOREIGN KEY ( `library_id` )
	REFERENCES `library`( `id` )
	ON DELETE No Action
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_E89FD608A76ED395" ---------------------------
ALTER TABLE `user_tag`
	ADD CONSTRAINT `FK_E89FD608A76ED395` FOREIGN KEY ( `user_id` )
	REFERENCES `fos_user`( `id` )
	ON DELETE Cascade
	ON UPDATE No Action;
-- -------------------------------------------------------------


-- CREATE LINK "FK_E89FD608BAD26311" ---------------------------
ALTER TABLE `user_tag`
	ADD CONSTRAINT `FK_E89FD608BAD26311` FOREIGN KEY ( `tag_id` )
	REFERENCES `tags`( `id` )
	ON DELETE Cascade
	ON UPDATE No Action;
-- -------------------------------------------------------------


/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
-- ---------------------------------------------------------


