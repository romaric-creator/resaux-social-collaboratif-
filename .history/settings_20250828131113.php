<?php
$page_title = "Paramètres du Profil";
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkLogin();

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Récupérer les informations actuelles de l'utilisateur
$stmt = $conn->prepare("SELECT u.*, e.description, e.secteur, e.horaires_ouverture, e.telephone, e.site_web, e.latitude, e.longitude
                        FROM users u
                        LEFT JOIN entreprises e ON u.id = e.id_user
                        WHERE u.id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    $_SESSION['error_message'] = "Erreur: Profil utilisateur introuvable.";
    header("Location: " . BASE_URL . "index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mise à jour des informations de base de l'utilisateur
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $localisation = trim($_POST['localisation'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';

    // Mise à jour des informations spécifiques à l'entreprise (si applicable)
    $description = trim($_POST['description'] ?? '');
    $secteur = trim($_POST['secteur'] ?? '');
    $horaires_ouverture = trim($_POST['horaires_ouverture'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $site_web = trim($_POST['site_web'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    // Validation
    if (empty($nom) || empty($email) || empty($localisation)) {
        $error_message = "Nom, Email et Localisation sont requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format d'email invalide.";
    } elseif ($new_password && $new_password !== $confirm_new_password) {
        $error_message = "Les nouveaux mots de passe ne correspondent pas.";
    } elseif ($new_password && strlen($new_password) < 6) {
        $error_message = "Le nouveau mot de passe doit faire au moins 6 caractères.";
    } else {
        try {
            // Commencer une transaction pour s'assurer que les deux tables sont mises à jour ou aucune
            $conn->beginTransaction();

            // Mettre à jour la table users
            $sql_user = "UPDATE users SET nom = ?, email = ?, localisation = ? ";
            $params_user = [$nom, $email, $localisation];

            if ($new_password) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $sql_user .= ", password = ? ";
                $params_user[] = $hashed_password;
            }
            // Correction : ajouter un espace avant WHERE
            $sql_user .= " WHERE id = ?";
            $params_user[] = $user_id;

            $stmt_user = $conn->prepare($sql_user);
            $stmt_user->execute($params_user);

            // Gérer l'upload de la photo de profil
            if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
                $allowed_img_types = ['image/jpeg', 'image/png', 'image/gif'];
                $uploaded_photo_path = uploadFile($_FILES['profile_photo'], 'profiles', $allowed_img_types, 5); // 5MB max

                if ($uploaded_photo_path) {
                    // Supprimer l'ancienne photo si elle existe
                    if ($user_data['photo_profil']) {
                        deleteUploadedFile($user_data['photo_profil']);
                    }
                    $stmt_update_photo = $conn->prepare("UPDATE users SET photo_profil = ? WHERE id = ?");
                    $stmt_update_photo->execute([$uploaded_photo_path, $user_id]);
                    $_SESSION['user_photo_profil'] = $uploaded_photo_path; // Mettre à jour la session
                } else {
                    $error_message .= " Erreur lors de l'upload de la photo de profil.";
                }
            }

            // Gérer l'upload de la photo de couverture (spécifique entreprise)
            if ($user_data['type_user'] === 'entreprise' && isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
                $allowed_img_types = ['image/jpeg', 'image/png', 'image/gif'];
                $uploaded_cover_path = uploadFile($_FILES['cover_photo'], 'profiles', $allowed_img_types, 10); // 10MB max

                if ($uploaded_cover_path) {
                    if ($user_data['photo_couverture']) {
                        deleteUploadedFile($user_data['photo_couverture']);
                    }
                    $stmt_update_cover = $conn->prepare("UPDATE users SET photo_couverture = ? WHERE id = ?");
                    $stmt_update_cover->execute([$uploaded_cover_path, $user_id]);
                } else {
                    $error_message .= " Erreur lors de l'upload de la photo de couverture.";
                }
            }

            // Mettre à jour la table entreprises si l'utilisateur est une entreprise
            if ($user_data['type_user'] === 'entreprise') {
                $stmt_check_entreprise = $conn->prepare("SELECT COUNT(*) FROM entreprises WHERE id_user = ?");
                $stmt_check_entreprise->execute([$user_id]);
                $entreprise_exists = $stmt_check_entreprise->fetchColumn() > 0;

                if ($entreprise_exists) {
                    $sql_entreprise = "UPDATE entreprises SET description = ?, secteur = ?, horaires_ouverture = ?, telephone = ?, site_web = ?, latitude = ?, longitude = ? WHERE id_user = ?";
                    $params_entreprise = [$description, $secteur, $horaires_ouverture, $telephone, $site_web, $latitude, $longitude, $user_id];
                } else {
                    // Créer une nouvelle entrée si elle n'existe pas (devrait exister avec le script actuel, mais pour robustesse)
                    $sql_entreprise = "INSERT INTO entreprises (id_user, nom, description, secteur, horaires_ouverture, telephone, site_web, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $params_entreprise = [$user_id, $nom, $description, $secteur, $horaires_ouverture, $telephone, $site_web, $latitude, $longitude];
                }
                $stmt_entreprise = $conn->prepare($sql_entreprise);
                $stmt_entreprise->execute($params_entreprise);
            }

            $conn->commit();
            $success_message = "Vos informations ont été mises à jour avec succès.";

            // Mettre à jour la session avec les nouvelles données
            $_SESSION['user_name'] = $nom;
            $_SESSION['user_email'] = $email;
            // Recharger user_data pour afficher les modifications immédiatement
            $stmt = $conn->prepare("SELECT u.*, e.description, e.secteur, e.horaires_ouverture, e.telephone, e.site_web, e.latitude, e.longitude
                                    FROM users u
                                    LEFT JOIN entreprises e ON u.id = e.id_user
                                    WHERE u.id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();

        } catch (PDOException $e) {
            $conn->rollBack();
            $error_message = "Une erreur est survenue lors de la mise à jour : " . $e->getMessage();
            // En production, loggez $e->getMessage()
        }
    }
}

require_once 'includes/header.php';
?>


<div class="main-content form-page">
    <div class="form-card">
        <h2 class="form-title">Paramètres du Profil</h2>

        <!-- Messages d'état -->
        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form action="settings.php" method="POST" enctype="multipart/form-data" class="modern-form">
            <!-- Section Informations Générales -->
            <fieldset style="margin-bottom:2em;">
                <legend><strong>Informations Générales</strong></legend>
                <div class="form-group">
                    <label for="nom">Nom / Nom de l'entreprise :</label>
                    <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user_data['nom']); ?>"
                        required>
                </div>
                <div class="form-group">
                    <label for="email">Email :</label>
                    <input type="email" id="email" name="email"
                        value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="localisation">Localisation :</label>
                    <input type="text" id="localisation" name="localisation"
                        value="<?php echo htmlspecialchars($user_data['localisation'] ?? ''); ?>" required>
                </div>
                <div class="form-group file-upload-group" style="display:flex;align-items:center;gap:1em;">
                    <div>
                        <label for="profile_photo">Photo de profil :</label>
                        <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
                        <p class="help-text">Taille max : 5MB. Formats : JPG, PNG, GIF.</p>
                    </div>
                    <?php if ($user_data['photo_profil']): ?>
                        <div class="current-photo-preview">
                            <img src="<?php echo getUserProfilePhoto($user_data['photo_profil']); ?>"
                                alt="Photo de profil actuelle">
                            <span>Photo actuelle</span>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>

            <!-- Section Mot de passe -->
            <fieldset style="margin-bottom:2em;">
                <legend><strong>Changer le mot de passe</strong></legend>
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe :</label>
                    <input type="password" id="new_password" name="new_password">
                    <p class="help-text">Laissez vide si vous ne souhaitez pas changer de mot de passe.</p>
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Confirmer le nouveau mot de passe :</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password">
                </div>
            </fieldset>

            <?php if ($user_data['type_user'] === 'entreprise'): ?>
                <!-- Section Entreprise -->
                <fieldset style="margin-bottom:2em;">
                    <legend><strong>Informations Entreprise</strong></legend>
                    <div class="form-group">
                        <label for="description">Description de l'entreprise :</label>
                        <textarea id="description"
                            name="description"><?php echo htmlspecialchars($user_data['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="secteur">Secteur d'activité :</label>
                        <input type="text" id="secteur" name="secteur"
                            value="<?php echo htmlspecialchars($user_data['secteur'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="horaires_ouverture">Horaires d'ouverture :</label>
                        <input type="text" id="horaires_ouverture" name="horaires_ouverture"
                            placeholder="Ex: Lun-Ven: 9h-18h"
                            value="<?php echo htmlspecialchars($user_data['horaires_ouverture'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="telephone">Téléphone :</label>
                        <input type="tel" id="telephone" name="telephone"
                            value="<?php echo htmlspecialchars($user_data['telephone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="site_web">Site Web :</label>
                        <input type="url" id="site_web" name="site_web" placeholder="https://votresite.com"
                            value="<?php echo htmlspecialchars($user_data['site_web'] ?? ''); ?>">
                    </div>
                    <div class="form-group file-upload-group" style="display:flex;align-items:center;gap:1em;">
                        <div>
                            <label for="cover_photo">Photo de couverture :</label>
                            <input type="file" id="cover_photo" name="cover_photo" accept="image/*">
                            <p class="help-text">Taille max : 10MB. Formats : JPG, PNG, GIF. Idéale pour la bannière de
                                profil.</p>
                        </div>
                        <?php if ($user_data['photo_couverture']): ?>
                            <div class="current-photo-preview">
                                <img src="<?php echo getCompanyCoverPhoto($user_data['photo_couverture']); ?>"
                                    alt="Photo de couverture actuelle">
                                <span>Couverture actuelle</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>
                <!-- Section Géolocalisation -->
                <fieldset style="margin-bottom:2em;">
                    <legend><strong>Géolocalisation</strong></legend>
                    <p class="help-text">Entrez les coordonnées latitude et longitude pour afficher votre entreprise sur une
                        carte.</p>
                    <div class="form-group">
                        <label for="latitude">Latitude :</label>
                        <input type="text" id="latitude" name="latitude"
                            value="<?php echo htmlspecialchars($user_data['latitude'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="longitude">Longitude :</label>
                        <input type="text" id="longitude" name="longitude"
                            value="<?php echo htmlspecialchars($user_data['longitude'] ?? ''); ?>">
                    </div>
                </fieldset>
            <?php endif; ?>

            <button type="submit" class="button full-width">Enregistrer les modifications</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>