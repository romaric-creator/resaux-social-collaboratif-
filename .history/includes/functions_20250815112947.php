<?php
// Fichier de fonctions utilitaires pour le projet
require_once __DIR__ . '/../config/app.php'; // Inclure le fichier de configuration
require_once __DIR__ . '/db.php'; // Pour $conn

/**
 * Gère l'upload d'un fichier image, vidéo ou document.
 * Cette fonction est améliorée pour une meilleure sécurité.
 * @param array $file_input Le tableau $_FILES['nom_du_champ']
 * @param string $target_sub_dir Le sous-dossier de 'uploads/' où stocker le fichier (ex: 'profiles', 'publications', 'brochures')
 * @param array $allowed_mime_types Les types MIME réels autorisés (ex: ['image/jpeg', 'application/pdf'])
 * @param int $max_size_mb La taille maximale en Mo
 * @return string|false Le chemin relatif du fichier généré (ex: 'profiles/nom_unique.jpg') si succès, false si échec.
 */
function uploadFile($file_input, $target_sub_dir, $allowed_mime_types, $max_size_mb) {
    // Vérifier si un fichier a été réellement uploadé sans erreur PHP
    if (!isset($file_input) || $file_input['error'] !== UPLOAD_ERR_OK) {
        // Gérer les erreurs spécifiques d'upload pour un meilleur diagnostic
        switch ($file_input['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                error_log("Upload Error: File exceeds max size (PHP config or form).");
                break;
            case UPLOAD_ERR_PARTIAL:
                error_log("Upload Error: File partially uploaded.");
                break;
            case UPLOAD_ERR_NO_FILE:
                // Pas d'erreur, juste aucun fichier sélectionné (souvent ok pour champs optionnels)
                return false;
            case UPLOAD_ERR_NO_TMP_DIR:
                error_log("Upload Error: Missing a temporary folder.");
                break;
            case UPLOAD_ERR_CANT_WRITE:
                error_log("Upload Error: Failed to write file to disk.");
                break;
            case UPLOAD_ERR_EXTENSION:
                error_log("Upload Error: A PHP extension stopped the file upload.");
                break;
            default:
                error_log("Upload Error: Unknown error code " . $file_input['error']);
        }
        return false;
    }

    $upload_base_dir = __DIR__ . '/../uploads/';
    $target_full_dir = $upload_base_dir . $target_sub_dir;

    // Créer le dossier cible s'il n'existe pas
    if (!is_dir($target_full_dir)) {
        if (!mkdir($target_full_dir, 0777, true)) { // Permissions 777 pour un dev local, ajuster en prod
            error_log("Failed to create upload directory: " . $target_full_dir);
            return false;
        }
    }

    // Sécurité: Assurer que les fichiers dans les dossiers d'upload ne peuvent pas être exécutés comme des scripts PHP.
    // Ajouter un fichier .htaccess avec des règles de sécurité.
    $htaccess_content = "
        <FilesMatch \"\.(php|phtml|php3|php4|php5|php7|phar|pl|py|cgi|sh|rb|htaccess)\$\">
            Require all denied
        </FilesMatch>
        # Force download for certain types or block execution of all files
        <Files *.*>
            ForceType application/octet-stream
            Header set Content-Disposition attachment
        </Files>
        # Re-allow images/videos to be displayed (browser will interpret based on actual MIME)
        <FilesMatch \"\.(jpg|jpeg|png|gif|webp|bmp|mp4|webm|ogg|pdf)\$\">
            ForceType none
            Header unset Content-Disposition
        </FilesMatch>
    ";
    // Ajout d'un Index.html vide pour éviter le listage des répertoires
    if (!file_exists($target_full_dir . '/.htaccess')) {
        file_put_contents($target_full_dir . '/.htaccess', $htaccess_content);
    }
    if (!file_exists($target_full_dir . '/index.html')) {
         file_put_contents($target_full_dir . '/index.html', '<!-- Silence is golden -->');
    }

    // Vérifier la taille du fichier
    if ($file_input['size'] > ($max_size_mb * 1024 * 1024)) {
        error_log("File too large: " . $file_input['size'] . " bytes. Max allowed: " . $max_size_mb . " MB.");
        return false;
    }

    // --- Validation du type MIME réel (plus sécurisé) ---
    // Utilise l'extension PHP finfo pour détecter le vrai type MIME du fichier
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        error_log("Cannot open fileinfo database.");
        return false;
    }
    $real_mime_type = finfo_file($finfo, $file_input['tmp_name']);
    finfo_close($finfo);

    if (!in_array($real_mime_type, $allowed_mime_types)) {
        error_log("Invalid file MIME type detected: " . $real_mime_type . " for file " . $file_input['name'] . ". Allowed types: " . implode(', ', $allowed_mime_types));
        return false;
    }

    // --- Validation de l'extension (whitelist) ---
    $file_extension = strtolower(pathinfo($file_input['name'], PATHINFO_EXTENSION));
    $allowed_extensions = [];
    // Construire la liste des extensions autorisées basée sur les types MIME
    foreach ($allowed_mime_types as $mime) {
        if (strpos($mime, 'image/') === 0) $allowed_extensions = array_merge($allowed_extensions, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
        if (strpos($mime, 'video/') === 0) $allowed_extensions = array_merge($allowed_extensions, ['mp4', 'webm', 'ogg']);
        if ($mime === 'application/pdf') $allowed_extensions[] = 'pdf';
        if ($mime === 'application/msword') $allowed_extensions[] = 'doc';
        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') $allowed_extensions[] = 'docx';
    }
    // S'assurer de l'unicité et nettoyer
    $allowed_extensions = array_unique($allowed_extensions);

    if (!in_array($file_extension, $allowed_extensions)) {
        error_log("Invalid file extension: " . $file_extension . " for file " . $file_input['name'] . ". Allowed extensions: " . implode(', ', $allowed_extensions));
        return false;
    }

    // Générer un nom de fichier unique et sécurisé pour éviter les collisions et les problèmes de chemin
    // uniqid(true) ajoute une entropie pour être encore plus unique
    $unique_filename = uniqid('upload_', true) . '.' . $file_extension;
    $target_file_path = $target_full_dir . $unique_filename;

    // Déplacer le fichier uploadé du répertoire temporaire vers le répertoire cible
    if (move_uploaded_file($file_input['tmp_name'], $target_file_path)) {
        return $target_sub_dir . $unique_filename; // Retourne le chemin relatif pour la BDD
    } else {
        error_log("Failed to move uploaded file from " . $file_input['tmp_name'] . " to: " . $target_file_path);
        return false;
    }
}

/**
 * Supprime un fichier uploadé du système de fichiers.
 * @param string $file_path Le chemin relatif du fichier (ex: 'profiles/mon_image.jpg')
 * @return bool True si le fichier a été supprimé ou n'existait pas, false en cas d'erreur.
 */
function deleteUploadedFile($file_path) {
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
 * La construction de la requête SQL est logiquement correcte: FROM -> JOINs -> WHERE -> GROUP BY -> ORDER BY.
 * @param PDO $conn L'objet de connexion PDO.
 * @param array $filters Tableau associatif de filtres (ex: ['location' => 'Paris', 'category' => 'Hébergement', 'search_query' => 'motclé'])
 * @param string $sort_by Critère de tri ('date', 'popularite').
 * @param int $limit Nombre de publications à récupérer.
 * @param int $offset Offset pour la pagination.
 * @return array Tableau associatif des publications.
 */
function getPublications($conn, $filters = [], $sort_by = 'date', $limit = 10, $offset = 0) {
    // 1) Vérifier si la colonne 'status' existe
    $hasStatus = false;
    try {
        $colStmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publications' AND COLUMN_NAME = 'status'");
        $colStmt->execute();
        $hasStatus = ($colStmt->fetchColumn() > 0);
    } catch (PDOException $e) {
        $hasStatus = false;
    }

    // 2) SELECT + JOINs (JOINs construits avant WHERE)
    $sql = "SELECT p.*, u.nom as user_nom, u.photo_profil as user_photo_profil, u.localisation as user_localisation, e.nom as entreprise_nom, e.secteur
            FROM publications p
            JOIN users u ON p.id_entreprise = u.id
            LEFT JOIN entreprises e ON u.id = e.id_user";

    // Si tri par popularité, préparer la jointure favoris (sera utilisée plus bas)
    $needFavsJoin = ($sort_by === 'popularite');

    if ($needFavsJoin) {
        // jointure ajoutée AVANT le WHERE
        $sql .= " LEFT JOIN favoris f ON p.id = f.id_publication";
    }

    // 3) WHERE clauses (n'ajouter p.status QUE si la colonne existe)
    $where_clauses = [];
    $params = [];

    if ($hasStatus) {
        $where_clauses[] = "p.status = 'publie'";
    }
    if (!empty($filters['location']) && $filters['location'] !== 'Toutes') {
        $where_clauses[] = "(u.localisation = :location OR p.localisation_pub = :location)";
        $params[':location'] = $filters['location'];
    }
    if (!empty($filters['category']) && $filters['category'] !== 'Toutes') {
        $where_clauses[] = "p.categorie = :category";
        $params[':category'] = $filters['category'];
    }
    if (!empty($filters['search_query'])) {
        $search_term = '%' . $filters['search_query'] . '%';
        $where_clauses[] = "(p.titre LIKE :search_query OR p.description LIKE :search_query OR u.nom LIKE :search_query OR e.description LIKE :search_query)";
        $params[':search_query'] = $search_term;
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    // 4) GROUP BY / ORDER BY
    if ($needFavsJoin) {
        $sql .= " GROUP BY p.id";
        $sql .= " ORDER BY COUNT(f.id) DESC, p.date_publication DESC";
    } else {
        $sql .= " ORDER BY p.date_publication DESC";
    }

    // 5) LIMIT / OFFSET
    $sql .= " LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);

    // bind des params dynamiques
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    // bind limit/offset en tant qu'entiers
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère les informations détaillées d'une entreprise par son ID utilisateur.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $user_id L'ID de l'utilisateur (de type entreprise).
 * @return array|false Tableau associatif des détails de l'entreprise ou false si non trouvée.
 */
function getCompanyDetails($conn, $user_id) {
    $stmt = $conn->prepare("SELECT u.*, e.*
                            FROM users u
                            JOIN entreprises e ON u.id = e.id_user
                            WHERE u.id = ? AND u.type_user = 'entreprise'");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


/**
 * Récupère les messages échangés entre deux utilisateurs.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $user_id L'ID du premier utilisateur.
 * @param int $dest_id L'ID du second utilisateur.
 * @param int $limit Nombre de messages à récupérer.
 * @param int $offset Offset pour la pagination (pour charger les anciens messages).
 * @return array Tableau associatif des messages.
 */
function getMessages($conn, $user_id, $dest_id, $limit = 50, $offset = 0) {
    $stmt = $conn->prepare("SELECT m.*, u_exp.nom as expediteur_nom, u_exp.photo_profil as expediteur_photo_profil
                            FROM messages m
                            JOIN users u_exp ON m.id_expediteur = u_exp.id
                            WHERE (m.id_expediteur = :user_id AND m.id_destinataire = :dest_id)
                            OR (m.id_expediteur = :dest_id_alt AND m.id_destinataire = :user_id_alt)
                            ORDER BY m.date_envoi ASC
                            LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':dest_id', $dest_id, PDO::PARAM_INT);
    $stmt->bindValue(':dest_id_alt', $dest_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id_alt', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Récupère la liste des conversations d'un utilisateur (les autres utilisateurs avec qui il a échangé).
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $user_id L'ID de l'utilisateur.
 * @return array Tableau associatif des utilisateurs avec qui une conversation existe.
 */
function getConversationsList($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT DISTINCT u.id, u.nom, u.photo_profil,
               (SELECT message FROM messages WHERE (id_expediteur = u.id AND id_destinataire = :user_id_msg1) OR (id_expediteur = :user_id_msg2 AND id_destinataire = u.id) ORDER BY date_envoi DESC LIMIT 1) AS last_message,
               (SELECT date_envoi FROM messages WHERE (id_expediteur = u.id AND id_destinataire = :user_id_date1) OR (id_expediteur = :user_id_date2 AND id_destinataire = u.id) ORDER BY date_envoi DESC LIMIT 1) AS last_message_date
        FROM users u
        WHERE u.id IN (SELECT id_expediteur FROM messages WHERE id_destinataire = :user_id_in1)
           OR u.id IN (SELECT id_destinataire FROM messages WHERE id_expediteur = :user_id_in2)
        ORDER BY last_message_date DESC
    ");
    $stmt->bindValue(':user_id_msg1', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id_msg2', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id_date1', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id_date2', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id_in1', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':user_id_in2', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Envoie un message et ajoute une notification.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_expediteur L'ID de l'expéditeur.
 * @param int $id_destinataire L'ID du destinataire.
 * @param string $message Le contenu du message.
 * @return bool Vrai si le message a été envoyé, faux sinon.
 */
function sendMessage($conn, $id_expediteur, $id_destinataire, $message) {
    if (empty($message)) {
        return false;
    }
    try {
        $stmt = $conn->prepare("INSERT INTO messages(id_expediteur, id_destinataire, message) VALUES(?, ?, ?)");
        $stmt->execute([$id_expediteur, $id_destinataire, $message]);
        // Ajouter une notification au destinataire
        $sender_name = $_SESSION['user_name'] ?? 'Quelqu\'un'; // Récupérer le nom de l'expéditeur depuis la session
        addNotification($conn, $id_destinataire, 'new_message', $id_expediteur, 'Vous avez un nouveau message de ' . htmlspecialchars($sender_name, ENT_QUOTES, 'UTF-8'));
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'envoi du message: " . $e->getMessage());
        return false;
    }
}


/**
 * Ajoute une nouvelle notification.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $id_user L'ID de l'utilisateur recevant la notification.
 * @param string $type Le type de notification (ex: 'new_message', 'new_like', 'new_comment', 'followed_company_pub', 'report_status').
 * @param int|null $id_reference L'ID de l'élément lié (message, publication, user qui a interagi).
 * @param string|null $message_custom Message personnalisé pour la notification.
 * @return bool Vrai si la notification a été ajoutée, faux sinon.
 */
function addNotification($conn, $id_user, $type, $id_reference = null, $message_custom = null) {
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
 * Récupère les notifications d'un utilisateur.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $user_id L'ID de l'utilisateur.
 * @param bool $only_unread Si vrai, ne retourne que les non lues.
 * @return array Tableau des notifications.
 */
function getNotifications($conn, $user_id, $only_unread = false) {
    $sql = "SELECT * FROM notifications WHERE id_user = ?";
    if ($only_unread) {
        $sql .= " AND lu = 0";
    }
    $sql .= " ORDER BY date_notification DESC LIMIT 20"; // Limiter pour la performance
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Marque une notification comme lue.
 * @param PDO $conn L'objet de connexion PDO.
 * @param int $notification_id L'ID de la notification.
 * @param int $user_id L'ID de l'utilisateur (pour sécurité).
 * @return bool Vrai si marquée lue, faux sinon.
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET lu = 1 WHERE id = ? AND id_user = ?");
        $stmt->execute([$notification_id, $user_id]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors du marquage d'une notification comme lue: " . $e->getMessage());
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
 * @param bool $is_verified Indique si l'avis est vérifié (par défaut 0).
 * @return bool Vrai si le commentaire a été ajouté, faux sinon.
 */
function addCommentAndRating($conn, $id_user, $contenu, $id_publication = null, $id_entreprise_profile = null, $note = null, $is_verified = 0) {
    try {
        if (empty($contenu) && empty($note)) {
            return false; // Rien à ajouter
        }
        $stmt = $conn->prepare("INSERT INTO commentaires(id_user, contenu, id_publication, id_entreprise_profile, note, is_verified) VALUES(?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_user, $contenu, $id_publication, $id_entreprise_profile, $note, $is_verified]);

        // Ajouter notification à l'entreprise ou à l'auteur de la publication
        if ($id_publication) {
            $stmt_pub_owner = $conn->prepare("SELECT id_entreprise FROM publications WHERE id = ?");
            $stmt_pub_owner->execute([$id_publication]);
            $owner_id = $stmt_pub_owner->fetchColumn();
            if ($owner_id && $owner_id != $id_user) { // Ne pas s'auto-notifier
                addNotification($conn, $owner_id, 'new_comment', $id_publication, htmlspecialchars($_SESSION['user_name'] ?? 'Quelqu\'un', ENT_QUOTES, 'UTF-8') . ' a commenté votre publication !');
            }
        } elseif ($id_entreprise_profile && $id_entreprise_profile != $id_user) { // Ne pas s'auto-notifier
            addNotification($conn, $id_entreprise_profile, 'new_review', $id_user, htmlspecialchars($_SESSION['user_name'] ?? 'Quelqu\'un', ENT_QUOTES, 'UTF-8') . ' a laissé un avis sur votre profil !');
        }
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
function toggleLikeOrFavorite($conn, $id_user, $id_publication = null, $id_entreprise_profile = null) {
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
                // Ajouter notification à l'auteur de la publication
                $stmt_pub_owner = $conn->prepare("SELECT id_entreprise FROM publications WHERE id = ?");
                $stmt_pub_owner->execute([$id_publication]);
                $owner_id = $stmt_pub_owner->fetchColumn();
                if ($owner_id && $owner_id != $id_user) { // Ne pas s'auto-notifier
                    addNotification($conn, $owner_id, 'new_like', $id_publication, htmlspecialchars($_SESSION['user_name'] ?? 'Quelqu\'un', ENT_QUOTES, 'UTF-8') . ' a aimé votre publication !');
                }
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
                // Ajouter notification à l'entreprise
                if ($id_entreprise_profile != $id_user) { // Ne pas s'auto-notifier
                    addNotification($conn, $id_entreprise_profile, 'followed_company', $id_user, htmlspecialchars($_SESSION['user_name'] ?? 'Quelqu\'un', ENT_QUOTES, 'UTF-8') . ' a ajouté votre entreprise à ses favoris !');
                }
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
function hasLikedPublication($conn, $id_publication, $id_user) {
    if (!$id_user) return false; // Utilisateur non connecté ne peut pas avoir liké
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
function hasFavoritedCompany($conn, $id_entreprise_profile, $id_user) {
    if (!$id_user) return false; // Utilisateur non connecté ne peut pas avoir favorisé
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
function countLikes($conn, $id_publication) {
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
function getCommentsForPublication($conn, $id_publication) {
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
function getCompanyReviews($conn, $id_entreprise_profile) {
    $stmt = $conn->prepare("SELECT c.*, u.nom as user_nom, u.photo_profil as user_photo_profil, c.is_verified
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
function getAverageRating($conn, $id_entreprise_profile) {
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
function getUserProfilePhoto($photo_profil_path) {
    // __DIR__ . '/../' remonte d'un niveau (de includes/ vers PlateformeTourisme/)
    // Assurez-vous que BASE_URL est défini avant d'appeler cette fonction
    if ($photo_profil_path && file_exists(__DIR__ . '/../uploads/' . $photo_profil_path)) {
        return BASE_URL . 'uploads/' . htmlspecialchars($photo_profil_path, ENT_QUOTES, 'UTF-8');
    }
    // Assurez-vous que cette image existe dans assets/images/
    return BASE_URL . 'assets/images/default_profile.png';
}

/**
 * Retourne le chemin complet de la photo de couverture par défaut si l'entreprise n'en a pas.
 * @param string|null $cover_photo_path Le chemin de la photo de couverture depuis la BDD.
 * @return string Le chemin complet de l'image (par défaut ou uploadée).
 */
function getCompanyCoverPhoto($cover_photo_path) {
    if ($cover_photo_path && file_exists(__DIR__ . '/../uploads/' . $cover_photo_path)) {
        return BASE_URL . 'uploads/' . htmlspecialchars($cover_photo_path, ENT_QUOTES, 'UTF-8');
    }
    return BASE_URL . 'assets/images/default_cover.jpg';
}


/**
 * Formate une date en un format lisible.
 * @param string $datetime La date et heure (format YYYY-MM-DD HH:MM:SS).
 * @return string La date formatée.
 */
function formatRelativeTime($datetime) {
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
function displayRatingStars($rating, $size = '') {
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
function getUsersForSuggestions($conn, $current_user_id, $limit = 5) {
    $sql = "SELECT id, nom, photo_profil, type_user, localisation
            FROM users
            WHERE id != ? AND type_user != 'admin'
            ORDER BY RAND()
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    // Bind positional parameters (1 et 2) en forçant PDO::PARAM_INT pour LIMIT
    $stmt->bindValue(1, (int)$current_user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
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
function getRecentChatContacts($conn, $user_id) {
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
