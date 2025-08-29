<?php
$page_title = "Mot de passe oublié";
require_once 'includes/db.php';
require_once 'includes/auth.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $message = "Veuillez entrer votre adresse email.";
        $message_type = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format d'email invalide.";
        $message_type = "error";
    } else {
        // Dans une vraie application, vous enverriez un email avec un lien de réinitialisation sécurisé.
        // Pour cette démo, on simule l'envoi.
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $message = "Si un compte est associé à cet email, des instructions de réinitialisation ont été envoyées.";
            $message_type = "success";
            // Ici, code pour générer un token, le stocker en DB et envoyer un email.
            // Ex: $reset_token = bin2hex(random_bytes(32));
            //     $stmt_insert_token = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
            //     $stmt_insert_token->execute([$reset_token, $email]);
            //     // Envoyer l'email avec le lien: BASE_URL . "reset_password.php?token=" . $reset_token
        } else {
            $message = "Si un compte est associé à cet email, des instructions de réinitialisation ont été envoyées.";
            $message_type = "success"; // Ne pas révéler si l'email existe pour des raisons de sécurité
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h2 class="auth-title">Mot de passe oublié ?</h2>
        <p class="auth-subtitle">Entrez votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe.</p>
        <?php if (!empty($message)): ?>
            <p class="<?php echo htmlspecialchars($message_type); ?>-message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="forgot_password.php" method="POST" class="auth-form">
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <button type="submit" class="button full-width">Envoyer le lien de réinitialisation</button>
        </form>
        <div class="auth-links">
            <a href="<?php echo BASE_URL; ?>login.php">Retour à la connexion</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
