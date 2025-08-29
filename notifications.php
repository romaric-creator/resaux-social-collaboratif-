<?php
$page_title = "Mes Notifications";
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

checkLogin();

$user_id = $_SESSION['user_id'];

// Marquer toutes les notifications comme lues si l'utilisateur y accède
// Ou si une action spécifique de marquage est envoyée
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 'true') {
    try {
        $stmt = $conn->prepare("UPDATE notifications SET lu = 1 WHERE id_user = ? AND lu = 0");
        $stmt->execute([$user_id]);
        $_SESSION['success_message'] = "Toutes les notifications ont été marquées comme lues.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur lors du marquage des notifications: " . $e->getMessage();
    }
    header("Location: " . BASE_URL . "notifications.php"); // Rediriger pour éviter le re-submit
    exit();
}

// Récupérer les notifications de l'utilisateur
$notifications = getNotifications($conn, $user_id);

require_once 'includes/header.php';
?>

<div class="main-content notifications-page">
    <div class="card">
        <div class="notifications-header">
            <h1><i class="fas fa-bell"></i> Mes Notifications</h1>
            <?php if (!empty($notifications)): ?>
                <a href="?mark_all_read=true" class="button button-small secondary"><i class="fas fa-check-double"></i> Marquer tout lu</a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <p class="success-message"><?php echo htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="error-message"><?php echo htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="notification-list">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['lu'] ? 'read' : 'unread'; ?>">
                        <div class="notification-icon">
                            <?php
                                $icon = 'fas fa-info-circle'; // Default
                                switch ($notification['type']) {
                                    case 'new_message': $icon = 'fas fa-envelope'; break;
                                    case 'new_like': $icon = 'fas fa-heart'; break;
                                    case 'new_comment': $icon = 'fas fa-comment'; break;
                                    case 'followed_company_pub':
                                    case 'followed_company': $icon = 'fas fa-building'; break;
                                    case 'report_status': $icon = 'fas fa-flag'; break;
                                    case 'new_review': $icon = 'fas fa-star'; break;
                                    // Add more cases for other notification types
                                }
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <p><?php echo nl2br(htmlspecialchars($notification['message_custom'] ?? 'Nouvelle notification', ENT_QUOTES, 'UTF-8')); ?></p>
                            <span class="notification-time"><?php echo formatRelativeTime($notification['date_notification']); ?></span>
                            <?php if (!$notification['lu']): ?>
                                <span class="notification-status">Non lu</span>
                            <?php endif; ?>
                            <?php
                                // Lien vers le contenu lié à la notification
                                $notification_link = '#';
                                switch ($notification['type']) {
                                    case 'new_message':
                                        if ($notification['id_reference']) {
                                            $notification_link = BASE_URL . 'chat/?to=' . htmlspecialchars($notification['id_reference'], ENT_QUOTES, 'UTF-8');
                                        }
                                        break;
                                    case 'new_like':
                                    case 'new_comment':
                                        if ($notification['id_reference']) {
                                            // Tenter de trouver l'ID de l'entreprise pour construire le lien vers la publication sur le profil
                                            $stmt_pub_owner = $conn->prepare("SELECT id_entreprise FROM publications WHERE id = ?");
                                            $stmt_pub_owner->execute([$notification['id_reference']]);
                                            $owner_id = $stmt_pub_owner->fetchColumn();
                                            if ($owner_id) {
                                                $notification_link = BASE_URL . 'profile.php?id=' . htmlspecialchars($owner_id, ENT_QUOTES, 'UTF-8') . '#post-' . htmlspecialchars($notification['id_reference'], ENT_QUOTES, 'UTF-8');
                                            }
                                        }
                                        break;
                                    case 'followed_company':
                                        if ($notification['id_reference']) {
                                            $notification_link = BASE_URL . 'profile.php?id=' . htmlspecialchars($notification['id_reference'], ENT_QUOTES, 'UTF-8');
                                        }
                                        break;
                                    case 'new_review':
                                        if ($notification['id_reference']) {
                                             // id_reference pour new_review est l'id_user qui a laissé l'avis
                                             // On veut le lien vers le profil de l'entreprise recevant l'avis
                                             $notification_link = BASE_URL . 'profile.php?id=' . htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8') . '#tab-reviews';
                                        }
                                        break;
                                    case 'report_status':
                                        $notification_link = BASE_URL . 'admin/manage_reports.php?id=' . htmlspecialchars($notification['id_reference'], ENT_QUOTES, 'UTF-8');
                                        break;
                                }
                                if ($notification_link !== '#'):
                            ?>
                                <a href="<?php echo $notification_link; ?>" class="notification-action-link button-small button-outline">Voir</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="info-message-card">Vous n'avez aucune notification pour le moment.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
