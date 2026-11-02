-- =====================================================================
--  CLUB MEDIA — ISET GAFSA  |  Create. Capture. Inspire.
--  Schéma MySQL / MariaDB (utf8mb4) — importable via phpMyAdmin.
--
--  Import : phpMyAdmin > Importer > choisir ce fichier > Exécuter.
--  Le script crée la base « club_media_db » si elle n'existe pas.
--  Pour utiliser un autre nom de base : remplacez club_media_db
--  ci-dessous (2 lignes) ET DB_NAME dans config/database.php.
--
--  Le script est ré-exécutable sans perte : tables en IF NOT EXISTS,
--  données d'exemple insérées seulement si la table events est vide.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+01:00';

CREATE DATABASE IF NOT EXISTS `club_media_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `club_media_db`;

-- ---------------------------------------------------------------------
-- Administrateurs (aucun compte par défaut : créé via setup.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `email`         VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Catégories d'événements
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_categories` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(80)  NOT NULL,
  `slug`       VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Événements
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`     INT UNSIGNED NULL,
  `title`           VARCHAR(180) NOT NULL,
  `description`     TEXT         NOT NULL,
  `cover_image`     VARCHAR(80)  NULL,
  `start_date`      DATE         NOT NULL,
  `start_time`      TIME         NOT NULL,
  `end_date`        DATE         NOT NULL,
  `end_time`        TIME         NOT NULL,
  `location`        VARCHAR(180) NOT NULL,
  `organizer`       VARCHAR(120) NOT NULL DEFAULT 'Club Media ISET Gafsa',
  `ticket_type`     ENUM('free','paid') NOT NULL DEFAULT 'free',
  `ticket_price`    DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `capacity`        INT UNSIGNED NOT NULL DEFAULT 0,
  `available_seats` INT UNSIGNED NOT NULL DEFAULT 0,
  `status`          ENUM('upcoming','ongoing','finished','cancelled') NOT NULL DEFAULT 'upcoming',
  `is_published`    TINYINT(1)   NOT NULL DEFAULT 1,
  `published_at`    DATETIME     NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_events_category` (`category_id`),
  KEY `idx_events_status` (`status`),
  KEY `idx_events_published_start` (`is_published`, `start_date`, `start_time`),
  CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Réservations (une réservation = 1 à N tickets)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservations` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_code`     VARCHAR(16)  NOT NULL,
  `access_token`         CHAR(48)     NOT NULL,
  `event_id`             INT UNSIGNED NOT NULL,
  `full_name`            VARCHAR(120) NOT NULL,
  `email`                VARCHAR(150) NOT NULL,
  `phone`                VARCHAR(25)  NOT NULL,
  `quantity`             TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price`           DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `total_amount`         DECIMAL(10,3) NOT NULL DEFAULT 0.000,
  `status`               ENUM('confirmed','pending_payment','cancelled') NOT NULL DEFAULT 'confirmed',
  `payment_confirmed_at` DATETIME     NULL,
  `payment_confirmed_by` INT UNSIGNED NULL,
  `cancelled_at`         DATETIME     NULL,
  `ip_address`           VARCHAR(45)  NULL,
  `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reservations_code` (`reservation_code`),
  UNIQUE KEY `uq_reservations_token` (`access_token`),
  KEY `idx_reservations_event_status` (`event_id`, `status`),
  KEY `idx_reservations_email` (`email`),
  KEY `idx_reservations_created` (`created_at`),
  CONSTRAINT `fk_reservations_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_reservations_admin` FOREIGN KEY (`payment_confirmed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Tickets (le QR code contient `token`, jamais l'identifiant séquentiel)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reservation_id` INT UNSIGNED NOT NULL,
  `event_id`       INT UNSIGNED NOT NULL,
  `ticket_number`  VARCHAR(20)  NOT NULL,
  `token`          CHAR(48)     NOT NULL,
  `holder_name`    VARCHAR(120) NOT NULL,
  `status`         ENUM('pending','valid','used','cancelled') NOT NULL DEFAULT 'pending',
  `used_at`        DATETIME     NULL,
  `used_by`        INT UNSIGNED NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tickets_number` (`ticket_number`),
  UNIQUE KEY `uq_tickets_token` (`token`),
  KEY `idx_tickets_reservation` (`reservation_id`),
  KEY `idx_tickets_event_status` (`event_id`, `status`),
  CONSTRAINT `fk_tickets_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tickets_admin` FOREIGN KEY (`used_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Vidéos (upload ou YouTube)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `videos` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`     INT UNSIGNED NULL,
  `title`        VARCHAR(160) NOT NULL,
  `description`  TEXT         NULL,
  `thumbnail`    VARCHAR(80)  NULL,
  `source_type`  ENUM('upload','youtube') NOT NULL,
  `video_file`   VARCHAR(80)  NULL,
  `video_url`    VARCHAR(255) NULL,
  `youtube_id`   VARCHAR(20)  NULL,
  `is_published` TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_videos_event` (`event_id`),
  KEY `idx_videos_published_created` (`is_published`, `created_at`),
  CONSTRAINT `fk_videos_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Albums et photos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `albums` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`    INT UNSIGNED NULL,
  `title`       VARCHAR(160) NOT NULL,
  `description` TEXT         NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_albums_event` (`event_id`),
  CONSTRAINT `fk_albums_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `photos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `album_id`   INT UNSIGNED NOT NULL,
  `filename`   VARCHAR(80)  NOT NULL,
  `caption`    VARCHAR(255) NULL,
  `width`      INT UNSIGNED NOT NULL DEFAULT 0,
  `height`     INT UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` INT          NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_photos_filename` (`filename`),
  KEY `idx_photos_album_sort` (`album_id`, `sort_order`, `id`),
  CONSTRAINT `fk_photos_album` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Équipe / membres
--   is_active          : compte dans « Nombre de membres »
--   show_on_team_page  : affiché sur la page publique « Notre équipe »
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `team_members` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`         VARCHAR(120) NOT NULL,
  `photo`             VARCHAR(80)  NULL,
  `role`              VARCHAR(80)  NOT NULL DEFAULT 'Membre',
  `description`       TEXT         NULL,
  `social_url`        VARCHAR(255) NULL,
  `display_order`     INT          NOT NULL DEFAULT 0,
  `is_active`         TINYINT(1)   NOT NULL DEFAULT 1,
  `show_on_team_page` TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_team_order` (`show_on_team_page`, `is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Messages du formulaire de contact
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(120) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `subject`    VARCHAR(180) NOT NULL,
  `message`    TEXT         NOT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45)  NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_messages_read_created` (`is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Paramètres du site (clé / valeur)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `website_settings` (
  `setting_key`   VARCHAR(80) NOT NULL,
  `setting_value` TEXT        NULL,
  `updated_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Journal d'activité de l'administration
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`   INT UNSIGNED NULL,
  `action`     VARCHAR(100) NOT NULL,
  `details`    VARCHAR(500) NULL,
  `ip_address` VARCHAR(45)  NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_created` (`created_at`),
  KEY `idx_logs_admin` (`admin_id`),
  CONSTRAINT `fk_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Limitation de débit (connexion, réservation, contact…)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rate_limits` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket`     VARCHAR(80) NOT NULL,
  `ip_hash`    CHAR(64)    NOT NULL,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_lookup` (`bucket`, `ip_hash`, `created_at`),
  KEY `idx_rate_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  DONNÉES D'EXEMPLE (modifiables / supprimables depuis l'administration)
-- =====================================================================

INSERT IGNORE INTO `event_categories` (`id`, `name`, `slug`) VALUES
  (1, 'Atelier',               'atelier'),
  (2, 'Projection & culture',  'projection-culture'),
  (3, 'Formation',             'formation'),
  (4, 'Conférence',            'conference'),
  (5, 'Tournage & reportage',  'tournage-reportage');

-- Trois événements d'exemple, créés seulement si aucun événement n'existe.
INSERT INTO `events`
  (`category_id`, `title`, `description`, `start_date`, `start_time`, `end_date`, `end_time`,
   `location`, `organizer`, `ticket_type`, `ticket_price`, `capacity`, `available_seats`, `status`, `is_published`, `published_at`)
SELECT * FROM (
  SELECT 1 AS category_id,
         'Atelier photographie (exemple)' AS title,
         'Événement d’exemple à modifier depuis l’administration.\n\nInitiez-vous aux bases de la photographie : cadrage, lumière et composition. Apportez votre téléphone ou votre appareil photo.' AS description,
         DATE_ADD(CURDATE(), INTERVAL 10 DAY) AS start_date, '15:00:00' AS start_time,
         DATE_ADD(CURDATE(), INTERVAL 10 DAY) AS end_date,   '18:00:00' AS end_time,
         'ISET Gafsa — salle à préciser' AS location,
         'Club Media ISET Gafsa' AS organizer,
         'free' AS ticket_type, 0.000 AS ticket_price, 40 AS capacity, 40 AS available_seats,
         'upcoming' AS status, 1 AS is_published, NOW() AS published_at
  UNION ALL
  SELECT 2, 'Soirée projection (exemple)',
         'Événement d’exemple à modifier depuis l’administration.\n\nUne soirée dédiée aux créations vidéo du club et de la communauté étudiante. Billet payant : la réservation est confirmée après validation du paiement par un administrateur.',
         DATE_ADD(CURDATE(), INTERVAL 21 DAY), '18:00:00',
         DATE_ADD(CURDATE(), INTERVAL 21 DAY), '21:00:00',
         'ISET Gafsa — amphithéâtre', 'Club Media ISET Gafsa',
         'paid', 5.000, 80, 80, 'upcoming', 1, NOW()
  UNION ALL
  SELECT 3, 'Formation montage vidéo (exemple)',
         'Événement d’exemple à modifier depuis l’administration.\n\nDécouvrez les fondamentaux du montage : rythme, coupes, son et exportation.',
         DATE_ADD(CURDATE(), INTERVAL 35 DAY), '14:00:00',
         DATE_ADD(CURDATE(), INTERVAL 35 DAY), '17:00:00',
         'ISET Gafsa — laboratoire informatique', 'Club Media ISET Gafsa',
         'free', 0.000, 25, 25, 'upcoming', 1, NOW()
) AS sample
WHERE NOT EXISTS (SELECT 1 FROM `events`);

-- Paramètres par défaut (texte de présentation à remplacer par des informations vérifiées).
INSERT IGNORE INTO `website_settings` (`setting_key`, `setting_value`) VALUES
  ('site_name',            'Club Media ISET Gafsa'),
  ('tagline',              'Create. Capture. Inspire.'),
  ('club_description',     'Le Club Media ISET Gafsa est un club étudiant dédié à la photographie, à la vidéo et à la création de contenus. Il couvre les événements de l’institut et fait vivre la communauté.'),
  ('contact_email',        ''),
  ('contact_phone',        ''),
  ('contact_address',      'ISET Gafsa, Gafsa, Tunisie'),
  ('social_instagram',     ''),
  ('social_facebook',      ''),
  ('social_youtube',       ''),
  ('social_linkedin',      ''),
  ('social_tiktok',        ''),
  ('logo',                 ''),
  ('hero_image',           ''),
  ('hero_video_id',        ''),
  ('payment_instructions', 'Pour finaliser votre réservation, réglez le montant auprès d’un membre du Club Media en indiquant votre code de réservation. Votre ticket sera activé dès que le paiement aura été confirmé par un administrateur.'),
  ('about_history',        'À compléter : racontez ici la création du club, ses premiers projets et son évolution. (Texte modifiable dans Administration > Paramètres.)'),
  ('about_mission',        'À compléter : décrivez la mission du club — par exemple documenter la vie de l’institut et former les étudiants aux métiers de l’image.'),
  ('about_vision',         'À compléter : décrivez la vision du club pour les années à venir.'),
  ('about_activities',     'Couverture photo et vidéo d’événements\nAteliers et formations\nProjections et rencontres\nProduction de contenus pour les réseaux sociaux'),
  ('about_values',         'Créativité|Oser proposer des idées et des formats nouveaux.\nPartage|Transmettre ce que l’on apprend aux autres membres.\nEngagement|Tenir ses projets jusqu’au bout, ensemble.'),
  ('about_community',      'À compléter : présentez la communauté du club et la façon de la rejoindre.'),
  ('about_timeline',       'Année|Début du club|À compléter avec une date et un fait vérifiés.\nÉtape 2|Premier événement|À compléter avec une date et un fait vérifiés.\nÉtape 3|Aujourd’hui|À compléter avec une date et un fait vérifiés.');
