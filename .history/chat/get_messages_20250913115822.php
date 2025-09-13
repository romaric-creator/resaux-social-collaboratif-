<?php
date_default_timezone_set("Africa/Douala");

header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/auth.php'; // Pour la session

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'messages' => [], 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Non authentifié.';
    echo json_encode($response);
    exit();
}

$current_user_id = $_SESSION['user_id'];
$recipient_id = $_GET['recipient_id'] ?? null;

if (empty($recipient_id)) {
    $response['message'] = 'ID du destinataire manquant.';
    echo json_encode($response);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT m.* FROM messages m
                            WHERE (m.id_expediteur = ? AND m.id_destinataire = ?)
                            OR (m.id_expediteur = ? AND m.id_destinataire = ?)
                            ORDER BY m.date_envoi ASC");
    $stmt->execute([$current_user_id, $recipient_id, $recipient_id, $current_user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($messages as &$msg) {
        // Ajouter un format de date lisible pour le frontend
        $msg['date_envoi_formatted'] = formatRelativeTime($msg['date_envoi']);
    }
    unset($msg); // Défaire la référence sur le dernier élément

    $response['success'] = true;
    $response['messages'] = $messages;

    // Marquer les messages reçus comme lus après les avoir récupérés
    $stmt_mark_read = $conn->prepare("UPDATE messages SET lu = 1 WHERE id_expediteur = ? AND id_destinataire = ? AND lu = 0");
    $stmt_mark_read->execute([$recipient_id, $current_user_id]);

} catch (PDOException $e) {
    $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    // Loggez l'erreur en production
}

echo json_encode($response);

/**
 * Cette fonction est incluse dans functions.php.
 * Copiée ici pour que le script get_messages.php puisse l'utiliser directement si non inclue via un autre moyen.
 * Il est recommandé de s'assurer que functions.php est bien chargé partout où cette fonction est utilisée.
 */
function formatRelativeTime($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'il y a l'instant';
    } elseif ($diff < 3600) {
        return 'il y a ' . floor($diff / 60) . ' min';
    } elseif ($diff < 86400) {
        return 'il y a ' . floor($diff / 3600) . ' h';
    } elseif ($diff < 2592000) { // 30 jours
        return 'il y a ' . floor($diff / 86400) . ' j';
    } else {
        return date('d/m/Y', $timestamp);
    }
}
?>
