<?php
// Fichier de connexion à la base de données
// Configuration de la base de données
$servername = "localhost";
$username = "root"; // <--- MODIFIEZ CECI AVEC VOTRE NOM D'UTILISATEUR MYSQL
$password = "";     // <--- MODIFIEZ CECI AVEC VOTRE MOT DE PASSE MYSQL
$dbname = "plateforme_tourisme";

try {
    // Établir la connexion PDO
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Définir le mode d'erreur sur exception pour une meilleure gestion des erreurs
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Définir le mode de récupération par défaut des résultats en tableau associatif
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En cas d'échec de la connexion, afficher un message d'erreur et arrêter le script
    // En production, il est préférable de logger l'erreur plutôt que de l'afficher directement
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>
