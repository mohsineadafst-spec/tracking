<?php
require_once 'includes/config.php';

// Vérifier la connexion
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialisation des variables
$id_dossier = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
$message_success = '';
$message_erreur = '';
$dossier = null;

// Définition des statuts
$statuts = [
    'Reçu' => ['icon' => 'bi-inbox', 'color' => 'primary', 'description' => 'Dossier réceptionné'],
    'Dossier à completer' => ['icon' => 'bi-pencil-square', 'color' => 'info', 'description' => 'Informations manquantes'],
    'En préparation' => ['icon' => 'bi-gear', 'color' => 'warning', 'description' => 'Préparation en cours'],
    'Essais en cours' => ['icon' => 'bi-flask', 'color' => 'success', 'description' => 'Tests et analyses en cours'],
    'Essais terminés' => ['icon' => 'bi-check-circle', 'color' => 'info', 'description' => 'Tests finalisés'],
    'En cours de validation et signatures' => ['icon' => 'bi-send-check', 'color' => 'danger', 'description' => 'Validation en attente']
];

// Vérifier si le dossier existe
if ($id_dossier > 0) {
    $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id = ?");
    $stmt->execute([$id_dossier]);
    $dossier = $stmt->fetch();
    
    if (!$dossier) {
        $message_erreur = "Dossier non trouvé.";
    }
} else {
    $message_erreur = "ID de dossier invalide.";
}

// Traitement du changement de statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_statut']) && $dossier) {
    $nouveau_statut = $_POST['statut'];
    $commentaire = trim($_POST['commentaire'] ?? '');
    
    // Vérifier si le statut existe
    if (array_key_exists($nouveau_statut, $statuts)) {
        try {
            // Enregistrer l'ancien statut
            $ancien_statut = $dossier['statut'];
            
            // Mettre à jour le statut
            $stmt = $pdo->prepare("UPDATE dossiers SET statut = ?, date_modification = NOW() WHERE id = ?");
            $stmt->execute([$nouveau_statut, $id_dossier]);
            
            // Enregistrer l'historique des changements
            $stmt_hist = $pdo->prepare("
                INSERT INTO historique_statuts (dossier_id, ancien_statut, nouveau_statut, commentaire, id, date_changement) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt_hist->execute([
                $id_dossier, 
                $ancien_statut, 
                $nouveau_statut, 
                $commentaire, 
                $_SESSION['user_id']
            ]);
            
            $message_success = "Statut mis à jour avec succès !";
            
            // Recharger le dossier
            $stmt = $pdo->prepare("SELECT * FROM dossiers WHERE id = ?");
            $stmt->execute([$id_dossier]);
            $dossier = $stmt->fetch();
            
        } catch (PDOException $e) {
            $message_erreur = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } else {
        $message_erreur = "Statut invalide.";
    }
}

// Récupérer l'historique des changements
$historique = [];
if ($dossier) {
    $stmt = $pdo->prepare("
        SELECT h.*, u.nom_complet 
        FROM historique_statuts h
        LEFT JOIN utilisateurs u ON h.id = u.id
        WHERE h.dossier_id = ?
        ORDER BY h.date_changement DESC
        LIMIT 20
    ");
    $stmt->execute([$id_dossier]);
    $historique = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CETIEV Express - Changer statut dossier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --cetiev-blue: #0A2540;
            --cetiev-dark-blue: #061a2e;
            --cetiev-yellow: #FFCC00;
            --cetiev-white: #FFFFFF;
            --cetiev-gray: #F5F5F5;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--cetiev-gray);
        }
        
        /* Navbar */
        .navbar-cetiev {
            background-color: var(--cetiev-blue);
            padding: 0.8rem 0;
            box-shadow: 0 2px 15px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-cetiev .navbar-brand {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-cetiev .logo-img {
            height: 45px;
            width: auto;
            filter: brightness(0) invert(1);
        }
        
        .navbar-cetiev .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }
        
        .navbar-cetiev .logo-text span {
            color: var(--cetiev-yellow);
        }
        
        .navbar-cetiev .logo-sub {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.7);
            letter-spacing: 1px;
        }
        
        .navbar-cetiev .nav-link {
            color: white !important;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .navbar-cetiev .nav-link:hover {
            color: var(--cetiev-yellow) !important;
            transform: translateY(-2px);
        }
        
        /* Main content */
        .main-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .main-card .card-header {
            background: linear-gradient(135deg, var(--cetiev-blue) 0%, var(--cetiev-dark-blue) 100%);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .main-card .card-header h4 {
            margin: 0;
        }
        
        /* Statut cards */
        .statut-option {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
        }
        
        .statut-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .statut-option.selected {
            border-color: var(--cetiev-yellow);
            background: linear-gradient(135deg, rgba(10,37,64,0.05) 0%, rgba(10,37,64,0.02) 100%);
        }
        
        .statut-option input[type="radio"] {
            display: none;
        }
        
        .statut-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .statut-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .statut-desc {
            font-size: 0.85rem;
            color: #666;
        }
        
        /* Badge statut actuel */
        .current-status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        /* Historique */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--cetiev-blue), #ddd);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .timeline-icon {
            position: absolute;
            left: -2rem;
            top: 0;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--cetiev-blue);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .timeline-content {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        /* Alert */
        .alert-custom {
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        /* Footer */
        .cetiev-footer {
            background: var(--cetiev-blue);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }
        
        .cetiev-footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .cetiev-footer a:hover {
            color: var(--cetiev-yellow);
        }
        
        /* Info dossier */
        .info-dossier {
            background: var(--cetiev-gray);
            border-radius: 10px;
            padding: 1rem;
        }
        
        @media (max-width: 768px) {
            .statut-option {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-cetiev navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="logo.png" alt="CETIEV Express" class="logo-img" 
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 50%22%3E%3Ctext x=%2210%22 y=%2235%22 font-family=%22Arial%22 font-size=%2225%22 font-weight=%22bold%22 fill=%22white%22%3ECETIEV%3C/text%3E%3Ctext x=%22120%22 y=%2235%22 font-family=%22Arial%22 font-size=%2225%22 font-weight=%22bold%22 fill=%22%23FFCC00%22%3EExpress%3C/text%3E%3C/svg%3E'; this.style.filter='none';">
                <div>
                    <div class="logo-text">CETIEV<span>Express</span></div>
                    <div class="logo-sub">WORLDWIDE DELIVERY</div>
                </div>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCETIEV">
                <i class="bi bi-list" style="color: white; font-size: 1.5rem;"></i>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarCETIEV">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nom_complet']); ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Tableau de bord
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Messages -->
        <?php if($message_success): ?>
            <div class="alert alert-success alert-dismissible fade show alert-custom" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?php echo $message_success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($message_erreur): ?>
            <div class="alert alert-danger alert-dismissible fade show alert-custom" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $message_erreur; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($dossier): ?>
            <!-- Informations du dossier -->
            <div class="main-card card">
                <div class="card-header">
                    <h4><i class="bi bi-folder2"></i> Dossier : <?php echo htmlspecialchars($dossier['numero_dossier']); ?></h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-dossier">
                                <h6><i class="bi bi-person"></i> Client</h6>
                                <p class="mb-0"><strong><?php echo htmlspecialchars($dossier['client_nom']); ?></strong></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-dossier">
                                <h6><i class="bi bi-calendar"></i> Date réception</h6>
                                <p class="mb-0"><strong><?php echo date('d/m/Y', strtotime($dossier['date_reception'])); ?></strong></p>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="info-dossier">
                                <h6><i class="bi bi-tag"></i> Statut actuel</h6>
                                <?php
                                $current_statut_info = $statuts[$dossier['statut']] ?? ['icon' => 'bi-question-circle', 'color' => 'secondary'];
                                ?>
                                <div class="current-status-badge bg-<?php echo $current_statut_info['color']; ?> text-white">
                                    <i class="bi <?php echo $current_statut_info['icon']; ?>"></i>
                                    <?php echo htmlspecialchars($dossier['statut']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulaire changement de statut -->
            <div class="main-card card">
                <div class="card-header">
                    <h4><i class="bi bi-arrow-repeat"></i> Changer le statut</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="formChangementStatut">
                        <input type="hidden" name="id" value="<?php echo $id_dossier; ?>">
                        
                        <div class="row">
                            <?php foreach($statuts as $statut => $info): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="statut-option <?php echo ($dossier['statut'] == $statut) ? 'selected' : ''; ?>" 
                                     onclick="selectStatut('<?php echo addslashes($statut); ?>')">
                                    <input type="radio" name="statut" value="<?php echo htmlspecialchars($statut); ?>" 
                                           id="statut_<?php echo md5($statut); ?>"
                                           <?php echo ($dossier['statut'] == $statut) ? 'checked' : ''; ?>>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="statut-icon bg-<?php echo $info['color']; ?> bg-opacity-25 text-<?php echo $info['color']; ?>">
                                            <i class="bi <?php echo $info['icon']; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="statut-title"><?php echo htmlspecialchars($statut); ?></div>
                                            <div class="statut-desc"><?php echo $info['description']; ?></div>
                                        </div>
                                        <?php if($dossier['statut'] == $statut): ?>
                                            <div class="ms-auto">
                                                <i class="bi bi-check-circle-fill text-success"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4">
                            <label class="form-label"><i class="bi bi-chat"></i> Commentaire (optionnel)</label>
                            <textarea class="form-control" name="commentaire" rows="3" 
                                      placeholder="Ajoutez un commentaire sur ce changement de statut..."></textarea>
                        </div>
                        
                        <div class="mt-4 d-flex gap-2 justify-content-end">
                            <a href="dossier-detail.php?id=<?php echo $id_dossier; ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" name="changer_statut" class="btn btn-primary" style="background-color: var(--cetiev-blue);">
                                <i class="bi bi-check-lg"></i> Valider le changement
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Historique des changements -->
            <div class="main-card card">
                <div class="card-header">
                    <h4><i class="bi bi-clock-history"></i> Historique des statuts</h4>
                </div>
                <div class="card-body">
                    <?php if(count($historique) > 0): ?>
                        <div class="timeline">
                            <?php foreach($historique as $hist): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon bg-<?php 
                                    $statut_info = $statuts[$hist['nouveau_statut']] ?? ['color' => 'secondary'];
                                    echo $statut_info['color']; 
                                ?> text-white">
                                    <i class="bi bi-arrow-right-short"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                                        <div>
                                            <strong><?php echo htmlspecialchars($hist['nom_complet'] ?? 'Utilisateur'); ?></strong>
                                            <span class="text-muted">a changé le statut de</span>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($hist['ancien_statut']); ?></span>
                                            <i class="bi bi-arrow-right"></i>
                                            <span class="badge bg-<?php echo $statut_info['color']; ?>"><?php echo htmlspecialchars($hist['nouveau_statut']); ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y H:i', strtotime($hist['date_changement'])); ?>
                                        </small>
                                    </div>
                                    <?php if(!empty($hist['commentaire'])): ?>
                                        <div class="mt-2 text-muted small">
                                            <i class="bi bi-chat"></i> "<?php echo htmlspecialchars($hist['commentaire']); ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clock-history display-4 text-muted"></i>
                            <p class="mt-2 mb-0 text-muted">Aucun historique disponible</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Dossier non trouvé -->
            <div class="main-card card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-folder-x display-1 text-muted"></i>
                    <h4 class="mt-3">Dossier non trouvé</h4>
                    <p class="text-muted">Le dossier que vous recherchez n'existe pas ou a été supprimé.</p>
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> Retour au tableau de bord
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="cetiev-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-2 justify-content-center justify-content-md-start">
                        <img src="logo.png" alt="CETIEV Express" style="height: 30px; filter: brightness(0) invert(1);" 
                             onerror="this.style.display='none'">
                        <div class="logo-text" style="font-size: 1rem;">CETIEV<span style="color: var(--cetiev-yellow);">Express</span></div>
                    </div>
                    <small class="mt-2 d-block">&copy; 2024 CETIEV Express - Tous droits réservés</small>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="#" class="me-3">Mentions légales</a>
                    <a href="#">Contact</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectStatut(statut) {
            // Désélectionner tous les radios
            document.querySelectorAll('.statut-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Sélectionner le radio correspondant
            const radio = document.querySelector(`input[value="${statut.replace(/"/g, '\\"')}"]`);
            if(radio) {
                radio.checked = true;
                radio.closest('.statut-option').classList.add('selected');
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(alert => {
                if(alert) {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        setTimeout(() => {
                            alert.style.display = 'none';
                        }, 300);
                    }, 3000);
                }
            });
        }, 100);
    </script>
</body>
</html>