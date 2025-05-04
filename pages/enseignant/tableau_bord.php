<?php
session_start();
require_once '../../auth/db.php';
require '../../auth/auth.php';

// Vérifier que l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header('Location: ../../auth/login.php');
    exit();
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle note submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_note'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Erreur de validation CSRF.";
    } else {
        $publicationId = filter_input(INPUT_POST, 'id_pub', FILTER_VALIDATE_INT);
        $note = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_INT);

        if ($publicationId && $note >= 1 && $note <= 5) {
            try {
                $stmt = $connexion->prepare("
                    INSERT INTO Note (valeur, id_enseignant, id_pub, date_note)
                    VALUES (:valeur, :id_enseignant, :id_pub, CURDATE())
                    ON DUPLICATE KEY UPDATE valeur = :valeur_update, date_note = CURDATE()
                ");
                $stmt->execute([
                    ':valeur' => $note,
                    ':id_enseignant' => $_SESSION['id'],
                    ':id_pub' => $publicationId,
                    ':valeur_update' => $note
                ]);
                $_SESSION['success'] = "Note enregistrée avec succès.";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Erreur lors de l'enregistrement de la note : " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Note invalide ou publication introuvable.";
        }
    }
    header("Location: tableau_bord.php");
    exit();
}

// Récupérer les publications de l'enseignant connecté avec le nombre de commentaires et la moyenne des notes
try {
    $enseignantId = $_SESSION['id'];
    
    $query = "
        SELECT 
            p.id_pub, 
            p.titre, 
            p.description, 
            p.date_pub, 
            p.contenu, 
            p.id_enseignant, 
            p.id_s_categorie,
            s.nom AS sous_categorie, 
            c.nom AS categorie,
            COALESCE(com.comment_count, 0) AS comment_count,
            COALESCE(n.average_note, 0) AS average_note,
            LOWER(SUBSTRING_INDEX(p.contenu, '.', -1)) AS file_extension
        FROM Publication p
        JOIN Sous_categorie s ON p.id_s_categorie = s.id_s_categorie
        JOIN Categorie c ON s.id_categorie = c.id_categorie
        LEFT JOIN (
            SELECT id_pub, COUNT(*) AS comment_count
            FROM Commentaire
            GROUP BY id_pub
        ) com ON p.id_pub = com.id_pub
        LEFT JOIN (
            SELECT id_pub, AVG(valeur) AS average_note
            FROM Note
            GROUP BY id_pub
        ) n ON p.id_pub = n.id_pub
        WHERE p.id_enseignant = :enseignantId
        ORDER BY p.date_pub DESC
    ";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':enseignantId', $enseignantId, PDO::PARAM_INT);
    $stmt->execute();
    $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure absolute URLs for contenu
    foreach ($publications as &$pub) {
        if (!empty($pub['contenu']) && !preg_match('#^https?://#', $pub['contenu'])) {
            $pub['contenu'] = '/edushare/' . ltrim(str_replace('\\', '/', $pub['contenu']), '/');
        }
    }
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
    <title>EduShare - Tableau de bord Enseignant</title>
    <link rel="stylesheet" href="../../assets/css/tabBordenseignant.css">
   
</head>
<body>
    <header>
        <div class="logo">
            <span>EduShare</span>
        </div>
        <nav>
            <a href="tableau_bord.php" class="active">Tableau de bord</a>
            <a href="../../includes/categorie/categorie.php">Catégories</a>
            <a href="../../includes/gestion/gestion.php">Gestion</a>
        </nav>
        <div class="user-profile">
            <span class="notification">3</span>
            <div style="width: 32px; height: 32px; background-color: #e5e7eb; border-radius: 50%;"></div>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['username']); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($_SESSION['user_type']); ?></span>     
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
                <?php if (isset($_SESSION['error'])): ?>
                    <div style="color: red;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="success-message"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
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
                                    $extension = $pub['file_extension'] ?? pathinfo($pub['contenu'], PATHINFO_EXTENSION);
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
                                    <h3><?php echo htmlspecialchars($pub['titre']); ?></h3>
                                    <p class="meta">
                                        Publié le <?php echo date('d/m/Y', strtotime($pub['date_pub'])); ?>
                                        • <?php echo htmlspecialchars($pub['categorie']); ?> / <?php echo htmlspecialchars($pub['sous_categorie']); ?>
                                    </p>
                                    <p class="description">
                                        <?php echo htmlspecialchars($pub['description']); ?>
                                    </p>
                                    <div class="rating">
                                        <form method="POST" action="">
                                            <input type="hidden" name="id_pub" value="<?php echo $pub['id_pub']; ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="submit_note" value="1">
                                            <div class="stars-container flex">
                                                <?php
                                                $note = $pub['average_note'] ?? 0;
                                                $fullStars = floor($note);
                                                $hasHalfStar = ($note - $fullStars) >= 0.5;
                                                for ($i = 1; $i <= 5; $i++):
                                                ?>
                                                    <span
                                                        class="star <?php echo $i <= $fullStars ? 'filled' : ($i === $fullStars + 1 && $hasHalfStar ? 'half' : ''); ?>"
                                                        onclick="this.parentNode.parentNode.querySelector('input[name=note]').value=<?php echo $i; ?>; this.parentNode.parentNode.submit();"
                                                    >★</span>
                                                <?php endfor; ?>
                                                <input type="hidden" name="note" value="">
                                           
                                            <span>(<?php echo number_format($note, 1); ?>)</span> 
                                        </div>
                                    </form>
                                        <span>• <a href="#" class="comment-link" data-pub-id="<?php echo $pub['id_pub']; ?>"><?php echo $pub['comment_count']; ?> commentaire(s)</a></span>
                                    </div>
                                </div>
                            </div>
                            <div class="action-buttons">
                            
                                <a href="<?php echo htmlspecialchars($pub['contenu']); ?>" target="_blank" class="view-btn file-btn" onclick="console.log('Opening file:', '<?php echo $pub['contenu']; ?>')">Voir le fichier</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modale pour les commentaires -->
    <div class="modal" id="commentModal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <iframe id="commentFrame"></iframe>
        </div>
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

            // Gestion de la modale des commentaires
            const modal = document.getElementById('commentModal');
            const commentFrame = document.getElementById('commentFrame');
            const closeModal = document.querySelector('.close-modal');
            const commentLinks = document.querySelectorAll('.comment-link');

            commentLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const pubId = link.getAttribute('data-pub-id');
                    commentFrame.src = `../../includes/commentaire/commentaire.php?id_pub=${pubId}`;
                    modal.style.display = 'flex';
                });
            });

            closeModal.addEventListener('click', () => {
                modal.style.display = 'none';
                commentFrame.src = '';
            });

            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    commentFrame.src = '';
                }
            });

            // Confirmation avant suppression (si ajouté)
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    if (!confirm('Êtes-vous sûr de vouloir supprimer cette publication ?')) {
                        e.preventDefault();
                    }
                });
            });

            // Log to confirm view and file buttons are rendered
            const viewButtons = document.querySelectorAll('.view-btn:not(.file-btn)');
            const fileButtons = document.querySelectorAll('.view-btn.file-btn');
            console.log('View buttons rendered:', viewButtons.length);
            console.log('View file buttons rendered:', fileButtons.length);
        });
    </script>
</body>
</html>