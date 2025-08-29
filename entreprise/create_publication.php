<?php
$page_title = "Créer une Annonce";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkEntreprise();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfToken(); // Protection CSRF

    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categorie = trim($_POST['categorie'] ?? '');
    $localisation_pub = trim($_POST['localisation_pub'] ?? '');
    $status = $_POST['status'] ?? 'brouillon'; // Default to brouillon
    $scheduled_date = null;
    $expiration_date = null;

    if ($status === 'programme' && !empty($_POST['scheduled_date'])) {
        $scheduled_date = date('Y-m-d H:i:s', strtotime($_POST['scheduled_date']));
    }
    if (!empty($_POST['expiration_date'])) {
        $expiration_date = date('Y-m-d H:i:s', strtotime($_POST['expiration_date']));
    }

    // Validation
    if (empty($titre) || empty($description) || empty($categorie)) {
        $error_message = "Le titre, la description et la catégorie sont obligatoires.";
    } elseif ($status === 'programme' && empty($scheduled_date)) {
        $error_message = "Une date de programmation est requise pour une publication programmée.";
    } elseif ($expiration_date && $scheduled_date && (new DateTime($expiration_date) <= new DateTime($scheduled_date))) {
        $error_message = "La date d'expiration doit être après la date de programmation.";
    } else {
        $image_path = null;
        $video_path = null;
        $document_path = null;

        try {
            // Gérer l'upload d'image
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_path = uploadFile($_FILES['image'], 'publications/', ALLOWED_PUBLICATION_MEDIA_TYPES, 10); // Max 10MB
                if (!$image_path) {
                    $error_message .= " Erreur lors de l'upload de l'image.";
                }
            }
            // Gérer l'upload de vidéo
            if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
                $video_path = uploadFile($_FILES['video'], 'publications/', ALLOWED_PUBLICATION_MEDIA_TYPES, 50); // Max 50MB
                if (!$video_path) {
                    $error_message .= " Erreur lors de l'upload de la vidéo.";
                }
            }
            // Gérer l'upload de document
            if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
                $document_path = uploadFile($_FILES['document'], 'brochures/', ALLOWED_BROCHURE_TYPES, 20); // Max 20MB
                if (!$document_path) {
                    $error_message .= " Erreur lors de l'upload du document.";
                }
            }

            // Si des erreurs d'upload ont été ajoutées, ne pas insérer
            if (!empty($error_message)) {
                // Nettoyer les fichiers partiellement uploadés
                if ($image_path) deleteUploadedFile($image_path);
                if ($video_path) deleteUploadedFile($video_path);
                if ($document_path) deleteUploadedFile($document_path);
            } else {
                $stmt = $conn->prepare("INSERT INTO publications (id_entreprise, titre, description, categorie, localisation_pub, image, video, document_url, status, scheduled_date, expiration_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $titre, $description, $categorie, $localisation_pub, $image_path, $video_path, $document_path, $status, $scheduled_date, $expiration_date]);

                $success_message = "Votre annonce a été créée avec succès !";
                // Réinitialiser les champs du formulaire après succès
                $_POST = [];
                $_FILES = [];
            }
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données : " . $e->getMessage();
            error_log("Create publication error: " . $e->getMessage());
        }
    }
}

// Récupérer les catégories existantes pour la liste déroulante
$stmt_categories = $conn->query("SELECT DISTINCT categorie FROM publications WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie ASC");
$existing_categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<div class="main-content form-page">
    <div class="form-card">
        <h2 class="form-title">Créer une Nouvelle Annonce</h2>
        <p class="auth-subtitle">Remplissez les informations pour publier une nouvelle annonce sur la plateforme.</p>

        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form action="create_publication.php" method="POST" enctype="multipart/form-data" class="modern-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-group">
                <label for="titre">Titre de l'annonce :</label>
                <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($_POST['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description complète :</label>
                <textarea id="description" name="description" rows="8" required><?php echo htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="form-group">
                <label for="categorie">Catégorie :</label>
                <select id="categorie" name="categorie" required>
                    <option value="">Sélectionner une catégorie</option>
                    <?php foreach ($existing_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($_POST['categorie'] ?? '') == $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Autre" <?php echo (($_POST['categorie'] ?? '') == 'Autre') ? 'selected' : ''; ?>>Autre (préciser dans description)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="localisation_pub">Localisation spécifique (si différente de votre profil) :</label>
                <input type="text" id="localisation_pub" name="localisation_pub" value="<?php echo htmlspecialchars($_POST['localisation_pub'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Paris, France">
                <p class="help-text">Laissez vide pour utiliser la localisation de votre profil d'entreprise.</p>
            </div>

            <div class="form-group file-upload-group">
                <label for="image">Image principale :</label>
                <input type="file" id="image" name="image" accept="image/*">
                <p class="help-text">Taille max : 10MB. Formats : JPG, PNG, GIF, WEBP, BMP. Une seule image par annonce.</p>
            </div>
            <div class="form-group file-upload-group">
                <label for="video">Vidéo (facultatif) :</label>
                <input type="file" id="video" name="video" accept="video/mp4,video/webm,video/ogg">
                <p class="help-text">Taille max : 50MB. Formats : MP4, WebM, OGG. Une seule vidéo par annonce.</p>
            </div>
            <div class="form-group file-upload-group">
                <label for="document">Brochure / Document PDF (facultatif) :</label>
                <input type="file" id="document" name="document" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                <p class="help-text">Taille max : 20MB. Formats : PDF, DOC, DOCX.</p>
            </div>

            <div class="form-group">
                <label for="status">Statut de la publication :</label>
                <select id="status" name="status" required>
                    <option value="brouillon" <?php echo (($_POST['status'] ?? '') == 'brouillon') ? 'selected' : ''; ?>>Brouillon</option>
                    <option value="publie" <?php echo (($_POST['status'] ?? '') == 'publie') ? 'selected' : ''; ?>>Publier maintenant</option>
                    <option value="programme" <?php echo (($_POST['status'] ?? '') == 'programme') ? 'selected' : ''; ?>>Programmer</option>
                </select>
                <p class="help-text">Les publications programmées deviendront publiques à la date choisie.</p>
            </div>
            <div class="form-group" id="scheduled_date_group" style="display: <?php echo (($_POST['status'] ?? '') == 'programme') ? 'block' : 'none'; ?>;">
                <label for="scheduled_date">Date et heure de programmation :</label>
                <input type="datetime-local" id="scheduled_date" name="scheduled_date" value="<?php echo htmlspecialchars($_POST['scheduled_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="form-group">
                <label for="expiration_date">Date d'expiration (facultatif) :</label>
                <input type="datetime-local" id="expiration_date" name="expiration_date" value="<?php echo htmlspecialchars($_POST['expiration_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <p class="help-text">L'annonce sera automatiquement désactivée après cette date.</p>
            </div>

            <button type="submit" class="button full-width">Créer l'annonce</button>
        </form>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#status').change(function() {
        if ($(this).val() === 'programme') {
            $('#scheduled_date_group').slideDown();
        } else {
            $('#scheduled_date_group').slideUp();
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
