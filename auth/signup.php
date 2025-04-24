<?php
session_start();
$connexion = require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $prenom = trim($_POST['prenom']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];
    $niveau = $_POST['niveau'];

    // Vérification du mot de passe
    if ($password !== $cpassword) {
        echo "Les mots de passe ne correspondent pas";
    } else {
        // Vérifier si l'email existe déjà
        $check = $connexion->prepare('SELECT id FROM etudiant WHERE email = :email');
        $check->execute(['email' => $email]);

        if ($check->rowCount() > 0) {
            echo "Cet e-mail est déjà utilisé.";
        } else {
            // Hasher le mot de passe
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insertion des données dans la base
            $s = $connexion->prepare('INSERT INTO etudiant(nom, prenom, email, mdps, niveau_etude) 
                                      VALUES(:nom, :prenom, :email, :mdps, :niveau_etude)');

            $success = $s->execute([
                ':nom' => $nom,
                ':prenom' => $prenom,
                ':email' => $email,
                ':mdps' => $hashedPassword,
                ':niveau_etude' => $niveau
            ]);

            if ($success) {
                // Rediriger vers le tableau de bord
                header('Location: login.php');
                exit();
            } else {
                echo "Erreur lors de l'inscription.";
            }
        }
    }
}
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Inscription</title>
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/signup.css">
</head>
<body>
    <!-- En-tête -->
    <div class="header">
        <div class="logo">
            <a href="../index.html">
                <i class="fas fa-book"></i> EduShare
            </a>
        </div>
        <div class="buttons">
            <a href="login.php"><button class="btn btn-outline-secondary">Connexion</button></a>
            <a href="signup.php"><button class="btn btn-dark">Inscription</button></a>
        </div>
    </div>

    <!-- Contenu principal -->
    <div class="main-content">
        <div class="signup-form">
            <h2>Créer un compte</h2>
            <p>Rejoignez notre communauté éducative pour partager et découvrir des ressources pédagogiques.</p>
            <form action="signup.php" method="post">
            <div class="form-row">
                <div class="form-group">
                    <label for="first-name">Prénom</label>
                    <input type="text" name="prenom" id="first-name" placeholder="hicham" required>
                </div>
                <div class="form-group">
                    <label for="last-name">Nom</label>
                    <input type="text"name="nom"  id="last-name" placeholder="chakir" required>
                </div>
            </div>
            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" name="email" id="email" placeholder="hicham.chakir@exemple.com" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label for="confirm-password">Confirmer le mot de passe</label>
                <input type="password" name="cpassword" id="confirm-password" required>
            </div>
            <div class="form-group">
                <label for="study-level">Niveau d'étude</label>
                <select name="niveau" id="study-level" required>
                    <option value="" disabled selected>Sélectionnez votre niveau</option>
                    <option value="primaire">Primaire</option>
                    <option value="college">Collège</option>
                    <option value="lycee">Lycée</option>
                    <option value="universite">Université</option>
                </select>
            </div>
            <button class="btn-signup">S'inscrire</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap 4 JS et dépendances -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>