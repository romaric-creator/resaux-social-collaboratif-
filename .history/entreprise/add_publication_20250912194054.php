<?php
$page_title = "Ajouter une Publication";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkEntreprise(); // Seules les entreprises peuvent ajouter des publications

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie = trim($_POST['categorie'] ?? '');
    $localisation_pub = trim($_POST['localisation_pub'] ?? ''); // Localisation spécifique à la publication

    $image_path = null;
    $video_path = null;
    $document_path = null;

    // Validation de base
    if (empty($titre) || empty($description)) {
        $error_message = "Le titre et la description sont obligatoires.";
    } else {
        try {
            // Gérer l'upload d'image
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_img_types = ['image/jpeg', 'image/png', 'image/gif'];
                $image_path = uploadFile($_FILES['image'], 'publications', $allowed_img_types, 10); // Max 10MB
                if (!$image_path) {
                    $error_message .= " Erreur lors de l'upload de l'image. ";
                }
            }

            // Gérer l'upload de vidéo
            if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                $allowed_video_types = ['video/mp4', 'video/webm', 'video/ogg'];
                $video_path = uploadFile($_FILES['video'], 'publications', $allowed_video_types, 50); // Max 50MB
                if (!$video_path) {
                    $error_message .= " Erreur lors de l'upload de la vidéo. ";
                }
            }

            // Gérer l'upload de document
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $allowed_doc_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $document_path = uploadFile($_FILES['document'], 'publications', $allowed_doc_types, 20); // Max 20MB
                if (!$document_path) {
                    $error_message .= " Erreur lors de l'upload du document. ";
                }
            }

            // Si aucune erreur d'upload, insérer la publication
            if (empty($error_message)) {
                $stmt = $conn->prepare("INSERT INTO publications (id_entreprise, titre, description, image, video, document_url, categorie, localisation_pub) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $titre, $description, $image_path, $video_path, $document_path, $categorie, $localisation_pub]);

                $success_message = "Votre publication a été ajoutée avec succès !";
                // Réinitialiser les champs après succès
                $titre = $description = $categorie = $localisation_pub = '';
                // Optionnel: rediriger vers le tableau de bord ou le fil d'actualité
                header("Location: " . BASE_URL . "index.php");
                exit();
            }

        } catch (PDOException $e) {
            $error_message = "Erreur de base de données : " . $e->getMessage();
            // Supprimer les fichiers uploadés si l'insertion DB échoue
            if ($image_path) deleteUploadedFile($image_path);
            if ($video_path) deleteUploadedFile($video_path);
            if ($document_path) deleteUploadedFile($document_path);
        }
    }
}

require_once '../includes/header.php';
?>

<div class="main-content form-page">
    <div class="form-card">
        <h2 class="form-title">Ajouter une Nouvelle Annonce / Publication</h2>

        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form action="add_publication.php" method="POST" enctype="multipart/form-data" class="modern-form">
            <div class="form-group">
                <label for="titre">Titre de l'annonce :</label>
                <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($titre ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description détaillée :</label>
                <textarea id="description" name="description" rows="6" required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="categorie">Catégorie (Ex: Hôtel, Restaurant, Activité, etc.) :</label>
                <input type="text" id="categorie" name="categorie" value="<?php echo htmlspecialchars($categorie ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="localisation_pub">Localisation spécifique (si différente du profil) :</label>
                <input type="text" id="localisation_pub" name="localisation_pub" placeholder="Ex: Douala, Cameroun" value="<?php echo htmlspecialchars($localisation_pub ?? ''); ?>">
            </div>

            <hr>
            <h3>Contenu Multimédia (Optionnel)</h3>
            <div class="form-group file-upload-group">
                <label for="image">Image :</label>
                <input type="file" id="image" name="image" accept="image/*">
                <p class="help-text">JPG, PNG, GIF. Max 10MB.</p>
            </div>
            <div class="form-group file-upload-group">
                <label for="video">Vidéo :</label>
                <input type="file" id="video" name="video" accept="video/mp4,video/webm,video/ogg">
                <p class="help-text">MP4, WebM, Ogg. Max 50MB.</p>
            </div>
            <div class="form-group file-upload-group">
                <label for="document">Document promotionnel (PDF, Word) :</label>
                <input type="file" id="document" name="document" accept=".pdf,.doc,.docx">
                <p class="help-text">PDF, DOC, DOCX. Max 20MB.</p>
            </div>

            <button type="submit" class="button full-width">Créer la Publication</button>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
