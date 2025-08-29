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
if (!$profile) { header('Location: index.php'); exit; }
// Récupérer les abonnés
$stmt = $conn->prepare("SELECT u.id, u.nom, u.photo_profil, u.type_user, u.localisation FROM users u JOIN followers f ON u.id = f.follower_id WHERE f.followed_id = ?");
$stmt->execute([$profile_id]);
$followers = $stmt->fetchAll();
$page_title = 'Abonnés de ' . htmlspecialchars($profile['nom']);
require_once 'includes/header.php';
?>
<div class="main-content" style="max-width:600px;margin:2em auto;">
    <h2 style="color:#3b82f6; margin-bottom:1.5em;"><i class="fas fa-users"></i> Abonnés de <?php echo htmlspecialchars($profile['nom']); ?></h2>
    <?php if ($followers): ?>
        <div style="display:flex; flex-direction:column; gap:1.2em;">
        <?php foreach ($followers as $f): ?>
            <div style="display:flex;align-items:center;gap:1.2em;padding:1.1em 1.2em;background:#fff;border-radius:18px;box-shadow:0 2px 12px rgba(59,130,246,0.07);transition:box-shadow .2s;">
                <img src="<?php echo getUserProfilePhoto($f['photo_profil']); ?>" alt="Profil" style="width:64px;height:64px;border-radius:50%;object-fit:cover;box-shadow:0 2px 8px #e9e9f7;">
                <div style="flex:1;">
                    <a href="profile.php?id=<?php echo $f['id']; ?>" style="font-weight:600;font-size:1.13em;color:#222;text-decoration:none;"><?php echo htmlspecialchars($f['nom']); ?></a><br>
                    <span style="font-size:0.97em;color:#3b82f6;font-weight:500;">Type: <?php echo htmlspecialchars($f['type_user']); ?></span>
                    <?php if ($f['localisation']): ?>
                        <span style="font-size:0.97em;color:#666;"> · <?php echo htmlspecialchars($f['localisation']); ?></span>
                    <?php endif; ?>
                </div>
                <a href="profile.php?id=<?php echo $f['id']; ?>" class="button button-small" style="margin-left:auto;">Voir le profil</a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align:center;color:#888;">Aucun abonné pour le moment.</p>
    <?php endif; ?>
    <div style="text-align:center;margin-top:2em;">
        <a href="profile.php?id=<?php echo $profile_id; ?>" class="button">Retour au profil</a>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
