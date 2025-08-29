<?php
$page_title = "Contact";
require_once 'includes/auth.php'; // Pour BASE_URL
require_once 'includes/header.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $message_content = trim($_POST['message'] ?? '');

    if (empty($nom) || empty($email) || empty($sujet) || empty($message_content)) {
        $error_message = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "L'adresse email est invalide.";
    } else {
        // Dans une vraie application, vous enverriez cet email via un serveur SMTP configuré
        // Pour cet exemple, nous allons simplement simuler l'envoi et afficher un message de succès.

        $to = "contact@votreplateforme.com"; // Remplacez par votre adresse email de contact
        $headers = "From: " . $email . "\r\n";
        $headers .= "Reply-To: " . $email . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $full_message = "De: " . $nom . " <" . $email . ">\n";
        $full_message .= "Sujet: " . $sujet . "\n\n";
        $full_message .= "Message:\n" . $message_content;

        // Note: La fonction mail() de PHP nécessite une configuration SMTP sur le serveur.
        // Pour un environnement de développement local (comme XAMPP/WAMP), cela pourrait ne pas fonctionner sans configuration.
        // En production, utilisez une bibliothèque comme PHPMailer ou un service d'envoi d'emails (SendGrid, Mailgun, etc.).
        // if (mail($to, $sujet, $full_message, $headers)) {
            $success_message = "Votre message a été envoyé avec succès. Nous vous répondrons dès que possible !";
            // Clear form fields
            $_POST = [];
        // } else {
        //     $error_message = "Une erreur est survenue lors de l'envoi de votre message. Veuillez réessayer plus tard.";
        // }
    }
}

require_once 'includes/header.php';
?>

<div class="main-content form-page">
    <div class="form-card">
        <h1 class="form-title">Contactez-nous</h1>
        <p class="auth-subtitle">Vous avez des questions, des suggestions ou besoin d'assistance ? N'hésitez pas à nous contacter via le formulaire ci-dessous.</p>

        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>

        <form action="contact.php" method="POST" class="modern-form">
            <div class="form-group">
                <label for="nom">Votre Nom :</label>
                <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Votre Email :</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="sujet">Sujet :</label>
                <input type="text" id="sujet" name="sujet" required value="<?php echo htmlspecialchars($_POST['sujet'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="message">Votre Message :</label>
                <textarea id="message" name="message" rows="8" required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="button full-width">Envoyer le Message</button>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
