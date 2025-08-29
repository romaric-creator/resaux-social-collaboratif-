<?php
$page_title = "Tableau de Bord Entreprise";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkEntreprise(); // S'assurer que seul un compte entreprise peut accéder

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'entreprise
$stmt_entreprise = $conn->prepare("SELECT u.*, e.nom as entreprise_nom, e.description, e.secteur, e.horaires_ouverture, e.telephone, e.site_web
                                FROM users u
                                JOIN entreprises e ON u.id = e.id_user
                                WHERE u.id = ?");
$stmt_entreprise->execute([$user_id]);
$entreprise_info = $stmt_entreprise->fetch();

if (!$entreprise_info) {
    // Si l'entrée entreprise n'existe pas encore (peut arriver si l'utilisateur s'inscrit en tant qu'entreprise mais n'a pas complété son profil)
    $_SESSION['error_message'] = "Veuillez compléter votre profil entreprise dans les paramètres.";
    header("Location: " . BASE_URL . "settings.php");
    exit();
}

// Compter le nombre de publications
$stmt_pubs_count = $conn->prepare("SELECT COUNT(*) FROM publications WHERE id_entreprise = ?");
$stmt_pubs_count->execute([$user_id]);
$publications_count = $stmt_pubs_count->fetchColumn();

// Compter le nombre de favoris sur le profil de cette entreprise
$stmt_fav_count = $conn->prepare("SELECT COUNT(*) FROM favoris WHERE id_entreprise_profile = ?");
$stmt_fav_count->execute([$user_id]);
$favoris_count = $stmt_fav_count->fetchColumn();

// Récupérer les 5 dernières publications de cette entreprise
$stmt_latest_pubs = $conn->prepare("SELECT id, titre, date_publication FROM publications WHERE id_entreprise = ? ORDER BY date_publication DESC LIMIT 5");
$stmt_latest_pubs->execute([$user_id]);
$latest_publications = $stmt_latest_pubs->fetchAll();

require_once '../includes/header.php';
?>

<div class="main-content dashboard-page">
    <div class="dashboard-header">
        <h1><i class="fas fa-briefcase"></i> Tableau de Bord Entreprise</h1>
        <p>Bienvenue, <?php echo htmlspecialchars($entreprise_info['nom']); ?> ! Gérez vos annonces et votre présence.</p>
    </div>

    <div class="dashboard-cards-grid">
        <div class="dashboard-card stat-card">
            <i class="fas fa-bullhorn icon-large"></i>
            <h3>Publications Actives</h3>
            <p class="stat-number"><?php echo $publications_count; ?></p>
            <a href="<?php echo BASE_URL; ?>entreprise/manage_publications.php" class="card-link">Gérer les publications</a>
        </div>
        <div class="dashboard-card stat-card">
            <i class="fas fa-bookmark icon-large"></i>
            <h3>Ajoutés aux Favoris</h3>
            <p class="stat-number"><?php echo $favoris_count; ?></p>
            <a href="#" class="card-link">Voir les stats avancées</a>
        </div>
        <div class="dashboard-card action-card">
            <i class="fas fa-plus-circle icon-large"></i>
            <h3>Nouvelle Publication</h3>
            <p>Créez une nouvelle annonce pour attirer plus de clients.</p>
            <a href="<?php echo BASE_URL; ?>entreprise/add_publication.php" class="button">Créer une annonce</a>
        </div>
        <div class="dashboard-card action-card">
            <i class="fas fa-user-edit icon-large"></i>
            <h3>Modifier Profil</h3>
            <p>Mettez à jour les informations de votre entreprise.</p>
            <a href="<?php echo BASE_URL; ?>settings.php" class="button secondary">Modifier les infos</a>
        </div>
    </div>

    <div class="dashboard-section">
        <h2>Vos dernières publications</h2>
        <?php if (!empty($latest_publications)): ?>
            <div class="latest-publications-list">
                <?php foreach ($latest_publications as $pub): ?>
                    <div class="list-item">
                        <span><?php echo htmlspecialchars($pub['titre']); ?></span>
                        <span class="date"><?php echo date('d/m/Y', strtotime($pub['date_publication'])); ?></span>
                        <div class="actions">
                            <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $user_id; ?>#post-<?php echo $pub['id']; ?>" class="action-link" title="Voir la publication"><i class="fas fa-eye"></i></a>
                            <a href="<?php echo BASE_URL; ?>entreprise/edit_publication.php?id=<?php echo $pub['id']; ?>" class="action-link" title="Modifier"><i class="fas fa-edit"></i></a>
                            <a href="<?php echo BASE_URL; ?>entreprise/delete_publication.php?id=<?php echo $pub['id']; ?>" class="action-link delete-action" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette publication ?');"><i class="fas fa-trash-alt"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="<?php echo BASE_URL; ?>entreprise/manage_publications.php" class="button button-small view-all-btn">Voir toutes les publications</a>
            </div>
        <?php else: ?>
            <p class="info-message-card">Vous n'avez pas encore de publications. Créez votre première annonce dès maintenant !</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
