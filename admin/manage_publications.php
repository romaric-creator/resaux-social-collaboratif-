<?php
$page_title = "Gérer les Publications (Admin)";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkAdmin();

$message = $_SESSION['status_message'] ?? '';
$message_type = $_SESSION['status_message_type'] ?? '';
unset($_SESSION['status_message']);
unset($_SESSION['status_message_type']);

// Filtrage et recherche
$search_query = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';

$sql = "SELECT p.*, u.nom as entreprise_nom, u.email as entreprise_email, u.localisation as entreprise_localisation
        FROM publications p
        JOIN users u ON p.id_entreprise = u.id
        WHERE 1=1";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (p.titre LIKE :search OR p.description LIKE :search OR u.nom LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}
if ($filter_status != 'all') {
    $sql .= " AND p.status = :status";
    $params[':status'] = $filter_status;
}
if ($filter_category != 'all') {
    $sql .= " AND p.categorie = :category";
    $params[':category'] = $filter_category;
}
$sql .= " ORDER BY p.date_publication DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$publications = $stmt->fetchAll();

// Récupérer les catégories uniques pour le filtre
$stmt_categories = $conn->query("SELECT DISTINCT categorie FROM publications WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie ASC");
$categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<div class="main-content dashboard-page">
    <div class="dashboard-header">
        <h1><i class="fas fa-ad"></i> Gestion des Publications (Admin)</h1>
        <p>Gérez et modérez toutes les publications de la plateforme.</p>
    </div>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <div class="filters-and-search-bar card">
        <form action="manage_publications.php" method="GET" class="filter-form-inline">
            <div class="form-group-inline">
                <label for="search">Rechercher :</label>
                <input type="text" id="search" name="search" placeholder="Titre, description, entreprise..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="form-group-inline">
                <label for="status">Statut :</label>
                <select name="status" id="status">
                    <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>Tous</option>
                    <option value="publie" <?php echo ($filter_status == 'publie') ? 'selected' : ''; ?>>Publié</option>
                    <option value="brouillon" <?php echo ($filter_status == 'brouillon') ? 'selected' : ''; ?>>Brouillon</option>
                    <option value="programme" <?php echo ($filter_status == 'programme') ? 'selected' : ''; ?>>Programmé</option>
                    <option value="expire" <?php echo ($filter_status == 'expire') ? 'selected' : ''; ?>>Expiré</option>
                    <option value="rejete" <?php echo ($filter_status == 'rejete') ? 'selected' : ''; ?>>Rejeté</option>
                </select>
            </div>
            <div class="form-group-inline">
                <label for="category">Catégorie :</label>
                <select name="category" id="category">
                    <option value="all" <?php echo ($filter_category == 'all') ? 'selected' : ''; ?>>Toutes</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_category == $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button">Filtrer</button>
        </form>
    </div>

    <div class="data-table card">
        <?php if (!empty($publications)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Pub</th>
                        <th>Titre</th>
                        <th>Entreprise</th>
                        <th>Catégorie</th>
                        <th>Statut</th>
                        <th>Date Pub.</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($publications as $pub): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pub['id']); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($pub['titre'], 0, 40, '...')); ?></td>
                            <td>
                                <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $pub['id_entreprise']; ?>" target="_blank">
                                    <?php echo htmlspecialchars($pub['entreprise_nom']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($pub['categorie'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo htmlspecialchars($pub['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($pub['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($pub['date_publication'])); ?></td>
                            <td class="action-buttons">
                                <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $pub['id_entreprise']; ?>#post-<?php echo $pub['id']; ?>" class="action-btn-icon" title="Voir la publication" target="_blank"><i class="fas fa-eye"></i></a>
                                <a href="<?php echo BASE_URL; ?>entreprise/edit_publication.php?id=<?php echo $pub['id']; ?>" class="action-btn-icon" title="Modifier la publication"><i class="fas fa-edit"></i></a>
                                <a href="<?php echo BASE_URL; ?>entreprise/delete_publication.php?id=<?php echo $pub['id']; ?>" class="action-btn-icon delete-btn" title="Supprimer la publication" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette publication ? Cette action est irréversible.');"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="info-message-card">Aucune publication trouvée avec les filtres actuels.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
