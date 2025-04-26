<?php
session_start();
require_once __DIR__ . '/../../auth/db.php';
require_once __DIR__ . '/../../auth/auth.php';

// Vérifier que l'utilisateur est connecté et est un enseignant
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'enseignant') {
    header('Location: /auth/login.php');
    exit;
}

// Générer jeton CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer données
try {
    $s1 = $connexion->query('SELECT c.*, e.nom AS enseignant_nom FROM Categorie c LEFT JOIN Enseignant e ON c.id_enseignant = e.id');
    $categories = $s1->fetchAll(PDO::FETCH_ASSOC);

    $s2 = $connexion->query('SELECT sc.*, c.nom AS categorie_nom FROM Sous_categorie sc LEFT JOIN Categorie c ON sc.id_categorie = c.id_categorie');
    $sCategories = $s2->fetchAll(PDO::FETCH_ASSOC);

    $s3 = $connexion->query('SELECT * FROM Enseignant');
    $enseignants = $s3->fetchAll(PDO::FETCH_ASSOC);

    $s4 = $connexion->query('SELECT * FROM Etudiant');
    $etudiants = $s4->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur DB: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
    $message = '<div class="alert alert-danger">Erreur lors du chargement des données.</div>';
}

// Traitement des formulaires
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        // Ajouter une catégorie
        if (isset($_POST['nom_categorie'], $_POST['description_categorie'])) {
            $nom_cat = trim($_POST['nom_categorie']);
            $description_cat = trim($_POST['description_categorie']);

            if (empty($nom_cat) || empty($description_cat)) {
                $message = '<div class="alert alert-danger">Tous les champs sont obligatoires.</div>';
            } elseif (strlen($nom_cat) > 100 || strlen($description_cat) > 255) {
                $message = '<div class="alert alert-danger">Le nom ou la description est trop long.</div>';
            } else {
                $ins1 = $connexion->prepare('INSERT INTO Categorie (nom, description, id_enseignant) VALUES (:nom, :description, :id_enseignant)');
                $ins1->execute([
                    'nom' => $nom_cat,
                    'description' => $description_cat,
                    'id_enseignant' => $_SESSION['id']
                ]);
                $message = '<div class="alert alert-success">Catégorie ajoutée avec succès.</div>';
                header('Location: /includes/gestion/gestion.php');
                exit;
            }
        }

        // Ajouter une sous-catégorie
        if (isset($_POST['nom_scat'], $_POST['id_categorie'], $_POST['description_scat'])) {
            $nom_scat = trim($_POST['nom_scat']);
            $id_categorie = (int)$_POST['id_categorie'];
            $description_scat = trim($_POST['description_scat']);

            if (empty($nom_scat) || empty($id_categorie) || empty($description_scat)) {
                $message = '<div class="alert alert-danger">Tous les champs sont obligatoires.</div>';
            } elseif (strlen($nom_scat) > 100 || strlen($description_scat) > 255) {
                $message = '<div class="alert alert-danger">Le nom ou la description est trop long.</div>';
            } else {
                $in1 = $connexion->prepare('INSERT INTO Sous_categorie (nom, description, id_categorie, id_enseignant) VALUES (:nom, :description, :id_categorie, :id_enseignant)');
                $in1->execute([
                    'nom' => $nom_scat,
                    'description' => $description_scat,
                    'id_categorie' => $id_categorie,
                    'id_enseignant' => $_SESSION['id']
                ]);
                $message = '<div class="alert alert-success">Sous-catégorie ajoutée avec succès.</div>';
                header('Location: /includes/gestion/gestion.php');
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur DB: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
        $message = '<div class="alert alert-danger">Erreur lors de l\'ajout: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = '<div class="alert alert-danger">Jeton CSRF invalide.</div>';
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Gestion de la plateforme</title>
    <link rel="stylesheet" href="../../assets/css/gestionplatform.css">
    <style>
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
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal {
            background: white;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
            overflow: hidden;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
        }
        .modal-body {
            padding: 15px;
        }
        .modal-footer {
            padding: 15px;
            border-top: 1px solid #eee;
            text-align: right;
        }
        .hidden {
            display: none;
        }
        .btn-primary {
            background-color: #4a6fdc;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background-color: #3a5fc6;
        }
        .btn-outline {
            background-color: #343a40;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-outline:hover {
            background-color: #23272b;
        }
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        .tab.active {
            border-bottom: 2px solid #4a6fdc;
            font-weight: bold;
        }
        .tab-content.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <span>EduShare</span>
        </div>
        <nav>
            <a href="/pages/enseignant/tableau_bord_enseignant.php">Tableau de bord</a>
            <a href="/pages/enseignant/categories.php">Catégories</a>
            <a href="/includes/gestion/gestion.php" class="active">Gestion</a>
        </nav>
        <div class="user-profile">
            <span class="notification">0</span>
            <div style="width: 32px; height: 32px; background-color: #e5e7eb; border-radius: 50%;"></div>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['username']); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($_SESSION['user_type']); ?></span>
        </div>
    </header>

    <div class="container">
        <div class="header-action">
            <h1>Gestion de la plateforme</h1>
            <a href="/pages/enseignant/tableau_bord_enseignant.php" class="btn btn-outline">← Retour au tableau de bord</a>
        </div>

        <?php if (!empty($message)): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="card">
            <div class="tabs">
                <div class="tab active" data-tab="categories">Catégories</div>
                <div class="tab" data-tab="sous-categories">Sous-catégories</div>
                <div class="tab" data-tab="utilisateurs">Utilisateurs</div>
            </div>
        </div>

        <div id="categories-tab" class="tab-content">
            <div class="header-action">
                <h2>Gestion des catégories</h2>
                <button class="btn btn-primary" id="btn-nouvelle-categorie">+ Nouvelle catégorie</button>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3>Catégories existantes</h3>
                    <p class="mb-4">Gérez les catégories principales de la plateforme</p>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Rechercher une catégorie...">
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Description</th>
                                <th>Créateur</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="4" class="text-center">Aucune catégorie disponible.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cat['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                        <td><?php echo htmlspecialchars($cat['enseignant_nom'] ?? 'Inconnu'); ?></td>
                                        <td>
                                            <a href="/includes/gestion/categorie.php?edit=<?php echo htmlspecialchars($cat['id_categorie']); ?>" class="btn btn-primary">Modifier</a>
                                            <a href="/includes/gestion/categorie.php?delete=<?php echo htmlspecialchars($cat['id_categorie']); ?>" class="btn btn-outline" onclick="return confirm('Voulez-vous supprimer cette catégorie ?')">Supprimer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="sous-categories-tab" class="tab-content hidden">
            <div class="header-action">
                <h2>Gestion des sous-catégories</h2>
                <button class="btn btn-primary" id="btn-nouvelle-sous-categorie">+ Nouvelle sous-catégorie</button>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3>Sous-catégories existantes</h3>
                    <p class="mb-4">Gérez les sous-catégories de la plateforme</p>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Rechercher une sous-catégorie...">
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Catégorie parente</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sCategories)): ?>
                                <tr><td colspan="4" class="text-center">Aucune sous-catégorie disponible.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sCategories as $scat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($scat['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($scat['categorie_nom'] ?? 'Inconnue'); ?></td>
                                        <td><?php echo htmlspecialchars($scat['description']); ?></td>
                                        <td>
                                            <a href="sous_categorie.php?edit=<?php echo htmlspecialchars($scat['id_s_categorie']); ?>" class="btn btn-primary">Modifier</a>
                                            <a href="sous_categorie.php?delete=<?php echo htmlspecialchars($scat['id_s_categorie']); ?>" class="btn btn-outline" onclick="return confirm('Voulez-vous supprimer cette sous-catégorie ?')">Supprimer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="utilisateurs-tab" class="tab-content hidden">
            <div class="header-action">
                <h2>Gestion des utilisateurs</h2>
                <select class="form-control" style="width: auto;">
                    <option>Tous les utilisateurs</option>
                    <option>Enseignants</option>
                    <option>Étudiants</option>
                </select>
            </div>
            <div class="card">
                <div class="card-body">
                    <h3>Liste des utilisateurs</h3>
                    <p class="mb-4">Gérez les comptes utilisateurs de la plateforme</p>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Rechercher un utilisateur...">
                    </div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($enseignants) && empty($etudiants)): ?>
                                <tr><td colspan="4" class="text-center">Aucun utilisateur disponible.</td></tr>
                            <?php else: ?>
                                <?php foreach ($enseignants as $ens): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ens['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($ens['email']); ?></td>
                                        <td>Enseignant</td>
                                        <td>
                                            <a href="/includes/supprimer/supp_enseignant.php?id=<?php echo htmlspecialchars($ens['id']); ?>" class="btn btn-outline" onclick="return confirm('Voulez-vous supprimer cet enseignant ?')">Supprimer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php foreach ($etudiants as $etud): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($etud['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($etud['email']); ?></td>
                                        <td>Étudiant</td>
                                        <td>
                                            <a href="/includes/supprimer/supp_etudiant.php?id=<?php echo htmlspecialchars($etud['id']); ?>" class="btn btn-outline" onclick="return confirm('Voulez-vous supprimer cet étudiant ?')">Supprimer</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="modal-nouvelle-categorie" class="modal-backdrop hidden">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Ajouter une nouvelle catégorie</h3>
                    <button class="modal-close" id="close-modal-categorie">×</button>
                </div>
                <form action="/includes/gestion/gestion.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-body">
                        <p class="modal-text">Créez une nouvelle catégorie pour organiser les ressources pédagogiques.</p>
                        <div class="form-group">
                            <label class="form-label">Nom de la catégorie</label>
                            <input type="text" class="form-control" name="nom_categorie" placeholder="Ex. : Sciences humaines" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description_categorie" rows="4" placeholder="Décrivez brièvement cette catégorie..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancel-modal-categorie">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer la catégorie</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="modal-nouvelle-sous-categorie" class="modal-backdrop hidden">
            <div class="modal">
                <div class="modal-header">
                    <h3 class="modal-title">Ajouter une nouvelle sous-catégorie</h3>
                    <button class="modal-close" id="close-modal-sous-categorie">×</button>
                </div>
                <form action="/includes/gestion/gestion.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="modal-body">
                        <p class="modal-text">Créez une nouvelle sous-catégorie pour organiser les ressources pédagogiques.</p>
                        <div class="form-group">
                            <label class="form-label">Nom de la sous-catégorie</label>
                            <input type="text" class="form-control" name="nom_scat" placeholder="Ex. : Philosophie" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Catégorie parente</label>
                            <select class="form-control" name="id_categorie" required>
                                <option value="" disabled selected>Sélectionnez une catégorie</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['id_categorie']); ?>">
                                        <?php echo htmlspecialchars($cat['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description_scat" rows="4" placeholder="Décrivez brièvement cette sous-catégorie..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" id="cancel-modal-sous-categorie">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer la sous-catégorie</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                // Gestion des onglets
                const tabs = document.querySelectorAll('.tab');
                const tabContents = document.querySelectorAll('.tab-content');
                tabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                        tabs.forEach(t => t.classList.remove('active'));
                        tabContents.forEach(c => c.classList.add('hidden'));
                        tab.classList.add('active');
                        document.getElementById(`${tab.dataset.tab}-tab`).classList.remove('hidden');
                    });
                });

                // Gestion des modals
                const openCatModal = document.getElementById('btn-nouvelle-categorie');
                const openSubCatModal = document.getElementById('btn-nouvelle-sous-categorie');
                const closeCatModal = document.getElementById('close-modal-categorie');
                const cancelCatModal = document.getElementById('cancel-modal-categorie');
                const closeSubCatModal = document.getElementById('close-modal-sous-categorie');
                const cancelSubCatModal = document.getElementById('cancel-modal-sous-categorie');
                const catModal = document.getElementById('modal-nouvelle-categorie');
                const subCatModal = document.getElementById('modal-nouvelle-sous-categorie');

                openCatModal.addEventListener('click', () => catModal.classList.remove('hidden'));
                openSubCatModal.addEventListener('click', () => subCatModal.classList.remove('hidden'));
                closeCatModal.addEventListener('click', () => catModal.classList.add('hidden'));
                cancelCatModal.addEventListener('click', () => catModal.classList.add('hidden'));
                closeSubCatModal.addEventListener('click', () => subCatModal.classList.add('hidden'));
                cancelSubCatModal.addEventListener('click', () => subCatModal.classList.add('hidden'));
            });
        </script>
</body>
</html>