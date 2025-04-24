<?php
session_start();
require_once '../../auth/db.php';
require '../../auth/auth.php';


?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Tableau de bord</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f5f5f5;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: rgb(196, 197, 208);;
            border-bottom: 1px solidrgb(157, 160, 167);
        }

        header .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
        }

        header nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        header nav a {
            text-decoration: none;
            color: #4b5563;
            font-size: 16px;
        }

        header nav a.active {
            font-weight: bold;
        }

        header .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        header .user-profile .notification {
            background-color: #22c55e;
            color: white;
            padding: 2px 6px;
            border-radius: 50%;
            font-size: 12px;
        }

        header .user-profile .user-name {
            font-weight: bold;
            color: #1f2937;
        }

        header .user-profile .user-role {
            font-size: 14px;
            color: #6b7280;
        }

        .container {
            display: flex;
            min-height: calc(100vh - 60px);
        }

        aside {
            width: 250px;
            background-color:rgb(196, 197, 208);
            padding: 20px;
            border-right: 1px solid #e5e7eb;
        }

        aside h2 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #1f2937;
        }

        aside ul {
            list-style: none;
        }

        aside ul li {
            margin-bottom: 15px;
        }

        aside ul li a {
            text-decoration: none;
            color: #4b5563;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        aside ul li a.active {
            font-weight: bold;
        }

        aside h3 {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0 10px;
            color: #1f2937;
        }

        aside .categories li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            color: #4b5563;
            margin-bottom: 10px;
        }

        aside .categories .count {
            background-color: #000;
            color: white;
            padding: 2px 8px;
            border-radius: 50%;
            font-size: 12px;
        }

        aside .see-all {
            display: block;
            margin-top: 10px;
            color: #22c55e;
            text-decoration: none;
            font-size: 14px;
        }

        aside .see-all:hover {
            text-decoration: underline;
        }

        main {
            flex: 1;
            padding: 20px;
        }

        main h1 {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #1f2937;
        }

        main .tabs-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        main .tabs {
            display: flex;
            gap: 10px;
        }

        main .tabs a {
            padding: 8px 16px;
            text-decoration: none;
            color: #4b5563;
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 9999px;
            font-size: 14px;
        }

        main .tabs a.active {
            background-color: #f3f4f6;
            font-weight: bold;
        }

        main .publish-btn {
            background-color: #1f2937;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        main .publish-btn:hover {
            background-color: #374151;
        }

        .resources {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .resource-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .resource-card .content {
            display: flex;
            gap: 16px;
        }

        .resource-card .icon {
            font-size: 24px;
        }

        .resource-card h3 {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #1f2937;
        }

        .resource-card .meta {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .resource-card .description {
            color: #4b5563;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .resource-card .rating {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #6b7280;
        }

        .resource-card .rating .star.filled {
            color: #f59e0b;
        }

        .resource-card .rating .star {
            color: #d1d5db;
        }

        .resource-card .view-btn {
            padding: 6px 12px;
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            text-decoration: none;
            color: #4b5563;
            font-size: 14px;
        }

        .resource-card .view-btn:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <span>EduShare</span>
        </div>
        <nav>
            <a href="tableau_bord_enseignant.php" class="active">Tableau de bord</a>
            <a href="categorie.html">Catégories</a>
         
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
            
            <h2> ☰       Menu</h2>
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