<?php
$page_title = "Réinitialiser le mot de passe";
require_once 'includes/db.php';
require_once 'includes/auth.php'; // Pour generateCsrfToken()

$error_message = '';
$success_message = '';
$token_valid = false;
$token_received = $_GET['token'] ?? '';

if (!empty($token_received)) {
    // En production, vérifier le token dans la table `password_resets`
    // Ici, nous utilisons la session pour la démo
    if (isset($_SESSION['reset_token_value']) && $_SESSION['reset_token_value'] === $token_received &&
        isset($_SESSION['reset_token_expires']) && strtotime($_SESSION['reset_token_expires']) > time()) {
        
        $token_valid = true;
    } else {
        $error_message = "Le lien de réinitialisation est invalide ou a expiré.";
    }
} else {
    $error_message = "Token de réinitialisation manquant.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    validateCsrfToken(); // Protection CSRF

    $new_password = $_POST['new_password'] ?? '';
    $confirm_new_password = $_POST['confirm_new_password'] ?? '';
    $submitted_token = $_POST['token'] ?? ''; // Le token doit être renvoyé par le formulaire

    if (empty($new_password) || empty($confirm_new_password)) {
        $error_message = "Veuillez remplir tous les champs du mot de passe.";
    } elseif ($new_password !== $confirm_new_password) {
        $error_message = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($_SESSION['reset_token_value'] !== $submitted_token) {
        $error_message = "Erreur de token. Veuillez utiliser le lien original.";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $user_id_to_reset = $_SESSION['reset_token_user_id'];

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id_to_reset]);

            if ($stmt->rowCount() > 0) {
                $success_message = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
                // Invalider le token après utilisation
                unset($_SESSION['reset_token_value']);
                unset($_SESSION['reset_token_expires']);
                unset($_SESSION['reset_token_user_id']);
                unset($_SESSION['reset_token_email']);

                $token_valid = false; // Désactiver le formulaire
            } else {
                $error_message = "Impossible de réinitialiser le mot de passe. L'utilisateur n'existe pas ou le token est invalide.";
            }
        } catch (PDOException $e) {
            $error_message = "Une erreur est survenue lors de la réinitialisation. Veuillez réessayer.";
            error_log("Reset password error: " . $e->getMessage());
        }
    }
}

require_once 'includes/header.php'; // Header va appeler generateCsrfToken()
?>

<div class="auth-container">
    <div class="auth-card">
        <h2 class="auth-title">Réinitialiser votre mot de passe</h2>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p>
            <div class="auth-links" style="margin-top: 20px;">
                <a href="<?php echo BASE_URL; ?>login.php" class="button">Se connecter</a>
            </div>
        <?php endif; ?>

        <?php if ($token_valid && empty($success_message)): ?>
            <form action="reset_password.php?token=<?php echo htmlspecialchars($token_received, ENT_QUOTES, 'UTF-8'); ?>" method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_received, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="new_password">Nouveau mot de passe :</label>
                    <input type="password" id="new_password" name="new_password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirm_new_password">Confirmer le nouveau mot de passe :</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" required autocomplete="new-password">
                </div>
                <button type="submit" class="button full-width">Définir le nouveau mot de passe</button>
            </form>
        <?php elseif(empty($success_message)): ?>
            <p class="info-message-card">Veuillez utiliser le lien de réinitialisation que vous avez reçu par email.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
