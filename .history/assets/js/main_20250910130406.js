// -------------------- Sauvegarde de la position entreprise (profil) --------------------
$(document).on("click", "#save-location-btn", function () {
  const msgDiv = $("#save-location-msg");
  msgDiv.text("Récupération de votre position...");
  if (!navigator.geolocation) {
    msgDiv.text("La géolocalisation n'est pas supportée par votre navigateur.");
    return;
  }
  navigator.geolocation.getCurrentPosition(
    function (position) {0 
      const lat = position.coords.latitude;
      const lng = position.coords.longitude;
      msgDiv.text("Envoi de la position...");
      $.ajax({
        url: "/PlateformeTourisme/ajax_handler.php",
        type: "POST",
        dataType: "json",
        data: {
          action: "save_company_location",
          latitude: lat,
          longitude: lng,
        },
        success: function (response) {
          if (response.success) {
            msgDiv.text("Position sauvegardée !");
            setTimeout(() => location.reload(), 1200);
          } else {
            msgDiv.text("Erreur : " + response.message);
          }
        },
        error: function () {
          msgDiv.text("Erreur lors de l'envoi de la position.");
        },
      });
    },
    function (error) {
      msgDiv.text("Impossible de récupérer la position : " + error.message);
    }
  );
});
$(document).ready(function () {
  // -------------------- Dropdown Menu (Header Profile) --------------------
  $(".profile-menu").on("click", function (event) {
    event.stopPropagation(); // Empêche le clic de se propager au document
    $(this).toggleClass("active"); // Ajoute/retire la classe 'active' pour afficher/masquer
  });

  // Fermer le dropdown si on clique en dehors
  $(document).on("click", function (event) {
    if (!$(event.target).closest(".profile-menu").length) {
      $(".profile-menu").removeClass("active");
    }
  });

  // -------------------- Post Options Dropdown --------------------
  $(document).on("click", ".post-options .options-btn", function (event) {
    event.stopPropagation();
    // Fermer tous les autres dropdowns ouverts
    $(".post-options.active")
      .not($(this).closest(".post-options"))
      .removeClass("active");
    $(this).closest(".post-options").toggleClass("active");
  });

  $(document).on("click", function (event) {
    if (!$(event.target).closest(".post-options").length) {
      $(".post-options").removeClass("active");
    }
  });

  // -------------------- AJAX for Likes --------------------
  $(document).on("click", ".post-actions .like-btn", function () {
    const button = $(this);
    const postId = button.data("post-id");
    const likesCountSpan = button.closest(".post-card").find(".likes-count");

    $.ajax({
      url: "/PlateformeTourisme/ajax_handler.php", // base_url est défini dans header.php et passé via une variable JS dans chat/index.php
      type: "POST",
      dataType: "json",
      data: {
        action: "toggle_like",
        post_id: postId,
      },
      success: function (response) {
        if (response.success) {
          button.toggleClass("liked");
          likesCountSpan.text(response.data.likes_count + " Likes");
        } else {
          alert("Erreur: " + response.message);
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error (Like):", status, error);
        alert("Une erreur est survenue. Veuillez réessayer.");
      },
    });
  });

  // -------------------- AJAX for Comments (Toggle visibility & Add) --------------------
  $(document).on("click", ".post-actions .comment-btn", function () {
    const postId = $(this).data("post-id");
    const commentsSection = $(this)
      .closest(".post-card")
      .find(".comments-section");
    commentsSection.slideToggle(200); // Animation douce d'ouverture/fermeture
  });

  $(document).on("submit", ".comments-section .comment-form", function (event) {
    event.preventDefault(); // Empêcher le rechargement de la page
    const form = $(this);
    const postId = form.data("post-id");
    const commentContent = form
      .find('input[name="comment_content"]')
      .val()
      .trim();
    const rating = form.find('select[name="rating"]').val();
    const commentList = form.closest(".comments-section").find(".comment-list");
    const commentsCountSpan = form
      .closest(".post-card")
      .find(".comments-count");

    if (commentContent === "" && rating === "") {
      alert("Veuillez écrire un commentaire ou donner une note.");
      return;
    }

    $.ajax({
      url: "/PlateformeTourisme/ajax_handler.php",
      type: "POST",
      dataType: "json",
      data: {
        action: "add_comment",
        post_id: postId,
        comment_content: commentContent,
        rating: rating,
      },
      success: function (response) {
        if (response.success) {
          form.find('input[name="comment_content"]').val(""); // Vider le champ
          form.find('select[name="rating"]').val(""); // Vider la sélection de note

          if (response.data.new_comment) {
            const newComment = response.data.new_comment;
            const starsHtml = newComment.note
              ? displayRatingStarsJS(newComment.note, "sm")
              : "";
            const commentHtml = `
                            <div class="comment-item">
                                <img src="${
                                  newComment.user_photo_profil_full_path
                                }" alt="Profil" class="comment-profile-pic">
                                <div class="comment-content">
                                    <span class="comment-author">${
                                      newComment.user_nom
                                    }</span>
                                    <p>${newComment.contenu.replace(
                                      /\n/g,
                                      "<br>"
                                    )}</p>
                                    <span class="comment-time">${
                                      newComment.date_commentaire_formatted
                                    }</span>
                                    ${starsHtml}
                                </div>
                            </div>
                        `;
            // Ajouter le nouveau commentaire en bas de la liste
            commentList.append(commentHtml);
            // Mettre à jour le compteur de commentaires
            commentsCountSpan.text(
              (parseInt(commentsCountSpan.text()) || 0) + 1 + " Commentaires"
            );
            // Enlever le message "Aucun commentaire" si présent
            commentList.find(".no-comments").remove();
            // Faire défiler la liste des commentaires vers le bas
            commentList.scrollTop(commentList[0].scrollHeight);
          }
        } else {
          alert("Erreur: " + response.message);
        }
      },
      error: function (xhr, status, error) {
        console.error("AJAX Error (Comment):", status, error);
        alert(
          "Une erreur est survenue lors de l'ajout du commentaire. Veuillez réessayer."
        );
      },
    });
  });

  // Helper function to generate star HTML (matches PHP's displayRatingStars)
  function displayRatingStarsJS(rating, size = "") {
    let html = '<div class="rating-stars ' + size + '">';
    for (let i = 1; i <= 5; i++) {
      if (i <= Math.floor(rating)) {
        html += '<i class="fas fa-star filled"></i>';
      } else if (
        i - 0.5 === Math.floor(rating) &&
        rating - Math.floor(rating) >= 0.5
      ) {
        html += '<i class="fas fa-star-half-alt filled"></i>';
      } else {
        html += '<i class="far fa-star"></i>';
      }
    }
    html += "</div>";
    return html;
  }

  // -------------------- AJAX for Favorite Company --------------------
  $(document).on(
    "click",
    ".company-card-actions .favorite-btn, .profile-page .favorite-btn",
    function () {
      const button = $(this);
      const companyId = button.data("company-id");

      $.ajax({
        url: "/PlateformeTourisme/ajax_handler.php",
        type: "POST",
        dataType: "json",
        data: {
          action: "toggle_favorite",
          company_id: companyId,
        },
        success: function (response) {
          if (response.success) {
            if (response.action === "followed") {
              button.removeClass("primary").addClass("secondary");
              button.html('<i class="fas fa-bookmark"></i> Favoris');
            } else if (response.action === "unfollowed") {
              button.removeClass("secondary").addClass("primary");
              button.html(
                '<i class="fas fa-bookmark"></i> Ajouter aux favoris'
              );
            }
            // Optionally update a favorite count if displayed somewhere
          } else {
            alert("Erreur: " + response.message);
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error (Favorite):", status, error);
          alert("Une erreur est survenue. Veuillez réessayer.");
        },
      });
    }
  );

  // -------------------- Profile Page Tabs --------------------
  $(".profile-tabs .tab-item").on("click", function () {
    const targetTab = $(this).data("tab");

    // Remove active class from all tabs and hide all content
    $(".profile-tabs .tab-item").removeClass("active");
    $(".profile-tab-content").hide();

    // Add active class to clicked tab and show its content
    $(this).addClass("active");
    $("#tab-" + targetTab).show();
  });

  // -------------------- AJAX for Adding Company Reviews --------------------
  $(document).on(
    "submit",
    ".profile-tab-content .review-form",
    function (event) {
      event.preventDefault();
      const form = $(this);
      const companyId = form.data("company-id");
      const reviewContent = form.find("#review_content").val().trim();
      const reviewRating = form.find("#review_rating").val();
      const reviewsList = form
        .closest(".profile-tab-content")
        .find(".reviews-list");
      const averageRatingDisplay = $(".company-rating span"); // Pour le texte "X (Y avis)"
      const averageRatingStars = $(".company-rating .rating-stars"); // Pour les étoiles

      if (reviewContent === "" || reviewRating === "") {
        alert("Veuillez écrire un avis et donner une note.");
        return;
      }

      $.ajax({
        url: "/PlateformeTourisme/ajax_handler.php",
        type: "POST",
        dataType: "json",
        data: {
          action: "add_comment", // Réutilise la fonction addCommentAndRating qui gère aussi les notes
          company_id: companyId,
          comment_content: reviewContent,
          rating: reviewRating,
        },
        success: function (response) {
          if (response.success) {
            form.find("#review_content").val("");
            form.find("#review_rating").val("");
            alert(response.message);

            // Recharger la section avis (simple rechargement de page pour cet exemple)
            // Ou implémenter un ajout dynamique comme pour les commentaires de publication
            location.reload(); // Pour l'instant, un rechargement simple est suffisant pour voir l'effet
            // En production, on ferait un appel AJAX pour récupérer les avis mis à jour
            // et mettre à jour la note moyenne et la liste dynamiquement.
          } else {
            alert("Erreur: " + response.message);
          }
        },
        error: function (xhr, status, error) {
          console.error("AJAX Error (Add Review):", status, error);
          alert(
            "Une erreur est survenue lors de l'ajout de l'avis. Veuillez réessayer."
          );
        },
      });
    }
  );

  // -------------------- AJAX for Reporting Content --------------------
  $(document).on("click", ".report-btn", function () {
    const userIdToReport = $(this).data("user-id");
    const confirmReport = confirm(
      "Êtes-vous sûr de vouloir signaler cet utilisateur ?"
    );

    if (confirmReport) {
      // Pour simplifier, nous signalons toujours comme "profil_user"
      // Dans une vraie app, un modal avec différentes raisons et types de contenu serait mieux.
      const raison = prompt(
        "Veuillez indiquer la raison du signalement (obligatoire) :"
      );
      if (raison && raison.trim() !== "") {
        $.ajax({
          url: "/PlateformeTourisme/ajax_handler.php",
          type: "POST",
          dataType: "json",
          data: {
            action: "report_content",
            type_contenu: "profil_user", // Ou déterminer dynamiquement 'profil_entreprise'
            id_contenu_signale: userIdToReport,
            raison: raison,
          },
          success: function (response) {
            if (response.success) {
              alert(response.message);
            } else {
              alert("Erreur lors du signalement : " + response.message);
            }
          },
          error: function (xhr, status, error) {
            console.error("AJAX Error (Report):", status, error);
            alert("Impossible de signaler le contenu. Veuillez réessayer.");
          },
        });
      } else {
        alert("La raison du signalement est obligatoire.");
      }
    }
  });
});
