<?php
$page_title = "Modifier Publication";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkEntreprise();

$user_id = $_SESSION['user_id'];
$publication_id = $_GET['id'] ?? null;

$success_message = '';
$error_message = '';
$publication = null;

if (!$publication_id) {
    $_SESSION['status_message'] = "ID de publication manquant.";
    $_SESSION['status_message_type'] = "error";
    header("Location: " . BASE_URL . "entreprise/manage_publications.php");
    exit();
}

// Récupérer la publication existante et s'assurer qu'elle appartient à l'entreprise connectée
$stmt = $conn->prepare("SELECT * FROM publications WHERE id = ? AND id_entreprise = ?");
$stmt->execute([$publication_id, $user_id]);
$publication = $stmt->fetch();

if (!$publication) {
    $_SESSION['status_message'] = "Publication introuvable ou vous n'avez pas la permission de la modifier.";
    $_SESSION['status_message_type'] = "error";
    header("Location: " . BASE_URL . "entreprise/manage_publications.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie = trim($_POST['categorie'] ?? '');
    $localisation_pub = trim($_POST['localisation_pub'] ?? '');

    $current_image_path = $publication['image'];
    $current_video_path = $publication['video'];
    $current_document_path = $publication['document_url'];

    // Gérer la suppression des fichiers existants si demandé
    if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
        if (deleteUploadedFile($current_image_path)) {
            $current_image_path = null;
        } else {
            $error_message .= " Erreur lors de la suppression de l'ancienne image. ";
        }
    }
    if (isset($_POST['delete_video']) && $_POST['delete_video'] == '1') {
        if (deleteUploadedFile($current_video_path)) {
            $current_video_path = null;
        } else {
            $error_message .= " Erreur lors de la suppression de l'ancienne vidéo. ";
        }
    }
    if (isset($_POST['delete_document']) && $_POST['delete_document'] == '1') {
        if (deleteUploadedFile($current_document_path)) {
            $current_document_path = null;
        } else {
            $error_message .= " Erreur lors de la suppression de l'ancien document. ";
        }
    }

    // Gérer l'upload de nouveaux fichiers
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowed_img_types = ['image/jpeg', 'image/png', 'image/gif'];
        $new_image_path = uploadFile($_FILES['image'], 'publications', $allowed_img_types, 10);
        if ($new_image_path) {
            deleteUploadedFile($current_image_path); // Supprimer l'ancienne après succès du nouvel upload
            $current_image_path = $new_image_path;
        } else {
            $error_message .= " Erreur lors de l'upload de la nouvelle image. ";
        }
    }
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $allowed_video_types = ['video/mp4', 'video/webm', 'video/ogg'];
        $new_video_path = uploadFile($_FILES['video'], 'publications', $allowed_video_types, 50);
        if ($new_video_path) {
            deleteUploadedFile($current_video_path);
            $current_video_path = $new_video_path;
        } else {
            $error_message .= " Erreur lors de l'upload de la nouvelle vidéo. ";
        }
    }
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $allowed_doc_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $new_document_path = uploadFile($_FILES['document'], 'publications', $allowed_doc_types, 20);
        if ($new_document_path) {
            deleteUploadedFile($current_document_path);
            $current_document_path = $new_document_path;
        } else {
            $error_message .= " Erreur lors de l'upload du nouveau document. ";
        }
    }

    // Mettre à jour la base de données
    if (empty($error_message)) {
        try {
            $stmt_update = $conn->prepare("UPDATE publications SET titre = ?, description = ?, image = ?, video = ?, document_url = ?, categorie = ?, localisation_pub = ? WHERE id = ?");
            $stmt_update->execute([$titre, $description, $current_image_path, $current_video_path, $current_document_path, $categorie, $localisation_pub, $publication_id]);

            $success_message = "Publication mise à jour avec succès !";
            // Recharger la publication pour afficher les nouvelles données
            $stmt = $conn->prepare("SELECT * FROM publications WHERE id = ? AND id_entreprise = ?");
            $stmt->execute([$publication_id, $user_id]);
            $publication = $stmt->fetch();

        } catch (PDOException $e) {
            $error_message .= " Erreur de base de données lors de la mise à jour : " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="main-content form-page">
    <div class="form-card">
        <h2 class="form-title">Modifier l'Annonce : <?php echo htmlspecialchars($publication['titre']); ?></h2>

        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form action="edit_publication.php?id=<?php echo $publication_id; ?>" method="POST" enctype="multipart/form-data" class="modern-form">
            <div class="form-group">
                <label for="titre">Titre de l'annonce :</label>
                <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($publication['titre']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description détaillée :</label>
                <textarea id="description" name="description" rows="6" required><?php echo htmlspecialchars($publication['description']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="categorie">Catégorie :</label>
                <input type="text" id="categorie" name="categorie" value="<?php echo htmlspecialchars($publication['categorie'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="localisation_pub">Localisation spécifique :</label>
                <input type="text" id="localisation_pub" name="localisation_pub" value="<?php echo htmlspecialchars($publication['localisation_pub'] ?? ''); ?>">
            </div>

            <hr>
            <h3>Contenu Multimédia Actuel</h3>
            <?php if ($publication['image']): ?>
                <div class="current-media-display">
                    <p>Image actuelle:</p>
                    <img src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($publication['image']); ?>" alt="Image actuelle" style="max-width: 200px; height: auto; border-radius: 8px;">
                    <div class="form-group-checkbox">
                        <input type="checkbox" id="delete_image" name="delete_image" value="1">
                        <label for="delete_image">Supprimer l'image actuelle</label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($publication['video']): ?>
                <div class="current-media-display">
                    <p>Vidéo actuelle:</p>
                    <video controls style="max-width: 300px; height: auto; border-radius: 8px;">
                        <source src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($publication['video']); ?>">
                        Votre navigateur ne supporte pas la vidéo.
                    </video>
                    <div class="form-group-checkbox">
                        <input type="checkbox" id="delete_video" name="delete_video" value="1">
                        <label for="delete_video">Supprimer la vidéo actuelle</label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($publication['document_url']): ?>
                <div class="current-media-display">
                    <p>Document actuel: <a href="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($publication['document_url']); ?>" target="_blank"><i class="fas fa-file-alt"></i> Voir le document</a></p>
                    <div class="form-group-checkbox">
                        <input type="checkbox" id="delete_document" name="delete_document" value="1">
                        <label for="delete_document">Supprimer le document actuel</label>
                    </div>
                </div>
            <?php endif; ?>

            <hr>
            <h3>Nouveau Contenu Multimédia (Optionnel)</h3>
            <p class="help-text">Si vous téléchargez un nouveau fichier, il remplacera l'ancien (sauf si vous cochez "Supprimer l'actuel" ET que le nouvel upload échoue).</p>
            <div class="form-group file-upload-group">
                <label for="image">Nouvelle Image :</label>
                <input type="file" id="image" name="image" accept="image/*">
                <p class="help-text">JPG, PNG, GIF. Max 10MB.</p>
            </div>
            <div class="form-group file-upload-group">
                <label for="video">Nouvelle Vidéo :</label>
                <input type="file" id="video" name="video" accept="video/mp4,video/webm,video/ogg">
                <p class="help-text">MP4, WebM, Ogg. Max 50MB.</p>
            </div>
            <div class="form-group file-upload-group">
                <label for="document">Nouveau Document :</label>
                <input type="file" id="document" name="document" accept=".pdf,.doc,.docx">
                <p class="help-text">PDF, DOC, DOCX. Max 20MB.</p>
            </div>

            <button type="submit" class="button full-width">Mettre à jour la Publication</button>
            <a href="<?php echo BASE_URL; ?>entreprise/manage_publications.php" class="button secondary full-width cancel-btn">Annuler</a>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
