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
    <h2 style="color:#3b82f6;"><i class="fas fa-users"></i> Abonnés de <?php echo htmlspecialchars($profile['nom']); ?></h2>
    <?php if ($followers): ?>
        <ul style="list-style:none;padding:0;">
        <?php foreach ($followers as $f): ?>
            <li style="display:flex;align-items:center;gap:1em;margin-bottom:1em;">
                <img src="<?php echo getUserProfilePhoto($f['photo_profil']); ?>" alt="Profil" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                <div>
                    <a href="profile.php?id=<?php echo $f['id']; ?>" style="font-weight:600;font-size:1.1em;"><?php echo htmlspecialchars($f['nom']); ?></a><br>
                    <span style="font-size:0.95em;color:#666;">Type: <?php echo htmlspecialchars($f['type_user']); ?><?php if ($f['localisation']) echo ' · '.htmlspecialchars($f['localisation']); ?></span>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Aucun abonné pour le moment.</p>
    <?php endif; ?>
    <a href="profile.php?id=<?php echo $profile_id; ?>" class="button">Retour au profil</a>
</div>
<?php require_once 'includes/footer.php'; ?>
