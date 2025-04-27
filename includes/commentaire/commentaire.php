<?php
session_start();
require_once '../../auth/db.php';
require_once '../../auth/auth.php';

// Vérifier que l'utilisateur est connecté et est un enseignant ou un étudiant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['enseignant', 'etudiant'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Générer un jeton CSRF si non défini
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifier que l'ID de la publication est fourni
if (!isset($_GET['id_pub']) || !filter_var($_GET['id_pub'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID de publication invalide.";
    header('Location: ../../pages/enseignant/tableau_bord_enseignant.php');
    exit();
}
$publicationId = (int)$_GET['id_pub'];

// Traiter la soumission d'un commentaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_submit'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Erreur de validation CSRF.";
    } else {
        $contenu = trim($_POST['contenu'] ?? '');

        if ($contenu) {
            try {
                $stmt = $connexion->prepare("
                    INSERT INTO Commentaire (contenu, date_com, id_enseignant, id_etudiant, id_pub)
                    VALUES (:contenu, CURDATE(), :id_enseignant, :id_etudiant, :id_pub)
                ");
                $stmt->execute([
                    ':contenu' => $contenu,
                    ':id_enseignant' => $_SESSION['user_type'] === 'enseignant' ? $_SESSION['id'] : null,
                    ':id_etudiant' => $_SESSION['user_type'] === 'etudiant' ? $_SESSION['id'] : null,
                    ':id_pub' => $publicationId
                ]);
                $_SESSION['success'] = "Commentaire ajouté avec succès.";
            } catch (PDOException $e) {
                error_log("Erreur ajout commentaire: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
                $_SESSION['error'] = "Erreur lors de l'ajout du commentaire.";
            }
        } else {
            $_SESSION['error'] = "Le commentaire ne peut pas être vide.";
        }
    }
    // Rediriger pour éviter la resoumission
    header("Location: commentaire.php?id_pub=" . $publicationId);
    exit();
}

// Récupérer les commentaires existants pour la publication
try {
    $stmt = $connexion->prepare("
        SELECT 
            c.contenu, 
            c.date_com, 
            COALESCE(e.prenom, et.prenom) AS prenom, 
            COALESCE(e.nom, et.nom) AS nom
        FROM Commentaire c
        LEFT JOIN Enseignant e ON c.id_enseignant = e.id
        LEFT JOIN Etudiant et ON c.id_etudiant = et.id
        WHERE c.id_pub = :id_pub
        ORDER BY c.date_com DESC
    ");
    $stmt->execute([':id_pub' => $publicationId]);
    $commentaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération commentaires: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
    $commentaires = [];
    $_SESSION['error'] = "Erreur lors de la récupération des commentaires.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commentaires - EduShare</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .comment-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .comment-form {
            margin-bottom: 20px;
        }
        .comment-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            resize: vertical;
            margin-bottom: 10px;
        }
        .comment-form button {
            background: #3b82f6;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .comment-form button:hover {
            background: #2563eb;
        }
        .comment-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .comment {
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 0;
        }
        .comment:last-child {
            border-bottom: none;
        }
        .comment p {
            margin: 5px 0;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        .success {
            color: green;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="comment-container">
        <h2>Commentaires</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <!-- Formulaire pour ajouter un commentaire -->
        <form class="comment-form" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <textarea name="contenu" rows="4" placeholder="Ajouter un commentaire..." required></textarea>
            <button type="submit" name="comment_submit">Commenter</button>
        </form>

        <!-- Liste des commentaires existants -->
        <div class="comment-list">
            <?php if (empty($commentaires)): ?>
                <p>Aucun commentaire pour cette publication.</p>
            <?php else: ?>
                <?php foreach ($commentaires as $comment): ?>
                    <div class="comment">
                        <p><strong><?php echo htmlspecialchars($comment['prenom'] . ' ' . $comment['nom'] ?? 'Anonyme'); ?></strong> - <?php echo date('d/m/Y', strtotime($comment['date_com'])); ?></p>
                        <p><?php echo htmlspecialchars($comment['contenu']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>