
<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
checkLogin();

// --- Pagination et récupération paginée des entreprises ---
$per_page = 16;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

$sql_base = "FROM users u JOIN entreprises e ON u.id = e.id_user WHERE u.type_user = 'entreprise'";
$params = [];
$where_clauses = [];

$filter_category = $_GET['category'] ?? '';
$filter_location = $_GET['location'] ?? '';
$search_query = $_GET['search'] ?? '';

if (!empty($filter_category) && $filter_category !== 'all') {
    $where_clauses[] = "e.secteur = :category";
    $params[':category'] = $filter_category;
}
if (!empty($filter_location) && $filter_location !== 'all') {
    $where_clauses[] = "u.localisation = :location";
    $params[':location'] = $filter_location;
}
if (!empty($search_query)) {
    $where_clauses[] = "(u.nom LIKE :search OR e.description LIKE :search OR e.secteur LIKE :search)";
    $params[':search'] = '%' . $search_query . '%';
}

$where_sql = '';
if (!empty($where_clauses)) {
    $where_sql = ' AND ' . implode(' AND ', $where_clauses);
}

// Compter le total de résultats
$count_sql = "SELECT COUNT(*) $sql_base$where_sql";
$stmt_count = $conn->prepare($count_sql);
$stmt_count->execute($params);
$total_results = (int)$stmt_count->fetchColumn();

// Récupérer les résultats paginés
$sql = "SELECT u.id, u.nom, u.photo_profil, u.photo_couverture, u.localisation, e.secteur, e.description $sql_base$where_sql ORDER BY u.nom ASC LIMIT :limit OFFSET :offset";
$stmt = $conn->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$entreprises = $stmt->fetchAll();


$total_pages = max(1, ceil($total_results / $per_page));

// Récupérer les catégories uniques pour le filtre
$stmt_categories = $conn->query("SELECT DISTINCT secteur FROM entreprises WHERE secteur IS NOT NULL AND secteur != '' ORDER BY secteur ASC");
$categories = $stmt_categories->fetchAll(PDO::FETCH_COLUMN);

// Récupérer les localisations uniques pour le filtre
$stmt_locations = $conn->query("SELECT DISTINCT localisation FROM users WHERE type_user = 'entreprise' AND localisation IS NOT NULL AND localisation != '' ORDER BY localisation ASC");
$locations = $stmt_locations->fetchAll(PDO::FETCH_COLUMN);


require_once '../includes/header.php';
?>

<div class="main-content explore-page">
    <div class="page-header">
        <h1><i class="fas fa-search-location"></i> Explorer les Entreprises Locales</h1>
        <p>Découvrez des restaurants, hôtels, activités de loisirs et lieux touristiques près de chez vous ou ailleurs.
        </p>
    </div>

    <div class="filters-and-search-bar card">
        <form action="explore.php" method="GET" class="filter-form-inline">
            <div class="form-group-inline">
                <label for="search">Rechercher :</label>
                <input type="text" id="search" name="search" placeholder="Nom, description, catégorie..."
                    value="<?php echo htmlspecialchars($search_query); ?>">
            </div>
            <div class="form-group-inline">
                <label for="category-filter">Catégorie :</label>
                <select name="category" id="category-filter">
                    <option value="all">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filter_category == $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group-inline">
                <label for="location-filter">Localisation :</label>
                <select name="location" id="location-filter">
                    <option value="all">Toutes les localisations</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($filter_location == $loc) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($loc); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="button">Appliquer les filtres</button>
        </form>
    </div>

    <div class="explore-results-header" style="margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <span style="font-size:1.08em;color:var(--text-color-medium);font-weight:500;">
            <?php echo $total_results; ?> résultat<?php echo $total_results>1?'s':''; ?> trouvé<?php echo $total_results>1?'s':''; ?>
        </span>
        <?php if ($total_pages > 1): ?>
        <div class="explore-pagination" style="display:flex;gap:4px;align-items:center;">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>" class="button button-small secondary">&lt;</a>
            <?php endif; ?>
            <?php for ($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="button button-small primary" style="pointer-events:none;opacity:0.7;"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$i])); ?>" class="button button-small secondary"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>" class="button button-small secondary">&gt;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="company-grid">
        <?php if (!empty($entreprises)): ?>
            <?php foreach ($entreprises as $entreprise):
                $profile_link = BASE_URL . 'profile.php?id=' . $entreprise['id'];
                $cover_photo = getCompanyCoverPhoto($entreprise['photo_couverture']);
                $profile_photo = getUserProfilePhoto($entreprise['photo_profil']);
                $average_rating = getAverageRating($conn, $entreprise['id']);
                $is_favorited = hasFavoritedCompany($conn, $entreprise['id'], $_SESSION['user_id']);
            ?>
                <div class="company-card">
                    <div class="company-card-header">
                        <a href="<?php echo $profile_link; ?>">
                            <img src="<?php echo $cover_photo; ?>"
                                alt="Couverture de <?php echo htmlspecialchars($entreprise['nom']); ?>"
                                class="card-cover-photo">
                            <img src="<?php echo $profile_photo; ?>"
                                alt="Profil de <?php echo htmlspecialchars($entreprise['nom']); ?>" class="card-profile-photo">
                        </a>
                    </div>
                    <div class="company-card-body">
                        <h3 class="company-name"><a
                                href="<?php echo $profile_link; ?>"><?php echo htmlspecialchars($entreprise['nom']); ?></a></h3>
                        <p class="company-category"><i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($entreprise['secteur'] ?? 'Non spécifié'); ?></p>
                        <p class="company-location"><i class="fas fa-map-marker-alt"></i>
                            <?php echo htmlspecialchars($entreprise['localisation'] ?? 'Non spécifiée'); ?></p>
                        <?php if ($average_rating !== null): ?>
                            <div class="company-card-rating">
                                <?php echo displayRatingStars($average_rating, 'sm'); ?>
                                <span><?php echo $average_rating; ?>/5</span>
                            </div>
                        <?php endif; ?>
                        <p class="company-description">
                            <?php echo htmlspecialchars(mb_strimwidth($entreprise['description'] ?? '', 0, 100, '...')); ?></p>
                    </div>
                    <div class="company-card-actions">
                        <a href="<?php echo $profile_link; ?>" class="button button-small">Voir le profil</a>
                        <button class="button button-small <?php echo $is_favorited ? 'secondary' : 'primary'; ?> favorite-btn"
                            data-company-id="<?php echo $entreprise['id']; ?>">
                            <i class="fas fa-bookmark"></i> <?php echo $is_favorited ? 'Favoris' : 'Ajouter aux favoris'; ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="info-message-card">Aucune entreprise trouvée avec les critères de recherche.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>