<?php
$page_title = "Profil";
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirige si non connecté
checkLogin();

$profile_id = $_GET['id'] ?? null;
if (!$profile_id) {
    // Si pas d'ID spécifié, afficher le profil de l'utilisateur connecté
    $profile_id = $_SESSION['user_id'];
}

// Récupérer les informations de l'utilisateur/entreprise du profil consulté
$stmt = $conn->prepare("SELECT u.*, e.description, e.secteur, e.horaires_ouverture, e.telephone, e.site_web, e.latitude, e.longitude
                        FROM users u
                        LEFT JOIN entreprises e ON u.id = e.id_user
                        WHERE u.id = ?");
$stmt->execute([$profile_id]);
$profile_user = $stmt->fetch();

if (!$profile_user) {
    // Gérer le cas où le profil n'existe pas
    $_SESSION['error_message'] = "Profil introuvable.";
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// Vérifier si c'est le profil de l'utilisateur connecté
$is_current_user_profile = ($_SESSION['user_id'] == $profile_id);

// Récupérer les publications de ce profil (si c'est une entreprise)
$profile_publications = [];
if ($profile_user['type_user'] === 'entreprise') {
    $stmt_pubs = $conn->prepare("SELECT p.*, u.nom as user_nom, u.photo_profil as user_photo_profil, e.nom as entreprise_nom
                                 FROM publications p
                                 JOIN users u ON p.id_entreprise = u.id
                                 JOIN entreprises e ON u.id = e.id_user
                                 WHERE p.id_entreprise = ? ORDER BY date_publication DESC");
    $stmt_pubs->execute([$profile_id]);
    $profile_publications = $stmt_pubs->fetchAll();
}

// Récupérer les avis/notes sur ce profil (si c'est une entreprise)
$company_reviews = [];
$average_rating = null;
if ($profile_user['type_user'] === 'entreprise') {
    $company_reviews = getCompanyReviews($conn, $profile_id);
    $average_rating = getAverageRating($conn, $profile_id);
}

// Vérifier si l'utilisateur connecté a mis en favori cette entreprise (si c'est une entreprise)
$is_favorited = false;
if ($profile_user['type_user'] === 'entreprise' && !$is_current_user_profile) {
    $is_favorited = hasFavoritedCompany($conn, $profile_id, $_SESSION['user_id']);
}

$page_title = htmlspecialchars($profile_user['nom']) . " - Profil";
require_once 'includes/header.php';
?>

<div class="main-content profile-page"
    style="background: linear-gradient(135deg, #f8fafc 0%, #e9f0fb 100%); min-height: 100vh; padding-bottom: 40px;">
    <div class="profile-header-section">
        <div class="cover-photo">
            <img src="<?php echo getCompanyCoverPhoto($profile_user['photo_couverture']); ?>" alt="Photo de couverture">
            <?php if ($is_current_user_profile): ?>
                <button class="edit-cover-btn button button-small"><i class="fas fa-camera"></i> Modifier la
                    couverture</button>
            <?php endif; ?>
            <div
                style="position: absolute; left: 50%; bottom: -90px; transform: translateX(-50%); z-index: 20; width: 180px; display: flex; flex-direction: column; align-items: center;">
                <img src="<?php echo getUserProfilePhoto($profile_user['photo_profil']); ?>" alt="Photo de profil"
                    class="profile-pic-large"
                    style="box-shadow: 0 4px 18px rgba(59,130,246,0.10); border: 3px solid #3b82f6; background: #fff; width: 160px; height: 160px;">
                <?php if ($is_current_user_profile): ?>
                    <button class="edit-profile-pic-btn button button-small"
                        style="margin-top: -24px; position: relative; z-index: 21;"><i class="fas fa-camera"></i> Modifier
                        le profil</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="profile-info-area" style="margin-top: 60px;">
            <h1 style="margin-top: 0.5em;">
                    
                </i>
                <?php echo htmlspecialchars($profile_user['nom']); ?></h1>
            <p class="user-type"><i class="fas fa-id-badge"></i>
                <?php echo htmlspecialchars(ucfirst($profile_user['type_user'])); ?></p>
            <?php if (!empty($profile_user['localisation'])): ?>
                <p class="location"><i class="fas fa-map-marker-alt"></i>
                    <?php echo htmlspecialchars($profile_user['localisation']); ?></p>
            <?php endif; ?>

            <?php if ($profile_user['type_user'] === 'entreprise' && $average_rating !== null): ?>
                <div class="company-rating">
                    <?php echo displayRatingStars($average_rating, 'lg'); ?>
                    <span><?php echo $average_rating; ?> (<?php echo count($company_reviews); ?> avis)</span>
                </div>
            <?php endif; ?>

            <hr style="margin: 1.5em 0; border: none; border-top: 1px solid #e5e7eb;">
            <div class="profile-actions">
                <?php if ($is_current_user_profile): ?>
                    <a href="<?php echo BASE_URL; ?>settings.php" class="button"><i class="fas fa-edit"></i> Modifier le
                        profil</a>
                    <?php if ($profile_user['type_user'] === 'entreprise'): ?>
                        <a href="<?php echo BASE_URL; ?>entreprise/dashboard.php" class="button primary"><i
                                class="fas fa-briefcase"></i> Gérer l'entreprise</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>chat/?user_id=<?php echo $profile_user['id']; ?>" class="button"><i
                            class="fas fa-comment-dots"></i> Message</a>
                    <?php if ($profile_user['type_user'] === 'entreprise'): ?>
                        <button class="button <?php echo $is_favorited ? 'secondary' : 'primary'; ?> favorite-btn"
                            data-company-id="<?php echo $profile_user['id']; ?>">
                            <i class="fas fa-bookmark"></i> <?php echo $is_favorited ? 'Favoris' : 'Ajouter aux favoris'; ?>
                        </button>
                    <?php endif; ?>
                    <button class="button secondary report-btn" data-user-id="<?php echo $profile_user['id']; ?>"><i
                            class="fas fa-flag"></i> Signaler</button>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- Fin de profile-header-section -->

    <div class="profile-content-section">
        <div class="profile-left-sidebar" style="box-shadow: 0 4px 24px rgba(0,0,0,0.07);">
            <h3 style="display: flex; align-items: center; gap: 8px;"><i class="fas fa-info-circle"
                    style="color: #3b82f6;"></i> À Propos</h3>
            <hr style="margin: 0.5em 0 1em 0; border: none; border-top: 1px solid #e5e7eb;">
            <?php if ($profile_user['type_user'] === 'entreprise'): ?>
                <p><i class="fas fa-industry"></i> <strong>Secteur:</strong>
                    <?php echo htmlspecialchars($profile_user['secteur'] ?? 'Non spécifié'); ?></p>
                <p><i class="fas fa-align-left"></i> <strong>Description:</strong>
                    <?php echo nl2br(htmlspecialchars($profile_user['description'] ?? 'Pas de description.')); ?></p>
                <?php if (!empty($profile_user['horaires_ouverture'])): ?>
                    <p><i class="fas fa-clock"></i> <strong>Horaires:</strong>
                        <?php echo htmlspecialchars($profile_user['horaires_ouverture']); ?></p>
                <?php endif; ?>
                <?php if (!empty($profile_user['telephone'])): ?>
                    <p><i class="fas fa-phone"></i> <strong>Téléphone:</strong>
                        <?php echo htmlspecialchars($profile_user['telephone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($profile_user['site_web'])): ?>
                    <p><i class="fas fa-globe"></i> <strong>Site Web:</strong> <a
                            href="<?php echo htmlspecialchars($profile_user['site_web']); ?>" target="_blank">Visiter</a></p>
                <?php endif; ?>
                <?php if (!empty($profile_user['localisation'])): ?>
                    <p><i class="fas fa-map-marker-alt"></i> <strong>Adresse:</strong>
                        <?php echo htmlspecialchars($profile_user['localisation']); ?></p>
                <?php endif; ?>
                <!-- Géolocalisation - Afficher la carte si des coordonnées sont présentes -->
                <?php if ($profile_user['latitude'] && $profile_user['longitude']): ?>
                    <div id="map" style="height: 200px; width: 100%; border-radius: 8px; margin-top: 15px;"></div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const mapDiv = document.getElementById('map');
                            if (mapDiv) {
                                mapDiv.innerHTML = '<a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($profile_user['latitude'] . ',' . $profile_user['longitude']); ?>" target="_blank" style="display: flex; align-items: center; justify-content: center; height: 100%; width: 100%; background-color: #e9ebee; color: #365899; text-decoration: none; border-radius: 8px;"><i class="fas fa-map-marked-alt fa-2x"></i><span style="margin-left: 10px;">Voir sur la carte</span></a>';
                            }
                        });
                    </script>
                <?php endif; ?>
            <?php else: ?>
                <p><i class="fas fa-user"></i> Type de compte:
                    <?php echo htmlspecialchars(ucfirst($profile_user['type_user'])); ?></p>
                <p><i class="fas fa-map-marker-alt"></i> Localisation:
                    <?php echo htmlspecialchars($profile_user['localisation'] ?? 'Non spécifié'); ?></p>
                <p><i class="fas fa-calendar-alt"></i> Membre depuis:
                    <?php echo date('d/m/Y', strtotime($profile_user['date_inscription'])); ?></p>
            <?php endif; ?>
        </div>

        <div class="profile-main-content">
            <h2
                style="font-size:1.3rem; color:#3b82f6; display:flex; align-items:center; gap:8px; margin-bottom:1.2em;">
                <i class="fas fa-stream"></i> Profil public</h2>
            <?php if ($profile_user['type_user'] === 'entreprise'): ?>
                <div class="profile-tabs">
                    <div class="tab-item active" data-tab="publications"><i class="fas fa-bullhorn"></i> Publications</div>
                    <div class="tab-item" data-tab="reviews"><i class="fas fa-star"></i> Avis & Notes</div>
                    <div class="tab-item" data-tab="gallery"><i class="fas fa-images"></i> Galerie</div>
                </div>

                <div id="tab-publications" class="profile-tab-content active">
                    <?php if ($is_current_user_profile): ?>
                        <div class="create-post-card">
                            <div class="post-header">
                                <img src="<?php echo getUserProfilePhoto($_SESSION['user_photo_profil'] ?? null); ?>"
                                    alt="Profil" class="profile-pic">
                                <div class="post-info">
                                    <span class="author">Créer une nouvelle publication</span>
                                </div>
                            </div>
                            <form action="<?php echo BASE_URL; ?>entreprise/add_publication.php" method="GET">
                                <textarea class="post-input"
                                    placeholder="Écrivez quelque chose... (cliquez pour ajouter une annonce complète)"
                                    disabled></textarea>
                                <div class="post-actions-create">
                                    <button type="submit" class="button"><i class="fas fa-plus-circle"></i> Ajouter une
                                        annonce</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($profile_publications)): ?>
                        <?php foreach ($profile_publications as $pub):
                            $post_author_photo = getUserProfilePhoto($pub['user_photo_profil']);
                            $is_liked = hasLikedPublication($conn, $pub['id'], $_SESSION['user_id']);
                            $likes_count = countLikes($conn, $pub['id']);
                            $comments = getCommentsForPublication($conn, $pub['id']);
                            ?>
                            <div class="post-card" data-post-id="<?php echo $pub['id']; ?>">
                                <div class="post-header">
                                    <img src="<?php echo $post_author_photo; ?>" alt="Profil de l'entreprise" class="profile-pic">
                                    <div class="post-info">
                                        <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $pub['id_entreprise']; ?>"
                                            class="author">
                                            <?php echo htmlspecialchars($pub['entreprise_nom'] ?? $pub['user_nom']); ?>
                                        </a>
                                        <span class="time"><?php echo formatRelativeTime($pub['date_publication']); ?></span>
                                        <?php if (!empty($pub['localisation_pub'])): ?>
                                            <span class="location"><i class="fas fa-map-marker-alt"></i>
                                                <?php echo htmlspecialchars($pub['localisation_pub']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($pub['categorie'])): ?>
                                            <span class="category"><i class="fas fa-tag"></i>
                                                <?php echo htmlspecialchars($pub['categorie']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($is_current_user_profile): ?>
                                        <div class="post-options">
                                            <button class="options-btn"><i class="fas fa-ellipsis-h"></i></button>
                                            <div class="options-dropdown">
                                                <a
                                                    href="<?php echo BASE_URL; ?>entreprise/edit_publication.php?id=<?php echo $pub['id']; ?>"><i
                                                        class="fas fa-edit"></i> Modifier</a>
                                                <a href="<?php echo BASE_URL; ?>entreprise/delete_publication.php?id=<?php echo $pub['id']; ?>"
                                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette publication ?');"><i
                                                        class="fas fa-trash-alt"></i> Supprimer</a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="post-body">
                                    <h2 class="post-title"><?php echo htmlspecialchars($pub['titre']); ?></h2>
                                    <p class="post-description"><?php echo nl2br(htmlspecialchars($pub['description'])); ?></p>
                                    <?php if ($pub['image']): ?>
                                        <div class="post-media">
                                            <img src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($pub['image']); ?>"
                                                alt="Image de publication">
                                        </div>
                                    <?php elseif ($pub['video']): ?>
                                        <div class="post-media">
                                            <video controls
                                                src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($pub['video']); ?>">
                                                Votre navigateur ne supporte pas la balise vidéo.
                                            </video>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($pub['document_url']): ?>
                                        <div class="post-media">
                                            <a href="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($pub['document_url']); ?>"
                                                target="_blank" class="document-link">
                                                <i class="fas fa-file-alt"></i> Télécharger le document
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="post-stats">
                                    <span class="likes-count"><?php echo $likes_count; ?> Likes</span>
                                    <span class="comments-count"><?php echo count($comments); ?> Commentaires</span>
                                </div>
                                <div class="post-actions">
                                    <button class="action-btn like-btn <?php echo $is_liked ? 'liked' : ''; ?>"
                                        data-post-id="<?php echo $pub['id']; ?>">
                                        <i class="fas fa-thumbs-up"></i> Like
                                    </button>
                                    <button class="action-btn comment-btn" data-post-id="<?php echo $pub['id']; ?>">
                                        <i class="fas fa-comment"></i> Commenter
                                    </button>
                                    <button class="action-btn share-btn" data-post-id="<?php echo $pub['id']; ?>">
                                        <i class="fas fa-share-alt"></i> Partager
                                    </button>
                                </div>

                                <!-- Zone des commentaires -->
                                <div class="comments-section" style="display: none;">
                                    <div class="comment-list">
                                        <?php if (!empty($comments)): ?>
                                            <?php foreach ($comments as $comment): ?>
                                                <div class="comment-item">
                                                    <img src="<?php echo getUserProfilePhoto($comment['user_photo_profil']); ?>"
                                                        alt="Profil" class="comment-profile-pic">
                                                    <div class="comment-content">
                                                        <span
                                                            class="comment-author"><?php echo htmlspecialchars($comment['user_nom']); ?></span>
                                                        <p><?php echo nl2br(htmlspecialchars($comment['contenu'])); ?></p>
                                                        <span
                                                            class="comment-time"><?php echo formatRelativeTime($comment['date_commentaire']); ?></span>
                                                        <?php if ($comment['note']): ?>
                                                            <?php echo displayRatingStars($comment['note'], 'sm'); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="no-comments">Aucun commentaire pour le moment.</p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="add-comment-form">
                                        <form class="comment-form" data-post-id="<?php echo $pub['id']; ?>">
                                            <img src="<?php echo getUserProfilePhoto($_SESSION['user_photo_profil'] ?? null); ?>"
                                                alt="Profil" class="comment-profile-pic">
                                            <input type="text" name="comment_content" placeholder="Écrire un commentaire..."
                                                required>
                                            <select name="rating" class="rating-select">
                                                <option value="">Note (optionnel)</option>
                                                <option value="1">1 étoile</option>
                                                <option value="2">2 étoiles</option>
                                                <option value="3">3 étoiles</option>
                                                <option value="4">4 étoiles</option>
                                                <option value="5">5 étoiles</option>
                                            </select>
                                            <button type="submit" class="button button-small">Envoyer</button>
                                        </form>
                                    </div>
                                </div><!-- Fin de comments-section -->
                            </div><!-- Fin de post-card -->
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-posts">Cette entreprise n'a pas encore de publications.</p>
                    <?php endif; ?>
                </div><!-- Fin de tab-publications -->

                <div id="tab-reviews" class="profile-tab-content" style="display:none;">
                    <h3>Avis et Notes</h3>
                    <div class="add-review-form">
                        <form class="review-form" data-company-id="<?php echo $profile_user['id']; ?>">
                            <div class="form-group">
                                <label for="review_content">Votre avis :</label>
                                <textarea id="review_content" name="review_content"
                                    placeholder="Partagez votre expérience..." required></textarea>
                            </div>
                            <div class="form-group">
                                <label for="review_rating">Votre note :</label>
                                <select id="review_rating" name="review_rating" required>
                                    <option value="">Sélectionner une note</option>
                                    <option value="1">1 étoile</option>
                                    <option value="2">2 étoiles</option>
                                    <option value="3">3 étoiles</option>
                                    <option value="4">4 étoiles</option>
                                    <option value="5">5 étoiles</option>
                                </select>
                            </div>
                            <button type="submit" class="button">Soumettre l'avis</button>
                        </form>
                    </div>
                    <hr>
                    <div class="reviews-list">
                        <?php if (!empty($company_reviews)): ?>
                            <?php foreach ($company_reviews as $review): ?>
                                <div class="review-item">
                                    <img src="<?php echo getUserProfilePhoto($review['user_photo_profil']); ?>" alt="Profil"
                                        class="comment-profile-pic">
                                    <div class="review-content">
                                        <span class="review-author"><?php echo htmlspecialchars($review['user_nom']); ?></span>
                                        <?php echo displayRatingStars($review['note']); ?>
                                        <p><?php echo nl2br(htmlspecialchars($review['contenu'])); ?></p>
                                        <span
                                            class="review-time"><?php echo formatRelativeTime($review['date_commentaire']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-reviews">Aucun avis pour le moment.</p>
                        <?php endif; ?>
                    </div>
                </div><!-- Fin de tab-reviews -->

                <div id="tab-gallery" class="profile-tab-content" style="display:none;">
                    <h3>Galerie Multimédia</h3>
                    <p>Contenu de la galerie ici. Actuellement, cette section est statique. Les uploads de la galerie seront
                        implémentés.</p>
                    <div class="gallery-grid">
                        <!-- Exemple d'éléments de galerie -->
                        <?php
                        $stmt_galerie = $conn->prepare("SELECT url_fichier, type_fichier FROM galerie_multimedia WHERE id_entreprise_user = ? ORDER BY date_ajout DESC");
                        $stmt_galerie->execute([$profile_id]);
                        $galerie_items = $stmt_galerie->fetchAll();

                        if (!empty($galerie_items)) {
                            foreach ($galerie_items as $item) {
                                echo '<div class="gallery-item">';
                                if ($item['type_fichier'] == 'image') {
                                    echo '<img src="' . BASE_URL . 'uploads/' . htmlspecialchars($item['url_fichier']) . '" alt="Image de la galerie">';
                                } elseif ($item['type_fichier'] == 'video') {
                                    echo '<video controls src="' . BASE_URL . 'uploads/' . htmlspecialchars($item['url_fichier']) . '">Votre navigateur ne supporte pas la vidéo.</video>';
                                }
                                echo '</div>';
                            }
                        } else {
                            echo '<p class="no-gallery-items">Aucun élément dans la galerie pour le moment.</p>';
                        }
                        ?>
                    </div>
                </div><!-- Fin de tab-gallery -->

            <?php else: // Si c'est un particulier, il n'a pas de publications d'entreprise ni d'avis sur lui-même comme entreprise ?>
                <div class="info-message-card">
                    <p>Cet utilisateur n'est pas une entreprise et ne publie pas d'annonces.</p>
                </div>
            <?php endif; ?>
        </div>
    </div><!-- Fin de profile-content-section -->
</div><!-- Fin de main-content -->

<?php require_once 'includes/footer.php'; ?>