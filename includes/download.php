<?php
session_start();
require_once '../auth/db.php';
require_once '../auth/auth.php';

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['etudiant', 'enseignant'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Accès non autorisé.');
}

// Validate publication ID
$id_pub = isset($_GET['id_pub']) ? intval($_GET['id_pub']) : 0;
if ($id_pub <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit('Publication invalide.');
}

try {
    // Fetch publication details
    $stmt = $connexion->prepare("SELECT contenu FROM publication WHERE id_pub = :id_pub");
    $stmt->execute(['id_pub' => $id_pub]);
    $publication = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$publication) {
        header('HTTP/1.1 404 Not Found');
        exit('Publication non trouvée.');
    }

    // Construct file path
    $file_name = basename($publication['contenu']); // Ensure only file name is used
    $file_path = __DIR__ . '/../Uploads/publications/' . $file_name;

    // Check if file exists
    if (!file_exists($file_path)) {
        header('HTTP/1.1 404 Not Found');
        exit('Fichier non trouvé.');
    }

    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Output file
    readfile($file_path);
    exit;
} catch (PDOException $e) {
    error_log("Erreur téléchargement: " . $e->getMessage(), 3, __DIR__ . '/../logs/errors.log');
    header('HTTP/1.1 500 Internal Server Error');
    exit('Erreur serveur.');
}
?>