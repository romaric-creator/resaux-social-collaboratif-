<?php
// Fichier de gestion de l'authentification et des rôles utilisateurs
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Définir le chemin de base du projet pour les redirections et les liens CSS/JS
// IMPORTANT : Si votre projet est dans un sous-dossier de votre serveur web (ex: 'http://localhost/monprojet/'),
// assurez-vous que BASE_URL est défini comme '/monprojet/'.
// Si votre projet est à la racine de votre serveur (ex: 'http://localhost/'), utilisez '/'.
// Le script Bash utilise 'PlateformeTourisme/', donc la valeur par défaut ci-dessous est adaptée si vous mettez
// le dossier 'PlateformeTourisme' directement dans votre racine web (ex: htdocs de XAMPP).
define('BASE_URL', '/PlateformeTourisme/'); // Assurez-vous que ceci corresponde à votre configuration réelle !

/**
 * Vérifie si l'utilisateur est connecté. Si non, redirige vers la page de connexion.
 */
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "login.php");
        exit();
    }
}

/**
 * Génère un token CSRF et le stocke dans la session.
 * @return string Le token CSRF généré.
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie la validité du token CSRF.
 * @param string $token Le token CSRF à vérifier.
 * @return bool Vrai si le token est valide, faux sinon.
 */
/**
 * Vérifie si l'utilisateur est un administrateur. Si non, redirige vers l'accueil.
 */
function checkAdmin() {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

/**
 * Vérifie si l'utilisateur est une entreprise. Si non, redirige vers l'accueil.
 */
function checkEntreprise() {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'entreprise') {
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

/**
 * Retourne vrai si l'utilisateur est un administrateur.
 */
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

/**
 * Retourne vrai si l'utilisateur est une entreprise.
 */
function isEntreprise() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'entreprise';
}
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $_SESSION['csrf_token'] !== $token) {
        return false;
    }
    unset($_SESSION['csrf_token']); // Utilisez le token une seule fois
    return true;
}

?>
