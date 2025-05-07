<?php
session_start();
require_once '../../auth/db.php';
require_once '../../auth/auth.php';

// Vérifier si l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header("Location: ../../auth/login.php");
    exit;
}

$user_id = (int) $_SESSION['id'];

// Générer un jeton CSRF si non défini
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les informations de l'utilisateur
$stmt = $connexion->prepare("SELECT * FROM enseignant WHERE id = :id");
$stmt->execute(['id' => $user_id]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    header("Location: ../../auth/login.php");
    exit;
}

// Traitement du formulaire de mise à jour du profil
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert alert-danger">Jeton CSRF invalide.</div>';
    } else {
        $nom = htmlspecialchars(trim($_POST['nom']));
        $prenom = htmlspecialchars(trim($_POST['prenom']));
        $email = htmlspecialchars(trim($_POST['email']));
        $matiere = htmlspecialchars(trim($_POST['matiere']));

        if (empty($nom) || empty($prenom) || empty($email) || empty($matiere)) {
            $message = '<div class="alert alert-danger">Tous les champs sont obligatoires.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Format d\'email invalide.</div>';
        } elseif (strlen($nom) > 100 || strlen($prenom) > 100 || strlen($matiere) > 100) {
            $message = '<div class="alert alert-danger">Les champs nom, prénom et matière ne doivent pas dépasser 100 caractères.</div>';
        } else {
            // Vérifier si l'email est déjà utilisé
            $check_email = $connexion->prepare("SELECT id FROM Enseignant WHERE email = :email AND id != :id");
            $check_email->execute(['email' => $email, 'id' => $user_id]);

            if ($check_email->rowCount() > 0) {
                $message = '<div class="alert alert-danger">Cet email est déjà utilisé par un autre compte.</div>';
            } else {
                $update = $connexion->prepare("UPDATE enseignant SET nom = :nom, prenom = :prenom, email = :email, matiere = :matiere WHERE id = :id");
                $success = $update->execute([
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'matiere' => $matiere,
                    'id' => $user_id
                ]);

                if ($success) {
                    $_SESSION['username'] = $nom;
                    $_SESSION['prenom'] = $prenom;
                    $_SESSION['email'] = $email;
                    $message = '<div class="alert alert-success">Votre profil a été mis à jour avec succès.</div>';

                    // Recharger les données
                    $stmt = $connexion->prepare("SELECT * FROM enseignant WHERE id = :id");
                    $stmt->execute(['id' => $user_id]);
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $message = '<div class="alert alert-danger">Une erreur est survenue lors de la mise à jour de votre profil.</div>';
                }
            }
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Traitement du formulaire de changement de mot de passe
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert alert-danger">Jeton CSRF invalide.</div>';
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        $check_pwd = $connexion->prepare("SELECT mdps FROM Enseignant WHERE id = :id");
        $check_pwd->execute(['id' => $user_id]);
        $user = $check_pwd->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($current_password, $user['mdps'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) < 8) {
                    $message = '<div class="alert alert-danger">Le mot de passe doit comporter au moins 8 caractères.</div>';
                } elseif (!preg_match('/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/', $new_password)) {
                    $message = '<div class="alert alert-danger">Le mot de passe doit contenir des lettres et des chiffres.</div>';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_pwd = $connexion->prepare("UPDATE enseignant SET mdps = :mdps WHERE id = :id");
                    $success = $update_pwd->execute([
                        'mdps' => $hashed_password,
                        'id' => $user_id
                    ]);

                    if ($success) {
                        $message = '<div class="alert alert-success">Votre mot de passe a été changé avec succès.</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Une erreur est survenue lors du changement de mot de passe.</div>';
                    }
                }
            } else {
                $message = '<div class="alert alert-danger">Les nouveaux mots de passe ne correspondent pas.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Le mot de passe actuel est incorrect.</div>';
        }
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Profil Enseignant</title>
    <link rel="stylesheet" href="../../assets/css/tabBordetudiant.css">
    <style>
       .profile-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .profile-section {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            flex: 1;
            min-width: 300px;
        }
        
        .profile-section h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn {
            background-color: #4a6fdc;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn:hover {
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
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <span>EduShare</span>
        </div>
        <nav>
            <a href="tableau_bord.php">Tableau de bord</a>
            <a href="../enseignant/categories.php">Catégories</a>
            <a href="../../includes/gestion/gestion.php">Gestion</a>
        </nav>
        <div class="user-profile">
            <span class="notification">0</span>
            <div style="width: 32px; height: 32px; background-color: #e5e7eb; border-radius: 50%;"></div>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['username']); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($_SESSION['user_type']); ?></span>
        </div>
    </header>

    <div class="container">
        <aside>
            <h2>☰ Menu</h2>
            <ul>
                <li><a href="../enseignant/tableau_bord.php"><span>📊</span> Tableau de bord</a></li>
                <li><a href="notifications.php"><span>🔔</span> Notifications <span >0</span></a></li>
                <li><a href="profile.php" class="active"><span>👤</span> Profil</a></li>
                <li><a href="../../includes/publier/publier.php"><span>⬆</span> Publier</a></li>
                <li><a href="../../auth/deconnexion.php"><span>➡️</span> Déconnexion</a></li>
            </ul>
        </aside>

        <main>
            <h1>Mon Profil</h1>
            <?php if (!empty($message)): ?>
                <?php echo $message; ?>
            <?php endif; ?>
            <div class="profile-container">
                <div class="profile-section">
                    <h2>Informations personnelles</h2>
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user_data['nom']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user_data['prenom']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="matiere">Matière enseignée</label>
                            <input type="text" id="matiere" name="matiere" value="<?php echo htmlspecialchars($user_data['matiere']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn">Mettre à jour</button>
                    </form>
                </div>

                <div class="profile-section">
                    <h2>Changer de mot de passe</h2>
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn">Changer le mot de passe</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
