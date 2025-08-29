<?php
require_once 'includes/auth.php'; // Pour BASE_URL
session_start();

// Détruire toutes les variables de session
$_SESSION = array();

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header("Location: " . BASE_URL . "login.php");
exit();
?>
