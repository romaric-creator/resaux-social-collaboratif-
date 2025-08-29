<?php
// Assurez-vous que la session est démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Inclusion de auth.php pour les fonctions de vérification de rôle et BASE_URL
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php'; // Pour getUserProfilePhoto etc.

// Informations de l'utilisateur connecté pour la barre de navigation
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_name = $_SESSION['user_name'] ?? 'Invité';
$current_user_photo = getUserProfilePhoto($_SESSION['user_photo_profil'] ?? null);
$current_user_type = $_SESSION['user_type'] ?? 'visiteur';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Plateforme Tourisme' : 'Plateforme Tourisme et Loisirs'; ?></title>
    <!-- Le chemin correct vers le fichier CSS, en utilisant BASE_URL -->
    <!-- C'est crucial pour que le style soit appliqué correctement, peu importe le sous-dossier -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo time(); ?>">
    <!-- Font Awesome pour les icônes (version 6.0.0-beta3 est un peu ancienne, mais fonctionnelle) -->
    <!-- Nous utilisons une version plus récente et stable de Font Awesome pour plus d'icônes et de compatibilité -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- jQuery CDN (requis pour les fonctionnalités AJAX et JavaScript dynamiques) -->
    <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/theme.js"></script>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <a href="<?php echo BASE_URL; ?>index.php" class="logo">
                <i class="fas fa-plane-departure"></i> Tourisme
            </a>
            <div class="search-bar">
                <input type="text" placeholder="Rechercher...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </div>
        </div>
        <nav class="header-center nav-links">
            <a href="<?php echo BASE_URL; ?>index.php" title="Accueil"><i class="fas fa-home"></i><span>Accueil</span></a>
            <a href="<?php echo BASE_URL; ?>entreprise/explore.php" title="Explorer les entreprises"><i class="fas fa-building"></i><span>Entreprises</span></a>
            <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $current_user_id; ?>" title="Mon profil"><i class="fas fa-user-circle"></i><span>Profil</span></a>
            <a href="<?php echo BASE_URL; ?>chat/" title="Messagerie"><i class="fas fa-comment-dots"></i><span>Chat</span></a>
            <a href="#" title="Notifications"><i class="fas fa-bell"></i><span>Notifications</span></a>
            <button id="theme-toggle" onclick="toggleTheme()"><i class="fas fa-adjust"></i></button>
        </nav>
        <div class="header-right">
            <?php if ($current_user_id): ?>
                <div class="profile-menu">
                    <img src="<?php echo $current_user_photo; ?>" alt="Photo de profil" title="<?php echo htmlspecialchars($current_user_name); ?>">
                    <div class="dropdown-menu">
                        <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $current_user_id; ?>"><i class="fas fa-user"></i> Mon Profil</a>
                        <?php if ($current_user_type === 'entreprise'): ?>
                            <a href="<?php echo BASE_URL; ?>entreprise/dashboard.php"><i class="fas fa-briefcase"></i> Espace Entreprise</a>
                        <?php endif; ?>
                        <?php if ($current_user_type === 'admin'): ?>
                            <a href="<?php echo BASE_URL; ?>admin/dashboard.php"><i class="fas fa-user-shield"></i> Administration</a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>settings.php"><i class="fas fa-cog"></i> Paramètres</a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo BASE_URL; ?>logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>login.php" class="button button-small">Se connecter</a>
                <a href="<?php echo BASE_URL; ?>register.php" class="button button-small secondary">S'inscrire</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="main-wrapper">
    <!-- Le contenu spécifique de chaque page sera inséré ici -->

    <script>
    // Gestion du menu profil : ouverture au hover ET au clic, fermeture si on sort ou clique ailleurs
    $(document).ready(function() {
        var $profileMenu = $('.profile-menu');
        var menuTimeout;

        // Ouvre au hover
        $profileMenu.on('mouseenter', function() {
            clearTimeout(menuTimeout);
            $(this).addClass('active');
        });
        // Ferme au mouseleave (avec petit délai pour permettre le passage sur le menu)
        $profileMenu.on('mouseleave', function() {
            menuTimeout = setTimeout(function() {
                $profileMenu.removeClass('active');
            }, 200);
        });
        // Ouvre/ferme au clic
        $profileMenu.on('click', function(e) {
            e.stopPropagation();
            $(this).toggleClass('active');
        });
        // Empêche la fermeture si on clique dans le menu
        $profileMenu.find('.dropdown-menu').on('mouseenter click', function(e) {
            clearTimeout(menuTimeout);
            e.stopPropagation();
        });
        $profileMenu.find('.dropdown-menu').on('mouseleave', function() {
            menuTimeout = setTimeout(function() {
                $profileMenu.removeClass('active');
            }, 200);
        });
        // Ferme si on clique ailleurs
        $(document).on('click', function() {
            $profileMenu.removeClass('active');
        });
    });
    </script>
