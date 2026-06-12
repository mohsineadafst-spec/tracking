<?php
require_once '../includes/config.php';

// Vérifier la connexion
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit();
}
if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    // L'utilisateur est un admin, accès autorisé
} else {
    // L'utilisateur n'est pas un admin, rediriger ou afficher un message d'erreur
    echo "<script>alert('Accès refusé : vous n\'avez pas les permissions nécessaires.');</script>";
    header('Location: login.php'); // Redirige vers le tableau de bord ou une page d'erreur
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['id'])) {
    csrf_check();
    $dossierId = (int)$_GET['id'];
    
    try {
        // Récupérer le statut actuel
        $stmt = $pdo->prepare("SELECT statut FROM dossiers WHERE id = ?");
        $stmt->execute([$dossierId]);
        $dossier = $stmt->fetch();
        
        if (!$dossier) {
            echo json_encode(['success' => false, 'message' => 'Dossier non trouvé']);
            exit();
        }
        
        // Déterminer le prochain statut
        $statuts = [
            'reçu' => 'en préparation',
            'en préparation' => 'en cours essai',
            'en cours essai' => 'en vérification',
            'en vérification' => 'rapport envoyé',
            'rapport envoyé' => 'rapport envoyé' // Dernier statut
        ];
        
        $ancienStatut = $dossier['statut'];
        $nouveauStatut = $statuts[$ancienStatut];
        
        // Mettre à jour le dossier
        $stmt = $pdo->prepare("UPDATE dossiers SET statut = ? WHERE id = ?");
        $stmt->execute([$nouveauStatut, $dossierId]);
        
        // Enregistrer dans l'historique
        $stmt = $pdo->prepare("
            INSERT INTO historique_statuts 
            (dossier_id, ancien_statut, nouveau_statut, utilisateur) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $dossierId, 
            $ancienStatut, 
            $nouveauStatut,
            $_SESSION['nom_complet']
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Statut mis à jour',
            'nouveau_statut' => $nouveauStatut
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>