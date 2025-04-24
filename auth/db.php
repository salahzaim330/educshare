<?php
$host = 'localhost';
$dbname = 'edushare';
$username = 'root';
$password = ''; // Nom de variable plus explicite

try {
    // Ajout de charset=utf8mb4 pour supporter les caractères spéciaux
    $connexion = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Gestion des erreurs
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Mode de récupération par défaut
            PDO::ATTR_EMULATE_PREPARES => false // Désactiver l'émulation pour plus de sécurité
        ]
    );
    return $connexion;
} catch (PDOException $e) {
    // Afficher un message générique pour l'utilisateur et arrêter l'exécution
    die('Erreur de connexion à la base de données. Veuillez réessayer plus tard.');
}
?>