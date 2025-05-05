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

// Gérer le marquage des notifications comme lues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notification_read'])) {
    $notificationId = filter_input(INPUT_POST, 'notification_id', FILTER_VALIDATE_INT);
    if ($notificationId) {
        try {
            $stmt = $connexion->prepare("UPDATE Notification SET status = 'read' WHERE id_notification = :id_notification AND id_etudiant = :id_etudiant");
            $stmt->execute([':id_notification' => $notificationId, ':id_etudiant' => $_SESSION['id']]);
            $_SESSION['success'] = "Notification marquée comme lue.";
        } catch (PDOException $e) {
            error_log("Erreur mise à jour notification: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
            $_SESSION['error'] = "Erreur lors de la mise à jour de la notification.";
        }
    }
    header("Location: notifications.php");
    exit();
}

// Gérer le marquage de toutes les notifications comme lues
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    try {
        $stmt = $connexion->prepare("UPDATE Notification SET status = 'read' WHERE id_etudiant = :id_etudiant AND status = 'unread'");
        $stmt->execute([':id_etudiant' => $_SESSION['id']]);
        $_SESSION['success'] = "Toutes les notifications ont été marquées comme lues.";
    } catch (PDOException $e) {
        error_log("Erreur mise à jour toutes notifications: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
        $_SESSION['error'] = "Erreur lors de la mise à jour des notifications.";
    }
    header("Location: notifications.php");
    exit();
}

// Récupérer toutes les notifications
try {
    $etudiantId = $_SESSION['id'];
    $stmt = $connexion->prepare("
        SELECT n.id_notification, n.contenu, n.date_notif, n.status, p.titre AS publication_titre
        FROM Notification n
        JOIN Publication p ON n.id_pub = p.id_pub
        WHERE n.id_etudiant = :id_etudiant
        ORDER BY n.date_notif DESC
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
    error_log("Erreur récupération notifications: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
    $notifications = [];
    $unread_count = 0;
    $_SESSION['error'] = "Erreur lors de la récupération des notifications.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Notifications</title>
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
            <a href="tableau_bord.php">Tableau de bord</a>
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
                <li><a href="tableau_bord.php"><span>📊</span> Tableau de bord</a></li>
                <li><a href="notifications.php" class="active"><span>🔔</span> Notifications <span class="notification"><?php echo $unread_count; ?></span></a></li>
                <li><a href="profile.php"><span>👤</span> Profil</a></li>
                <li><a href="../../includes/publier/publier.php"><span>⬆</span> Publier</a></li>
                <li><a href="../../auth/deconnexion.php"><span>➡️</span> Déconnexion</a></li>
            </ul>
        </aside>

        <main>
            <h1>Notifications</h1>
            <div class="tabs-container">
                <nav class="tabs">
                    <a href="#" class="active"><strong>Toutes les notifications</strong></a>
                </nav>
                <?php if ($unread_count > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="mark_all_read" value="1">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <button type="submit" class="publish-btn">Marquer tout comme lu</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="notifications">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="error-message"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="success-message"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (empty($notifications)): ?>
                    <div class="no-publications">
                        <p>Aucune notification reçue.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                        <div class="resource-card <?php echo $notif['status'] === 'unread' ? 'unread' : ''; ?>">
                            <div class="content">
                                <span class="icon">🔔</span>
                                <div>
                                    <h3><?php echo htmlspecialchars($notif['publication_titre']); ?></h3>
                                    <p class="meta">Reçu le <?php echo date('d/m/Y', strtotime($notif['date_notif'])); ?></p>
                                    <p class="description"><?php echo htmlspecialchars($notif['contenu']); ?></p>
                                    <?php if ($notif['status'] === 'unread'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="notification_id" value="<?php echo $notif['id_notification']; ?>">
                                            <input type="hidden" name="mark_notification_read" value="1">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <button type="submit" class="view-btn">Marquer comme lu</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>