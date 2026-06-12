<?php
// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Journaliser la déconnexion (optionnel)
if (isset($_SESSION['user_id']) && isset($_SESSION['nom_complet'])) {
    $log_file = 'logs/connexions_log.txt';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . " | Déconnexion | Utilisateur ID: " . $_SESSION['user_id'] . 
                 " | Nom: " . $_SESSION['nom_complet'] . 
                 " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Supprimer toutes les variables de session
$_SESSION = array();

// Supprimer le cookie de session si présent
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Détruire complètement la session
session_destroy();

// Supprimer les cookies supplémentaires si vous en utilisez
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
}

// Rediriger vers la page de connexion
header('Location: login.php?message=deconnecte');
exit();
?>