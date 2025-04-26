<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Enable if using HTTPS
session_start();
require_once '../../auth/db.php';
require_once '../../auth/auth.php';

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['etudiant', 'enseignant'])) {
    header('Location: ../../auth/login.php');
    exit();
}

// Determine dashboard based on user type
$dashboard = $_SESSION['user_type'] === 'etudiant' 
    ? '../../pages/etudiant/tableau_bord.php' 
    : '../../pages/enseignant/tableau_bord.php';

// Validate and fetch subcategory ID
$id_s_categorie = isset($_GET['id_s_categorie']) ? intval($_GET['id_s_categorie']) : 0;
if ($id_s_categorie <= 0) {
    $_SESSION['error'] = "Sous-catégorie invalide.";
    header("Location: categories.php");
    exit();
}

// Fetch subcategory details and publications
try {
    // Fetch subcategory details
    $stmt = $connexion->prepare("
        SELECT s.nom AS sous_categorie, c.nom AS categorie
        FROM sous_categorie s
        JOIN categorie c ON s.id_categorie = c.id_categorie
        WHERE s.id_s_categorie = :id_s_categorie
    ");
    $stmt->execute(['id_s_categorie' => $id_s_categorie]);
    $subcategory = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subcategory) {
        $_SESSION['error'] = "Sous-catégorie introuvable.";
        header("Location: categories.php");
        exit();
    }

    // Fetch publications for the subcategory
    $stmt = $connexion->prepare("
        SELECT p.id_pub, p.titre, p.date_pub, p.description, p.contenu
        FROM publication p
        WHERE p.id_s_categorie = :id_s_categorie
        ORDER BY p.date_pub DESC
    ");
    $stmt->execute(['id_s_categorie' => $id_s_categorie]);
    $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    $subcategory = null;
    $publications = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Publications de la Sous-catégorie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/react@18.2.0/umd/react.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/react-dom@18.2.0/umd/react-dom.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@babel/standalone@7.22.5/babel.min.js"></script>
    <style>
        body { background-color: #f5f5f5; }
        header { box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1); }
        .menu-icon { cursor: pointer; }
        .publication-card { transition: transform 0.2s; }
        .publication-card:hover { transform: translateY(-4px); }
    </style>
</head>
<body>
    <div id="root"></div>

    <script type="text/babel">
        // Header Component
        const Header = () => {
            const userName = <?php echo json_encode(htmlspecialchars($_SESSION['prenom'] . ' ' . $_SESSION['username'])); ?>;
            const userType = <?php echo json_encode(htmlspecialchars($_SESSION['user_type'])); ?>;
            const dashboard = <?php echo json_encode($dashboard); ?>;

            return (
                <header className="bg-white border-b p-4 flex justify-between items-center">
                    <div className="flex items-center gap-2">
                        <span className="menu-icon text-2xl">☰</span>
                        <a href={dashboard} className="text-xl font-bold">EduShare</a>
                    </div>
                    <nav className="flex gap-4">
                        <a href={dashboard} className="text-gray-700 hover:font-bold">Tableau de bord</a>
                        <a href="categories.php" className="text-gray-700 font-bold">Catégories</a>

                        
                    </nav>
                    <div className="flex items-center gap-2">
                        <span className="bg-green-500 text-white rounded-full px-2 py-1 text-sm">3</span>
                        <span className="font-bold">{userName}</span>
                        <span className="text-gray-500">{userType}</span>
                    </div>
                </header>
            );
        };

        // SubcategoryPublications Component
        const SubcategoryPublications = () => {
            const subcategory = <?php echo json_encode($subcategory); ?>;
            const publications = <?php echo json_encode($publications); ?>;
            const dashboard = <?php echo json_encode($dashboard); ?>;

            return (
                <main className="p-4">
                    <div className="max-w-4xl mx-auto">
                        <div className="mb-4">
                            <h1 className="text-2xl font-bold">
                                Publications de {subcategory ? `${subcategory.categorie} - ${subcategory.sous_categorie}` : 'Sous-catégorie'}
                            </h1>
                            <p className="text-gray-500">Voici toutes les publications pour cette sous-catégorie.</p>
                        </div>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div className="bg-red-100 text-red-700 p-4 rounded mb-4">
                                <?php echo htmlspecialchars($_SESSION['error']); ?>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        {subcategory ? (
                            publications.length === 0 ? (
                                <div className="bg-white border border-gray-300 rounded-md p-4 text-center">
                                    <p className="text-gray-500">Aucune publication trouvée pour cette sous-catégorie.</p>
                                    <a href="publier.php" className="text-blue-600 hover:underline">Publier une nouvelle ressource</a>
                                </div>
                            ) : (
                                <div className="grid gap-4">
                                    {publications.map(pub => (
                                        <div key={pub.id_pub} className="publication-card bg-white border border-gray-300 rounded-md p-4">
                                            <h3 className="text-lg font-bold">{pub.titre}</h3>
                                            <p className="text-gray-600 mb-2">{pub.description}</p>
                                            <p className="text-sm text-gray-500">Publié le : {new Date(pub.date_pub).toLocaleDateString('fr-FR')}</p>
                                            <a
                                                href={pub.contenu}
                                                download
                                                className="inline-block mt-2 bg-gray-800 text-white px-3 py-1 rounded hover:bg-gray-900"
                                            >
                                                Télécharger
                                            </a>
                                        </div>
                                    ))}
                                </div>
                            )
                        ) : (
                            <div className="bg-white border border-gray-300 rounded-md p-4 text-center">
                                <p className="text-gray-500">Sous-catégorie introuvable.</p>
                                <a href="categories.php" className="text-blue-600 hover:underline">Retour aux catégories</a>
                            </div>
                        )}
                    </div>
                </main>
            );
        };

        // App Component
        const App = () => {
            return (
                <div>
                    <Header />
                    <SubcategoryPublications />
                </div>
            );
        };

        // Render the App
        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>