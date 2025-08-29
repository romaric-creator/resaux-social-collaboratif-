<?php
$page_title = "Politique de Confidentialité";
require_once 'includes/auth.php'; // Pour BASE_URL
require_once 'includes/header.php';
?>

<div class="main-content static-page">
    <div class="card">
        <h1>Politique de Confidentialité</h1>
        <p>La présente Politique de Confidentialité décrit comment notre Plateforme de Tourisme (ci-après « nous », « notre » ou « la Plateforme ») collecte, utilise et protège vos informations personnelles.</p>

        <h2>1. Collecte des informations</h2>
        <p>Nous collectons différentes informations lorsque vous utilisez notre Plateforme, notamment :</p>
        <ul>
            <li><strong>Informations d'inscription :</strong> Nom, adresse email, mot de passe (haché), type d'utilisateur (particulier, entreprise, admin), localisation.</li>
            <li><strong>Informations de profil (pour les entreprises) :</strong> Description, secteur, horaires, téléphone, site web, coordonnées géographiques.</li>
            <li><strong>Contenus que vous publiez :</strong> Publications, images, vidéos, documents, commentaires, messages.</li>
            <li><strong>Données d'utilisation :</strong> Pages visitées, interactions avec les publications et profils, recherches effectuées, adresse IP, type de navigateur.</li>
            <li><strong>Informations de paiement :</strong> Pour les fonctionnalités publicitaires (non implémentées dans cette version de base), nous collecterions des informations de paiement via des passerelles sécurisées (nous ne stockerions pas les numéros de carte directement).</li>
        </ul>

        <h2>2. Utilisation des informations</h2>
        <p>Les informations collectées sont utilisées pour :</p>
        <ul>
            <li>Fournir, maintenir et améliorer notre Plateforme.</li>
            <li>Personnaliser votre expérience utilisateur et les recommandations.</li>
            <li>Traiter vos publications et vos annonces.</li>
            <li>Faciliter la communication entre utilisateurs et entreprises (messagerie).</li>
            <li>Gérer les comptes utilisateurs et l'authentification.</li>
            <li>Analyser l'utilisation de la Plateforme pour des améliorations futures (analytics).</li>
            <li>Détecter, prévenir et résoudre les problèmes techniques et de sécurité.</li>
            <li>Répondre à vos demandes et vous fournir un support client.</li>
            <li>Envoyer des notifications et des communications marketing (avec votre consentement).</li>
        </ul>

        <h2>3. Partage des informations</h2>
        <p>Nous ne partageons vos informations personnelles qu'avec des tiers dans les cas suivants :</p>
        <ul>
            <li>Avec votre consentement explicite.</li>
            <li>Avec des fournisseurs de services tiers qui nous aident à opérer la Plateforme (hébergement, analyse de données, services de messagerie), sous des accords de confidentialité stricts.</li>
            <li>Pour se conformer à une obligation légale, à une demande gouvernementale ou pour protéger nos droits et notre sécurité.</li>
            <li>Dans le cadre d'une fusion, acquisition ou vente d'actifs, vos informations pourraient être transférées à l'entité acquéreuse.</li>
        </ul>

        <h2>4. Sécurité des données</h2>
        <p>Nous mettons en œuvre des mesures de sécurité techniques et organisationnelles appropriées pour protéger vos informations contre l'accès non autorisé, la modification, la divulgation ou la destruction. Cela inclut le hachage des mots de passe (bcrypt), l'utilisation de connexions sécurisées (HTTPS) et la gestion des accès.</p>

        <h2>5. Vos droits</h2>
        <p>Conformément au RGPD et aux lois applicables, vous disposez de droits concernant vos données personnelles :</p>
        <ul>
            <li>Droit d'accès à vos données.</li>
            <li>Droit de rectification des données inexactes.</li>
            <li>Droit à l'effacement de vos données (droit à l'oubli).</li>
            <li>Droit à la limitation du traitement.</li>
            <li>Droit à la portabilité des données.</li>
            <li>Droit d'opposition au traitement.</li>
            <li>Droit de retirer votre consentement à tout moment.</li>
        </ul>
        <p>Pour exercer ces droits, veuillez nous contacter à l'adresse fournie sur notre page de contact.</p>

        <h2>6. Modifications de cette Politique</h2>
        <p>Nous nous réservons le droit de modifier cette Politique de Confidentialité à tout moment. Toute modification sera publiée sur cette page avec une date de mise à jour. Nous vous encourageons à consulter régulièrement cette page pour prendre connaissance des éventuelles modifications.</p>

        <p><em>Dernière mise à jour : <?php echo date('d M Y'); ?></em></p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
