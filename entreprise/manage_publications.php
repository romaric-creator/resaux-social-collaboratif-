<?php
$page_title = "Gérer mes Publications";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();
checkEntreprise();

$user_id = $_SESSION['user_id'];

// Récupérer toutes les publications de l'entreprise actuelle
$stmt_pubs = $conn->prepare("SELECT id, titre, description, image, video, document_url, categorie, localisation_pub, date_publication
                            FROM publications
                            WHERE id_entreprise = ?
                            ORDER BY date_publication DESC");
$stmt_pubs->execute([$user_id]);
$publications = $stmt_pubs->fetchAll();

$message = $_SESSION['status_message'] ?? '';
$message_type = $_SESSION['status_message_type'] ?? ''; // 'success' ou 'error'
unset($_SESSION['status_message']); // Nettoyer après affichage
unset($_SESSION['status_message_type']);

require_once '../includes/header.php';
?>

<div class="main-content dashboard-page">
    <div class="dashboard-header">
        <h1><i class="fas fa-list-alt"></i> Gérer mes Publications</h1>
        <p>Aperçu et gestion de toutes vos annonces publiées.</p>
        <a href="<?php echo BASE_URL; ?>entreprise/add_publication.php" class="button button-small"><i class="fas fa-plus-circle"></i> Ajouter une nouvelle publication</a>
    </div>

    <?php if (!empty($message)): ?>
        <p class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <div class="publications-management-table card">
        <?php if (!empty($publications)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Description</th>
                        <th>Catégorie</th>
                        <th>Localisation</th>
                        <th>Date de Publication</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($publications as $pub): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(mb_strimwidth($pub['titre'], 0, 30, '...')); ?></td>
                            <td><?php echo htmlspecialchars(mb_strimwidth($pub['description'], 0, 50, '...')); ?></td>
                            <td><?php echo htmlspecialchars($pub['categorie'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($pub['localisation_pub'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($pub['date_publication'])); ?></td>
                            <td class="action-buttons">
                                <a href="<?php echo BASE_URL; ?>profile.php?id=<?php echo $user_id; ?>#post-<?php echo $pub['id']; ?>" class="action-btn-icon" title="Voir"><i class="fas fa-eye"></i></a>
                                <a href="<?php echo BASE_URL; ?>entreprise/edit_publication.php?id=<?php echo $pub['id']; ?>" class="action-btn-icon" title="Modifier"><i class="fas fa-edit"></i></a>
                                <a href="<?php echo BASE_URL; ?>entreprise/delete_publication.php?id=<?php echo $pub['id']; ?>" class="action-btn-icon delete-btn" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette publication ? Cette action est irréversible.');"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="info-message-card">Vous n'avez pas encore de publications. <a href="<?php echo BASE_URL; ?>entreprise/add_publication.php">Créez-en une maintenant !</a></p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
