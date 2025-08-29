<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkEntreprise(); // Seules les entreprises peuvent supprimer leurs publications

$user_id = $_SESSION['user_id'];
$publication_id = $_GET['id'] ?? null;

if (!$publication_id) {
    $_SESSION['status_message'] = "ID de publication manquant pour la suppression.";
    $_SESSION['status_message_type'] = "error";
    header("Location: " . BASE_URL . "entreprise/manage_publications.php");
    exit();
}

try {
    // Récupérer les chemins des fichiers associés à la publication
    $stmt_get_files = $conn->prepare("SELECT image, video, document_url FROM publications WHERE id = ? AND id_entreprise = ?");
    $stmt_get_files->execute([$publication_id, $user_id]);
    $files_to_delete = $stmt_get_files->fetch();

    if (!$files_to_delete) {
        $_SESSION['status_message'] = "Publication introuvable ou vous n'avez pas la permission de la supprimer.";
        $_SESSION['status_message_type'] = "error";
        header("Location: " . BASE_URL . "entreprise/manage_publications.php");
        exit();
    }

    // Supprimer d'abord la publication de la base de données
    $stmt_delete = $conn->prepare("DELETE FROM publications WHERE id = ? AND id_entreprise = ?");
    if ($stmt_delete->execute([$publication_id, $user_id])) {
        // Si la suppression DB est réussie, supprimer les fichiers physiques
        if ($files_to_delete['image']) {
            deleteUploadedFile($files_to_delete['image']);
        }
        if ($files_to_delete['video']) {
            deleteUploadedFile($files_to_delete['video']);
        }
        if ($files_to_delete['document_url']) {
            deleteUploadedFile($files_to_delete['document_url']);
        }

        $_SESSION['status_message'] = "Publication supprimée avec succès.";
        $_SESSION['status_message_type'] = "success";
    } else {
        $_SESSION['status_message'] = "Erreur lors de la suppression de la publication de la base de données.";
        $_SESSION['status_message_type'] = "error";
    }

} catch (PDOException $e) {
    $_SESSION['status_message'] = "Erreur de base de données lors de la suppression : " . $e->getMessage();
    $_SESSION['status_message_type'] = "error";
}

header("Location: " . BASE_URL . "entreprise/manage_publications.php");
exit();
?>
