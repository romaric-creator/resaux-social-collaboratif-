<?php
$page_title = "Inscription";
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirige l'utilisateur s'il est déjà connecté
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $type_user = $_POST['type_user'] ?? 'particulier';
    $localisation = trim($_POST['localisation'] ?? '');

    // Validation des champs
    if (empty($nom) || empty($email) || empty($password) || empty($confirm_password) || empty($type_user) || empty($localisation)) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Le format de l'email est invalide.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Les mots de passe ne correspondent pas.";
    } elseif (strlen($password) < 6) {
        $error_message = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Cet email est déjà utilisé.";
            } else {
                // Hacher le mot de passe avant de l'insérer
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insérer le nouvel utilisateur
                $stmt = $conn->prepare("INSERT INTO users (nom, email, password, type_user, localisation) VALUES (:nom, :email, :password, :type_user, :localisation)");
                $stmt->bindParam(':nom', $nom);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':type_user', $type_user);
                $stmt->bindParam(':localisation', $localisation);

                if ($stmt->execute()) {
                    $success_message = "Votre compte a été créé avec succès. Vous pouvez maintenant vous connecter.";
                    // Optionnel: rediriger directement vers la page de connexion
                    header("Location: " . BASE_URL . "login.php?registered=true");
                    exit();
                } else {
                    $error_message = "Une erreur est survenue lors de l'inscription. Veuillez réessayer.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données : " . $e->getMessage();
            // En production, loggez l'erreur
        }
    }
}

require_once 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h2 class="auth-title">Créez votre compte</h2>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <form action="register.php" method="POST" class="auth-form">
            <div class="form-group">
                <label for="nom">Nom ou Nom de l'entreprise :</label>
                <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email :</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" name="password" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe :</label>
                <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="type_user">Type de compte :</label>
                <select id="type_user" name="type_user" required>
                    <option value="particulier" <?php echo (($_POST['type_user'] ?? '') == 'particulier') ? 'selected' : ''; ?>>Particulier</option>
                    <option value="entreprise" <?php echo (($_POST['type_user'] ?? '') == 'entreprise') ? 'selected' : ''; ?>>Entreprise</option>
                </select>
            </div>
            <div class="form-group">
                <label for="localisation">Localisation (Ville, Pays) :</label>
                <input type="text" id="localisation" name="localisation" required value="<?php echo htmlspecialchars($_POST['localisation'] ?? ''); ?>">
            </div>
            <button type="submit" class="button full-width">S'inscrire</button>
        </form>
        <div class="auth-links">
            <a href="<?php echo BASE_URL; ?>login.php">Déjà un compte ? Connectez-vous</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
