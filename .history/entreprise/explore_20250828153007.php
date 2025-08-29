<?php
$page_title = "Explorer les Entreprises";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();

// Récupérer toutes les entreprises avec leurs infos de profil users
$sql = "SELECT u.id, u.nom, u.photo_profil, u.photo_couverture, u.localisation, e.secteur, e.description
        FROM users u
        JOIN entreprises e ON u.id = e.id_user
        WHERE u.type_user = 'entreprise'";

$params = [];
$where_clauses = [];

// Filtres
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


// Ajout correct des filtres à la clause WHERE existante
if (!empty($where_clauses)) {
    // La requête de base a déjà un WHERE, donc on ajoute les filtres avec AND
    $sql .= ' AND ' . implode(' AND ', $where_clauses);
}

$sql .= " ORDER BY u.nom ASC"; // Tri par nom par défaut

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$entreprises = $stmt->fetchAll();

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
        <p>Découvrez des restaurants, hôtels, activités de loisirs et lieux touristiques près de chez vous ou ailleurs.</p>
    </div>

    <div class="filters-and-search-bar card">
        <form action="explore.php" method="GET" class="filter-form-inline">
            <div class="form-group-inline">
                <label for="search">Rechercher :</label>
                <input type="text" id="search" name="search" placeholder="Nom, description, catégorie..." value="<?php echo htmlspecialchars($search_query); ?>">
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
                            <img src="<?php echo $cover_photo; ?>" alt="Couverture de <?php echo htmlspecialchars($entreprise['nom']); ?>" class="card-cover-photo">
                            <img src="<?php echo $profile_photo; ?>" alt="Profil de <?php echo htmlspecialchars($entreprise['nom']); ?>" class="card-profile-photo">
                        </a>
                    </div>
                    <div class="company-card-body">
                        <h3 class="company-name"><a href="<?php echo $profile_link; ?>"><?php echo htmlspecialchars($entreprise['nom']); ?></a></h3>
                        <p class="company-category"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($entreprise['secteur'] ?? 'Non spécifié'); ?></p>
                        <p class="company-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($entreprise['localisation'] ?? 'Non spécifiée'); ?></p>
                        <?php if ($average_rating !== null): ?>
                            <div class="company-card-rating">
                                <?php echo displayRatingStars($average_rating, 'sm'); ?>
                                <span><?php echo $average_rating; ?>/5</span>
                            </div>
                        <?php endif; ?>
                        <p class="company-description"><?php echo htmlspecialchars(mb_strimwidth($entreprise['description'] ?? '', 0, 100, '...')); ?></p>
                    </div>
                    <div class="company-card-actions">
                        <a href="<?php echo $profile_link; ?>" class="button button-small">Voir le profil</a>
                        <button class="button button-small <?php echo $is_favorited ? 'secondary' : 'primary'; ?> favorite-btn" data-company-id="<?php echo $entreprise['id']; ?>">
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
