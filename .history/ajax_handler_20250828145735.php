
        case 'save_location':
            $target_user_id = $_POST['user_id'] ?? null;
            $location = trim($_POST['location'] ?? '');
            if (!$target_user_id || !$location) {
                $response['message'] = "ID d'utilisateur ou localisation manquant.";
                break;
            }
            // Table: saved_locations (id, user_id, target_user_id, location, date_saved)
            $stmt = $conn->prepare("SELECT COUNT(*) FROM saved_locations WHERE user_id = ? AND target_user_id = ? AND location = ?");
            $stmt->execute([$user_id, $target_user_id, $location]);
            if ($stmt->fetchColumn() > 0) {
                $response['success'] = true;
                $response['message'] = 'Déjà sauvegardé.';
                break;
            }
            $stmt = $conn->prepare("INSERT INTO saved_locations (user_id, target_user_id, location, date_saved) VALUES (?, ?, ?, NOW())");
            if ($stmt->execute([$user_id, $target_user_id, $location])) {
                $response['success'] = true;
                $response['message'] = 'Position sauvegardée !';
            } else {
                $response['message'] = 'Erreur lors de la sauvegarde.';
            }
            break;
<?php
header('Content-Type: application/json');
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => '', 'action' => '', 'data' => []];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Non authentifié.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'toggle_like':
            $publication_id = $_POST['post_id'] ?? null;
            if (!$publication_id) {
                $response['message'] = 'ID de publication manquant.';
                break;
            }
            $result = toggleLikeOrFavorite($conn, $user_id, $publication_id, null);
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            $response['action'] = $result['action']; // 'liked' or 'unliked'
            $response['data']['likes_count'] = countLikes($conn, $publication_id);

            // Ajouter une notification à l'auteur de la publication si c'est un like
            if ($result['success'] && $result['action'] === 'liked') {
                $stmt_pub_author = $conn->prepare("SELECT id_entreprise FROM publications WHERE id = ?");
                $stmt_pub_author->execute([$publication_id]);
                $author_id = $stmt_pub_author->fetchColumn();
                addNotification($conn, $author_id, 'new_like', $publication_id, $_SESSION['user_name'] . " a aimé votre publication.");
            }
            break;

        case 'add_comment':
            $publication_id = $_POST['post_id'] ?? null;
            $company_profile_id = $_POST['company_id'] ?? null; // Pour les avis sur profil entreprise
            $comment_content = trim($_POST['comment_content'] ?? '');
            $rating = $_POST['rating'] ?? null;

            if (empty($comment_content) && empty($rating)) {
                $response['message'] = 'Contenu du commentaire ou note vide.';
                break;
            }

            if ($publication_id) {
                // Commentaire sur une publication
                $add_result = addCommentAndRating($conn, $user_id, $comment_content, $publication_id, null, $rating);
                if ($add_result) {
                    $response['success'] = true;
                    $response['message'] = 'Commentaire ajouté.';
                    $response['action'] = 'comment_added';
                    // Récupérer et renvoyer le nouveau commentaire pour l'affichage en temps réel
                    $stmt_new_comment = $conn->prepare("SELECT c.*, u.nom as user_nom, u.photo_profil as user_photo_profil
                                                    FROM commentaires c JOIN users u ON c.id_user = u.id
                                                    WHERE c.id_user = ? AND c.id_publication = ? ORDER BY c.id DESC LIMIT 1");
                    $stmt_new_comment->execute([$user_id, $publication_id]);
                    $new_comment = $stmt_new_comment->fetch(PDO::FETCH_ASSOC);
                    if ($new_comment) {
                        $new_comment['date_commentaire_formatted'] = formatRelativeTime($new_comment['date_commentaire']);
                        $new_comment['user_photo_profil_full_path'] = getUserProfilePhoto($new_comment['user_photo_profil']);
                        $response['data']['new_comment'] = $new_comment;
                    }

                    // Ajouter une notification à l'auteur de la publication
                    $stmt_pub_author = $conn->prepare("SELECT id_entreprise FROM publications WHERE id = ?");
                    $stmt_pub_author->execute([$publication_id]);
                    $author_id = $stmt_pub_author->fetchColumn();
                    addNotification($conn, $author_id, 'new_comment', $publication_id, $_SESSION['user_name'] . " a commenté votre publication.");
                } else {
                    $response['message'] = 'Erreur lors de l\'ajout du commentaire.';
                }
            } elseif ($company_profile_id) {
                // Avis/Note sur un profil entreprise
                $add_result = addCommentAndRating($conn, $user_id, $comment_content, null, $company_profile_id, $rating);
                if ($add_result) {
                    $response['success'] = true;
                    $response['message'] = 'Avis ajouté.';
                    $response['action'] = 'review_added';
                    // Recharger la note moyenne
                    $response['data']['average_rating'] = getAverageRating($conn, $company_profile_id);
                    // Ajouter une notification à l'entreprise
                    addNotification($conn, $company_profile_id, 'new_review', $user_id, $_SESSION['user_name'] . " a laissé un avis sur votre profil.");
                } else {
                    $response['message'] = 'Erreur lors de l\'ajout de l\'avis.';
                }
            } else {
                $response['message'] = 'Cible du commentaire non spécifiée.';
            }
            break;

        case 'toggle_follow':
            $target_user_id = $_POST['user_id'] ?? null;
            if (!$target_user_id || $target_user_id == $user_id) {
                $response['message'] = "ID d'utilisateur cible manquant ou invalide.";
                break;
            }
            // Vérifier si déjà suivi
            $stmt = $conn->prepare("SELECT COUNT(*) FROM followers WHERE follower_id = ? AND followed_id = ?");
            $stmt->execute([$user_id, $target_user_id]);
            $is_following = $stmt->fetchColumn() > 0;
            if ($is_following) {
                // Désuivre
                $stmt = $conn->prepare("DELETE FROM followers WHERE follower_id = ? AND followed_id = ?");
                $stmt->execute([$user_id, $target_user_id]);
                $response['success'] = true;
                $response['action'] = 'unfollowed';
                $response['message'] = "Utilisateur désuivi.";
            } else {
                // Suivre
                $stmt = $conn->prepare("INSERT INTO followers (follower_id, followed_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $target_user_id]);
                $response['success'] = true;
                $response['action'] = 'followed';
                $response['message'] = "Utilisateur suivi.";
                // Optionnel : notification
                addNotification($conn, $target_user_id, 'new_follower', $user_id, $_SESSION['user_name'] . " vous suit maintenant.");
            }
            break;

        case 'toggle_favorite':
            $company_id = $_POST['company_id'] ?? null;
            if (!$company_id) {
                $response['message'] = 'ID d\'entreprise manquant.';
                break;
            }
            $result = toggleLikeOrFavorite($conn, $user_id, null, $company_id);
            $response['success'] = $result['success'];
            $response['message'] = $result['message'];
            $response['action'] = $result['action']; // 'followed' or 'unfollowed'
            // Ajouter une notification à l'entreprise si elle est mise en favori
            if ($result['success'] && $result['action'] === 'followed') {
                addNotification($conn, $company_id, 'new_follower', $user_id, $_SESSION['user_name'] . " vous a ajouté à ses favoris.");
            }
            break;

        case 'report_content':
            $type_contenu = $_POST['type_contenu'] ?? ''; // 'publication', 'commentaire', 'profil_user', 'profil_entreprise'
            $id_contenu_signale = $_POST['id_contenu_signale'] ?? null;
            $raison = trim($_POST['raison'] ?? '');

            if (empty($type_contenu) || empty($id_contenu_signale) || empty($raison)) {
                $response['message'] = 'Informations de signalement incomplètes.';
                break;
            }

            // TODO: Ajouter une vérification plus robuste que l'ID existe vraiment dans la bonne table.
            $stmt_report = $conn->prepare("INSERT INTO signalements (id_user_signaleur, type_contenu, id_contenu_signale, raison) VALUES (?, ?, ?, ?)");
            if ($stmt_report->execute([$user_id, $type_contenu, $id_contenu_signale, $raison])) {
                $response['success'] = true;
                $response['message'] = 'Contenu signalé avec succès. Un administrateur va examiner votre signalement.';
                // Optionnel: Notifier les admins d'un nouveau signalement
                // addNotification($conn, $admin_id, 'new_report', $signalement_id, "Nouveau signalement en attente d'examen.");
            } else {
                $response['message'] = 'Erreur lors du signalement du contenu.';
            }
            break;


        default:
            $response['message'] = 'Action non reconnue.';
            break;
    }
} catch (PDOException $e) {
    $response['message'] = 'Erreur de base de données : ' . $e->getMessage();
}

echo json_encode($response);
?>