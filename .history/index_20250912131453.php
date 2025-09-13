<?php
$page_title = "Fil d'actualité";
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirige si non connecté
checkLogin();

// Récupérer les publications avec filtres et tri
$filter_location = $_GET['location'] ?? 'Toutes';
$sort_by = $_GET['sort'] ?? 'date'; // 'date' ou 'popularite'
$publications = getPublications($conn, $filter_location, $sort_by);

// Récupérer la liste des localisations uniques pour le filtre
$stmt_loc = $conn->query("SELECT DISTINCT localisation FROM users WHERE localisation IS NOT NULL AND localisation != '' UNION SELECT DISTINCT localisation_pub FROM publications WHERE localisation_pub IS NOT NULL AND localisation_pub != '' ORDER BY localisation ASC");
$locations = $stmt_loc->fetchAll(PDO::FETCH_COLUMN);

// Récupérer des utilisateurs pour les suggestions (colonne de droite)
$suggested_users = getUsersForSuggestions($conn, $_SESSION['user_id'] ?? 0, 5);

require_once 'includes/header.php';
?>

<div class="main-content">
    <div class="left-sidebar">
        <!-- Contenu de la barre latérale gauche (similaire à Facebook) -->
        <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $_SESSION['user_id']; ?>"
            class="menu-item profile-link-sidebar">
            <img src="<?php echo getUserProfilePhoto($_SESSION['user_photo_profil'] ?? null); ?>" alt="Profil"
                class="profile-pic-small">
            <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </a>
        <div class="sidebar-section">
            <a href="<?php echo BASE_URL; ?>index.php" class="menu-item"><i class="fas fa-newspaper"></i> Fil
                d'actualité</a>
            <a href="<?php echo BASE_URL; ?>chat/" class="menu-item"><i class="fas fa-comments"></i> Messagerie</a>
            <a href="<?php echo BASE_URL; ?>entreprise/explore.php" class="menu-item"><i class="fas fa-building"></i>
                Explorer entreprises</a>
            <a href="<?php echo BASE_URL; ?>favoris.php" class="menu-item"><i class="fas fa-bookmark"></i> Vos
                Favoris</a>
            <a href="<?php echo BASE_URL; ?>evenement.php" class="menu-item"><i class="fas fa-calendar-alt"></i>
                Événements</a>
            <?php if (isEntreprise()): ?>
                <a href="<?php echo BASE_URL; ?>entreprise/dashboard.php" class="menu-item"><i class="fas fa-briefcase"></i>
                    Espace Entreprise</a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
                <a href="<?php echo BASE_URL; ?>admin/dashboard.php" class="menu-item"><i class="fas fa-user-shield"></i>
                    Panneau Admin</a>
            <?php endif; ?>
        </div>
        <div class="sidebar-section filter-sort-section">
            <h3><i class="fas fa-filter"></i> Filtres & Tri</h3>
            <form action="index.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="location-filter">Localisation :</label>
                    <select name="location" id="location-filter" class="form-control">
                        <option value="Toutes" <?php echo ($filter_location == 'Toutes') ? 'selected' : ''; ?>>Toutes
                        </option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($filter_location == $loc) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sort-by">Trier par :</label>
                    <select name="sort" id="sort-by" class="form-control">
                        <option value="date" <?php echo ($sort_by == 'date') ? 'selected' : ''; ?>>Date (le plus récent)
                        </option>
                        <option value="popularite" <?php echo ($sort_by == 'popularite') ? 'selected' : ''; ?>>Popularité
                        </option>
                    </select>
                </div>
                <button type="submit" class="button button-small">Appliquer</button>
            </form>
        </div>
    </div>

    <div class="main-feed">
        <!-- Section pour créer une publication (simplifiée) -->
        <?php if (isEntreprise()): ?>
            <div class="create-post-card">
                <div class="post-header">
                    <img src="<?php echo getUserProfilePhoto($_SESSION['user_photo_profil'] ?? null); ?>" alt="Profil"
                        class="profile-pic">
                    <div class="post-info">
                        <span class="author"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <span class="time">Quoi de neuf ?</span>
                    </div>
                </div>
                <form action="<?php echo BASE_URL; ?>entreprise/add_publication.php" method="GET">
                    <textarea class="post-input" placeholder="Créer une nouvelle publication..." disabled></textarea>
                    <div class="post-actions-create">
                        <button type="submit" class="button" <?php echo (isEntreprise() ? '' : 'disabled title="Seules les entreprises peuvent publier"'); ?>>
                            <i class="fas fa-plus-circle"></i> Publier une annonce
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        <?php if (!empty($publications)): ?>
            <?php foreach ($publications as $pub):
                $post_author_photo = getUserProfilePhoto($pub['user_photo_profil']);
                $is_liked = hasLikedPublication($conn, $pub['id'], $_SESSION['user_id']);
                $likes_count = countLikes($conn, $pub['id']);
                $comments = getCommentsForPublication($conn, $pub['id']);
                ?>
                <div class="post-card" data-post-id="<?php echo $pub['id']; ?>">
                    <div class="post-header">
                        <img src="<?php echo $post_author_photo; ?>" alt="Profil de l'entreprise" class="profile-pic">
                        <div class="post-info">
                            <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $pub['id_entreprise']; ?>" class="author">
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
                                <video controls src="<?php echo BASE_URL . 'uploads/' . htmlspecialchars($pub['video']); ?>">
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
                                        <img src="<?php echo getUserProfilePhoto($comment['user_photo_profil']); ?>" alt="Profil"
                                            class="comment-profile-pic">
                                        <div class="comment-content">
                                            <span class="comment-author"><?php echo htmlspecialchars($comment['user_nom']); ?></span>
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
                                <input type="text" name="comment_content" placeholder="Écrire un commentaire..." required>
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
            <p class="no-posts">Aucune publication trouvée pour le moment. Soyez le premier à publier !</p>
        <?php endif; ?>
    </div><!-- Fin de main-feed -->

    <div class="right-sidebar">
        <!-- Contenu de la barre latérale droite (suggestions, publicités, etc.) -->
        <div class="sidebar-section" style="overflow:hidden;">
            <h3><i class="fas fa-user-plus"></i> Suggestions d'utilisateurs</h3>
            <?php if (!empty($suggested_users)): ?>
                <?php foreach ($suggested_users as $s_user): ?>
                    <div class="suggestion-item">
                        <img src="<?php echo getUserProfilePhoto($s_user['photo_profil']); ?>" alt="Profil"
                            class="profile-pic-small">
                        <div class="suggestion-info">
                            <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $s_user['id']; ?>"
                                class="suggestion-name"><?php echo htmlspecialchars($s_user['nom']); ?></a>
                            <span class="suggestion-type"><?php echo htmlspecialchars(ucfirst($s_user['type_user'])); ?></span>
                            <span class="suggestion-location"><?php echo htmlspecialchars($s_user['localisation']); ?></span>
                        </div>
                        <button class="button button-small <?php isFollow($conn,$_SESSION['user_Id'],,$s_user['']) ?> follow-btn"
                            data-user-id="<?php echo $s_user['id']; ?>">
                            Suivre
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Aucune suggestion pour le moment.</p>
            <?php endif; ?>
        </div>

        <div class="sidebar-section">
            <h3><i class="fas fa-calendar-star"></i> Événements à venir</h3>
            <p>Aucun événement programmé.</p>
            <button class="button button-small secondary">Créer un événement</button>
        </div>
        <div class="sidebar-section">
            <h3><i class="fas fa-ad"></i> Publicité</h3>
            <p>Découvrez les meilleurs offres locales !</p>
            <img src="<?php echo BASE_URL; ?>assets/images/ad_placeholder.png" alt="Publicité"
                style="max-width: 100%; border-radius: 8px;">
        </div>
    </div>
</div><!-- Fin de main-content -->

<?php require_once 'includes/footer.php'; ?>
<script>
    // Suivre/désuivre un utilisateur (AJAX)
    $(document).on('click', '.follow-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var userId = $btn.data('user-id');
        $.post('ajax_handler.php', { action: 'toggle_follow', user_id: userId }, function (response) {
            if (response.success) {
                if (response.action === 'followed') {
                    $btn.text('Abonné').removeClass('button-small').addClass('button-secondary');
                } else if (response.action === 'unfollowed') {
                    $btn.text('Suivre').removeClass('button-secondary').addClass('button-small');
                }
            } else {
                alert('Erreur : ' + (response.message || 'Impossible de suivre cet utilisateur.'));
            }
        }, 'json');
    });
</script>


<script>
    // Like AJAX
    $(document).on('click', '.like-btn', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var postId = $btn.data('post-id');
        $.post('ajax_handler.php', { action: 'toggle_like', post_id: postId }, function (response) {
            if (response.success) {
                $btn.toggleClass('liked', response.action === 'liked');
                $btn.closest('.post-card').find('.likes-count').text((response.data && typeof response.data.likes_count !== 'undefined' ?? response.data.likes_count) + ' Likes');
                alert(response.data.likes_count);
            }
        }, 'json');
    });

    // Afficher/masquer les commentaires
    $(document).on('click', '.comment-btn', function (e) {
        e.preventDefault();
        var $card = $(this).closest('.post-card');
        $card.find('.comments-section').slideToggle(200);
    });

    // Soumission d'un commentaire (affichage dynamique)
    $(document).on('submit', '.comment-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var postId = $form.data('post-id');
        var content = $form.find('input[name="comment_content"]').val();
        var rating = $form.find('select[name="rating"]').val();
        var $commentList = $form.closest('.comments-section').find('.comment-list');
        $.post('ajax_handler.php', {
            action: 'add_comment',
            post_id: postId,
            comment_content: content,
            rating: rating
        }, function (response) {
            if (response.success && response.data && response.data.new_comment) {
                var c = response.data.new_comment;
                var stars = '';
                if (c.note && c.note > 0) {
                    for (var i = 1; i <= 5; i++) {
                        if (i <= c.note) stars += '<i class="fas fa-star filled" style="color:#f7b928;font-size:0.9em;"></i>';
                        else stars += '<i class="far fa-star" style="color:#dadde1;font-size:0.9em;"></i>';
                    }
                }
                var html = '<div class="comment-item">' +
                    '<img src="' + c.user_photo_profil_full_path + '" alt="Profil" class="comment-profile-pic">' +
                    '<div class="comment-content">' +
                    '<span class="comment-author">' + c.user_nom + '</span>' +
                    '<p>' + c.contenu.replace(/\n/g, '<br>') + '</p>' +
                    '<span class="comment-time">' + c.date_commentaire_formatted + '</span>' +
                    (stars ? stars : '') +
                    '</div></div>';
                $commentList.append(html);
                $form[0].reset();
                $form.find('input[name="comment_content"]').focus();
                // Met à jour le compteur de commentaires
                var $count = $form.closest('.post-card').find('.comments-count');
                var n = $commentList.find('.comment-item').length;
                $count.text(n + ' Commentaires');
                // Retire le message "Aucun commentaire" si présent
                $commentList.find('.no-comments').remove();
            } else {
                alert('Erreur lors de l\'ajout du commentaire.');
            }
        }, 'json');
    });
</script>