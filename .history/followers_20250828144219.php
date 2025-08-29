<?php
// followers.php : Liste des abonnés d'un utilisateur
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
checkLogin();
$profile_id = $_GET['id'] ?? $_SESSION['user_id'];
// Récupérer les infos du profil
$stmt = $conn->prepare("SELECT nom, photo_profil FROM users WHERE id = ?");
$stmt->execute([$profile_id]);
$profile = $stmt->fetch();
if (!$profile) {
    header('Location: index.php');
    exit;
}
// Récupérer les abonnés
$stmt = $conn->prepare("SELECT u.id, u.nom, u.photo_profil, u.type_user, u.localisation FROM users u JOIN followers f ON u.id = f.follower_id WHERE f.followed_id = ?");
$stmt->execute([$profile_id]);
$followers = $stmt->fetchAll();
$page_title = 'Abonnés de ' . htmlspecialchars($profile['nom']);
require_once 'includes/header.php';
?>
<div class="main-content followers-main-content">
    <div class="followers-header">
        <h2><i class="fas fa-users"></i> Abonnés de
            <?php echo htmlspecialchars($profile['nom']); ?></h2>
    </div>
    <?php if ($followers): ?>
        <div class="followers-list-wrapper">
            <?php foreach ($followers as $f): ?>
                <div class="followers-list-card">
                    <img src="<?php echo getUserProfilePhoto($f['photo_profil']); ?>" alt="Profil" class="followers-avatar">
                    <div class="followers-info">
                        <a href="profile.php?id=<?php echo $f['id']; ?>" class="followers-name"><?php echo htmlspecialchars($f['nom']); ?></a><br>
                        <span class="followers-badge"><?php echo htmlspecialchars($f['type_user']); ?></span>
                        <?php if ($f['localisation']): ?>
                            <span class="followers-location"> <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($f['localisation']); ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="profile.php?id=<?php echo $f['id']; ?>" class="followers-btn followers-profile-btn">Voir le
                        profil</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="followers-empty">Aucun abonné pour le moment.</p>
    <?php endif; ?>
    <div class="followers-back-btn-wrapper">
        <a href="profile.php?id=<?php echo $profile_id; ?>" class="followers-btn">Retour au profil</a>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>