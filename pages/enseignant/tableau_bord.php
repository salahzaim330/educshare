<?php
session_start();
require '../../auth/db.php';
require '../../auth/auth.php';


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Tableau de bord</title>
    <link rel="stylesheet" href="../../assets/css/tabBordenseignant.css">
</head>
<body>
    <header>
        <div class="logo">
            <span>EduShare</span>
        </div>
        <nav>
            <a href="tableau_bord.php" class="active">Tableau de bord</a>
            <a href="categorie.html">Catégories</a>
            <a href="gestion.php">Gestion</a>
        </nav>
        <div class="user-profile">
            <span class="notification">3</span>
            <div style="width: 32px; height: 32px; background-color: #e5e7eb; border-radius: 50%;"></div>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['prenom']. ' '.$_SESSION['username']) ?></span>
            <span class="user-role"><?php echo htmlspecialchars( $_SESSION['user_type']) ?></span>     
        </div>
    </header>

    <div class="container">
        <aside>
            <h2>Menu</h2>
            <ul>
                <li><a href="tableau_bord_enseignant.php" class="active"><span>📊</span> Tableau de bord</a></li>
                <li><a href="profil.html"><span>👤</span> Profil</a></li>
                <li><a href="publier.php"><span>⬆</span> Publier</a></li>
                <li><a href="deconnexion.php"><span>➡️</span> Déconnexion</a></li>
            </ul>
            <h3>Catégories</h3>
            <ul class="categories">
                <li><span>Mathématiques</span><span class="count">12</span></li>
                <li><span>Physique</span><span class="count">8</span></li>
                <li><span>Informatique</span><span class="count">15</span></li>
            </ul>
            <a href="categorie.html" class="see-all">Voir toutes les catégories</a>
        </aside>
    
        <main>
            <h1>Tableau de bord</h1>
            <div class="tabs-container">
                <nav class="tabs">
                    
                    
                    <a href="#">Mes publications</a>
                </nav>
                <a href="publier.php" class="publish-btn"><span>⬆</span> Publier une ressource</a>
            </div>

            <div class="resources">
                <div class="resource-card">
                    <div class="content">
                        <span class="icon">📄</span>
                        <div>
                            <h3>Introduction à l'algèbre linéaire</h3>
                            <p class="meta">Par Marie Laurent • Mathématiques • Il y a 2 jours</p>
                            <p class="description">
                                Ce document présente les concepts fondamentaux de l'algèbre linéaire, incluant les vecteurs, les matrices et les transformations linéaires.
                            </p>
                            <div class="rating">
                                <span class="star filled">★</span>
                                <span class="star filled">★</span>
                                <span class="star filled">★</span>
                                <span class="star filled">★</span>
                                <span class="star">★</span>
                                <span>(4.2)</span>
                                <span>• 42 téléchargements</span>
                            </div>
                        </div>
                    </div>
                    <a href="view_publication.html?id=1" class="view-btn">Voir</a>
                </div>
                <div class="resource-card">
                    <div class="content">
                        <span class="icon">📹</span>
                        <div>
                            <h3>Tutoriel Python pour débutants</h3>
                            <p class="meta">Par Thomas Dubois • Informatique • Il y a 3 jours</p>
                            <p class="description">
                                Cette vidéo explique les bases de la programmation Python pour les débutants, avec des exemples pratiques et des exercices.
                            </p>
                            <div class="rating">
                                <span class="star filled">★</span>
                                <span class="star filled">★</span>
                                <span class="star filled">★</span>
                                <span class="star filled">★</span>
                                <span class="star filled">★</span>
                                <span>(4.8)</span>
                                <span>• 12 téléchargements</span>
                            </div>
                        </div>
                    </div>
                    <a href="view_publication.html?id=2" class="view-btn">Voir</a>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab switching functionality
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.tabs a');
            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    console.log('Tab switched to:', tab.textContent);
                });
            });
        });
    </script>
</body>
</html>