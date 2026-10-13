-- MySQL/InnoDB schema for wilycode/translation-database

CREATE TABLE translation_resource (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uName (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE translation_language (
  code CHAR(2) NOT NULL,
  name VARCHAR(30) NOT NULL,
  PRIMARY KEY (code),
  UNIQUE KEY uName (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO translation_language (code, name) VALUES
  ('en', 'English'),
  ('pl', 'Polish');

CREATE TABLE translation_key (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  resource INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  plural TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uNameResource (name, resource),
  KEY iResource (resource),
  CONSTRAINT fkTranslationKey_resource
    FOREIGN KEY (resource) REFERENCES translation_resource (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE translation (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` INT UNSIGNED NOT NULL,
  language CHAR(2) NOT NULL,
  content TEXT NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uKeyLanguage (`key`, language),
  KEY iLanguage (language),
  KEY iKey (`key`),
  CONSTRAINT fkTranslation_key
    FOREIGN KEY (`key`) REFERENCES translation_key (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fkTranslation_language
    FOREIGN KEY (language) REFERENCES translation_language (code)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
