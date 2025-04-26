<?php
session_start();
require_once '../../auth/db.php';
require '../../auth/auth.php';

// Vérifier que l'utilisateur est connecté et est soit enseignant soit étudiant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['etudiant', 'enseignant'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Récupérer les publications de l'utilisateur connecté
try {
    $userId = $_SESSION['id'];
    $userType = $_SESSION['user_type'];
    
    $query = "SELECT p.*, s.nom AS sous_categorie, c.nom AS categorie 
              FROM Publication p
              JOIN Sous_categorie s ON p.id_s_categorie = s.id_s_categorie
              JOIN Categorie c ON s.id_categorie = c.id_categorie
              WHERE ";
    
    // Différencier selon le type d'utilisateur
    if ($userType === 'enseignant') {
        $query .= "p.id_enseignant = :userId";
    } else {
        $query .= "p.id_etudiant = :userId";
    }
    
    $query .= " ORDER BY p.date_pub DESC";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $publications = [];
    $_SESSION['error'] = "Erreur lors de la récupération des publications : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Mes Publications</title>
    <link rel="stylesheet" href="../../assets/css/tabBordetudiant.css">
    <style>
        /* Styles supplémentaires */
        .resource-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .no-publications {
            text-align: center;
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .publish-btn {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 1rem;
        }
        
        /* Styles pour les étoiles */
        .star {
            color: #e5e7eb;
        }
        .star.filled {
            color: #f59e0b;
        }
        .star.half {
            background: linear-gradient(90deg, #f59e0b 50%, #e5e7eb 50%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .content {
            display: flex;
            gap: 1rem;
            flex-grow: 1;
        }
        
        .icon {
            font-size: 2rem;
        }
        
        .view-btn {
            align-self: center;
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <span>EduShare</span>
        </div>
        <nav>
            <a href="tableau_bord.php" class="active">Tableau de bord</a>
            <a href="../../includes/categorie/categorie.php">Catégories</a>
        </nav>
        <div class="user-profile">
            <span class="notification">3</span>
            <div style="width: 32px; height: 32px; background-color: #e5e7eb; border-radius: 50%;"></div>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['prenom']. ' '.$_SESSION['username']) ?></span>
            <span class="user-role"><?php echo htmlspecialchars($_SESSION['user_type']) ?></span>     
        </div>
    </header>

    <div class="container">
        <aside>
            <h2> ☰ Menu</h2>
            <ul>
                <li><a href="tableau_bord.php" class="active"><span>📊</span> Tableau de bord</a></li>
                <li><a href="profile.php"><span>👤</span> Profil</a></li>
                <li><a href="../../includes/publier/publier.php"><span>⬆</span> Publier</a></li>
                <li><a href="../../auth/deconnexion.php"><span>➡️</span> Déconnexion</a></li>
            </ul>
        </aside>

        <main>
            <h1>Mes Publications</h1>
            <div class="tabs-container">
                <nav class="tabs">
                    <a href="#" class="active"><strong>Mes publications</strong></a>
                </nav>
                <a href="../../includes/publier/publier.php" class="publish-btn"><span>⬆</span> Publier une ressource</a>
            </div>

            <div class="resources">
                <?php if (empty($publications)): ?>
                    <div class="no-publications">
                        <p>Vous n'avez pas encore publié de ressources.</p>
                        <a href="../../includes/publier/publier.php" class="publish-btn">Publier ma première ressource</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($publications as $pub): ?>
                        <div class="resource-card">
                            <div class="content">
                                <span class="icon">
                                    <?php 
                                    $extension = pathinfo($pub['contenu'], PATHINFO_EXTENSION);
                                    switch(strtolower($extension)) {
                                        case 'pdf': echo '📄'; break;
                                        case 'docx': echo '📝'; break;
                                        case 'pptx': echo '📊'; break;
                                        case 'mp4': case 'avi': echo '📹'; break;
                                        default: echo '📁';
                                    }
                                    ?>
                                </span>
                                <div>
                                    <h3><?php echo htmlspecialchars($pub['titre']) ?></h3>
                                    <p class="meta">
                                        Publié le <?php echo date('d/m/Y', strtotime($pub['date_pub'])) ?>
                                        • <?php echo htmlspecialchars($pub['categorie']) ?> / <?php echo htmlspecialchars($pub['sous_categorie']) ?>
                                    </p>
                                    <p class="description">
                                        <?php echo htmlspecialchars($pub['description']) ?>
                                    </p>
                                    <div class="rating">
                                        <?php
                                        $note = $pub['note'] ?? 0;
                                        $fullStars = floor($note);
                                        $hasHalfStar = ($note - $fullStars) >= 0.5;
                                        
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $fullStars) {
                                                echo '<span class="star filled">★</span>';
                                            } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                                                echo '<span class="star half">★</span>';
                                            } else {
                                                echo '<span class="star">★</span>';
                                            }
                                        }
                                        ?>
                                        <span>(<?php echo number_format($note, 1) ?>)</span>
                                        <?php if (isset($pub['download_count'])): ?>
                                            <span>• <?php echo $pub['download_count'] ?> téléchargements</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            
                            <a href="../../includes/categorie/subcategory_publications.php?id=<?php echo $pub['id_pub'] ?>" class="view-btn">Voir</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Gestion des onglets
            const tabs = document.querySelectorAll('.tabs a');
            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                });
            });

            // Confirmation avant suppression (si vous ajoutez cette fonctionnalité)
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer cette publication ?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>