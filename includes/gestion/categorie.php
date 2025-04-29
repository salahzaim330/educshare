<?php
session_start();
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/auth.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header('Location: ../../auth/login.php');
    exit;
}

// Générer jeton CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Vérifier si l'ID de la catégorie est fourni
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['message'] = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>ID de catégorie invalide.</div>';
    header('Location: gestion.php');
    exit;
}

$id_categorie = intval($_GET['id']);
$message = '';

// Charger les données de la catégorie
try {
    $stmt = $connexion->prepare('SELECT * FROM Categorie WHERE id_categorie = :id');
    $stmt->execute([':id' => $id_categorie]);
    $categorie = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$categorie) {
        $_SESSION['message'] = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Catégorie introuvable.</div>';
        header('Location: gestion.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Erreur DB: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
    $message = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Erreur lors du chargement de la catégorie.</div>';
}

// Modification de la catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $nom = trim($_POST['nom']);
        $description = trim($_POST['description']);

        // Validation des champs
        if (empty($nom) || empty($description)) {
            $message = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Tous les champs sont obligatoires.</div>';
        } elseif (strlen($nom) > 100 || strlen($description) > 255) {
            $message = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Le nom (max 100 caractères) ou la description (max 255 caractères) est trop long.</div>';
        } else {
            // Vérifier si le nom existe déjà pour une autre catégorie
            $stmt = $connexion->prepare('SELECT COUNT(*) FROM Categorie WHERE nom = :nom AND id_categorie != :id');
            $stmt->execute([':nom' => $nom, ':id' => $id_categorie]);
            if ($stmt->fetchColumn() > 0) {
                $message = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Une catégorie avec ce nom existe déjà.</div>';
            } else {
                // Mettre à jour la catégorie
                $stmt = $connexion->prepare('UPDATE Categorie SET nom = :nom, description = :description WHERE id_categorie = :id');
                $stmt->execute([
                    ':nom' => $nom,
                    ':description' => $description,
                    ':id' => $id_categorie
                ]);
                $_SESSION['message'] = '<div class="alert alert-success flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Catégorie modifiée avec succès.</div>';
                header('Location: gestion.php');
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur DB: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
        if ($e->getCode() == 23000) {
            $message = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Une catégorie avec ce nom existe déjà.</div>';
        } else {
            $message = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = '<div class="alert alert-danger flex items-center"><svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Jeton CSRF invalide.</div>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Modifier une catégorie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(to bottom, #f5f7fa, #e5e7eb);
            min-height: 100vh;
            margin: 0;
        }
        .header {
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .header .logo {
            font-size: 1.75rem;
            font-weight: 800;
            color: #1e40af;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .header .logo:hover {
            color: #1e3a8a;
        }
        .main-content {
            padding: 2rem 1rem;
            max-width: 800px;
            margin: 0 auto;
        }
        .form-container {
            background: #ffffff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .form-container:hover {
            transform: translateY(-4px);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4a6fdc;
            box-shadow: 0 0 0 3px rgba(74, 111, 220, 0.2);
        }
        .btn-submit {
            background: linear-gradient(135deg, #4a6fdc 0%, #3b82f6 100%);
            color: #ffffff;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #3a5fc6 0%, #2563eb 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            background: #6b7280;
            color: #ffffff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background: #4b5563;
            transform: translateY(-2px);
        }
        .btn-back svg {
            margin-right: 0.5rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease;
            font-size: 0.875rem;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        @media (max-width: 640px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }
            .main-content {
                padding: 1rem;
            }
            .form-container {
                padding: 1.5rem;
            }
            .header .logo {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="../../pages/enseignant/tableau_bord.php" class="logo">EduShare</a>
        <a href="gestion.php" class="btn-back">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Retour à la gestion
        </a>
    </div>

    <div class="main-content">
        <div class="form-container">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Modifier une catégorie</h2>
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            <form action="modifier_categorie.php?id=<?php echo htmlspecialchars($id_categorie); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="id_categorie" value="<?php echo htmlspecialchars($categorie['id_categorie']); ?>">
                <div class="form-group">
                    <label for="nom">Nom de la catégorie</label>
                    <input type="text" name="nom" id="nom" value="<?php echo htmlspecialchars($categorie['nom']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" rows="4" required><?php echo htmlspecialchars($categorie['description']); ?></textarea>
                </div>
                <button type="submit" class="btn-submit">Modifier</button>
            </form>
        </div>
    </div>
</body>
</html>