<?php
session_start();
require_once '../../auth/db.php';
require_once '../../auth/auth.php';

// Vérifier que l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'etudiant') {
    header('Location: ../../auth/login.php');
    exit();
}

// Générer un jeton CSRF si non défini
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gérer la soumission des notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_note'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Erreur de validation CSRF.";
    } else {
        $publicationId = filter_input(INPUT_POST, 'id_pub', FILTER_VALIDATE_INT);
        $note = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_INT);

        if ($publicationId && $note >= 1 && $note <= 5) {
            try {
                $stmt = $connexion->prepare("
                    INSERT INTO Note (valeur, id_etudiant, id_pub, date_note)
                    VALUES (:valeur, :id_etudiant, :id_pub, CURDATE())
                    ON DUPLICATE KEY UPDATE valeur = :valeur_update, date_note = CURDATE()
                ");
                $stmt->execute([
                    ':valeur' => $note,
                    ':id_etudiant' => $_SESSION['id'],
                    ':id_pub' => $publicationId,
                    ':valeur_update' => $note
                ]);
                $_SESSION['success'] = "Note enregistrée avec succès.";
            } catch (PDOException $e) {
                error_log("Erreur enregistrement note: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
                $_SESSION['error'] = "Erreur lors de l'enregistrement de la note.";
            }
        } else {
            $_SESSION['error'] = "Note invalide ou publication introuvable.";
        }
    }
    header("Location: tableau_bord.php");
    exit();
}

// Gérer le marquage des notifications comme lues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notification_read'])) {
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    if ($notificationId) {
        try {
            $stmt = $connexion->prepare("UPDATE Notification SET status = 'read' WHERE id_notification = :id_notification AND id_etudiant = :id_etudiant");
            $stmt->execute([':id_notification' => $notificationId, ':id_etudiant' => $_SESSION['id']]);
        } catch (PDOException $e) {
            error_log("Erreur mise à jour notification: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
        }
    }
    header("Location: tableau_bord.php");
    exit();
}

// Récupérer les publications de l'étudiant connecté
try {
    $etudiantId = $_SESSION['id'];
    $query = "
        SELECT 
            p.id_pub, 
            p.titre, 
            p.description, 
            p.date_pub, 
            p.contenu, 
            p.id_etudiant, 
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
        WHERE p.id_etudiant = :etudiantId
        ORDER BY p.date_pub DESC
    ";
    
    $stmt = $connexion->prepare($query);
    $stmt->bindParam(':etudiantId', $etudiantId, PDO::PARAM_INT);
    $stmt->execute();
    $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure absolute URLs for contenu
    foreach ($publications as &$pub) {
        if (!empty($pub['contenu']) && !preg_match('#^https?://#', $pub['contenu'])) {
            $pub['contenu'] = '/edushare/' . ltrim(str_replace('\\', '/', $pub['contenu']), '/');
        }
    }

    // Récupérer les notifications
    $stmt = $connexion->prepare("
        SELECT n.id_notification, n.contenu, n.date_notif, n.status, p.titre AS publication_titre
        FROM Notification n
        JOIN Publication p ON n.id_pub = p.id_pub
        WHERE n.id_etudiant = :id_etudiant
        ORDER BY n.date_notif DESC
        LIMIT 10
    ");
    $stmt->execute([':id_etudiant' => $etudiantId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compter les notifications non lues
    $stmt = $connexion->prepare("
        SELECT COUNT(*) AS unread_count
        FROM Notification
        WHERE id_etudiant = :id_etudiant AND status = 'unread'
    ");
    $stmt->execute([':id_etudiant' => $etudiantId]);
    $unread_count = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
} catch (PDOException $e) {
    error_log("Erreur récupération données: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
    $publications = [];
    $notifications = [];
    $unread_count = 0;
    $_SESSION['error'] = "Erreur lors de la récupération des données.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Tableau de bord Étudiant</title>
    <link rel="stylesheet" href="../../assets/css/tabBordetudiant.css">
    <link rel="stylesheet" href="../../assets/css/commentaireetudiant.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
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
            <span class="notification"><?php echo $unread_count; ?></span>
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
                <li><a href="notifications.php"><span>🔔</span> Notifications <span class="notification"><?php echo $unread_count; ?></span></a></li>
                <li><a href="profile.php"><span>👤</span> Profil</a></li>
                <li><a href="../../includes/publier/publier.php"><span>⬆</span> Publier</a></li>
                <li><a href="../../auth/deconnexion.php"><span>➡️</span> Déconnexion</a></li>
            </ul>
        </aside>

        <main>
            <h1>Tableau de bord</h1>
           
            <div class="tabs-container">
                <nav class="tabs">
                    <a href="#" class="active"><strong>Mes publications</strong></a>
                </nav>
                <a href="../../includes/publier/publier.php" class="publish-btn"><span>⬆</span> Publier une ressource</a>
            </div>

            <div class="resources">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="error-message"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
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
                                            <input type="hidden" name="note" value="">
                                            <div class="stars-container flex">
                                                <?php
                                                $note = $pub['average_note'] ?? 0;
                                                $fullStars = floor($note);
                                                $hasHalfStar = ($note - $fullStars) >= 0.5;
                                                for ($i = 1; $i <= 5; $i++):
                                                ?>
                                                    <span
                                                        class="star <?php echo $i <= $fullStars ? 'filled' : ($i === $fullStars + 1 && $hasHalfStar ? 'half' : ''); ?>"
                                                        data-value="<?php echo $i; ?>"
                                                    >★</span>
                                                <?php endfor; ?>
                                            </div>
                                            <span>(<?php echo number_format($note, 1); ?>)</span>
                                            <br>
                                        </form>
                                        <span>• <a href="#" class="comment-link" data-pub-id="<?php echo $pub['id_pub']; ?>"><?php echo $pub['comment_count']; ?> commentaires</a></span>
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
                    const iframeSrc = `../../includes/commentaire/commentaire.php?id_pub=${pubId}`;
                    console.log('Opening comment modal, iframe src:', iframeSrc);
                    commentFrame.src = iframeSrc;
                    modal.style.display = 'flex';
                    commentFrame.onload = () => {
                        console.log('Comment iframe loaded for pubId:', pubId);
                    };
                    commentFrame.onerror = () => {
                        console.error('Error loading iframe:', iframeSrc);
                        modal.querySelector('.modal-content').innerHTML += '<p style="color: red;">Erreur: Impossible de charger les commentaires.</p>';
                    };
                });
            });

            closeModal.addEventListener('click', () => {
                console.log('Close modal clicked');
                modal.style.display = 'none';
                commentFrame.src = '';
            });

            modal.addEventListener('click', (e) => {
                console.log('Modal clicked, target:', e.target.tagName, e.target.className);
                if (e.target === modal) {
                    modal.style.display = 'none';
                    commentFrame.src = '';
                }
            });

            // Gestion du clic sur l'iframe pour éviter de bloquer les clics
            commentFrame.addEventListener('click', () => {
                console.log('Iframe clicked, ensuring close button is accessible');
                closeModal.style.pointerEvents = 'auto';
                closeModal.style.zIndex = '2000';
            });

            // Gestion des étoiles pour la notation
            const starsContainers = document.querySelectorAll('.stars-container');
            starsContainers.forEach(container => {
                const stars = container.querySelectorAll('.star');
                const noteInput = container.parentNode.querySelector('input[name="note"]');
                const form = container.parentNode;

                stars.forEach(star => {
                    star.addEventListener('mouseover', () => {
                        const value = parseInt(star.getAttribute('data-value'));
                        stars.forEach((s, index) => {
                            if (index < value) {
                                s.classList.add('preview');
                            } else {
                                s.classList.remove('preview');
                            }
                        });
                    });

                    star.addEventListener('mouseout', () => {
                        stars.forEach(s => s.classList.remove('preview'));
                    });

                    star.addEventListener('click', (e) => {
                        e.preventDefault();
                        const value = parseInt(star.getAttribute('data-value'));
                        noteInput.value = value;
                        stars.forEach((s, index) => {
                            if (index < value) {
                                s.classList.add('filled');
                                s.classList.remove('half');
                            } else {
                                s.classList.remove('filled', 'half');
                            }
                        });
                        console.log('Submitting note:', value);
                        form.submit();
                    });
                });

                container.addEventListener('mouseout', () => {
                    stars.forEach(s => s.classList.remove('preview'));
                });
            });

            // Log to confirm view buttons are rendered
            const viewButtons = document.querySelectorAll('.view-btn.file-btn');
            console.log('View file buttons rendered:', viewButtons.length);
        });
    </script>
</body>
</html>