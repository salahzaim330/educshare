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

// Handle note submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_note'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Erreur de validation CSRF.";
    } else {
        $publicationId = filter_input(INPUT_POST, 'id_pub', FILTER_VALIDATE_INT);
        $note = filter_input(INPUT_POST, 'note', FILTER_VALIDATE_INT);

        if ($publicationId && $note >= 1 && $note <= 5) {
            try {
                $stmt = $connexion->prepare("
                    INSERT INTO Note (valeur, id_etudiant, id_enseignant, id_pub, date_note)
                    VALUES (:valeur, :id_etudiant, :id_enseignant, :id_pub, CURDATE())
                    ON DUPLICATE KEY UPDATE valeur = :valeur_update, date_note = CURDATE()
                ");
                $stmt->execute([
                    ':valeur' => $note,
                    ':id_etudiant' => $_SESSION['user_type'] === 'etudiant' ? $_SESSION['id'] : null,
                    ':id_enseignant' => $_SESSION['user_type'] === 'enseignant' ? $_SESSION['id'] : null,
                    ':id_pub' => $publicationId,
                    ':valeur_update' => $note
                ]);
                $_SESSION['success'] = "Note enregistrée avec succès.";
            } catch (PDOException $e) {
                error_log("Erreur enregistrement note: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
                $_SESSION['error'] = "Erreur lors de l'enregistrement de la note.";
            }
        } else {
            $_SESSION['error'] = "Note invalide ou publication introuvable.";
        }
    }
    header("Location: subcategory_publications.php?id_s_categorie=$id_s_categorie");
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

    // Fetch publications with comment count, average note, and file extension
    $stmt = $connexion->prepare("
        SELECT 
            p.id_pub, 
            p.titre, 
            p.date_pub, 
            p.description, 
            p.contenu, 
            COUNT(com.id) AS comment_count,
            COALESCE(AVG(n.valeur), 0) AS average_note,
            LOWER(SUBSTRING_INDEX(p.contenu, '.', -1)) AS file_extension
        FROM publication p
        LEFT JOIN Commentaire com ON p.id_pub = com.id_pub
        LEFT JOIN Note n ON p.id_pub = n.id_pub
        WHERE p.id_s_categorie = :id_s_categorie
        GROUP BY p.id_pub
        ORDER BY p.date_pub DESC
    ");
    $stmt->execute(['id_s_categorie' => $id_s_categorie]);
    $publications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure absolute URLs for contenu
    foreach ($publications as &$pub) {
        if (!empty($pub['contenu']) && !preg_match('#^https?://#', $pub['contenu'])) {
            $pub['contenu'] = '/edushare/' . ltrim(str_replace('\\', '/', $pub['contenu']), '/');
        }
    }
} catch (PDOException $e) {
    error_log("Erreur récupération données: " . $e->getMessage(), 3, __DIR__ . '/../../logs/errors.log');
    $_SESSION['error'] = "Erreur de base de données.";
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
        .comment-link {
            color: #3b82f6;
            text-decoration: none;
            cursor: pointer;
        }
        .comment-link:hover {
            text-decoration: underline;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 900px;
            height: 80%;
            border-radius: 8px;
            overflow: auto;
            position: relative;
        }
        .modal-content iframe,
        .modal-content img,
        .modal-content video {
            width: 100%;
            height: 100%;
            border: none;
            object-fit: contain;
        }
        .modal-content .error {
            color: red;
            padding: 20px;
            text-align: center;
        }
        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #333;
        }
        .star {
            font-size: 1.2rem;
            cursor: pointer;
            color: #e5e7eb;
        }
        .star.filled {
            color: #f59e0b;
        }
        .star.half {
            background: linear-gradient(90deg, #f59e0b 50%, #e5e7eb 50%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .star.preview {
            color: #f59e0b;
        }
        .stars-container {
            display: inline-flex;
            gap: 2px;
        }
        .view-btn {
            display: inline-block;
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            text-decoration: none;
            margin-left: 0.5rem;
        }
        .view-btn:hover {
            background: #059669;
        }
    </style>
</head>
<body>
    <div id="root"></div>

    <!-- Modale pour commentaires et contenu -->
    <div class="modal" id="contentModal">
        <div class="modal-content">
            <span class="close-modal">×</span>
            <div id="modalContent"></div>
        </div>
    </div>

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
                        {userType === 'enseignant' && (
                            <a href="../../includes/gestion/gestion.php" className="text-gray-700 hover:font-bold">Gestion</a>
                        )}
                    </nav>
                    <div className="flex items-center gap-2">
                        <span className="bg-green-500 text-white rounded-full px-2 py-1 text-sm">3</span>
                        <span className="font-bold">{userName}</span>
                        <span className="text-gray-500">{userType}</span>
                    </div>
                </header>
            );
        };

        // StarRating Component
        const StarRating = ({ pubId, averageNote }) => {
            const [selected, setSelected] = React.useState(0);
            const [hover, setHover] = React.useState(0);
            const csrfToken = <?php echo json_encode($_SESSION['csrf_token']); ?>;

            const handleSubmit = (value) => {
                const formData = new FormData();
                formData.append('id_pub', pubId);
                formData.append('note', value);
                formData.append('csrf_token', csrfToken);
                formData.append('submit_note', '1');

                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                }).then(() => {
                    window.location.reload();
                }).catch(error => {
                    console.error('Erreur soumission note:', error);
                });
            };

            const fullStars = Math.floor(averageNote);
            const hasHalfStar = averageNote - fullStars >= 0.5;

            return (
                <div className="stars-container">
                    {[1, 2, 3, 4, 5].map((star) => (
                        <span
                            key={star}
                            className={`star ${
                                hover >= star || selected >= star
                                    ? 'preview'
                                    : star <= fullStars
                                    ? 'filled'
                                    : star === fullStars + 1 && hasHalfStar
                                    ? 'half'
                                    : ''
                            }`}
                            onMouseOver={() => setHover(star)}
                            onMouseOut={() => setHover(0)}
                            onClick={() => {
                                setSelected(star);
                                handleSubmit(star);
                            }}
                        >
                            ★
                        </span>
                    ))}
                    <span className="ml-2 text-sm text-gray-500">({averageNote.toFixed(1)})</span>
                </div>
            );
        };

        // SubcategoryPublications Component
        const SubcategoryPublications = () => {
            const subcategory = <?php echo json_encode($subcategory); ?>;
            const publications = <?php echo json_encode($publications); ?>;
            const dashboard = <?php echo json_encode($dashboard); ?>;

            const openCommentModal = (pubId) => {
                console.log('Opening comment modal for pubId:', pubId);
                const modal = document.getElementById('contentModal');
                const modalContent = document.getElementById('modalContent');
                modalContent.innerHTML = `<iframe id="commentFrame" src="../commentaire/commentaire.php?id_pub=${pubId}"></iframe>`;
                modal.style.display = 'flex';
            };

            const openContentModal = (contentUrl, fileExtension) => {
                console.log('Opening content modal:', { contentUrl, fileExtension });
                const modal = document.getElementById('contentModal');
                const modalContent = document.getElementById('modalContent');

                // Normalize file extension
                const ext = fileExtension ? fileExtension.toLowerCase().replace('.', '') : 'unknown';

                // Extensions to open in a new tab
                const newTabExtensions = ['pdf', 'docx'];

                // Extensions viewable in modal
                const viewableExtensions = {
                    'png': () => `<img src="${contentUrl}" alt="Publication" onerror="this.parentNode.innerHTML='<p class=\\'error\\'>Erreur: Image introuvable.</p>'" />`,
                    'jpg': () => `<img src="${contentUrl}" alt="Publication" onerror="this.parentNode.innerHTML='<p class=\\'error\\'>Erreur: Image introuvable.</p>'" />`,
                    'jpeg': () => `<img src="${contentUrl}" alt="Publication" onerror="this.parentNode.innerHTML='<p class=\\'error\\'>Erreur: Image introuvable.</p>'" />`,
                    'gif': () => `<img src="${contentUrl}" alt="Publication" onerror="this.parentNode.innerHTML='<p class=\\'error\\'>Erreur: Image introuvable.</p>'" />`,
                    'mp4': () => `<video controls><source src="${contentUrl}" type="video/mp4" onerror="this.parentNode.innerHTML='<p class=\\'error\\'>Erreur: Vidéo introuvable.</p>'"></video>`,
                    'avi': () => `<video controls><source src="${contentUrl}" type="video/avi" onerror="this.parentNode.innerHTML='<p class=\\'error\\'>Erreur: Vidéo introuvable.</p>'"></video>`
                };

                // Check if file exists before proceeding
                fetch(contentUrl, { method: 'HEAD' })
                    .then(res => {
                        if (!res.ok) {
                            modalContent.innerHTML = `<p class="error">Erreur: Fichier introuvable à ${contentUrl}</p>`;
                            modal.style.display = 'flex';
                        } else if (newTabExtensions.includes(ext)) {
                            // Open PDF and DOCX in a new tab
                            window.open(contentUrl, '_blank');
                        } else if (viewableExtensions[ext]) {
                            // Display viewable files in modal
                            modalContent.innerHTML = viewableExtensions[ext]();
                            modal.style.display = 'flex';
                        } else {
                            // Handle non-viewable, non-new-tab extensions
                            modalContent.innerHTML = '<p class="error">Ce type de fichier ne peut pas être prévisualisé. Ouverture dans une nouvelle fenêtre...</p>';
                            modal.style.display = 'flex';
                            setTimeout(() => {
                                window.open(contentUrl, '_blank');
                                modal.style.display = 'none';
                                modalContent.innerHTML = '';
                            }, 2000);
                        }
                    })
                    .catch(err => {
                        console.error('Fetch error:', err);
                        modalContent.innerHTML = `<p class="error">Erreur: Impossible de vérifier le fichier à ${contentUrl}</p>`;
                        modal.style.display = 'flex';
                    });
            };

            return (
                <main className="p-4">
                    <div className="max-w-4xl mx-auto">
                        <div className="mb-4">
                            <h1 className="text-2xl font-bold">
                                Publications de {subcategory ? `${subcategory.categorie} - ${subcategory.sous_categorie}` : 'Sous-catégorie'}
                            </h1>
                            <p className="text-gray-500">Voici toutes les publications pour cette sous-catégorie.</p>
                        </div>
                        {<?php if (isset($_SESSION['error'])): ?>
                            <div className="bg-red-100 text-red-700 p-4 rounded mb-4">
                                <?php echo htmlspecialchars($_SESSION['error']); ?>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>}
                        {<?php if (isset($_SESSION['success'])): ?>
                            <div className="bg-green-100 text-green-700 p-4 rounded mb-4">
                                <?php echo htmlspecialchars($_SESSION['success']); ?>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>}
                        {subcategory ? (
                            publications.length === 0 ? (
                                <div className="bg-white border border-gray-300 rounded-md p-4 text-center">
                                    <p className="text-gray-500">Aucune publication trouvée pour cette sous-catégorie.</p>
                                    <a href="../publier/publier.php" className="text-blue-600 hover:underline">Publier une nouvelle ressource</a>
                                </div>
                            ) : (
                                <div className="grid gap-4">
                                    {publications.map(pub => (
                                        <div key={pub.id_pub} className="publication-card bg-white border border-gray-300 rounded-md p-4">
                                            <h3 className="text-lg font-bold">{pub.titre}</h3>
                                            <p className="text-gray-600 mb-2">{pub.description}</p>
                                            <p className="text-sm text-gray-500">Publié le : {new Date(pub.date_pub).toLocaleDateString('fr-FR')}</p>
                                            <p className="text-sm text-gray-500">
                                                <StarRating pubId={pub.id_pub} averageNote={parseFloat(pub.average_note)} />
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                <a
                                                    href="#"
                                                    className="comment-link"
                                                    onClick={() => openCommentModal(pub.id_pub)}
                                                >
                                                    {pub.comment_count} commentaire(s)
                                                </a>
                                            </p>
                                            <div className="mt-2">
                                                <p className="text-sm text-gray-500">Debug: contenu={pub.contenu}, extension={pub.file_extension}</p>
                                                <a
                                                    href={pub.contenu}
                                                    download
                                                    className="inline-block bg-gray-800 text-white px-3 py-1 rounded hover:bg-gray-900"
                                                >
                                                    Télécharger
                                                </a>
                                                <a
                                                    href="#"
                                                    className="view-btn"
                                                    onClick={() => openContentModal(pub.contenu, pub.file_extension)}
                                                >
                                                    Consulter
                                                </a>
                                            </div>
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

        // Gestion de la modale
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('contentModal');
            const modalContent = document.getElementById('modalContent');
            const closeModal = document.querySelector('.close-modal');

            if (closeModal) {
                closeModal.addEventListener('click', () => {
                    console.log('Closing modal');
                    modal.style.display = 'none';
                    modalContent.innerHTML = '';
                });
            }

            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        console.log('Closing modal via background click');
                        modal.style.display = 'none';
                        modalContent.innerHTML = '';
                    }
                });
            }
        });
    </script>
</body>
</html>