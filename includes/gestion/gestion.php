<?php
session_start();
require_once '../../auth/db.php';
require_once '../../auth/auth.php';

$s1 = $connexion->query('SELECT * FROM Categorie');
$categorie = $s1->fetchAll(PDO::FETCH_ASSOC);

$s2 = $connexion->query('SELECT * FROM Sous_categorie');
$sCategorie = $s2->fetchAll(PDO::FETCH_ASSOC);

$s3 = $connexion->query('SELECT * FROM Enseignant');
$enseignant = $s3->fetchAll(PDO::FETCH_ASSOC);

$s4 = $connexion->query('SELECT * FROM Etudiant');
$etudiant = $s4->fetchAll(PDO::FETCH_ASSOC);

if (empty($etudiant) || empty($enseignant) || empty($categorie)) {
    die("Aucune donnée trouvée dans l'une des tables !");
}

$tableau = ($_SESSION['user_type'] === 'etudiant') 
? '../../etudiant/pages/tableau_bord.php' 
: '../../pages/enseignant/tableau_bord.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // ➤ Ajouter une catégorie
    if (isset($_POST['nom_categorie']) && isset($_POST['description_categorie'])) {

        $nom_cat = $_POST['nom_categorie'];
        $description_cat = $_POST['description_categorie'];

        if (empty($nom_cat) || empty($description_cat)) {
            die('Un champ est vide');
        }

        $ins1 = $connexion->prepare('INSERT INTO categorie (nom, description, id_enseignant) VALUES (:nom, :description, :id_enseignant)');
        $ins1->execute([
            'nom' => $nom_cat,
            'description' => $description_cat,
            'id_enseignant' => $_SESSION['id']
        ]);

        if ($ins1->rowCount() > 0) {
            header('Location: gestion.php');
            exit();
        }
    }

    // ➤ Ajouter une sous-catégorie
    if (isset($_POST['nom_scat']) && isset($_POST['cat_parente']) && isset($_POST['description_scat'])) {

        $nom_scat = $_POST['nom_scat'];
        $cat_parente = $_POST['cat_parente'];
        $description_scat = $_POST['description_scat'];

        if (empty($nom_scat) || empty($cat_parente) || empty($description_scat)) {
            die('Un champ est vide');
        }

        $stmp = $connexion->prepare('SELECT id_categorie FROM categorie WHERE nom = :nom');
        $stmp->execute(['nom' => $cat_parente]);
        $r = $stmp->fetch(PDO::FETCH_ASSOC);

        if (empty($r)) {
            die('Aucun id de catégorie trouvé');
        }

        $in1 = $connexion->prepare('INSERT INTO sous_categorie (nom, description, id_categorie, id_enseignant) VALUES (:nom, :description, :id_categorie, :id_enseignant)');
        $in1->execute([
            'nom' => $nom_scat,
            'description' => $description_scat,
            'id_categorie' => $r['id_categorie'],
            'id_enseignant' => $_SESSION['id']
        ]);

        if ($in1->rowCount() > 0) {
            header('Location: gestion.php');
            exit();
        }
    }
}
?>





<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Gestion de la plateforme</title>
    <link rel="stylesheet" href="../../assets/css/gestionplatform.css">

    


</head>
<body>
    <!-- En-tête -->
    <header>
        <div class="logo">
            <span>EduShare</span>
        </div>
        <nav>
            <a href="<?= $tableau ?>">Tableau de bord</a>
            <a href="categorie.php">Catégories</a>
            <a href="gestion.php" class="active">Gestion</a>
        </nav>
        <div class="user-profile">
            <span class="notification">3</span>
            <div style="width: 32px; height: 32px; background-color: #e5e7eb; border-radius: 50%;"></div>
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['prenom']. ' ' . $_SESSION['username']) ?></span>
            <span class="user-role"><?php echo htmlspecialchars($_SESSION['user_type']) ?></span>
        </div>
    </header>

    <!-- Contenu principal -->
    <div class="container">
        <div class="header-action">
            <h1>Gestion de la plateforme</h1>
            <a href="../../pages/enseignant/tableau_bord.php" class="btn btn-outline" style="background-color: #343a40; color: white; padding: 8px 16px; border: none; border-radius: 4px; text-decoration: none; font-size: 14px; cursor: pointer; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#23272b'" onmouseout="this.style.backgroundColor='#343a40'">
                ← Retour au tableau de bord
            </a>
        </div>

        <!-- Onglets de navigation -->
        <div class="card">
            <div class="tabs">
                <div class="tab active" data-tab="categories">Catégories</div>
                <div class="tab" data-tab="sous-categories">Sous-catégories</div>
                <div class="tab" data-tab="utilisateurs">Utilisateurs</div>
            </div>
        </div>

        <!-- Contenu des onglets -->
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
                            <?php if(empty($categorie)): ?>
                            <tr>
                                <td colspan="4" class="text-center">Aucune catégorie disponible.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($categorie as $cat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cat['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                        <td>
                                        <?php
                                                $s = $connexion->prepare('SELECT nom FROM Enseignant WHERE id = :id');
                                                $s->execute([':id' => $cat['id_enseignant']]);
    
                                                    $res = $s->fetch(PDO::FETCH_ASSOC);
    
                                                    if ($res) {
                                                    echo htmlspecialchars($res['nom']);
                                                    } else {
                                                    echo "enseignant non trouvée.";
                                                    }
                                                    ?>
                                    </td>
                                        <td>
                                        <a href="../supprimer/supp_categorie.php?id=<?php echo htmlspecialchars($cat['id_categorie']); ?>" style="background-color: #343a40; color: white; padding: 8px 16px; border: none; border-radius: 4px; text-decoration: none; font-size: 14px; cursor: pointer; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#23272b'" onmouseout="this.style.backgroundColor='#343a40'">Supprimer</a>

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
                            <?php if(empty($sCategorie)): ?>
                            <tr>
                                <td colspan="4" class="text-center">Aucune sous-catégorie disponible.</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($sCategorie as $scat): ?>
                                    <tr>
                                    <td><?php echo htmlspecialchars($scat['nom']); ?></td>
                                    <td>
                                            <?php
                                                $s = $connexion->prepare('SELECT nom FROM Categorie WHERE id_categorie = :id_categorie');
                                                $s->execute([':id_categorie' => $scat['id_categorie']]);
    
                                                    $res = $s->fetch(PDO::FETCH_ASSOC);
    
                                                    if ($res) {
                                                    echo htmlspecialchars($res['nom']);
                                                    } else {
                                                    echo "Catégorie non trouvée.";
                                                    }
                                                    ?>
                                                    </td>
                                        <td><?php echo htmlspecialchars($scat['description']); ?></td>
                                        <td>
                                        <a href="../supprimer/supp_sous_categorie.php?id=<?php echo htmlspecialchars($scat['id_s_categorie']); ?>" style="background-color: #343a40; color: white; padding: 8px 16px; border: none; border-radius: 4px; text-decoration: none; font-size: 14px; cursor: pointer; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#23272b'" onmouseout="this.style.backgroundColor='#343a40'">Supprimer</a>

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
                            <?php if(empty($etudiant)): ?>
                            <tr>
                                <td colspan="4" class="text-center">Aucun utilisateur disponible.</td>
                            </tr>
                            <?php else : ?>
                                <!--affichage pour les enseignants -->
                                <?php foreach($enseignant as $ens): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ens['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($ens['email']) ?></td>
                                        <td>enseignant</td>
                                        <td><a href="../supprimer/supp_enseignant.php?id=<?php echo htmlspecialchars($ens['id']); ?>" style="background-color: #343a40; color: white; padding: 8px 16px; border: none; border-radius: 4px; text-decoration: none; font-size: 14px; cursor: pointer; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#23272b'" onmouseout="this.style.backgroundColor='#343a40'">Supprimer</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                    <!--affichage pour les etudiants -->
                                    <?php foreach($etudiant as $etud): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($etud['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($etud['email']) ?></td>
                                        <td>etudiant</td>
                                        <td><a href="../supprimer/supp_etudiant.php?id=<?php echo htmlspecialchars($etud['id']); ?>" style="background-color: #343a40; color: white; padding: 8px 16px; border: none; border-radius: 4px; text-decoration: none; font-size: 14px; cursor: pointer; transition: background-color 0.3s ease;" onmouseover="this.style.backgroundColor='#23272b'" onmouseout="this.style.backgroundColor='#343a40'">Supprimer</a></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    
                                
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter une nouvelle catégorie -->
    <div id="modal-nouvelle-categorie" class="modal-backdrop hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter une nouvelle catégorie</h3>
                <button class="modal-close" id="close-modal-categorie">×</button>
            </div>
            <form action="gestion.php" method="post">
                <div class="modal-body">
                    <p class="modal-text">Créez une nouvelle catégorie pour organiser les ressources pédagogiques.</p>
                    <div class="form-group">
                        <label class="form-label">Nom de la catégorie</label>
                        <input type="text" class="form-control" name="nom_categorie" placeholder="Ex. : Sciences humaines" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description_categorie" rows="4" placeholder="Décrivez brièvement cette catégorie..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-modal-categorie">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer la catégorie</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour ajouter une nouvelle sous-catégorie -->
    <div id="modal-nouvelle-sous-categorie" class="modal-backdrop hidden">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Ajouter une nouvelle sous-catégorie</h3>
                <button class="modal-close" id="close-modal-sous-categorie">×</button>
            </div>
            <form action="gestion.php" method="POST">
                <div class="modal-body">
                    <p class="modal-text">Créez une nouvelle sous-catégorie pour organiser les ressources pédagogiques.</p>
                    <div class="form-group">
                        <label class="form-label">Nom de la sous-catégorie</label>
                        <input type="text" name="nom_scat" class="form-control" name="nom" placeholder="Ex. : Philosophie" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catégorie parente</label>
                        <input type="hidden" name="categorie_id" id="categorie_id">
                        <input type="text" name="cat_parente" class="form-control" id="categorie_nom" placeholder="Sélectionnez une catégorie" >
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description_scat" rows="4" placeholder="Décrivez brièvement cette sous-catégorie..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancel-modal-sous-categorie">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer la sous-catégorie</button>
                </div>
            </form>
        </div>
    </div>

    <script
        src=".../components/common/gestion.js">
    </script>
</body>
</html>