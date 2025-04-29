<?php
session_start();
require_once  '../../auth/db.php';
require_once '../../auth/auth.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header('Location: ../../auth/login.php');
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
            header('Location: ../../includes/gestion/gestion.php');
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
        header('Location: ../../includes/gestion/gestion.php');
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
    <style>
        /* Variables CSS pour cohérence avec tabBordenseignant.css */
        :root {
            --primary-color: #4a6fdc;
            --primary-hover: #3a5fc6;
            --gray-light: #e5e7eb;
            --gray-medium: #d1d5db;
            --gray-dark: #6b7280;
            --background: #f3f4f6;
            --white: #ffffff;
            --success-bg: #d4edda;
            --success-border: #c3e6cb;
            --success-text: #155724;
            --danger-bg: #f8d7da;
            --danger-border: #f5c6cb;
            --danger-text: #721c24;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.2s ease;
        }

        /* Reset et base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: var(--background);
            color: #1f2937;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            background-color: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            transition: var(--transition);
        }

        .header .logo:hover {
            transform: scale(1.05);
        }

        .btn-back {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-medium);
            border-radius: 0.375rem;
            color: var(--gray-dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-back:hover {
            background-color: var(--gray-light);
            transform: translateY(-2px);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
        }

        /* Form Container */
        .form-container {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .form-container:hover {
            transform: translateY(-5px);
        }

        .form-container h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        /* Form Group */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--gray-dark);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-medium);
            border-radius: 0.375rem;
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 111, 220, 0.2);
        }

        .form-control::placeholder {
            color: var(--gray-medium);
        }

        select.form-control {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 12l-6-6h12l-6 6z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        /* Submit Button */
        .btn-submit {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            border: none;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        /* Alerts */
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            opacity: 0;
            animation: fadeIn 0.3s ease forwards;
        }

        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-text);
            border: 1px solid var(--success-border);
        }

        .alert-danger {
            background-color: var(--danger-bg);
            color: var(--danger-text);
            border: 1px solid var(--danger-border);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .btn-back {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .btn-submit {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">EduShare</div>
        <a href="gestion.php" class="btn-back">Retour à la gestion</a>
    </div>

    <div class="main-content">
        <div class="form-container">
            <h2><?php echo isset($sous_categorie) ? 'Modifier une sous-catégorie' : 'Nouvelle sous-catégorie'; ?></h2>
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            <form action="sous_categorie.php" method="POST">
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
                <button type="submit" class="btn-submit"><?php echo isset($sous_categorie) ? 'Modifier' : 'Ajouter'; ?></button>
            </form>
        </div>
    </div>
</body>
</html>