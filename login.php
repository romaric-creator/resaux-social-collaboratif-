<?php
$page_title = "Connexion";
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirige l'utilisateur s'il est déjà connecté
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Veuillez remplir tous les champs.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, nom, email, password, type_user, photo_profil FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Connexion réussie, démarrer la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['nom'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['type_user'];
                $_SESSION['user_photo_profil'] = $user['photo_profil'];

                // Redirection après connexion réussie
                header("Location: " . BASE_URL . "index.php");
                exit();
            } else {
                $error_message = "Email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $error_message = "Une erreur est survenue lors de la connexion. Veuillez réessayer.";
            // En production, loggez $e->getMessage()
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h2 class="auth-title">Connectez-vous à votre compte</h2>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST" class="auth-form">
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="button full-width">Se connecter</button>
        </form>
        <div class="auth-links">
            <a href="<?php echo BASE_URL; ?>register.php">Pas encore de compte ? Inscrivez-vous</a>
            <a href="<?php echo BASE_URL; ?>forgot_password.php">Mot de passe oublié ?</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
