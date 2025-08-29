<?php
$page_title = "Tableau de Bord Admin";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkAdmin(); // S'assurer que seul un admin peut accéder

// Récupérer les statistiques générales
$total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_entreprises = $conn->query("SELECT COUNT(*) FROM users WHERE type_user = 'entreprise'")->fetchColumn();
$total_publications = $conn->query("SELECT COUNT(*) FROM publications")->fetchColumn();
$pending_reports = $conn->query("SELECT COUNT(*) FROM signalements WHERE statut = 'en_attente'")->fetchColumn();

// Récupérer les derniers signalements en attente
$stmt_reports = $conn->query("SELECT s.*, u.nom as signaleur_nom
                            FROM signalements s
                            JOIN users u ON s.id_user_signaleur = u.id
                            WHERE s.statut = 'en_attente'
                            ORDER BY s.date_signalement DESC LIMIT 5");
$latest_reports = $stmt_reports->fetchAll();

// Récupérer les 5 derniers utilisateurs inscrits
$stmt_new_users = $conn->query("SELECT id, nom, email, type_user, date_inscription FROM users ORDER BY date_inscription DESC LIMIT 5");
$latest_users = $stmt_new_users->fetchAll();


require_once '../includes/header.php';
?>

<div class="main-content dashboard-page">
    <div class="dashboard-header">
        <h1><i class="fas fa-user-shield"></i> Tableau de Bord Administrateur</h1>
        <p>Gérez l'ensemble de la plateforme et supervisez les activités.</p>
    </div>

    <div class="dashboard-cards-grid">
        <div class="dashboard-card stat-card">
            <i class="fas fa-users icon-large"></i>
            <h3>Utilisateurs Totaux</h3>
            <p class="stat-number"><?php echo $total_users; ?></p>
            <a href="<?php echo BASE_URL; ?>admin/manage_users.php" class="card-link">Gérer les utilisateurs</a>
        </div>
        <div class="dashboard-card stat-card">
            <i class="fas fa-industry icon-large"></i>
            <h3>Entreprises Enregistrées</h3>
            <p class="stat-number"><?php echo $total_entreprises; ?></p>
            <a href="<?php echo BASE_URL; ?>admin/manage_companies.php" class="card-link">Gérer les entreprises</a>
        </div>
        <div class="dashboard-card stat-card">
            <i class="fas fa-ad icon-large"></i>
            <h3>Publications Totales</h3>
            <p class="stat-number"><?php echo $total_publications; ?></p>
            <a href="<?php echo BASE_URL; ?>admin/manage_publications.php" class="card-link">Gérer les publications</a>
        </div>
        <div class="dashboard-card stat-card <?php echo ($pending_reports > 0) ? 'alert-card' : ''; ?>">
            <i class="fas fa-flag icon-large"></i>
            <h3>Signalements en Attente</h3>
            <p class="stat-number"><?php echo $pending_reports; ?></p>
            <a href="<?php echo BASE_URL; ?>admin/manage_reports.php" class="card-link">Gérer les signalements</a>
        </div>
    </div>

    <div class="dashboard-section">
        <h2>Derniers Signalements en Attente</h2>
        <?php if (!empty($latest_reports)): ?>
            <div class="latest-activity-list">
                <?php foreach ($latest_reports as $report): ?>
                    <div class="list-item">
                        <span>Signalé par <strong><?php echo htmlspecialchars($report['signaleur_nom']); ?></strong> (<?php echo htmlspecialchars($report['type_contenu']); ?> ID: <?php echo htmlspecialchars($report['id_contenu_signale']); ?>)</span>
                        <span class="reason"><?php echo htmlspecialchars(mb_strimwidth($report['raison'], 0, 50, '...')); ?></span>
                        <span class="date"><?php echo formatRelativeTime($report['date_signalement']); ?></span>
                        <div class="actions">
                            <a href="<?php echo BASE_URL; ?>admin/manage_reports.php?report_id=<?php echo $report['id']; ?>" class="action-link" title="Traiter"><i class="fas fa-tools"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="<?php echo BASE_URL; ?>admin/manage_reports.php" class="button button-small view-all-btn">Voir tous les signalements</a>
            </div>
        <?php else: ?>
            <p class="info-message-card">Aucun signalement en attente. Tout est sous contrôle !</p>
        <?php endif; ?>
    </div>

    <div class="dashboard-section">
        <h2>Derniers Utilisateurs Inscrits</h2>
        <?php if (!empty($latest_users)): ?>
            <div class="latest-activity-list">
                <?php foreach ($latest_users as $user): ?>
                    <div class="list-item">
                        <span><strong><?php echo htmlspecialchars($user['nom']); ?></strong> (<?php echo htmlspecialchars(ucfirst($user['type_user'])); ?>)</span>
                        <span class="email"><?php echo htmlspecialchars($user['email']); ?></span>
                        <span class="date"><?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?></span>
                        <div class="actions">
                            <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $user['id']; ?>" class="action-link" title="Voir profil"><i class="fas fa-eye"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="<?php echo BASE_URL; ?>admin/manage_users.php" class="button button-small view-all-btn">Voir tous les utilisateurs</a>
            </div>
        <?php else: ?>
            <p class="info-message-card">Aucun nouvel utilisateur pour le moment.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
