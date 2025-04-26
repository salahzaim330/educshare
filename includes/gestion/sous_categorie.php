<?php
session_start();
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/auth.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header('Location: /auth/login.php');
    exit;
}

// Générer jeton CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer toutes les catégories
try {
    $categories = $connexion->query('SELECT * FROM Categorie')->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur DB: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
    $message = '<div class="alert alert-danger">Erreur lors du chargement des catégories.</div>';
}

// Ajout ou modification d'une sous-catégorie
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $nom = trim($_POST['nom']);
        $description = trim($_POST['description']);
        $id_categorie = (int)$_POST['id_categorie'];
        $id_enseignant = $_SESSION['id'];

        if (empty($nom) || empty($description) || empty($id_categorie)) {
            $message = '<div class="alert alert-danger">Tous les champs sont obligatoires.</div>';
        } elseif (strlen($nom) > 100 || strlen($description) > 255) {
            $message = '<div class="alert alert-danger">Le nom ou la description est trop long.</div>';
        } else {
            if (isset($_POST['id_s_categorie']) && !empty($_POST['id_s_categorie'])) {
                // Modification
                $stmt = $connexion->prepare('UPDATE Sous_categorie SET nom = :nom, description = :description, id_categorie = :id_categorie WHERE id_s_categorie = :id');
                $stmt->execute([
                    ':nom' => $nom,
                    ':description' => $description,
                    ':id_categorie' => $id_categorie,
                    ':id' => $_POST['id_s_categorie']
                ]);
                $message = '<div class="alert alert-success">Sous-catégorie modifiée avec succès.</div>';
            } else {
                // Ajout
                $stmt = $connexion->prepare('INSERT INTO Sous_categorie (nom, description, id_categorie, id_enseignant) VALUES (:nom, :description, :id_categorie, :id_enseignant)');
                $stmt->execute([
                    ':nom' => $nom,
                    ':description' => $description,
                    ':id_categorie' => $id_categorie,
                    ':id_enseignant' => $id_enseignant
                ]);
                $message = '<div class="alert alert-success">Sous-catégorie ajoutée avec succès.</div>';
            }
            header('Location: /includes/gestion/gestion.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erreur DB: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
        $message = '<div class="alert alert-danger">Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = '<div class="alert alert-danger">Jeton CSRF invalide.</div>';
}

// Suppression d'une sous-catégorie
if (isset($_GET['delete'])) {
    try {
        $stmt = $connexion->prepare('DELETE FROM Sous_categorie WHERE id_s_categorie = :id');
        $stmt->execute([':id' => $_GET['delete']]);
        $message = '<div class="alert alert-success">Sous-catégorie supprimée avec succès.</div>';
        header('Location: /includes/gestion/gestion.php');
        exit;
    } catch (PDOException $e) {
        error_log("Erreur DB: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
        $message = '<div class="alert alert-danger">Erreur lors de la suppression.</div>';
    }
}

// Charger les données pour modification
$sous_categorie = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $connexion->prepare('SELECT * FROM Sous_categorie WHERE id_s_categorie = :id');
        $stmt->execute([':id' => $_GET['edit']]);
        $sous_categorie = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur DB: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
        $message = '<div class="alert alert-danger">Erreur lors du chargement de la sous-catégorie.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Gestion des sous-catégories</title>
    <link rel="stylesheet" href="/assets/css/gestionplatform.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 40px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .header .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .main-content {
            padding: 40px;
        }
        .form-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        .btn-back {
            margin-bottom: 20px;
        }
        .btn-submit {
            background-color: #4a6fdc;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            border: none;
        }
        .btn-submit:hover {
            background-color: #3a5fc6;
        }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        @media (max-width: 576px) {
            .main-content {
                padding: 20px;
            }
            .form-container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">EduShare</div>
        <a href="gestion.php" class="btn btn-outline-secondary">Retour à la gestion</a>
    </div>

    <div class="main-content">
        <div class="form-container">
            <h2><?php echo isset($sous_categorie) ? 'Modifier une sous-catégorie' : 'Nouvelle sous-catégorie'; ?></h2>
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            <form action="/includes/gestion/sous_categorie.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <?php if (isset($sous_categorie)): ?>
                    <input type="hidden" name="id_s_categorie" value="<?php echo htmlspecialchars($sous_categorie['id_s_categorie']); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="nom">Nom de la sous-catégorie</label>
                    <input type="text" class="form-control" name="nom" id="nom" value="<?php echo isset($sous_categorie) ? htmlspecialchars($sous_categorie['nom']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="id_categorie">Catégorie parente</label>
                    <select class="form-control" name="id_categorie" id="id_categorie" required>
                        <option value="" disabled <?php echo !isset($sous_categorie) ? 'selected' : ''; ?>>Sélectionnez une catégorie</option>
                        <?php foreach ($categories as $categorie): ?>
                            <option value="<?php echo htmlspecialchars($categorie['id_categorie']); ?>" <?php echo isset($sous_categorie) && $sous_categorie['id_categorie'] == $categorie['id_categorie'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categorie['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" name="description" id="description" rows="3" required><?php echo isset($sous_categorie) ? htmlspecialchars($sous_categorie['description']) : ''; ?></textarea>
                </div>
                <button type="submit" class="btn btn-submit"><?php echo isset($sous_categorie) ? 'Modifier' : 'Ajouter'; ?></button>
            </form>
        </div>
    </div>
</body>
</html>