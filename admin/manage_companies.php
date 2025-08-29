<?php
$page_title = "Gérer les Entreprises";
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
$filter_sector = $_GET['sector'] ?? 'all';

$sql = "SELECT u.id, u.nom, u.email, u.localisation, u.date_inscription, e.secteur, e.description, e.telephone, e.site_web
        FROM users u
        JOIN entreprises e ON u.id = e.id_user
        WHERE u.type_user = 'entreprise'";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (u.nom LIKE :search OR u.email LIKE :search OR u.localisation LIKE :search OR e.secteur LIKE :search OR e.description LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}
if ($filter_sector != 'all') {
    $sql .= " AND e.secteur = :sector";
    $params[':sector'] = $filter_sector;
}
$sql .= " ORDER BY u.nom ASC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$companies = $stmt->fetchAll();

// Récupérer les secteurs uniques pour le filtre
$stmt_sectors = $conn->query("SELECT DISTINCT secteur FROM entreprises WHERE secteur IS NOT NULL AND secteur != '' ORDER BY secteur ASC");
$sectors = $stmt_sectors->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<div class="main-content dashboard-page">
    <div class="dashboard-header">
        <h1><i class="fas fa-building"></i> Gestion des Entreprises</h1>
        <p>Affichez, recherchez et gérez les profils d'entreprise.</p>
    </div>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <div class="filters-and-search-bar card">
        <form action="manage_companies.php" method="GET" class="filter-form-inline">
            <div class="form-group-inline">
                <label for="search">Rechercher :</label>
                <input type="text" id="search" name="search" placeholder="Nom, email, secteur..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="form-group-inline">
                <label for="sector">Secteur :</label>
                <select name="sector" id="sector">
                    <option value="all" <?php echo ($filter_sector == 'all') ? 'selected' : ''; ?>>Tous</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($filter_sector == $s) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button">Filtrer</button>
        </form>
    </div>

    <div class="data-table card">
        <?php if (!empty($companies)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID User</th>
                        <th>Nom Entreprise</th>
                        <th>Email</th>
                        <th>Secteur</th>
                        <th>Localisation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $company): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($company['id']); ?></td>
                            <td><?php echo htmlspecialchars($company['nom']); ?></td>
                            <td><?php echo htmlspecialchars($company['email']); ?></td>
                            <td><?php echo htmlspecialchars($company['secteur'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($company['localisation'] ?? 'N/A'); ?></td>
                            <td class="action-buttons">
                                <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $company['id']; ?>" class="action-btn-icon" title="Voir profil"><i class="fas fa-eye"></i></a>
                                <!-- Vous pouvez ajouter une page d'édition spécifique aux infos entreprise ici -->
                                <!-- <a href="<?php echo BASE_URL; ?>admin/edit_company.php?id=<?php echo $company['id']; ?>" class="action-btn-icon" title="Modifier infos entreprise"><i class="fas fa-edit"></i></a> -->
                                <a href="<?php echo BASE_URL; ?>admin/delete_user.php?id=<?php echo $company['id']; ?>" class="action-btn-icon delete-btn" title="Supprimer l'entreprise et ses données" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette entreprise ? Toutes ses publications, commentaires, etc. seront supprimés.');"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="info-message-card">Aucune entreprise trouvée.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
