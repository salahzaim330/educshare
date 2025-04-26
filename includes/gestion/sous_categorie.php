<?php
session_start();
$connexion = require_once '../../auth/db.php';

// Vérifier si l'utilisateur est connecté et a les droits nécessaires
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header('Location: ../../auth/login.php');
    exit();
}

// Récupérer toutes les catégories pour le formulaire
$categories = $connexion->query('SELECT * FROM Categorie')->fetchAll();

// Ajout ou modification d'une sous-catégorie
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $description = trim($_POST['description']);
    $id_categorie = $_POST['id_categorie'];
    $id_enseignant = $_SESSION['user_id'];

    if (isset($_POST['id_s_categorie']) && !empty($_POST['id_s_categorie'])) {
        // Modification
        $stmt = $connexion->prepare('UPDATE Sous_categorie SET nom = :nom, description = :description, id_categorie = :id_categorie WHERE id_s_categorie = :id');
        $stmt->execute([
            ':nom' => $nom,
            ':description' => $description,
            ':id_categorie' => $id_categorie,
            ':id' => $_POST['id_s_categorie']
        ]);
    } else {
        // Ajout
        $stmt = $connexion->prepare('INSERT INTO Sous_categorie (nom, description, id_categorie, id_enseignant) VALUES (:nom, :description, :id_categorie, :id_enseignant)');
        $stmt->execute([
            ':nom' => $nom,
            ':description' => $description,
            ':id_categorie' => $id_categorie,
            ':id_enseignant' => $id_enseignant
        ]);
    }
    header('Location: gestion.php');
    exit();
}

// Suppression d'une sous-catégorie
if (isset($_GET['delete'])) {
    $stmt = $connexion->prepare('DELETE FROM Sous_categorie WHERE id_s_categorie = :id');
    $stmt->execute([':id' => $_GET['delete']]);
    header('Location: gestion.php');
    exit();
}

// Charger les données pour modification
$sous_categorie = null;
if (isset($_GET['edit'])) {
    $stmt = $connexion->prepare('SELECT * FROM Sous_categorie WHERE id_s_categorie = :id');
    $stmt->execute([':id' => $_GET['edit']]);
    $sous_categorie = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Gestion des sous-catégories</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
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
            background-color: #000;
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
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
        <a href="gestion.php" class="btn btn-outline-secondary">Retour au tableau de bord</a>
    </div>

    <div class="main-content">
        <div class="form-container">
            <h2><?php echo isset($sous_categorie) ? 'Modifier une sous-catégorie' : 'Nouvelle sous-catégorie'; ?></h2>
            <form action="g_sous_categorie.php" method="post">
                <?php if (isset($sous_categorie)): ?>
                    <input type="hidden" name="id_s_categorie" value="<?php echo $sous_categorie['id_s_categorie']; ?>">
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
                            <option value="<?php echo $categorie['id_categorie']; ?>" <?php echo isset($sous_categorie) && $sous_categorie['id_categorie'] == $categorie['id_categorie'] ? 'selected' : ''; ?>>
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

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>