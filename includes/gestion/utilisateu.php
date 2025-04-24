<?php
session_start();
$connexion = require_once 'db.php';

// Vérifier si l'utilisateur est connecté et a les droits nécessaires
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header('Location: login.php');
    exit();
}

// Modification d'un utilisateur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $type = $_POST['type'];

    $table = $type === 'Étudiant' ? 'Etudiant' : 'Enseignant';
    $stmt = $connexion->prepare("UPDATE $table SET nom = :nom, prenom = :prenom, email = :email WHERE id = :id");
    $stmt->execute([
        ':nom' => $nom,
        ':prenom' => $prenom,
        ':email' => $email,
        ':id' => $id
    ]);
    header('Location: gestion.php');
    exit();
}

// Suppression d'un utilisateur
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $type = $_GET['type'];

    $table = $type === 'Étudiant' ? 'Etudiant' : 'Enseignant';
    $stmt = $connexion->prepare("DELETE FROM $table WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: gestion.php');
    exit();
}

// Charger les données pour modification
$user = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $type = $_GET['type'];
    $table = $type === 'Étudiant' ? 'Etudiant' : 'Enseignant';
    $stmt = $connexion->prepare("SELECT * FROM $table WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Gestion des utilisateurs</title>
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
            <h2>Modifier un utilisateur</h2>
            <form action="g_utilisateur.php" method="post">
                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                <input type="hidden" name="type" value="<?php echo $_GET['type']; ?>">
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" class="form-control" name="nom" id="nom" value="<?php echo htmlspecialchars($user['nom']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" class="form-control" name="prenom" id="prenom" value="<?php echo htmlspecialchars($user['prenom']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <button type="submit" class="btn btn-submit">Modifier</button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>