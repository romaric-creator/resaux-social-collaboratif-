<?php
$page_title = "Gérer les Utilisateurs";
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
$filter_type = $_GET['type'] ?? 'all';

$sql = "SELECT id, nom, email, type_user, localisation, date_inscription FROM users WHERE 1=1";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (nom LIKE :search OR email LIKE :search OR localisation LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}
if ($filter_type != 'all') {
    $sql .= " AND type_user = :type";
    $params[':type'] = $filter_type;
}
$sql .= " ORDER BY date_inscription DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="main-content dashboard-page">
    <div class="dashboard-header">
        <h1><i class="fas fa-users"></i> Gestion des Utilisateurs</h1>
        <p>Affichez, recherchez et gérez les comptes utilisateurs.</p>
    </div>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <div class="filters-and-search-bar card">
        <form action="manage_users.php" method="GET" class="filter-form-inline">
            <div class="form-group-inline">
                <label for="search">Rechercher :</label>
                <input type="text" id="search" name="search" placeholder="Nom, email, localisation..." value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="form-group-inline">
                <label for="type">Type de compte :</label>
                <select name="type" id="type">
                    <option value="all" <?php echo ($filter_type == 'all') ? 'selected' : ''; ?>>Tous</option>
                    <option value="particulier" <?php echo ($filter_type == 'particulier') ? 'selected' : ''; ?>>Particulier</option>
                    <option value="entreprise" <?php echo ($filter_type == 'entreprise') ? 'selected' : ''; ?>>Entreprise</option>
                    <option value="admin" <?php echo ($filter_type == 'admin') ? 'selected' : ''; ?>>Admin</option>
                </select>
            </div>
            <button type="submit" class="button">Filtrer</button>
        </form>
    </div>

    <div class="data-table card">
        <?php if (!empty($users)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Type</th>
                        <th>Localisation</th>
                        <th>Date Inscription</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['nom']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($user['type_user'])); ?></td>
                            <td><?php echo htmlspecialchars($user['localisation'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($user['date_inscription'])); ?></td>
                            <td class="action-buttons">
                                <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $user['id']; ?>" class="action-btn-icon" title="Voir profil"><i class="fas fa-eye"></i></a>
                                <?php if ($user['type_user'] != 'admin'): // Empêcher la suppression ou modification des admins par accident ?>
                                    <a href="<?php echo BASE_URL; ?>admin/delete_user.php?id=<?php echo $user['id']; ?>" class="action-btn-icon delete-btn" title="Supprimer l'utilisateur" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Toutes les données associées seront perdues.');"><i class="fas fa-trash-alt"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="info-message-card">Aucun utilisateur trouvé.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
