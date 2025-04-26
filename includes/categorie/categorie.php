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

// Fetch categories and subcategories
try {
    // Fetch all categories
    $stmt = $connexion->query("SELECT id_categorie, nom, description FROM Categorie ORDER BY nom");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all subcategories with their category names
    $stmt = $connexion->query("
        SELECT s.id_s_categorie, s.nom AS sous_categorie, s.description, s.id_categorie, c.nom AS categorie
        FROM Sous_categorie s
        JOIN Categorie c ON s.id_categorie = c.id_categorie
        ORDER BY c.nom, s.nom
    ");
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch followed subcategories for the user
    $user_id = $_SESSION['id'];
    $user_type = $_SESSION['user_type'];
    $id_field = $user_type === 'etudiant' ? 'id_etudiant' : 'id_enseignant';
    $stmt = $connexion->prepare("SELECT id_s_categorie FROM Suivre_sous_categorie WHERE $id_field = :user_id");
    $stmt->execute(['user_id' => $user_id]);
    $followed_subcategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    $categories = [];
    $subcategories = [];
    $followed_subcategories = [];
}

// Handle follow/unfollow actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: categories.php");
        exit();
    }

    $action = $_POST['action'] ?? '';
    $id_s_categorie = intval($_POST['id_s_categorie'] ?? 0);
    
    try {
        if ($action === 'follow' && $id_s_categorie > 0) {
            // Check if already followed
            $stmt = $connexion->prepare("SELECT id FROM Suivre_sous_categorie WHERE $id_field = :user_id AND id_s_categorie = :id_s_categorie");
            $stmt->execute(['user_id' => $user_id, 'id_s_categorie' => $id_s_categorie]);
            if (!$stmt->fetch()) {
                $stmt = $connexion->prepare("INSERT INTO Suivre_sous_categorie (id_s_categorie, $id_field) VALUES (:id_s_categorie, :user_id)");
                $stmt->execute(['id_s_categorie' => $id_s_categorie, 'user_id' => $user_id]);
                $_SESSION['success'] = "Sous-catégorie suivie avec succès.";
            } else {
                $_SESSION['error'] = "Vous suivez déjà cette sous-catégorie.";
            }
        } elseif ($action === 'unfollow' && $id_s_categorie > 0) {
            $stmt = $connexion->prepare("DELETE FROM Suivre_sous_categorie WHERE $id_field = :user_id AND id_s_categorie = :id_s_categorie");
            $stmt->execute(['user_id' => $user_id, 'id_s_categorie' => $id_s_categorie]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['success'] = "Sous-catégorie non suivie.";
            } else {
                $_SESSION['error'] = "Vous ne suivez pas cette sous-catégorie.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de l'opération : " . $e->getMessage();
    }
    header("Location: categories.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Catégories</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/react@18.2.0/umd/react.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/react-dom@18.2.0/umd/react-dom.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@babel/standalone@7.22.5/babel.min.js"></script>
    <style>
        body { background-color: #f5f5f5; }
        header { box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1); }
        .menu-icon { cursor: pointer; }
        .category-card { transition: transform 0.2s; }
        .category-card:hover { transform: translateY(-4px); }
        .subcategory-card { transition: opacity 0.3s; }
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

        // Categories Component
        const Categories = () => {
            const categories = <?php echo json_encode($categories); ?>;
            const subcategories = <?php echo json_encode($subcategories); ?>;
            const followedSubcategories = <?php echo json_encode($followed_subcategories); ?>;
            const csrfToken = <?php echo json_encode(htmlspecialchars($_SESSION['csrf_token'])); ?>;
            const [selectedCategory, setSelectedCategory] = React.useState(null);

            const handleCategoryClick = (id_categorie) => {
                setSelectedCategory(selectedCategory === id_categorie ? null : id_categorie);
            };

            const handleFollow = (id_s_categorie, sous_categorie) => {
                if (window.confirm(`Suivre la sous-catégorie "${sous_categorie}" ?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'categories.php';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="follow">
                        <input type="hidden" name="id_s_categorie" value="${id_s_categorie}">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            };

            const handleUnfollow = (id_s_categorie, sous_categorie) => {
                if (window.confirm(`Ne plus suivre la sous-catégorie "${sous_categorie}" ?`)) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'categories.php';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="unfollow">
                        <input type="hidden" name="id_s_categorie" value="${id_s_categorie}">
                        <input type="hidden" name="csrf_token" value="${csrfToken}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            };

            return (
                <main className="p-4">
                    <div className="max-w-4xl mx-auto">
                        <div className="mb-4">
                            <h1 className="text-2xl font-bold">Catégories</h1>
                            <p className="text-gray-500">Explorez les catégories et leurs sous-catégories.</p>
                        </div>
                        <?php if (isset($_SESSION['success'])): ?>
                            <div className="bg-green-100 text-green-700 p-4 rounded mb-4">
                                <?php echo htmlspecialchars($_SESSION['success']); ?>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['error'])): ?>
                            <div className="bg-red-100 text-red-700 p-4 rounded mb-4">
                                <?php echo htmlspecialchars($_SESSION['error']); ?>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        {categories.length === 0 ? (
                            <div className="bg-white border border-gray-300 rounded-md p-4 text-center">
                                <p className="text-gray-500">Aucune catégorie trouvée.</p>
                            </div>
                        ) : (
                            <div className="grid gap-4">
                                {categories.map(category => (
                                    <div key={category.id_categorie} className="category-card bg-white border border-gray-300 rounded-md p-4">
                                        <div
                                            className="flex justify-between items-center cursor-pointer"
                                            onClick={() => handleCategoryClick(category.id_categorie)}
                                        >
                                            <div>
                                                <h2 className="text-lg font-bold">{category.nom}</h2>
                                                <p className="text-gray-600">{category.description || 'Aucune description disponible.'}</p>
                                            </div>
                                            <span className="text-2xl">{selectedCategory === category.id_categorie ? '▲' : '▼'}</span>
                                        </div>
                                        {selectedCategory === category.id_categorie && (
                                            <div className="subcategory-card mt-4 grid gap-3">
                                                {subcategories
                                                    .filter(sub => sub.id_categorie === category.id_categorie)
                                                    .map(sub => (
                                                        <div key={sub.id_s_categorie} className="border-t pt-3">
                                                            <div className="flex justify-between items-center">
                                                                <div>
                                                                    <h3 className="text-md font-semibold">{sub.sous_categorie}</h3>
                                                                    <p className="text-gray-600 text-sm">{sub.description || 'Aucune description disponible.'}</p>
                                                                </div>
                                                                <div className="flex gap-2">
                                                                    <a
                                                                        href={`subcategory_publications.php?id_s_categorie=${sub.id_s_categorie}`}
                                                                        className="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700"
                                                                    >
                                                                        Voir
                                                                    </a>
                                                                    <button
                                                                        onClick={() => followedSubcategories.includes(sub.id_s_categorie)
                                                                            ? handleUnfollow(sub.id_s_categorie, sub.sous_categorie)
                                                                            : handleFollow(sub.id_s_categorie, sub.sous_categorie)}
                                                                        className={`px-3 py-1 rounded text-white ${
                                                                            followedSubcategories.includes(sub.id_s_categorie)
                                                                                ? 'bg-red-600 hover:bg-red-700'
                                                                                : 'bg-green-600 hover:bg-green-700'
                                                                        }`}
                                                                    >
                                                                        {followedSubcategories.includes(sub.id_s_categorie) ? 'Ne plus suivre' : 'Suivre'}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))}
                                                {subcategories.filter(sub => sub.id_categorie === category.id_categorie).length === 0 && (
                                                    <p className="text-gray-500 text-sm">Aucune sous-catégorie disponible.</p>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
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
                    <Categories />
                </div>
            );
        };

        // Render the App
        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
    </script>
</body>
</html>