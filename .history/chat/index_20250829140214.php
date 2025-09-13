
<?php
$page_title = "Messagerie";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
checkLogin();
$current_user_id = $_SESSION['user_id'];
$recipient_id = $_GET['user_id'] ?? null;
$contacts = getRecentChatContacts($conn, $current_user_id);
$active_recipient = null;
$messages = [];
if ($recipient_id) {
        $stmt_check_recipient = $conn->prepare("SELECT id, nom, photo_profil FROM users WHERE id = ? AND id != ?");
        $stmt_check_recipient->execute([$recipient_id, $current_user_id]);
        $active_recipient = $stmt_check_recipient->fetch();
        if ($active_recipient) {
                $messages = getMessages($conn, $current_user_id, $active_recipient['id']);
                $stmt_mark_read = $conn->prepare("UPDATE messages SET lu = 1 WHERE id_expediteur = ? AND id_destinataire = ? AND lu = 0");
                $stmt_mark_read->execute([$active_recipient['id'], $current_user_id]);
        } else {
                $recipient_id = null;
        }
}
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

<main class="main-content chat-page">
    <section class="chat-container">
        <aside class="chat-sidebar" aria-label="Liste des conversations">
            <header class="chat-sidebar-header">
                <input type="text" class="search-chat" placeholder="Rechercher des conversations..." aria-label="Rechercher">
            </header>
            <nav class="contact-list" aria-label="Contacts">
                <?php if (!empty($contacts)): ?>
                    <?php foreach ($contacts as $contact): ?>
                        <a href="<?php echo BASE_URL; ?>chat/?user_id=<?php echo $contact['id']; ?>" class="contact-item<?php echo ($active_recipient && $active_recipient['id'] == $contact['id']) ? ' active' : ''; ?>">
                            <img src="<?php echo getUserProfilePhoto($contact['photo_profil']); ?>" alt="Profil de <?php echo htmlspecialchars($contact['nom']); ?>" class="profile-pic-chat">
                            <div class="contact-info">
                                <span class="name"><?php echo htmlspecialchars($contact['nom']); ?></span>
                                <span class="last-message"><?php echo htmlspecialchars(mb_strimwidth($contact['last_message'], 0, 30, '...')) ?: 'Aucun message'; ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-contacts">Aucune conversation récente.</p>
                <?php endif; ?>
            </nav>
        </aside>
        <section class="chat-main" aria-label="Zone de discussion">
            <?php if ($active_recipient): ?>
                <header class="chat-header">
                    <img src="<?php echo getUserProfilePhoto($active_recipient['photo_profil']); ?>" alt="Profil de <?php echo htmlspecialchars($active_recipient['nom']); ?>" class="profile-pic-chat">
                    <span class="chat-header-name"><?php echo htmlspecialchars($active_recipient['nom']); ?></span>
                </header>
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
                <footer class="chat-input-area">
                    <input type="text" id="message-input" placeholder="Écrire un message..." aria-label="Écrire un message" autocomplete="off" autofocus>
                    <button id="send-message-btn" aria-label="Envoyer"><i class="fas fa-paper-plane"></i></button>
                </footer>
            <?php else: ?>
                <div class="empty-chat-state">
                    <i class="fas fa-comments fa-5x"></i>
                    <h2>Sélectionnez une conversation ou commencez-en une nouvelle.</h2>
                    <p>Vos messages privés s'afficheront ici.</p>
                </div>
            <?php endif; ?>
        </section>
    </section>
</main>

<script>
$(document).ready(function() {
    const recipientId = <?php echo json_encode($active_recipient['id'] ?? null); ?>;
    const currentUserId = <?php echo json_encode($current_user_id); ?>;
    const base_url = <?php echo json_encode(BASE_URL); ?>;
    const messageArea = $('#message-area');
    function scrollToBottom() {
        messageArea.scrollTop(messageArea[0].scrollHeight);
    }
    if (recipientId) {
        scrollToBottom();
    }
    function loadMessages() {
        if (!recipientId) return;
        $.ajax({
            url: base_url + 'chat/get_messages.php',
            type: 'GET',
            data: { recipient_id: recipientId },
            success: function(response) {
                if (response.success) {
                    const oldScrollHeight = messageArea[0].scrollHeight;
                    const oldScrollTop = messageArea.scrollTop();
                    const isScrolledToBottom = (oldScrollTop + messageArea.innerHeight()) >= (oldScrollHeight - 10);
                    messageArea.html('');
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
                    messageInput.val('');
                    loadMessages();
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
    $('#message-input').on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            $('#send-message-btn').click();
        }
    });
    if (recipientId) {
        setInterval(loadMessages, 3000);
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
