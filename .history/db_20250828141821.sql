-- Création de la base de données
CREATE DATABASE IF NOT EXISTS `plateforme_tourisme`;
USE `plateforme_tourisme`;

-- Table pour les utilisateurs (personnes et entreprises)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `type_user` enum('particulier','entreprise','admin') NOT NULL,
  `localisation` varchar(255) DEFAULT NULL,
  `date_inscription` timestamp NOT NULL DEFAULT current_timestamp(),
  `photo_profil` varchar(255) DEFAULT NULL, -- Nouvelle colonne pour photo de profil
  `photo_couverture` varchar(255) DEFAULT NULL, -- Nouvelle colonne pour photo de couverture (pour entreprises)
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les entreprises (informations supplémentaires)
CREATE TABLE IF NOT EXISTS `entreprises` (
  `id_entreprise` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `secteur` varchar(255) NOT NULL,
  `description` TEXT,
  `horaires_ouverture` VARCHAR(255) DEFAULT NULL, -- Ex: "Lun-Ven: 9h-18h"
  `telephone` VARCHAR(50) DEFAULT NULL,
  `site_web` VARCHAR(255) DEFAULT NULL,
  `latitude` DECIMAL(10,8) DEFAULT NULL, -- Pour géolocalisation
  `longitude` DECIMAL(11,8) DEFAULT NULL, -- Pour géolocalisation
  PRIMARY KEY (`id_entreprise`),
  UNIQUE KEY `id_user` (`id_user`),
  CONSTRAINT `fk_entreprise_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les galeries multimédia d'entreprise (pour plus de photos/vidéos)
CREATE TABLE IF NOT EXISTS `galerie_multimedia` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_entreprise_user` INT(11) NOT NULL, -- Lié à l'id_user du compte entreprise
  `url_fichier` VARCHAR(255) NOT NULL,
  `type_fichier` ENUM('image', 'video') NOT NULL,
  `description` TEXT DEFAULT NULL,
  `date_ajout` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_galerie_entreprise` FOREIGN KEY (`id_entreprise_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les publications d'entreprise
CREATE TABLE IF NOT EXISTS `publications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_entreprise` int(11) NOT NULL, -- C'est l'id_user de l'entreprise qui publie
  `titre` varchar(255) NOT NULL,
  `description` TEXT NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `video` varchar(255) DEFAULT NULL, -- Pour les vidéos uploadées
  `document_url` VARCHAR(255) DEFAULT NULL, -- Pour les documents promotionnels
  `categorie` VARCHAR(100) DEFAULT NULL, -- Catégorie spécifique à la publication si différente de l'entreprise
  `localisation_pub` VARCHAR(255) DEFAULT NULL, -- Localisation spécifique à la publication
  `date_publication` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_publication_entreprise_user_id` FOREIGN KEY (`id_entreprise`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les commentaires
CREATE TABLE IF NOT EXISTS `commentaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_publication` int(11) DEFAULT NULL, -- Peut être un commentaire sur une publication
  `id_entreprise_profile` int(11) DEFAULT NULL, -- Ou un commentaire sur un profil entreprise
  `id_user` int(11) NOT NULL,
  `contenu` TEXT NOT NULL,
  `note` INT(1) DEFAULT NULL CHECK (note BETWEEN 1 AND 5), -- Note de 1 à 5
  `date_commentaire` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_commentaire_publication` FOREIGN KEY (`id_publication`) REFERENCES `publications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commentaire_entreprise_profile` FOREIGN KEY (`id_entreprise_profile`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commentaire_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les messages de la messagerie
CREATE TABLE IF NOT EXISTS `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_expediteur` int(11) NOT NULL,
  `id_destinataire` int(11) NOT NULL,
  `message` TEXT NOT NULL,
  `date_envoi` timestamp NOT NULL DEFAULT current_timestamp(),
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_message_expediteur` FOREIGN KEY (`id_expediteur`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_destinataire` FOREIGN KEY (`id_destinataire`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les likes/favoris
CREATE TABLE IF NOT EXISTS `favoris` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_publication` int(11) DEFAULT NULL, -- Like sur une publication
  `id_entreprise_profile` int(11) DEFAULT NULL, -- Ou mise en favoris d'un profil entreprise
  `id_user` int(11) NOT NULL,
  `date_favoris` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fav_pub` (`id_publication`, `id_user`), -- Un like par pub par user
  UNIQUE KEY `unique_fav_ent` (`id_entreprise_profile`, `id_user`), -- Un favori par entreprise par user
  CONSTRAINT `fk_favoris_publication` FOREIGN KEY (`id_publication`) REFERENCES `publications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favoris_entreprise_profile` FOREIGN KEY (`id_entreprise_profile`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favoris_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table pour les notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `type` varchar(50) NOT NULL, -- Ex: 'new_message', 'new_like', 'new_comment', 'followed_company_pub', 'report_status'
  `id_reference` int(11) DEFAULT NULL, -- ID de l'élément lié (message, publication, user qui a interagi)
  `message_custom` TEXT DEFAULT NULL, -- Message personnalisé pour certaines notifications
  `date_notification` timestamp NOT NULL DEFAULT current_timestamp(),
  `lu` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_notification_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour les signalements
CREATE TABLE IF NOT EXISTS `signalements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_user_signaleur` INT(11) NOT NULL,
  `type_contenu` ENUM('publication', 'commentaire', 'profil_user', 'profil_entreprise') NOT NULL,
  `id_contenu_signale` INT(11) NOT NULL,
  `raison` TEXT NOT NULL,
  `date_signalement` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `statut` ENUM('en_attente', 'traite', 'rejete') DEFAULT 'en_attente',
  `id_admin_traitant` INT(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_signaleur` FOREIGN KEY (`id_user_signaleur`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_admin_traitant` FOREIGN KEY (`id_admin_traitant`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `followers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_follow (follower_id, followed_id)
);
-- Ajout d'un utilisateur admin par défaut (mot de passe: adminpass)
-- Hachage de 'adminpass' avec bcrypt
INSERT INTO `users` (`nom`, `email`, `password`, `type_user`, `localisation`) VALUES
('Admin User', 'admin@example.com', '$2y$10$tJ0sXqL3Z9V0M5B2C1X4Y8U6A7D9E2F0G1H2I3J4K5L6M7N8O9P.QRstUvW', 'admin', 'Paris');
