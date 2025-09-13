<?php
// Fichier de fonctions utilitaires pour le projet

/**
 * Gère l'upload d'un fichier image ou vidéo.
 * @param array $file_input Le tableau $_FILES['nom_du_champ']
 * @param string $target_sub_dir Le sous-dossier de 'uploads/' où stocker le fichier (ex: 'profiles', 'publications')
 * @param array $allowed_types Les types MIME autorisés (ex: ['image/jpeg', 'image/png'])
 * @param int $max_size_mb La taille maximale en Mo
 * @return string|false Le chemin relatif du fichier généré (ex: 'profiles/nom_unique.jpg') si succès, false si échec.
 */
function uploadFile($file_input, $target_sub_dir, $allowed_types, $max_size_mb)
{
    // Vérifier si un fichier a été réellement uploadé sans erreur PHP
    if (!isset($file_input) || $file_input['error'] !== UPLOAD_ERR_OK) {
        // Retourne false si pas de fichier ou erreur d'upload système
        return false;
    }

    $upload_base_dir = __DIR__ . '/../uploads/';
    $target_full_dir = $upload_base_dir . $target_sub_dir;

    // Créer le dossier cible s'il n'existe pas
    if (!is_dir($target_full_dir)) {
        if (!mkdir($target_full_dir, 0777, true)) {
            error_log("Failed to create upload directory: " . $target_full_dir);
            return false;
        }
    }

    // Vérifier le type de fichier
    if (!in_array($file_input['type'], $allowed_types)) {
        error_log("Invalid file type: " . $file_input['type']);
        return false;
    }

    // Vérifier la taille du fichier
    if ($file_input['size'] > ($max_size_mb * 1024 * 1024)) {
        error_log("File too large: " . $file_input['size'] . " bytes.");
        return false;
    }

    // Générer un nom de fichier unique pour éviter les collisions
    $file_extension = pathinfo($file_input['name'], PATHINFO_EXTENSION);
    $unique_filename = uniqid('upload_', true) . '.' . $file_extension;
    $target_file_path = $target_full_dir . '/' . $unique_filename;

    // Déplacer le fichier uploadé du répertoire temporaire vers le répertoire cible
    if (move_uploaded_file($file_input['tmp_name'], $target_file_path)) {
        return $target_sub_dir . '/' . $unique_filename; // Retourne le chemin relatif pour la BDD
    } else {
        error_log("Failed to move uploaded file to: " . $target_file_path);
        return false;
    }
}

/**
 * Supprime un fichier uploadé du système de fichiers.
 * @param string $file_path Le chemin relatif du fichier (ex: 'profiles/mon_image.jpg')
 * @return bool True si le fichier a été supprimé ou n'existait pas, false en cas d'erreur.
 */
function deleteUploadedFile($file_path)
{
    if (empty($file_path)) {
        return true; // Rien à supprimer
    }
    $full_path = __DIR__ . '/../uploads/' . $file_path;
    if (file_exists($full_path)) {
        return unlink($full_path);
    }
    return true; // Fichier n'existe pas, donc déjà "supprimé"
}


/**
 * Récupère les publications avec les informations de l'entreprise associée.
 * @param PDO $conn L'objet de connexion PDO.
 * @param string|null $filter_location La localisation pour filtrer les publications.
 * @param string|null $sort_by Critère de tri ('date', 'popularite').
 * @return array Tableau associatif des publications.
 */
function getPublications($conn, $filter_location = null, $sort_by = 'date')
{
    $params = [];
    $base_select = "SELECT p.*, u.nom as user_nom, u.photo_profil as user_photo_profil, u.localisation as user_localisation, e.nom as entreprise_nom, e.secteur";
    $base_from = " FROM publications p
        JOIN users u ON p.id_entreprise = u.id
        LEFT JOIN entreprises e ON u.id = e.id_user";
    $where = "";
    if ($filter_location && $filter_location !== 'Toutes') {
        $where = " WHERE (u.localisation = :localisation OR p.localisation_pub = :localisation)";
        $params[':localisation'] = $filter_location;
    }

    if ($sort_by === 'popularite') {
        // On ajoute le LEFT JOIN favoris AVANT le WHERE
        $sql = $base_select . $base_from . " LEFT JOIN favoris f ON p.id = f.id_publication" . $where .
            " GROUP BY p.id ORDER BY COUNT(f.id) DESC, p.date_publication DESC";
    } else {
        $sql = $base_select . $base_from . $where . " ORDER BY p.date_publication DESC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



/**
 * Récupère les messages échangés entre deux utilisateurs.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $user_id L'ID du premier utilisateur.
 * @param int $dest_id L'ID du second utilisateur.
 * @return array Tableau associatif des messages.
 */
function getMessages($conn, $user_id, $dest_id)
{
    $stmt = $conn->prepare("SELECT m.*, u_exp.nom as expediteur_nom, u_exp.photo_profil as expediteur_photo_profil
                            FROM messages m
                            JOIN users u_exp ON m.id_expediteur = u_exp.id
                            WHERE (m.id_expediteur = ? AND m.id_destinataire = ?)
                            OR (m.id_expediteur = ? AND m.id_destinataire = ?)
                            ORDER BY m.date_envoi ASC");
    $stmt->execute([$user_id, $dest_id, $dest_id, $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ajoute une nouvelle notification.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_user L'ID de l'utilisateur recevant la notification.
 * @param string $type Le type de notification (ex: 'new_message', 'new_like', 'new_comment', 'followed_company_pub', 'report_status').
 * @param int|null $id_reference L'ID de l'élément lié (message, publication, user qui a interagi).
 * @param string|null $message_custom Message personnalisé pour certaines notifications.
 * @return bool Vrai si la notification a été ajoutée, faux sinon.
 */
function addNotification($conn, $id_user, $type, $id_reference = null, $message_custom = null)
{
    // Éviter de notifier l'utilisateur s'il est l'expéditeur de l'action qui déclenche la notification
    if (isset($_SESSION['user_id']) && $id_user == $_SESSION['user_id']) {
        return true; // Ne rien faire si c'est une auto-notification
    }
    try {
        $stmt = $conn->prepare("INSERT INTO notifications(id_user, type, id_reference, message_custom) VALUES(?, ?, ?, ?)");
        $stmt->execute([$id_user, $type, $id_reference, $message_custom]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'une notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Ajoute un commentaire et/ou une note à une publication ou un profil entreprise.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_user L'ID de l'utilisateur qui commente.
 * @param string $contenu Le contenu du commentaire.
 * @param int|null $id_publication L'ID de la publication (si commentaire sur publication).
 * @param int|null $id_entreprise_profile L'ID du profil entreprise (si commentaire sur profil).
 * @param int|null $note La note de 1 à 5 (optionnel).
 * @return bool Vrai si le commentaire a été ajouté, faux sinon.
 */
function addCommentAndRating($conn, $id_user, $contenu, $id_publication = null, $id_entreprise_profile = null, $note = null)
{
    try {
        if (empty($contenu) && empty($note)) {
            return false; // Rien à ajouter
        }
        $stmt = $conn->prepare("INSERT INTO commentaires(id_user, contenu, id_publication, id_entreprise_profile, note) VALUES(?, ?, ?, ?, ?)");
        $stmt->execute([$id_user, $contenu, $id_publication, $id_entreprise_profile, $note]);
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'ajout d'un commentaire: " . $e->getMessage());
        return false;
    }
}

/**
 * Ajoute ou retire un "like" ou "favori" sur une publication ou un profil entreprise.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_user L'ID de l'utilisateur.
 * @param int|null $id_publication L'ID de la publication (si like).
 * @param int|null $id_entreprise_profile L'ID du profil entreprise (si favori).
 * @return array ['success' => bool, 'action' => 'liked'|'unliked'|'followed'|'unfollowed', 'message' => string]
 */
function toggleLikeOrFavorite($conn, $id_user, $id_publication = null, $id_entreprise_profile = null)
{
    try {
        if ($id_publication) {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM favoris WHERE id_publication = ? AND id_user = ?");
            $stmt_check->execute([$id_publication, $id_user]);
            if ($stmt_check->fetchColumn() > 0) {
                $stmt = $conn->prepare("DELETE FROM favoris WHERE id_publication = ? AND id_user = ?");
                $stmt->execute([$id_publication, $id_user]);
                return ['success' => true, 'action' => 'unliked', 'message' => 'Like retiré.'];
            } else {
                $stmt = $conn->prepare("INSERT INTO favoris(id_publication, id_user) VALUES(?, ?)");
                $stmt->execute([$id_publication, $id_user]);
                return ['success' => true, 'action' => 'liked', 'message' => 'Like ajouté.'];
            }
        } elseif ($id_entreprise_profile) {
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM favoris WHERE id_entreprise_profile = ? AND id_user = ?");
            $stmt_check->execute([$id_entreprise_profile, $id_user]);
            if ($stmt_check->fetchColumn() > 0) {
                $stmt = $conn->prepare("DELETE FROM favoris WHERE id_entreprise_profile = ? AND id_user = ?");
                $stmt->execute([$id_entreprise_profile, $id_user]);
                return ['success' => true, 'action' => 'unfollowed', 'message' => 'Entreprise retirée des favoris.'];
            } else {
                $stmt = $conn->prepare("INSERT INTO favoris(id_entreprise_profile, id_user) VALUES(?, ?)");
                $stmt->execute([$id_entreprise_profile, $id_user]);
                return ['success' => true, 'action' => 'followed', 'message' => 'Entreprise ajoutée aux favoris.'];
            }
        }
        return ['success' => false, 'message' => 'Type de favori/like non spécifié.'];
    } catch (PDOException $e) {
        error_log("Erreur lors de toggleLikeOrFavorite: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur de base de données.'];
    }
}

/**
 * Vérifie si l'utilisateur a liké une publication.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_publication L'ID de la publication.
 * @param int $id_user L'ID de l'utilisateur.
 * @return bool Vrai si liké, faux sinon.
 */
function hasLikedPublication($conn, $id_publication, $id_user)
{
    if (!$id_user)
        return false; // Utilisateur non connecté ne peut pas avoir liké
    $stmt = $conn->prepare("SELECT COUNT(*) FROM favoris WHERE id_publication = ? AND id_user = ?");
    $stmt->execute([$id_publication, $id_user]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Vérifie si l'utilisateur a mis en favori un profil entreprise.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_entreprise_profile L'ID du profil entreprise (qui est aussi un user_id).
 * @param int $id_user L'ID de l'utilisateur.
 * @return bool Vrai si en favori, faux sinon.
 */
function hasFavoritedCompany($conn, $id_entreprise_profile, $id_user)
{
    if (!$id_user)
        return false; // Utilisateur non connecté ne peut pas avoir favorisé
    $stmt = $conn->prepare("SELECT COUNT(*) FROM favoris WHERE id_entreprise_profile = ? AND id_user = ?");
    $stmt->execute([$id_entreprise_profile, $id_user]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Compte le nombre de likes pour une publication donnée.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_publication L'ID de la publication.
 * @return int Le nombre de likes.
 */
function countLikes($conn, $id_publication)
{
    $stmt = $conn->prepare("SELECT COUNT(*) FROM favoris WHERE id_publication = ?");
    $stmt->execute([$id_publication]);
    return $stmt->fetchColumn();
}

/**
 * Récupère les commentaires pour une publication, avec le nom et photo de profil de l'auteur.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_publication L'ID de la publication.
 * @return array Tableau associatif des commentaires.
 */
function getCommentsForPublication($conn, $id_publication)
{
    $stmt = $conn->prepare("SELECT c.*, u.nom as user_nom, u.photo_profil as user_photo_profil
                            FROM commentaires c
                            JOIN users u ON c.id_user = u.id
                            WHERE id_publication = ?
                            ORDER BY date_commentaire ASC");
    $stmt->execute([$id_publication]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les commentaires et notes pour un profil entreprise.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_entreprise_profile L'ID du profil entreprise (qui est aussi un user_id).
 * @return array Tableau associatif des commentaires/notes.
 */
function getCompanyReviews($conn, $id_entreprise_profile)
{
    $stmt = $conn->prepare("SELECT c.*, u.nom as user_nom, u.photo_profil as user_photo_profil
                            FROM commentaires c
                            JOIN users u ON c.id_user = u.id
                            WHERE id_entreprise_profile = ?
                            ORDER BY date_commentaire DESC");
    $stmt->execute([$id_entreprise_profile]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcule la note moyenne d'un profil entreprise.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_entreprise_profile L'ID du profil entreprise (qui est aussi un user_id).
 * @return float|null La note moyenne ou null si aucune note.
 */
function getAverageRating($conn, $id_entreprise_profile)
{
    $stmt = $conn->prepare("SELECT AVG(note) FROM commentaires WHERE id_entreprise_profile = ? AND note IS NOT NULL");
    $stmt->execute([$id_entreprise_profile]);
    $avg = $stmt->fetchColumn();
    return $avg ? round($avg, 1) : null;
}

/**
 * Retourne le chemin complet de l'image de profil par défaut si l'utilisateur n'en a pas.
 * @param string|null $photo_profil_path Le chemin de la photo de profil depuis la BDD.
 * @return string Le chemin complet de l'image (par défaut ou uploadée).
 */
function getUserProfilePhoto($photo_profil_path)
{
    // __DIR__ . '/../' remonte d'un niveau (de includes/ vers PlateformeTourisme/)
    if ($photo_profil_path && file_exists(__DIR__ . '/../uploads/' . $photo_profil_path)) {
        return BASE_URL . 'uploads/' . $photo_profil_path;
    }
    // Assurez-vous que cette image existe dans assets/images/
    return BASE_URL . 'assets/images/default_profile.png';
}

/**
 * Retourne le chemin complet de la photo de couverture par défaut si l'entreprise n'en a pas.
 * @param string|null $cover_photo_path Le chemin de la photo de couverture depuis la BDD.
 * @return string Le chemin complet de l'image (par défaut ou uploadée).
 */
function getCompanyCoverPhoto($cover_photo_path)
{
    if ($cover_photo_path && file_exists(__DIR__ . '/../uploads/' . $cover_photo_path)) {
        return BASE_URL . 'uploads/' . $cover_photo_path;
    }
    return BASE_URL . 'assets/images/default_cover.jpg';
}


/**
 * Formate une date en un format lisible.
 * @param string $datetime La date et heure (format YYYY-MM-DD HH:MM:SS).
 * @return string La date formatée.
 */
function formatRelativeTime($datetime)
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'il y a ' . $diff . ' sec';
    } elseif ($diff < 3600) {
        return 'il y a ' . floor($diff / 60) . ' min';
    } elseif ($diff < 86400) {
        return 'il y a ' . floor($diff / 3600) . ' h';
    } elseif ($diff < 2592000) { // 30 jours
        return 'il y a ' . floor($diff / 86400) . ' j';
    } else {
        return date('d/m/Y', $timestamp);
    }
}


/**
 * Affiche les étoiles de notation.
 * @param float $rating La note à afficher.
 * @param string $size Taille des étoiles (ex: 'lg', 'sm').
 * @return string HTML des étoiles.
 */
function displayRatingStars($rating, $size = '')
{
    $html = '<div class="rating-stars ' . htmlspecialchars($size) . '">';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($rating)) {
            $html .= '<i class="fas fa-star filled"></i>'; // Étoile pleine
        } elseif ($i - 0.5 == floor($rating) && $rating - floor($rating) >= 0.5) {
            $html .= '<i class="fas fa-star-half-alt filled"></i>'; // Demi-étoile
        } else {
            $html .= '<i class="far fa-star"></i>'; // Étoile vide
        }
    }
    $html .= '</div>';
    return $html;
}

/**
 * Récupère une liste d'utilisateurs (pour les suggestions ou la messagerie).
 * @param PDO $conn La connexion PDO.
 * @param int $current_user_id L'ID de l'utilisateur actuel pour l'exclure ou d'autres logiques.
 * @param int $limit Le nombre maximum d'utilisateurs à récupérer.
 * @return array Tableau d'utilisateurs.
 */
function getUsersForSuggestions($conn, $current_user_id, $limit = 5)
{
    $sql = "SELECT id, nom, photo_profil, type_user, localisation FROM users WHERE id != ? AND type_user != 'admin' ORDER BY RAND() LIMIT ?";
    $stmt = $conn->prepare($sql);

    // Bind the parameters and specify their types
    $stmt->bindParam(1, $current_user_id, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les contacts de chat récents pour un utilisateur.
 * Ordonne par le message le plus récent.
 * @param PDO $conn La connexion PDO.
 * @param int $user_id L'ID de l'utilisateur.
 * @return array Tableau d'objets contact, chacun avec les détails de l'utilisateur et le dernier message.
 */
function getRecentChatContacts($conn, $user_id)
{
    $stmt = $conn->prepare("
        SELECT
            u.id,
            u.nom,
            u.photo_profil,
            (SELECT message FROM messages
             WHERE (id_expediteur = u.id AND id_destinataire = :user_id)
             OR (id_expediteur = :user_id AND id_destinataire = u.id)
             ORDER BY date_envoi DESC LIMIT 1) as last_message,
            (SELECT date_envoi FROM messages
             WHERE (id_expediteur = u.id AND id_destinataire = :user_id)
             OR (id_expediteur = :user_id AND id_destinataire = u.id)
             ORDER BY date_envoi DESC LIMIT 1) as last_message_date
        FROM users u
        WHERE u.id IN (SELECT id_expediteur FROM messages WHERE id_destinataire = :user_id)
           OR u.id IN (SELECT id_destinataire FROM messages WHERE id_expediteur = :user_id)
        GROUP BY u.id
        ORDER BY last_message_date DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>