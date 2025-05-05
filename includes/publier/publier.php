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

// Fetch categories and subcategories for the form
try {
    $stmt = $connexion->query("SELECT id_categorie, nom FROM Categorie ORDER BY nom");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $connexion->query("
        SELECT s.id_s_categorie, s.nom AS sous_categorie, s.id_categorie
        FROM sous_categorie s
        ORDER BY s.nom
    ");
    $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
    $categories = [];
    $subcategories = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: publier.php");
        exit();
    }

    // Validate form inputs
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $id_sous_categorie = intval($_POST['sous_categorie'] ?? 0);

    if (empty($titre) || empty($description) || $id_sous_categorie <= 0) {
        $_SESSION['error'] = "Tous les champs sont obligatoires.";
        header("Location: publier.php");
        exit();
    }

    // Validate subcategory
    try {
        $stmt = $connexion->prepare("SELECT id_s_categorie FROM Sous_categorie WHERE id_s_categorie = :id");
        $stmt->execute(['id' => $id_sous_categorie]);
        if (!$stmt->fetch()) {
            $_SESSION['error'] = "Sous-catégorie invalide.";
            header("Location: publier.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
        header("Location: publier.php");
        exit();
    }

    // File handling
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Erreur lors du téléchargement du fichier.";
        header("Location: publier.php");
        exit();
    }

    $allowed_extensions = ['pdf', 'docx', 'pptx', 'txt'];
    $max_size = 20 * 1024 * 1024; // 20 MB
    $file_extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $file_size = $_FILES['file']['size'];
    $filename = uniqid() . '_' . basename($_FILES['file']['name']);
    $upload_dir = __DIR__ . '/../../Uploads/publications/';
    $relative_path = 'Uploads/publications/' . $filename;
    $dest = $upload_dir . $filename;

    // Validate file
    if (!in_array($file_extension, $allowed_extensions)) {
        $_SESSION['error'] = "Type de fichier non autorisé. Formats acceptés : PDF, DOCX, PPTX, TXT.";
        header("Location: publier.php");
        exit();
    }
    if ($file_size > $max_size) {
        $_SESSION['error'] = "Le fichier est trop volumineux. Taille maximale : 20 Mo.";
        header("Location: publier.php");
        exit();
    }

    // Additional server-side file type validation
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $file_type = finfo_file($finfo, $_FILES['file']['tmp_name']);
    finfo_close($finfo);
    $allowed_mime_types = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain'
    ];
    if (!in_array($file_type, $allowed_mime_types)) {
        $_SESSION['error'] = "Type de fichier invalide détecté par le serveur.";
        header("Location: publier.php");
        exit();
    }

    // Ensure upload directory exists and is writable
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $_SESSION['error'] = "Erreur lors de la création du dossier d'upload.";
            header("Location: publier.php");
            exit();
        }
    }
    if (!is_writable($upload_dir)) {
        $_SESSION['error'] = "Le dossier d'upload n'est pas accessible en écriture.";
        header("Location: publier.php");
        exit();
    }

    // Move uploaded file
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
        $_SESSION['error'] = "Erreur lors du déplacement du fichier.";
        header("Location: publier.php");
        exit();
    }

    // Determine user type
    $id_etudiant = $_SESSION['user_type'] === 'etudiant' ? $_SESSION['id'] : null;
    $id_enseignant = $_SESSION['user_type'] === 'enseignant' ? $_SESSION['id'] : null;

    // Insert publication and notifications
    try {
        // Start transaction
        $connexion->beginTransaction();

        // Insert publication
        $stmt = $connexion->prepare("
            INSERT INTO Publication 
            (titre, date_pub, contenu, description, id_enseignant, id_etudiant, id_s_categorie)
            VALUES (:titre, NOW(), :contenu, :description, :id_enseignant, :id_etudiant, :id_s_categorie)
        ");
        $stmt->execute([
            'titre' => $titre,
            'contenu' => $relative_path,
            'description' => $description,
            'id_enseignant' => $id_enseignant,
            'id_etudiant' => $id_etudiant,
            'id_s_categorie' => $id_sous_categorie
        ]);
        $id_pub = $connexion->lastInsertId();

        // Fetch followers of the subcategory
        $stmt = $connexion->prepare("
            SELECT id_enseignant, id_etudiant
            FROM Suivre_sous_categorie
            WHERE id_s_categorie = :id_s_categorie
        ");
        $stmt->execute([':id_s_categorie' => $id_sous_categorie]);
        $followers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Insert notifications for each follower
        foreach ($followers as $follower) {
            $contenu_notif = "Nouvelle publication dans la sous-catégorie : " . htmlspecialchars($titre);
            $stmt = $connexion->prepare("
                INSERT INTO Notification 
                (contenu, date_notif, id_etudiant, id_enseignant, id_pub, status)
                VALUES (:contenu, CURDATE(), :id_etudiant, :id_enseignant, :id_pub, 'unread')
            ");
            $stmt->execute([
                ':contenu' => $contenu_notif,
                ':id_etudiant' => $follower['id_etudiant'],
                ':id_enseignant' => $follower['id_enseignant'],
                ':id_pub' => $id_pub
            ]);
        }

        // Commit transaction
        $connexion->commit();

        $_SESSION['success'] = "Publication réussie et notifications envoyées !";
        header("Location: $dashboard");
        exit();
    } catch (PDOException $e) {
        // Rollback transaction and delete uploaded file
        $connexion->rollBack();
        if (file_exists($dest)) {
            unlink($dest);
        }
        error_log("Erreur lors de la publication: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
        $_SESSION['error'] = "Erreur de base de données : " . $e->getMessage();
        header("Location: publier.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduShare - Publier une ressource</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/react@18.2.0/umd/react.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/react-dom@18.2.0/umd/react-dom.production.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@babel/standalone@7.22.5/babel.min.js"></script>
    <style>
        body { background-color: #f5f5f5; }
        header { box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1); }
        .menu-icon { cursor: pointer; }
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
                    <a href="categories.php" className="text-gray-700 hover:font-bold">Catégories</a>
                </nav>
                <div className="flex items-center gap-2">
                    <span className="bg-green-500 text-white rounded-full px-2 py-1 text-sm">0</span>
                    <span className="font-bold">{userName}</span>
                    <span className="text-gray-500">{userType}</span>
                </div>
            </header>
        );
    };

    // PublishForm Component
    const PublishForm = () => {
        const categories = <?php echo json_encode($categories); ?>;
        const subcategories = <?php echo json_encode($subcategories); ?>;
        const csrfToken = <?php echo json_encode(htmlspecialchars($_SESSION['csrf_token'])); ?>;
        const dashboard = <?php echo json_encode($dashboard); ?>;
        const [title, setTitle] = React.useState('');
        const [description, setDescription] = React.useState('');
        const [categoryId, setCategoryId] = React.useState(categories[0]?.id_categorie || '');
        const [subcategoryId, setSubcategoryId] = React.useState('');
        const [file, setFile] = React.useState(null);
        const [fileName, setFileName] = React.useState('Aucun fichier sélectionné');
        const [isSubmitting, setIsSubmitting] = React.useState(false);
        const [error, setError] = React.useState(<?php echo json_encode($_SESSION['error'] ?? ''); ?>);
        const [success, setSuccess] = React.useState(<?php echo json_encode($_SESSION['success'] ?? ''); ?>);

        // Clear session messages
        <?php unset($_SESSION['error'], $_SESSION['success']); ?>

        // Update subcategory when category changes
        React.useEffect(() => {
            if (categories.length > 0 && !categoryId) {
                setCategoryId(categories[0].id_categorie);
            }
            const firstSubcategory = subcategories.find(sub => sub.id_categorie === parseInt(categoryId));
            setSubcategoryId(firstSubcategory?.id_s_categorie || '');
        }, [categoryId, categories, subcategories]);

        const handleFileChange = (e) => {
            const selectedFile = e.target.files[0];
            if (selectedFile) {
                const extension = selectedFile.name.split('.').pop().toLowerCase();
                const allowedExtensions = ['pdf', 'docx', 'pptx', 'txt'];
                
                if (!allowedExtensions.includes(extension)) {
                    setError('Type de fichier non autorisé. Formats acceptés : PDF, DOCX, PPTX, TXT.');
                    setFile(null);
                    setFileName('Aucun fichier sélectionné');
                    return;
                }
                
                if (selectedFile.size > 20 * 1024 * 1024) {
                    setError('Le fichier est trop volumineux. Taille maximale : 20 Mo.');
                    setFile(null);
                    setFileName('Aucun fichier sélectionné');
                    return;
                }
                
                setError('');
                setFile(selectedFile);
                setFileName(selectedFile.name);
            } else {
                setFile(null);
                setFileName('Aucun fichier sélectionné');
            }
        };

        const handleSubmit = (e) => {
            e.preventDefault();
            if (!title.trim() || !description.trim() || !subcategoryId || !file) {
                setError('Veuillez remplir tous les champs obligatoires.');
                return;
            }
            setIsSubmitting(true);
            e.target.submit();
        };

        const handleCancel = () => {
            if (window.confirm('Êtes-vous sûr de vouloir annuler ? Les données non enregistrées seront perdues.')) {
                window.location.href = dashboard;
            }
        };

        return (
            <main className="p-4">
                <div className="max-w-xl mx-auto">
                    <div className="mb-4">
                        <h1 className="text-2xl font-bold">Publier une ressource</h1>
                    </div>
                    {error && (
                        <div className="bg-red-100 text-red-700 p-4 rounded mb-4">
                            {error}
                        </div>
                    )}
                    {success && (
                        <div className="bg-green-100 text-green-700 p-4 rounded mb-4">
                            {success}
                        </div>
                    )}
                    <div className="bg-white border border-gray-300 rounded-md p-4">
                        <h2 className="text-lg font-bold uppercase mb-2">Informations sur la ressource</h2>
                        <p className="text-gray-500 mb-4">Renseignez les détails de votre ressource pédagogique</p>
                        <form action="publier.php" method="post" enctype="multipart/form-data" onSubmit={handleSubmit}>
                            <input type="hidden" name="csrf_token" value={csrfToken} />
                            <div className="mb-4">
                                <label htmlFor="title" className="block text-sm font-medium mb-1">Titre</label>
                                <input
                                    type="text"
                                    id="title"
                                    name="titre"
                                    className="w-full border border-gray-300 rounded-md p-2"
                                    placeholder="Titre de votre ressource"
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                    disabled={isSubmitting}
                                    required
                                />
                            </div>

                            <div className="mb-4">
                                <label htmlFor="description" className="block text-sm font-medium mb-1">Description</label>
                                <textarea
                                    id="description"
                                    name="description"
                                    className="w-full border border-gray-300 rounded-md p-2"
                                    placeholder="Décrivez votre ressource en quelques phrases..."
                                    rows="3"
                                    value={description}
                                    onChange={(e) => setDescription(e.target.value)}
                                    disabled={isSubmitting}
                                    required
                                ></textarea>
                            </div>

                            <div className="grid grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label htmlFor="category" className="block text-sm font-medium mb-1">Catégorie</label>
                                    <select
                                        id="category"
                                        name="categorie"
                                        className="w-full border border-gray-300 rounded-md p-2"
                                        value={categoryId}
                                        onChange={(e) => setCategoryId(e.target.value)}
                                        disabled={isSubmitting}
                                        required
                                    >
                                        {categories.map(cat => (
                                            <option key={cat.id_categorie} value={cat.id_categorie}>{cat.nom}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label htmlFor="subcategory" className="block text-sm font-medium mb-1">Sous-catégorie</label>
                                    <select
                                        id="subcategory"
                                        name="sous_categorie"
                                        className="w-full border border-gray-300 rounded-md p-2"
                                        value={subcategoryId}
                                        onChange={(e) => setSubcategoryId(e.target.value)}
                                        disabled={isSubmitting}
                                        required
                                    >
                                        <option value="">Sélectionnez une sous-catégorie</option>
                                        {subcategories
                                            .filter(sub => sub.id_categorie === parseInt(categoryId))
                                            .map(sub => (
                                                <option key={sub.id_s_categorie} value={sub.id_s_categorie}>{sub.sous_categorie}</option>
                                            ))}
                                    </select>
                                </div>
                            </div>

                            <div className="mb-4">
                                <label htmlFor="file" className="block text-sm font-medium mb-1">Fichier</label>
                                <div className="border-2 border-dashed border-gray-300 rounded-md p-4 text-center bg-gray-50">
                                    <input
                                        type="file"
                                        id="file"
                                        name="file"
                                        accept=".pdf,.docx,.pptx,.txt"
                                        className="hidden"
                                        onChange={handleFileChange}
                                        disabled={isSubmitting}
                                        required
                                    />
                                    <label htmlFor="file" className="block mb-2 cursor-pointer">
                                        {fileName}<br />
                                        <span className="text-2xl">⬆</span><br />
                                        Cliquez pour télécharger ou glissez-déposez
                                    </label>
                                    <p className="text-gray-500 text-sm">PDF, DOCX, PPTX, TXT (max. 20 Mo)</p>
                                </div>
                            </div>

                            <div className="flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={handleCancel}
                                    className="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300"
                                    disabled={isSubmitting}
                                >
                                    Annuler
                                </button>
                                <button
                                    type="submit"
                                    className="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-900 disabled:bg-gray-500"
                                    disabled={isSubmitting}
                                >
                                    {isSubmitting ? 'Publication en cours...' : 'Publier'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        );
    };

    // App Component
    const App = () => {
        return (
            <div>
                <Header />
                <PublishForm />
            </div>
        );
    };

    // Render the App
    ReactDOM.render(<App />, document.getElementById('root'));
</script>
</body>
</html>