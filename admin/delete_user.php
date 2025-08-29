<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php'; // Pour deleteUploadedFile

checkLogin();
checkAdmin();

$user_id_to_delete = $_GET['id'] ?? null;

if (!$user_id_to_delete) {
    $_SESSION['status_message'] = "ID d'utilisateur manquant pour la suppression.";
    $_SESSION['status_message_type'] = "error";
    header("Location: " . BASE_URL . "admin/manage_users.php");
    exit();
}

try {
    // Récupérer les informations de l'utilisateur pour vérifier le type et récupérer les chemins de fichiers
    $stmt_user_info = $conn->prepare("SELECT photo_profil, photo_couverture, type_user FROM users WHERE id = ?");
    $stmt_user_info->execute([$user_id_to_delete]);
    $user_info = $stmt_user_info->fetch();

    if (!$user_info) {
        $_SESSION['status_message'] = "Utilisateur introuvable.";
        $_SESSION['status_message_type'] = "error";
        header("Location: " . BASE_URL . "admin/manage_users.php");
        exit();
    }

    if ($user_info['type_user'] === 'admin') {
        $_SESSION['status_message'] = "Vous ne pouvez pas supprimer un compte administrateur.";
        $_SESSION['status_message_type'] = "error";
        header("Location: " . BASE_URL . "admin/manage_users.php");
        exit();
    }

    // Commencer une transaction pour s'assurer que tout est supprimé ou rien
    $conn->beginTransaction();

    // 1. Supprimer les publications de l'utilisateur (si c'est une entreprise) et leurs fichiers associés
    if ($user_info['type_user'] === 'entreprise') {
        $stmt_get_pubs_files = $conn->prepare("SELECT image, video, document_url FROM publications WHERE id_entreprise = ?");
        $stmt_get_pubs_files->execute([$user_id_to_delete]);
        $pubs_files = $stmt_get_pubs_files->fetchAll();

        foreach ($pubs_files as $file) {
            deleteUploadedFile($file['image']);
            deleteUploadedFile($file['video']);
            deleteUploadedFile($file['document_url']);
        }
        // Les publications seront supprimées en cascade avec la suppression de l'utilisateur grâce à la clé étrangère
    }

    // 2. Supprimer les éléments de galerie (si c'est une entreprise) et leurs fichiers associés
    $stmt_get_gallery_files = $conn->prepare("SELECT url_fichier FROM galerie_multimedia WHERE id_entreprise_user = ?");
    $stmt_get_gallery_files->execute([$user_id_to_delete]);
    $gallery_files = $stmt_get_gallery_files->fetchAll();

    foreach ($gallery_files as $file) {
        deleteUploadedFile($file['url_fichier']);
    }
    // La galerie sera supprimée en cascade

    // 3. Supprimer les photos de profil et de couverture de l'utilisateur
    deleteUploadedFile($user_info['photo_profil']);
    deleteUploadedFile($user_info['photo_couverture']);

    // 4. Supprimer l'utilisateur (ce qui déclenchera les suppressions en cascade pour entreprises, publications, commentaires, messages, favoris, notifications, signalements)
    $stmt_delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt_delete_user->execute([$user_id_to_delete])) {
        $conn->commit();
        $_SESSION['status_message'] = "Utilisateur et toutes ses données associées supprimés avec succès.";
        $_SESSION['status_message_type'] = "success";
    } else {
        $conn->rollBack();
        $_SESSION['status_message'] = "Erreur lors de la suppression de l'utilisateur de la base de données.";
        $_SESSION['status_message_type'] = "error";
    }

} catch (PDOException $e) {
    $conn->rollBack();
    $_SESSION['status_message'] = "Erreur de base de données lors de la suppression : " . $e->getMessage();
    $_SESSION['status_message_type'] = "error";
}

header("Location: " . BASE_URL . "admin/manage_users.php");
exit();
?>
