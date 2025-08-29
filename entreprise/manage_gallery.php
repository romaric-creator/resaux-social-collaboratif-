<?php
$page_title = "Gérer ma Galerie";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkEntreprise();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Traitement de l'ajout de média
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_media') {
    validateCsrfToken(); // Protection CSRF

    $description = trim($_POST['description'] ?? '');
    $media_type = $_POST['media_type'] ?? ''; // 'image' ou 'video'
    $uploaded_file_path = null;

    if (empty($media_type)) {
        $error_message = "Veuillez spécifier le type de média (image ou vidéo).";
    } elseif ($media_type === 'image' && (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK)) {
        $error_message = "Veuillez sélectionner une image.";
    } elseif ($media_type === 'video' && (!isset($_FILES['media_file']) || $_FILES['media_file']['error'] !== UPLOAD_ERR_OK)) {
        $error_message = "Veuillez sélectionner une vidéo.";
    } else {
        try {
            if ($media_type === 'image') {
                $uploaded_file_path = uploadFile($_FILES['media_file'], 'gallery_media/', ALLOWED_GALLERY_MEDIA_TYPES, 10); // 10MB for images
            } elseif ($media_type === 'video') {
                $uploaded_file_path = uploadFile($_FILES['media_file'], 'gallery_media/', ALLOWED_GALLERY_MEDIA_TYPES, 100); // 100MB for videos
            }

            if ($uploaded_file_path) {
                $stmt = $conn->prepare("INSERT INTO galerie_multimedia (id_entreprise_user, url_fichier, type_fichier, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $uploaded_file_path, $media_type, $description]);
                $success_message = "Média ajouté à la galerie avec succès !";
            } else {
                $error_message = "Erreur lors de l'upload du fichier. Veuillez vérifier la taille et le format.";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données : " . $e->getMessage();
            error_log("Add gallery media error: " . $e->getMessage());
        }
    }
}

// Traitement de la suppression de média
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_media') {
    validateCsrfToken(); // Protection CSRF

    $media_id = isset($_POST['media_id']) ? (int)$_POST['media_id'] : 0;

    if ($media_id === 0) {
        $error_message = "ID de média manquant.";
    } else {
        try {
            // Récupérer le chemin du fichier avant de le supprimer de la BDD
            $stmt_media = $conn->prepare("SELECT url_fichier FROM galerie_multimedia WHERE id = ? AND id_entreprise_user = ?");
            $stmt_media->execute([$media_id, $user_id]);
            $media_data = $stmt_media->fetch();

            if ($media_data) {
                // Supprimer de la BDD
                $stmt_delete = $conn->prepare("DELETE FROM galerie_multimedia WHERE id = ? AND id_entreprise_user = ?");
                $stmt_delete->execute([$media_id, $user_id]);

                if ($stmt_delete->rowCount() > 0) {
                    // Supprimer le fichier physique
                    deleteUploadedFile($media_data['url_fichier']);
                    $success_message = "Média supprimé avec succès.";
                } else {
                    $error_message = "Média introuvable ou vous n'êtes pas autorisé à le supprimer.";
                }
            } else {
                $error_message = "Média introuvable.";
            }
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données lors de la suppression : " . $e->getMessage();
            error_log("Delete gallery media error: " . $e->getMessage());
        }
    }
}

// Récupérer les médias actuels de la galerie
$stmt_gallery = $conn->prepare("SELECT * FROM galerie_multimedia WHERE id_entreprise_user = ? ORDER BY date_ajout DESC");
$stmt_gallery->execute([$user_id]);
$gallery_media = $stmt_gallery->fetchAll();

require_once '../includes/header.php';
?>

<div class="main-content dashboard-page">
    <div class="dashboard-header">
        <h1><i class="fas fa-images"></i> Gérer ma Galerie</h1>
        <p>Ajoutez, supprimez et organisez les images et vidéos de votre entreprise.</p>
    </div>

    <div class="card add-media-form-card">
        <h2>Ajouter un nouveau média</h2>
        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form action="manage_gallery.php" method="POST" enctype="multipart/form-data" class="modern-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="add_media">

            <div class="form-group">
                <label for="media_type">Type de média :</label>
                <select id="media_type" name="media_type" required>
                    <option value="">Sélectionner</option>
                    <option value="image">Image</option>
                    <option value="video">Vidéo</option>
                </select>
            </div>
            <div class="form-group file-upload-group">
                <label for="media_file">Fichier média :</label>
                <input type="file" id="media_file" name="media_file" accept="image/*,video/*" required>
                <p class="help-text">Images: max 10MB (JPG, PNG, GIF, WEBP). Vidéos: max 100MB (MP4, WebM, OGG).</p>
            </div>
            <div class="form-group">
                <label for="description">Description (facultatif) :</label>
                <textarea id="description" name="description" rows="3" placeholder="Une courte description de ce média..."></textarea>
            </div>
            <button type="submit" class="button full-width">Ajouter à la galerie</button>
        </form>
    </div>

    <div class="card current-gallery-card">
        <h2>Médias de la Galerie</h2>
        <?php if (!empty($gallery_media)): ?>
            <div class="gallery-grid-admin">
                <?php foreach ($gallery_media as $media): ?>
                    <div class="gallery-item-admin">
                        <?php if ($media['type_fichier'] === 'image'): ?>
                            <img src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($media['url_fichier'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($media['description'] ?? 'Image de la galerie', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php elseif ($media['type_fichier'] === 'video'): ?>
                            <video controls src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($media['url_fichier'], ENT_QUOTES, 'UTF-8'); ?>"></video>
                        <?php endif; ?>
                        <div class="media-actions">
                            <p><?php echo htmlspecialchars(mb_strimwidth($media['description'] ?? '', 0, 50, '...'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <form action="manage_gallery.php" method="POST" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="delete_media">
                                <input type="hidden" name="media_id" value="<?php echo htmlspecialchars($media['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="button-icon-small red-icon" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce média ?')"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="info-message-card">Votre galerie est vide. Ajoutez des images ou vidéos !</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
