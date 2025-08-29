<?php
header('Content-Type: application/json');
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php'; // Pour addNotification

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Non authentifié.';
    echo json_encode($response);
    exit();
}

$id_expediteur = $_SESSION['user_id'];
$id_destinataire = $_POST['recipient_id'] ?? null;
$message_content = trim($_POST['message'] ?? '');

if (empty($id_destinataire) || empty($message_content)) {
    $response['message'] = 'Destinataire ou message vide.';
    echo json_encode($response);
    exit();
}

// Vérifier que l'expéditeur n'essaie pas de s'envoyer un message à lui-même
if ($id_expediteur == $id_destinataire) {
    $response['message'] = 'Vous ne pouvez pas vous envoyer de message à vous-même.';
    echo json_encode($response);
    exit();
}

try {
    // Vérifier si le destinataire existe
    $stmt_check_dest = $conn->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
    $stmt_check_dest->execute([$id_destinataire]);
    if ($stmt_check_dest->fetchColumn() == 0) {
        $response['message'] = 'Destinataire introuvable.';
        echo json_encode($response);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO messages (id_expediteur, id_destinataire, message) VALUES (?, ?, ?)");
    if ($stmt->execute([$id_expediteur, $id_destinataire, $message_content])) {
        $response['success'] = true;
        $response['message'] = 'Message envoyé.';

        // Ajouter une notification au destinataire
        addNotification($conn, $id_destinataire, 'new_message', $id_expediteur, "Vous avez un nouveau message de " . $_SESSION['user_name']);

    } else {
        $response['message'] = 'Erreur lors de l\'envoi du message.';
    }
} catch (PDOException $e) {
    $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
    // Loggez l'erreur en production
}

echo json_encode($response);
?>
