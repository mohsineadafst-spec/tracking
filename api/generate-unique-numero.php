<?php
// api/generate-unique-numero.php
require_once '../includes/config.php';

header('Content-Type: application/json');

function genererNumeroUnique($pdo) {
    $maxAttempts = 20;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {

        // Générer un numéro sécurisé de 10 chiffres
        $numero = '';
        for ($i = 0; $i < 10; $i++) {
            $numero .= random_int(0, 9);
        }

        // Vérifier si le numéro existe déjà
        $stmt = $pdo->prepare("
            SELECT 1 
            FROM dossiers 
            WHERE numero_dossier = ? 
            LIMIT 1
        ");
        $stmt->execute([$numero]);

        if (!$stmt->fetch()) {
            return $numero;
        }
    }

    // Fallback (moins idéal mais acceptable)
    return date('YmdHis') . random_int(100, 999);
}

try {
    $numero = genererNumeroUnique($pdo);
    echo json_encode([
        'success' => true,
        'numero' => $numero
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>