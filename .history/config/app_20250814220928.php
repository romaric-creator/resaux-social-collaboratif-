<?php
// Fichier: config/app.php

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'plateforme_tourisme');
define('DB_USERNAME', 'root'); // Remplacez par votre nom d'utilisateur MySQL
define('DB_PASSWORD', '');     // Remplacez par votre mot de passe MySQL

// URL de base de l'application
// C'est le chemin PUBLIC depuis la racine de votre serveur web (ex: http://localhost/PlateformeTourisme/)
define('BASE_URL', '/PlateformeTourisme/');

// Paramètres de sécurité
define('CSRF_TOKEN_LIFETIME', 3600); // Durée de vie du token CSRF en secondes (1 heure)

// Paramètres d'upload
define('UPLOAD_MAX_FILESIZE_MB_PROFILES', 5); // Taille max pour les photos de profil (MB)
define('UPLOAD_MAX_FILESIZE_MB_PUBLICATIONS_IMG', 10); // Taille max pour les images de publication (MB)
define('UPLOAD_MAX_FILESIZE_MB_PUBLICATIONS_VIDEO', 50); // Taille max pour les vidéos de publication (MB)
define('UPLOAD_MAX_FILESIZE_MB_PUBLICATIONS_DOC', 10); // Taille max pour les documents de publication (MB)
define('UPLOAD_MAX_FILESIZE_MB_GALLERY', 20); // Taille max pour les fichiers de galerie (MB)

// Types MIME autorisés pour l'upload (sécurité renforcée)
define('ALLOWED_IMAGE_MIMES', serialize([
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'
]));
define('ALLOWED_VIDEO_MIMES', serialize([
    'video/mp4', 'video/webm', 'video/ogg'
]));
define('ALLOWED_DOCUMENT_MIMES', serialize([
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    'application/vnd.ms-powerpoint', // .ppt
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' // .pptx
]));

// Options d'affichage
define('POSTS_PER_PAGE', 10); // Nombre de publications par page

// Définir le fuseau horaire
date_default_timezone_set('Africa/Douala'); // Ajustez à votre fuseau horaire si nécessaire

// Activer ou désactiver le mode debug (affiche les erreurs PHP)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
?>
