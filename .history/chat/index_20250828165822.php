<?php
$page_title = "Messagerie";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

checkLogin();

$current_user_id = $_SESSION['user_id'];

// Récupérer l'ID du destinataire de l'URL ou le premier contact de la liste
$recipient_id = $_GET['user_id'] ?? null;

// Récupérer les contacts de chat récents de l'utilisateur connecté
$contacts = getRecentChatContacts($conn, $current_user_id);

$active_recipient = null;
$messages = [];

if ($recipient_id) {
    // Vérifier si le destinataire existe et n'est pas l'utilisateur lui-même
    $stmt_check_recipient = $conn->prepare("SELECT id, nom, photo_profil FROM users WHERE id = ? AND id != ?");
    $stmt_check_recipient->execute([$recipient_id, $current_user_id]);
    $active_recipient = $stmt_check_recipient->fetch();

    if ($active_recipient) {
        // Récupérer les messages avec le destinataire actif
        $messages = getMessages($conn, $current_user_id, $active_recipient['id']);

        // Marquer les messages reçus comme lus
        $stmt_mark_read = $conn->prepare("UPDATE messages SET lu = 1 WHERE id_expediteur = ? AND id_destinataire = ? AND lu = 0");
        $stmt_mark_read->execute([$active_recipient['id'], $current_user_id]);
    } else {
        // Destinataire non valide ou inexistant, réinitialiser
        $recipient_id = null;
    }
}

// Si aucun destinataire n'est sélectionné via URL et qu'il y a des contacts, sélectionner le premier
if (!$recipient_id && !empty($contacts)) {
    $recipient_id = $contacts[0]['id'];
    $stmt_check_recipient = $conn->prepare("SELECT id, nom, photo_profil FROM users WHERE id = ?");
    $stmt_check_recipient->execute([$recipient_id]);
    $active_recipient = $stmt_check_recipient->fetch();
    if ($active_recipient) {
        $messages = getMessages($conn, $current_user_id, $active_recipient['id']);
        $stmt_mark_read = $conn->prepare("UPDATE messages SET lu = 1 WHERE id_expediteur = ? AND id_destinataire = ? AND lu = 0");
        $stmt_mark_read->execute([$active_recipient['id'], $current_user_id]);
    }
}


require_once '../includes/header.php';
?>

<div class="main-content chat-page">
    <div class="chat-container">
        <div class="chat-sidebar">
            <div class="search-chat">
                <input type="text" placeholder="Rechercher des conversations...">
            </div>
            <div class="contact-list">
                <?php if (!empty($contacts)): ?>
                    <?php foreach ($contacts as $contact): ?>
                        <a href="<?php echo BASE_URL; ?>chat/?user_id=<?php echo $contact['id']; ?>" class="contact-item <?php echo ($active_recipient && $active_recipient['id'] == $contact['id']) ? 'active' : ''; ?>">
                            <img src="<?php echo getUserProfilePhoto($contact['photo_profil']); ?>" alt="Profil" class="profile-pic-chat">
                            <div class="contact-info">
                                <span class="name"><?php echo htmlspecialchars($contact['nom']); ?></span>
                                <span class="last-message"><?php echo htmlspecialchars(mb_strimwidth($contact['last_message'], 0, 30, '...')) ?: 'Aucun message'; ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-contacts">Aucune conversation récente.</p>
                <?php endif; ?>
            </div>
        </div><!-- Fin de chat-sidebar -->

        <div class="chat-main">
            <?php if ($active_recipient): ?>
                <div class="chat-header">
                    <img src="<?php echo getUserProfilePhoto($active_recipient['photo_profil']); ?>" alt="Profil" class="profile-pic-chat">
                    <span><?php echo htmlspecialchars($active_recipient['nom']); ?></span>
                </div>
                <div class="message-area" id="message-area">
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message-bubble <?php echo ($msg['id_expediteur'] == $current_user_id) ? 'sent' : 'received'; ?>">
                                <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                                <span class="timestamp"><?php echo formatRelativeTime($msg['date_envoi']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-messages">Commencez une conversation avec <?php echo htmlspecialchars($active_recipient['nom']); ?>.</p>
                    <?php endif; ?>
                </div>
                <div class="chat-input-area">
                    <input type="text" id="message-input" placeholder="Écrire un message..." autofocus>
                    <button id="send-message-btn"><i class="fas fa-paper-plane"></i></button>
                </div>
            <?php else: ?>
                <div class="empty-chat-state">
                    <i class="fas fa-comments fa-5x"></i>
                    <h2>Sélectionnez une conversation ou commencez-en une nouvelle.</h2>
                    <p>Vos messages privés s'afficheront ici.</p>
                </div>
            <?php endif; ?>
        </div><!-- Fin de chat-main -->
    </div><!-- Fin de chat-container -->
</div><!-- Fin de main-content -->

<script>
$(document).ready(function() {
    const recipientId = <?php echo json_encode($active_recipient['id'] ?? null); ?>;
    const currentUserId = <?php echo json_encode($current_user_id); ?>;
    const base_url = <?php echo json_encode(BASE_URL); ?>;
    const messageArea = $('#message-area');

    // Fonction pour faire défiler vers le bas des messages
    function scrollToBottom() {
        messageArea.scrollTop(messageArea[0].scrollHeight);
    }

    // Charger les messages et faire défiler au chargement de la page
    if (recipientId) {
        scrollToBottom();
    }

    // Fonction pour charger les messages via AJAX
    function loadMessages() {
        if (!recipientId) return; // Ne rien faire si pas de destinataire actif

        $.ajax({
            url: base_url + 'chat/get_messages.php',
            type: 'GET',
            data: { recipient_id: recipientId },
            success: function(response) {
                if (response.success) {
                    const oldScrollHeight = messageArea[0].scrollHeight;
                    const oldScrollTop = messageArea.scrollTop();
                    const isScrolledToBottom = (oldScrollTop + messageArea.innerHeight()) >= (oldScrollHeight - 10); // +- 10px tolerance

                    messageArea.html(''); // Vider la zone de message
                    if (response.messages.length > 0) {
                        response.messages.forEach(function(msg) {
                            const messageClass = (msg.id_expediteur == currentUserId) ? 'sent' : 'received';
                            const timestampClass = (msg.id_expediteur == currentUserId) ? '' : 'received';
                            const messageHtml = `
                                <div class="message-bubble ${messageClass}">
                                    <p>${msg.message.replace(/\n/g, '<br>')}</p>
                                    <span class="timestamp ${timestampClass}">${msg.date_envoi_formatted}</span>
                                </div>
                            `;
                            messageArea.append(messageHtml);
                        });
                        // Faire défiler vers le bas seulement si l'utilisateur était déjà en bas
                        if (isScrolledToBottom) {
                            scrollToBottom();
                        }
                    } else {
                        messageArea.html('<p class="no-messages">Commencez une conversation.</p>');
                    }
                } else {
                    console.error("Erreur lors du chargement des messages :", response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Erreur AJAX de chargement des messages :", status, error);
            }
        });
    }

    // Fonction pour envoyer un message
    $('#send-message-btn').on('click', function() {
        const messageInput = $('#message-input');
        const messageContent = messageInput.val().trim();

        if (messageContent === '' || !recipientId) {
            return;
        }

        $.ajax({
            url: base_url + 'chat/send_message.php',
            type: 'POST',
            data: {
                recipient_id: recipientId,
                message: messageContent
            },
            success: function(response) {
                if (response.success) {
                    messageInput.val(''); // Vider l'input
                    loadMessages(); // Recharger les messages pour voir le nouveau
                } else {
                    alert("Erreur lors de l'envoi du message : " + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Erreur AJAX d'envoi de message :", status, error);
                alert("Impossible d'envoyer le message. Veuillez réessayer.");
            }
        });
    });

    // Envoyer le message avec la touche Entrée
    $('#message-input').on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) { // Entrée sans Shift
            e.preventDefault(); // Empêcher le saut de ligne
            $('#send-message-btn').click();
        }
    });

    // Rafraîchir les messages toutes les 3 secondes si un destinataire est sélectionné
    if (recipientId) {
        setInterval(loadMessages, 3000);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
