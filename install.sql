-- ============================================================
--  GROUPE JNAK SARL – Script d'installation MySQL
--  Exécutez ce fichier via phpMyAdmin ou la console MySQL
--  Commande : mysql -u root -p < install.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `jnak_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `jnak_db`;

-- ============================================================
--  TABLE : articles
-- ============================================================
CREATE TABLE IF NOT EXISTS `articles` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255)     NOT NULL,
  `author`      VARCHAR(100)     NOT NULL,
  `tag`         VARCHAR(60)      NOT NULL DEFAULT 'Management',
  `excerpt`     TEXT             NOT NULL,
  `content`     LONGTEXT         NOT NULL,
  `image_url`   VARCHAR(500)         NULL,
  `status`      ENUM('published','draft') NOT NULL DEFAULT 'draft',
  `likes`       INT UNSIGNED     NOT NULL DEFAULT 0,
  `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP
                                          ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status`  (`status`),
  KEY `idx_tag`     (`tag`),
  KEY `idx_created` (`created_at` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE : comments
-- ============================================================
CREATE TABLE IF NOT EXISTS `comments` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `article_id`  INT UNSIGNED     NOT NULL,
  `name`        VARCHAR(100)     NOT NULL,
  `text`        TEXT             NOT NULL,
  `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_article` (`article_id`),
  CONSTRAINT `fk_comment_article`
    FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE : likes (pour éviter les doublons par IP)
-- ============================================================
CREATE TABLE IF NOT EXISTS `article_likes` (
  `article_id`  INT UNSIGNED     NOT NULL,
  `ip_hash`     VARCHAR(64)      NOT NULL,
  `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`article_id`, `ip_hash`),
  CONSTRAINT `fk_like_article`
    FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE : contact_messages
-- ============================================================
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nom`         VARCHAR(100)     NOT NULL,
  `email`       VARCHAR(150)     NOT NULL,
  `sujet`       VARCHAR(255)     NOT NULL,
  `domaine`     VARCHAR(100)         NULL,
  `message`     TEXT             NOT NULL,
  `lu`          TINYINT(1)       NOT NULL DEFAULT 0,
  `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lu` (`lu`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE : admin_sessions
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_sessions` (
  `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `token_hash`  VARCHAR(64)      NOT NULL,
  `expires_at`  DATETIME         NOT NULL,
  `created_at`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token_hash`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Données de démonstration (article exemple)
-- ============================================================
INSERT INTO `articles` (`title`, `author`, `tag`, `excerpt`, `content`, `image_url`, `status`, `likes`) VALUES
(
  'Bienvenue sur le blog du Groupe Jnak SARL',
  'Équipe Jnak',
  'Management',
  'Découvrez les actualités, conseils et analyses du Groupe Jnak SARL, votre partenaire pour la réussite de vos projets en Afrique centrale.',
  '<p>Bienvenue sur notre espace blog ! Ici, nous partageons régulièrement nos <strong>analyses du marché</strong>, nos conseils pratiques et les actualités du Groupe Jnak SARL.</p><p>Que vous soyez intéressé par le commerce, l''agriculture, l''immobilier ou le tourisme, vous trouverez ici des informations utiles pour <strong>développer vos projets</strong>.</p><p>N''hésitez pas à laisser vos commentaires et à partager nos articles !</p>',
  'https://images.unsplash.com/photo-1497366216548-37526070297c?w=700&q=80',
  'published',
  0
);

SELECT 'Installation terminée avec succès !' AS message;
