<?php
// api/check-dossier.php - Vérifier si un numéro de dossier existe déjà
require_once '../includes/config.php';

header('Content-Type: application/json');

// Vérifier si le paramètre numero est présent
if (!isset($_GET['numero']) || empty($_GET['numero'])) {
    echo json_encode(['exists' => false, 'error' => 'Numéro manquant']);
    exit();
}

$numero = $_GET['numero'];

// Vérifier le format (10 chiffres)
if (!preg_match('/^\d{10}$/', $numero)) {
    echo json_encode(['exists' => false, 'valid_format' => false, 'message' => 'Le numéro doit contenir exactement 10 chiffres']);
    exit();
}

try {
    // Vérifier si le numéro existe déjà
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE numero_dossier = ?");
    $stmt->execute([$numero]);
    $count = $stmt->fetchColumn();
    
    echo json_encode([
        'exists' => ($count > 0),
        'valid_format' => true,
        'message' => ($count > 0) ? 'Ce numéro est déjà utilisé' : 'Numéro disponible'
    ]);
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'error' => 'Erreur de base de données']);
}
?>