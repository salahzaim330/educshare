// Header Component
// Header Component
const Header = ({ tableau, userData }) => {
    return (
        <header className="bg-white border-b p-4 flex justify-between items-center">
            <div className="flex items-center gap-2">
                <span className="menu-icon text-2xl">☰</span>
                <span className="text-xl font-bold"><a href={tableau}>EduShare</a></span>
            </div>
            <nav className="flex gap-4">
                <a href={tableau} className="text-gray-700 hover:font-bold">Tableau de bord</a>
                <a href="categorie.html" className="text-gray-700 hover:font-bold">Catégories</a>
                <a href="gestion.php" className="text-gray-700 hover:font-bold">Gestion</a>
            </nav>
            <div className="flex items-center gap-2">
                <span className="bg-green-500 text-white rounded-full px-2 py-1 text-sm">3</span>
                <span className="font-bold">{userData.prenom} {userData.username}</span>
                <span className="text-gray-500">{userData.user_type}</span>
            </div>
        </header>
    );
};

// PublishForm Component
const PublishForm = () => {
    const [title, setTitle] = React.useState('');
    const [description, setDescription] = React.useState('');
    const [category, setCategory] = React.useState('Informatique');
    const [subcategory, setSubcategory] = React.useState('Programmation - Python');
    const [resourceType, setResourceType] = React.useState('Document');
    const [file, setFile] = React.useState(null);

    // Updated mapping of categories to subcategories based on the provided hierarchy
    const subcategoriesMap = {
        'Informatique': [
            'Programmation - Python',
            'Programmation - Java',
            'Programmation - C++',
            'Programmation - JavaScript',
            'Développement web - Front-end (HTML, CSS, JS)',
            'Développement web - Back-end (Node.js, PHP, Django)',
            'Base de données - SQL',
            'Base de données - NoSQL (MongoDB)',
            'Intelligence artificielle - Machine Learning',
            'Intelligence artificielle - Deep Learning',
            'Cybersécurité - Cryptographie',
            'Cybersécurité - Sécurité des réseaux'
        ],
        'Physique': [
            'Mécanique - Dynamique',
            'Mécanique - Statique',
            'Électricité & Magnétisme - Courant alternatif/continu',
            'Électricité & Magnétisme - Champs électromagnétiques',
            'Optique - Optique géométrique',
            'Optique - Optique physique',
            'Thermodynamique - Lois des gaz',
            'Thermodynamique - Transferts de chaleur',
            'Physique moderne - Relativité',
            'Physique moderne - Physique quantique'
        ],
        'Mathématiques': [
            'Algèbre - Polynômes',
            'Algèbre - Matrices',
            'Analyse - Dérivées & intégrales',
            'Analyse - Suites & séries',
            'Probabilités & Statistiques - Variables aléatoires',
            'Probabilités & Statistiques - Loi normale',
            'Géométrie - Géométrie analytique',
            'Géométrie - Trigonométrie',
            'Logique & Ensembles - Théorie des ensembles',
            'Logique & Ensembles - Logique mathématique'
        ],
        'Langues': [
            'Français - Grammaire',
            'Français - Orthographe',
            'Français - Rédaction',
            'Anglais - Vocabulary',
            'Anglais - Grammar',
            'Anglais - Conversation',
            'Espagnol - Compréhension écrite',
            'Espagnol - Expression orale',
            'Arabe - Langue classique',
            'Arabe - Darija (dialecte marocain)'
        ]
    };

    // Update subcategory when category changes
    React.useEffect(() => {
        const subcategories = subcategoriesMap[category] || [];
        setSubcategory(subcategories[0] || ''); // Set to first subcategory or empty if none
    }, [category]);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (title && description && category && subcategory && resourceType && file) {
            const maxSize = 20 * 1024 * 1024; // 20 MB in bytes
            if (file.size > maxSize) {
                alert('Le fichier est trop volumineux. La taille maximale est de 20 Mo.');
                return;
            }
            alert('Ressource publiée avec succès !');
            window.location.href = 'dashboard_enseignant.html';
        } else {
            alert('Veuillez remplir tous les champs.');
        }
    };

    const handleCancel = () => {
        if (confirm('Êtes-vous sûr de vouloir annuler ? Les données non enregistrées seront perdues.')) {
            window.location.href = 'dashboard_enseignant.html';
        }
    };

    const handleFileChange = (e) => {
        const selectedFile = e.target.files[0];
        setFile(selectedFile);
        const label = e.target.nextElementSibling;
        label.innerHTML = (selectedFile ? selectedFile.name : 'Aucun fichier sélectionné') +
            '<br><span className="text-2xl">⬆</span><br>Cliquez pour télécharger ou glissez-déposez';
    };

    return (
        <main className="p-4">
            <div className="max-w-xl mx-auto">
                <div className="mb-4">
                    <h1 className="text-2xl font-bold">Publier une ressource</h1>
                </div>
                <div className="bg-white border border-gray-300 rounded-md p-4">
                    <h2 className="text-lg font-bold uppercase mb-2">Informations sur la ressource</h2>
                    <p className="text-gray-500 mb-4">Renseignez les détails de votre ressource pédagogique</p>
                    <form  name="myform" action="publier.php" method="post" enctype="multipart/form-data">
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
                                required
                            ></textarea>
                        </div>

                        <div className="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label htmlFor="category" className="block text-sm font-medium mb-1">Catégorie</label>
                                <input
                                    type="text"
                                    id="category"
                                    name="categorie"
                                    className="w-full border border-gray-300 rounded-md p-2"
                                    value={category}
                                    onChange={(e) => setCategory(e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <label htmlFor="subcategory" className="block text-sm font-medium mb-1">Sous-catégorie</label>
                                <input
                                    type="text"
                                    id="subcategory"
                                    name="sous_categorie"
                                    className="w-full border border-gray-300 rounded-md p-2"
                                    value={subcategory}
                                    onChange={(e) => setSubcategory(e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div className="mb-4">
                            <label className="block text-sm font-medium mb-1">Type de ressource</label>
                            <div className="flex gap-3">
                                <div className="flex-1">
                                    <input
                                        type="radio"
                                        id="document"
                                        
                                        name="resource-type"
                                        value="Document"
                                        checked={resourceType === 'Document'}
                                        onChange={(e) => setResourceType(e.target.value)}
                                        className="hidden"
                                    />
                                    <label
                                        htmlFor="document"
                                        className={`flex justify-center items-center gap-2 border border-gray-300 rounded-lg p-4 w-full min-h-[60px] cursor-pointer transition-colors duration-300 ${resourceType === 'Document' ? 'bg-gray-800 text-white' : 'bg-white text-gray-700'}`}
                                    >
                                        📄 Document
                                    </label>
                                </div>
                                <div className="flex-1">
                                    <input
                                        type="radio"
                                        id="video"
                                        name="resource-type"
                                        value="Vidéo"
                                        checked={resourceType === 'Vidéo'}
                                        onChange={(e) => setResourceType(e.target.value)}
                                        className="hidden"
                                    />
                                    <label
                                        htmlFor="video"
                                        className={`flex justify-center items-center gap-2 border border-gray-300 rounded-lg p-4 w-full min-h-[60px] cursor-pointer transition-colors duration-300 ${resourceType === 'Vidéo' ? 'bg-gray-800 text-white' : 'bg-white text-gray-700'}`}
                                    >
                                        ⏯ Vidéo
                                    </label>
                                </div>
                                <div className="flex-1">
                                    <input
                                        type="radio"
                                        id="presentation"
                                        name="resource-type"
                                        value="Présentation"
                                        checked={resourceType === 'Présentation'}
                                        onChange={(e) => setResourceType(e.target.value)}
                                        className="hidden"
                                    />
                                    <label
                                        htmlFor="presentation"
                                        className={`flex justify-center items-center gap-2 border border-gray-300 rounded-lg p-4 w-full min-h-[60px] cursor-pointer transition-colors duration-300 ${resourceType === 'Présentation' ? 'bg-gray-800 text-white' : 'bg-white text-gray-700'}`}
                                    >
                                        📊 Présentation
                                    </label>
                                </div>
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
                                    required
                                />
                                <label htmlFor="file" className="block mb-2">
                                    <span className="text-2xl">⬆</span><br />
                                    Cliquez pour télécharger ou glissez-déposez
                                </label>
                                <p className="text-gray-500 text-sm">PDF, DOCX, PPTX (MAX. 20 Mo)</p>
                            </div>
                        </div>

                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                
                                className="bg-gray-200 text-gray-700 px-4 py-2 rounded hover:bg-gray-300"
                            >
                               <a  href="<?= $tableau ?>"> Annuler</a>
                            </button>
                            <button
                                type="submit"
                                className="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-900"
                            >   
                                Publier
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
const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(<App />);