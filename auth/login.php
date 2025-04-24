<?php
session_start();

$connexion = require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Vérification pour l'enseignant
    $ens = $connexion->prepare('SELECT * FROM enseignant WHERE email = :email');
    $ens->execute(['email' => $email]);
    $enseignant = $ens->fetch(PDO::FETCH_ASSOC);

    if ($enseignant && $password==$enseignant['mdps']) {
        $_SESSION['user_type']='enseignant';
        $_SESSION['username'] = $enseignant['nom'];
        $_SESSION['prenom']=$enseignant['prenom'];
        $_SESSION['id'] = $enseignant['id'];
        $_SESSION['email'] = $enseignant['email'];
        header('Location: ../pages/enseignant/tableau_bord.php');
        exit;
    }

    // Vérification pour l'étudiant
    $etud = $connexion->prepare('SELECT * FROM etudiant WHERE email = :email');
    $etud->execute(['email' => $email]);
    $etudiant = $etud->fetch(PDO::FETCH_ASSOC);

    if ($etudiant && password_verify($password, $etudiant['mdps'])) {
        $_SESSION['user_type']='etudiant';
        $_SESSION['username'] = $etudiant['nom'];
        $_SESSION['id'] = $etudiant['id'];
        $_SESSION['email'] = $etudiant['email'];
        $_SESSION['prenom']=$etudiant['prenom'];
        header('Location: ../pages/etudiant/tableau_bord.php');
        exit;
    }

    echo '<div class="text-center text-danger mt-3">Email ou mot de passe incorrect.</div>';
}
?>






<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Connexion</title>
    <!-- Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome pour les icônes -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
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
        <div class="login-form">
            <h2>Connexion</h2>
            <p>Connectez-vous à votre compte pour accéder à vos ressources et partager du contenu.</p>
            
            <form action="login.php" method="post">
            <div class="form-group">
                <label for="email">Adresse e-mail</label>
                <input type="email" name="email" id="email" placeholder="hicham.chakir@exemple.com" required>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" name="password" id="password" required>
                <a href="#" class="forgot-password">Mot de passe oublié ?</a>
            </div>  

            <button class="btn-login">Se connecter</button>
            </form>

            <div class="signup-link">
                Vous n'avez pas de compte ? <a href="signup.php">Inscrivez-vous</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap 4 JS et dépendances -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>