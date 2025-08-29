<?php
// following.php : Liste des abonnements d'un utilisateur
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
// Récupérer les abonnements
$stmt = $conn->prepare("SELECT u.id, u.nom, u.photo_profil, u.type_user, u.localisation FROM users u JOIN followers f ON u.id = f.followed_id WHERE f.follower_id = ?");
$stmt->execute([$profile_id]);
$following = $stmt->fetchAll();
require_once 'includes/header.php';
?>
<style>
    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e9f0fb 100%);
    }

    .followers-list-card {
        display: flex;
        align-items: center;
        width: 90%;
        gap: 1.2em;
        padding: 1.1em 1.2em;
        background: #872424ff;
        border-radius: 18px;
        box-shadow: 0 2px 12px rgba(59, 130, 246, 0.07);
        transition: box-shadow .2s, transform .2s;
        position: relative;
    }

    .followers-list-card:hover {
        box-shadow: 0 6px 32px rgba(59, 130, 246, 0.13);
        transform: translateY(-2px) scale(1.015);
    }

    .followers-avatar {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 2px 8px #e9e9f7;
        border: 3px solid #e0e7ff;
    }

    .followers-badge {
        display: inline-block;
        background: #3b82f6;
        color: #fff;
        font-size: 0.85em;
        border-radius: 8px;
        padding: 2px 10px;
        margin-right: 6px;
        margin-top: 4px;
    }

    .followers-btn {
        background: #fff;
        color: #3b82f6;
        border: 1.5px solid #3b82f6;
        border-radius: 8px;
        padding: 7px 18px;
        font-weight: 600;
        transition: background .2s, color .2s;
        cursor: pointer;
        text-decoration: none;
    }

    .followers-btn:hover {
        background: #3b82f6;
        color: #fff;
    }

    .followers-header {
        position: sticky;
        top: 0;
        background: rgba(248, 250, 252, 0.95);
        z-index: 10;
        padding: 1.2em 0 0.7em 0;
        margin-bottom: 1.5em;
        border-radius: 0 0 18px 18px;
        box-shadow: 0 2px 8px #e9e9f7;
    }

    @media (max-width:700px) {
        .main-content {
            max-width: 98vw !important;
        }

        .followers-list-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.7em;
        }

        .followers-avatar {
            width: 54px;
            height: 54px;
        }
    }
</style>
<div class="main-content" style="max-width:600px;
        width: 90%;
        back
margin:2em auto;">
    <div class="followers-header">
        <h2 style="color:#3b82f6;"><i class="fas fa-user-friends"></i> Abonnements de
            <?php echo htmlspecialchars($profile['nom']); ?></h2>
    </div>
    <?php if ($following): ?>
        <div style="display:flex;
        width: 90%;
         flex-direction:column; gap:1.2em;">
            <?php foreach ($following as $f): ?>
                <div class="followers-list-card">
                    <img src="<?php echo getUserProfilePhoto($f['photo_profil']); ?>" alt="Profil" class="followers-avatar">
                    <div style="flex:1;min-width:0;">
                        <a href="profile.php?id=<?php echo $f['id']; ?>"
                            style="font-weight:600;font-size:1.13em;color:#222;text-decoration:none;word-break:break-word;white-space:normal;display:inline-block;max-width:220px;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;"><?php echo htmlspecialchars($f['nom']); ?></a><br>
                        <span class="followers-badge"><?php echo htmlspecialchars($f['type_user']); ?></span>
                        <?php if ($f['localisation']): ?>
                            <span style="font-size:0.97em;color:#666;"> <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($f['localisation']); ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="profile.php?id=<?php echo $f['id']; ?>" class="followers-btn" style="margin-left:auto;">Voir le
                        profil</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="text-align:center;color:#888;">Aucun abonnement pour le moment.</p>
    <?php endif; ?>
    <div style="text-align:center;margin-top:2em;">
        <a href="profile.php?id=<?php echo $profile_id; ?>" class="followers-btn">Retour au profil</a>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>