<?php
$page_title = "Gérer les Signalements";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkAdmin();

$admin_user_id = $_SESSION['user_id'];
$message = $_SESSION['status_message'] ?? '';
$message_type = $_SESSION['status_message_type'] ?? '';
unset($_SESSION['status_message']);
unset($_SESSION['status_message_type']);

// Traitement des actions de signalement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'])) {
    $report_id = $_POST['report_id'];
    $action_type = $_POST['action_type'] ?? ''; // 'resolve' ou 'reject'

    try {
        if ($action_type === 'resolve') {
            $stmt = $conn->prepare("UPDATE signalements SET statut = 'traite', id_admin_traitant = ? WHERE id = ?");
            $stmt->execute([$admin_user_id, $report_id]);
            $_SESSION['status_message'] = "Signalement #{$report_id} marqué comme traité.";
            $_SESSION['status_message_type'] = "success";
            // TODO: Ajouter une notification à l'utilisateur qui a signalé que son signalement a été traité.
        } elseif ($action_type === 'reject') {
            $stmt = $conn->prepare("UPDATE signalements SET statut = 'rejete', id_admin_traitant = ? WHERE id = ?");
            $stmt->execute([$admin_user_id, $report_id]);
            $_SESSION['status_message'] = "Signalement #{$report_id} marqué comme rejeté.";
            $_SESSION['status_message_type'] = "success";
            // TODO: Ajouter une notification à l'utilisateur qui a signalé que son signalement a été rejeté.
        } else {
            $_SESSION['status_message'] = "Action de signalement invalide.";
            $_SESSION['status_message_type'] = "error";
        }
    } catch (PDOException $e) {
        $_SESSION['status_message'] = "Erreur de base de données lors du traitement du signalement : " . $e->getMessage();
        $_SESSION['status_message_type'] = "error";
    }
    header("Location: " . BASE_URL . "admin/manage_reports.php");
    exit();
}

// Récupérer tous les signalements (filtrables)
$filter_status = $_GET['status'] ?? 'en_attente';

$sql = "SELECT s.*, u.nom as signaleur_nom, u.photo_profil as signaleur_photo
        FROM signalements s
        JOIN users u ON s.id_user_signaleur = u.id";
$params = [];

if ($filter_status !== 'all') {
    $sql .= " WHERE s.statut = :status";
    $params[':status'] = $filter_status;
}
$sql .= " ORDER BY s.date_signalement DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="main-content dashboard-page">
    <div class="dashboard-header">
        <h1><i class="fas fa-flag"></i> Gestion des Signalements</h1>
        <p>Examinez et traitez les contenus signalés par les utilisateurs.</p>
    </div>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <div class="filters-and-search-bar card">
        <form action="manage_reports.php" method="GET" class="filter-form-inline">
            <div class="form-group-inline">
                <label for="status">Statut :</label>
                <select name="status" id="status">
                    <option value="en_attente" <?php echo ($filter_status == 'en_attente') ? 'selected' : ''; ?>>En attente</option>
                    <option value="traite" <?php echo ($filter_status == 'traite') ? 'selected' : ''; ?>>Traité</option>
                    <option value="rejete" <?php echo ($filter_status == 'rejete') ? 'selected' : ''; ?>>Rejeté</option>
                    <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>Tous</option>
                </select>
            </div>
            <button type="submit" class="button">Filtrer</button>
        </form>
    </div>

    <div class="data-table card">
        <?php if (!empty($reports)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Signaleur</th>
                        <th>Type Contenu</th>
                        <th>ID Contenu</th>
                        <th>Raison</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($report['id']); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $report['id_user_signaleur']; ?>" target="_blank">
                                    <img src="<?php echo getUserProfilePhoto($report['signaleur_photo']); ?>" alt="Signaleur" class="profile-pic-tiny">
                                    <?php echo htmlspecialchars($report['signaleur_nom']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($report['type_contenu']); ?></td>
                            <td><?php echo htmlspecialchars($report['id_contenu_signale']); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($report['raison'], 0, 50, '...')); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($report['date_signalement'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($report['statut']); ?>">
                                    <?php
                                        if ($report['statut'] == 'en_attente') echo 'En attente';
                                        elseif ($report['statut'] == 'traite') echo 'Traité';
                                        else echo 'Rejeté';
                                    ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <?php if ($report['statut'] == 'en_attente'): ?>
                                    <form action="manage_reports.php" method="POST" style="display:inline-block;">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <input type="hidden" name="action_type" value="resolve">
                                        <button type="submit" class="action-btn-icon" title="Marquer comme traité"><i class="fas fa-check-circle green-icon"></i></button>
                                    </form>
                                    <form action="manage_reports.php" method="POST" style="display:inline-block;">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <input type="hidden" name="action_type" value="reject">
                                        <button type="submit" class="action-btn-icon" title="Rejeter le signalement"><i class="fas fa-times-circle red-icon"></i></button>
                                    </form>
                                <?php else: ?>
                                    <span class="action-btn-icon disabled" title="Signalement déjà traité"><i class="fas fa-info-circle"></i></span>
                                <?php endif; ?>
                                                               <!-- Lien pour voir le contenu signalé (à implémenter en fonction du type de contenu) -->
                                <?php
                                    $content_link = '#'; // Lien par défaut si non trouvé
                                    $link_title = 'Voir le contenu';
                                    switch ($report['type_contenu']) {
                                        case 'publication':
                                            $content_link = BASE_URL . 'profile.php?id=' . $_SESSION['user_id'] . '#post-' . $report['id_contenu_signale']; // Lien vers la publication sur le profil de l'entreprise
                                            break;
                                        case 'commentaire':
                                            // Difficile de lier directement un commentaire, on pourrait lier à la publication/profil parent
                                            // Pour l'exemple, on peut chercher la publication associée au commentaire
                                            $stmt_parent = $conn->prepare("SELECT id_publication, id_entreprise_profile FROM commentaires WHERE id = ?");
                                            $stmt_parent->execute([$report['id_contenu_signale']]);
                                            $parent = $stmt_parent->fetch();
                                            if ($parent && $parent['id_publication']) {
                                                // Lien vers la publication si le commentaire y est lié
                                                // Il faudrait d'abord récupérer l'ID entreprise de cette publication
                                                $stmt_pub_ent_id = $conn->prepare("SELECT id_entreprise FROM publications WHERE id = ?");
                                                $stmt_pub_ent_id->execute([$parent['id_publication']]);
                                                $ent_id = $stmt_pub_ent_id->fetchColumn();
                                                if($ent_id) {
                                                    $content_link = BASE_URL . 'profile.php?id=' . $ent_id . '#post-' . $parent['id_publication'];
                                                }
                                            } elseif ($parent && $parent['id_entreprise_profile']) {
                                                // Lien vers le profil entreprise si le commentaire y est lié
                                                $content_link = BASE_URL . 'profile.php?id=' . $parent['id_entreprise_profile'];
                                            }
                                            $link_title = 'Voir le commentaire / son contenu parent';
                                            break;
                                        case 'profil_user':
                                        case 'profil_entreprise':
                                            $content_link = BASE_URL . 'profile.php?id=' . $report['id_contenu_signale'];
                                            break;
                                        case 'message':
                                            // Pour un message, il faudrait un lien direct vers la conversation spécifique
                                            // Ceci est plus complexe et nécessiterait la récupération des participants de la conversation
                                            // Pour cet exemple, on mettra un lien générique vers le chat
                                            $content_link = BASE_URL . 'chat/';
                                            $link_title = 'Aller à la messagerie';
                                            break;
                                    }
                                ?>
                                <a href="<?php echo htmlspecialchars($content_link); ?>" target="_blank" class="action-btn-icon" title="<?php echo htmlspecialchars($link_title); ?>"><i class="fas fa-eye blue-icon"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="info-message-card">Aucun signalement trouvé pour ce statut.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
