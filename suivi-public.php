<?php
// suivi-public.php - Page publique de suivi
require_once 'includes/config.php';

// Désactiver la vérification de session pour cette page
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die(" Ce lien n'est pas valide.");
}

// Récupérer le dossier avec ce token
$stmt = $pdo->prepare("
    SELECT d.*, 
           CASE 
               WHEN lien_active = 0 THEN 'désactivé'
               WHEN date_expiration_lien < CURDATE() THEN 'expiré'
               ELSE 'actif'
           END as etat_lien
    FROM dossiers d 
    WHERE token_unique = ?
");
$stmt->execute([$token]);
$dossier = $stmt->fetch();

// Vérifier si le lien est valide
if (!$dossier) {
    die("Lien invalide. Ce dossier n'existe pas ou le lien a été révoqué.");
}

if ($dossier['lien_active'] == 0) {
    die("Ce lien a été désactivé. Le rapport a déjà été livré.");
}

if (strtotime($dossier['date_expiration_lien']) < time()) {
    die("Ce lien a expiré. Contactez le laboratoire pour un nouveau lien.");
}

// Calcul du délai restant ou dépassé pour la date prévue du rapport
$date_prevue = new DateTime($dossier['date_prevue_rapport']);
$date_actuelle = new DateTime();
$diff_rapport = $date_actuelle->diff($date_prevue);
$jours_restants = $diff_rapport->days;
$est_depasse = $date_actuelle > $date_prevue;
$jours_texte = $est_depasse ? "dépassé de {$jours_restants} jour(s)" : "dans {$jours_restants} jour(s)";

// Vérifier si la table consultations_liens existe avant d'y insérer
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'consultations_liens'")->rowCount() > 0;
    
    if ($tableExists) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $stmt = $pdo->prepare("
            INSERT INTO consultations_liens (dossier_id, ip_address, user_agent)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$dossier['id'], $ip, $user_agent]);
    }
} catch (Exception $e) {
    error_log("Erreur d'enregistrement de consultation: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CETIEV - Suivi de votre dossier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --cetiev-blue: #3A7CA5;      /* Bleu moins concentré, plus doux */
            --cetiev-dark-blue: #2C5F8A;  /* Version plus foncée mais toujours douce */
            --cetiev-light-blue: #E8F1F8; /* Version très claire pour fonds */
            --cetiev-yellow: #FFCC00;
            --cetiev-white: #FFFFFF;
            --cetiev-gray: #F5F7FA;
            --cetiev-success: #28a745;
            --cetiev-success-light: #d4edda;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--cetiev-gray) 0%, #eef2f7 100%);
            min-height: 100vh;
        }
        
        .public-container {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* Header */
        .cetiev-header {
            background: linear-gradient(135deg, var(--cetiev-blue) 0%, var(--cetiev-dark-blue) 100%);
            color: white;
            padding: 1.5rem 2rem;
            text-align: center;
        }
        
        .cetiev-header .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .cetiev-header .logo-text span {
            color: var(--cetiev-yellow);
        }
        
        .cetiev-header .logo-sub {
            font-size: 0.8rem;
            opacity: 0.8;
            letter-spacing: 2px;
        }
        
        /* Logo ajusté */
        .logo-wrapper {
            display: flex;
            justify-content: center;
            margin-bottom: 0.5rem;
        }
        
        .logo-wrapper img {
            height: 60px;
            width: auto;
            filter: brightness(0) invert(1);
        }
        
        /* Content */
        .cetiev-content {
            padding: 2rem;
        }
        
        /* Timeline */
        .timeline-cetiev {
            position: relative;
            padding: 1rem 0;
        }
        
        .timeline-cetiev::before {
            content: '';
            position: absolute;
            left: 25px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, var(--cetiev-yellow), var(--cetiev-success), var(--cetiev-blue));
            border-radius: 3px;
        }
        
        .timeline-step {
            position: relative;
            margin-bottom: 2rem;
            padding-left: 70px;
        }
        
        .timeline-step .timeline-icon {
            position: absolute;
            left: 12px;
            top: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--cetiev-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }
        
        .timeline-step.completed .timeline-icon {
            background: var(--cetiev-blue);
            border-color: var(--cetiev-blue);
            color: white;
        }
        
        .timeline-step.completed-available .timeline-icon {
            background: var(--cetiev-success);
            border-color: var(--cetiev-success);
            color: white;
        }
        
        .timeline-step.active .timeline-icon {
            background: var(--cetiev-yellow);
            border-color: var(--cetiev-yellow);
            color: var(--cetiev-dark-blue);
            transform: scale(1.1);
            box-shadow: 0 0 0 3px rgba(58,124,165,0.2);
        }
        
        .timeline-content {
            background: var(--cetiev-gray);
            padding: 1rem;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .timeline-step.completed-available .timeline-content {
            background: var(--cetiev-success-light);
            border-left: 3px solid var(--cetiev-success);
        }
        
        .timeline-step.active .timeline-content {
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 3px solid var(--cetiev-yellow);
        }
        
        .timeline-title {
            font-weight: 600;
            color: var(--cetiev-dark-blue);
            margin-bottom: 0.25rem;
        }
        
        .timeline-desc {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .status-recu { background: #e3f2fd; color: #1565c0; }
        .status-dossier-a-completer { background: #e1f5fe; color: #0277bd; }
        .status-en-preparation { background: #fff3e0; color: #e65100; }
        .status-essais-en-cours { background: #e8f5e9; color: #2e7d32; }
        .status-essais-termines { background: #e0f2f1; color: #00695c; }
        .status-en-cours-de-validation-et-signatures { background: #fce4ec; color: #c2185b; }
        .status-rapport-disponible { background: #d4edda; color: #155724; }
        
        /* Info card */
        .info-card {
            background: var(--cetiev-gray);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid rgba(58,124,165,0.1);
        }
        
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6c757d;
            letter-spacing: 1px;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--cetiev-dark-blue);
            margin-top: 0.25rem;
        }
        
        /* Alert info */
        .alert-cetiev {
            background: linear-gradient(135deg, rgba(58,124,165,0.05) 0%, rgba(58,124,165,0.02) 100%);
            border-left: 4px solid var(--cetiev-yellow);
            border-radius: 12px;
        }
        
        /* Date prevue */
        .date-prevue {
            background: linear-gradient(135deg, var(--cetiev-blue) 0%, var(--cetiev-dark-blue) 100%);
            color: white;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        
        .date-prevue i {
            color: var(--cetiev-yellow);
            font-size: 1.5rem;
        }
        
        .date-prevue .jours {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        /* Boutons */
        .btn-cetiev {
            background-color: var(--cetiev-blue);
            color: white;
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-cetiev:hover {
            background-color: var(--cetiev-dark-blue);
            transform: translateY(-2px);
            color: var(--cetiev-yellow);
        }
        
        .btn-outline-cetiev {
            border: 2px solid var(--cetiev-blue);
            color: var(--cetiev-blue);
            border-radius: 50px;
            padding: 0.5rem 1.2rem;
            transition: all 0.3s;
            background: transparent;
        }
        
        .btn-outline-cetiev:hover {
            background-color: var(--cetiev-blue);
            color: white;
        }
        
        .btn-success-cetiev {
            background-color: var(--cetiev-success);
            color: white;
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-success-cetiev:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        
        /* Footer */
        .cetiev-footer {
            background: var(--cetiev-dark-blue);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .cetiev-footer a {
            color: var(--cetiev-yellow);
            text-decoration: none;
        }
        
        /* Card et titres */
        .card-header.bg-white {
            background-color: white !important;
        }
        
        h5, h6 {
            color: var(--cetiev-dark-blue) !important;
        }
        
        /* Animation pour le statut disponible */
        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
            }
        }
        
        .rapport-disponible-pulse {
            animation: pulse-green 2s infinite;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .public-container {
                margin: 20px;
            }
            .cetiev-content {
                padding: 1rem;
            }
            .timeline-step {
                padding-left: 55px;
            }
            .timeline-step .timeline-icon {
                left: 5px;
                width: 24px;
                height: 24px;
            }
            .timeline-cetiev::before {
                left: 17px;
            }
            .logo-wrapper img {
                height: 45px;
            }
            .cetiev-header .logo-text {
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="public-container">
            <!-- Header CETIEV avec logo ajusté -->
            <div class="cetiev-header">
                <div class="logo-wrapper">
                    <img src="logo.png" alt="CETIEV" 
                         onerror="this.src='https://via.placeholder.com/120x60?text=CETIEV+Express'; this.style.filter='none';"> 
                </div>
                <div class="logo-text">
                 <span>État d’avancement de votre demande</span>
                </div>
                <div class="retour-page-recherche mt-2">
                    <a href="index.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left"></i> Retour à la page de recherche
                    </a>
                </div>
                <div class="logo-sub">
                  
                </div>
                <div class="mt-3">
                    <span class="badge bg-light text-dark rounded-pill">
                        <i class="bi bi-shield-check text-success"></i> Lien sécurisé
                    </span>
                </div>
            </div>

            <div class="cetiev-content">
                <!-- Informations du dossier -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-label">N° DOSSIER</div>
                            <div class="info-value">
                                <i class="bi bi-upc-scan" style="color: var(--cetiev-yellow);"></i>
                                <?php echo htmlspecialchars($dossier['numero_dossier']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-label">CLIENT</div>
                            <div class="info-value">
                                <i class="bi bi-person-circle"></i>
                                <?php echo htmlspecialchars($dossier['client_nom']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="info-card">
                            <div class="info-label">DATE DE RÉCEPTION</div>
                            <div class="info-value">
                                <i class="bi bi-calendar-check"></i>
                                <?php echo date('d/m/Y', strtotime($dossier['date_reception'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="date-prevue">
                            <i class="bi bi-calendar-week"></i>
                            <div class="info-label text-white-50 mt-2">DATE PRÉVISIONNELLE DU RAPPORT</div>
                            <div class="fs-4 fw-bold">
                                <?php echo date('d/m/Y', strtotime($dossier['date_prevue_rapport'])); ?>
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-hourglass-split"></i>
                                    <?php echo $est_depasse ? 'En retard' : 'Restant'; ?> : <?php echo $jours_restants; ?> jour(s)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statut actuel -->
                <div class="alert alert-cetiev mb-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-info-circle-fill fs-2" style="color: var(--cetiev-blue);"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="mb-1" style="color: var(--cetiev-dark-blue);">Statut actuel</h5>
                            <div class="status-badge status-<?php echo strtolower(str_replace(' ', '-', str_replace('/', '-', $dossier['statut']))); ?>">
                                <i class="bi bi-arrow-right-circle-fill"></i>
                                <?php echo htmlspecialchars($dossier['statut']); ?>
                            </div>
                            <p class="mt-2 mb-0 text-muted small">
                                Dernière mise à jour : <?php echo date('d/m/Y à H:i', strtotime($dossier['date_modification'] ?? $dossier['date_reception'])); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Timeline du processus - AVEC LE NOUVEAU STATUT RAPPORT DISPONIBLE -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 pt-3">
                        <h5 class="mb-0" style="color: var(--cetiev-dark-blue);">
                            <i class="bi bi-diagram-3" style="color: var(--cetiev-yellow);"></i> État d'avancement de votre demande
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline-cetiev">
                            <?php
                            // NOUVELLE ÉTAPE : RAPPORT DISPONIBLE AJOUTÉE À LA FIN
                            $etapes = [
                                'Reçu' => ['Réception du dossier', 'bi-inbox', 'Votre dossier a été réceptionné'],
                                'Dossier à completer' => ['Dossier à compléter', 'bi-pencil-square', 'Informations complémentaires nécessaires'],
                                'En préparation' => ['Préparation du dossier', 'bi-gear', 'Préparation des analyses en cours'],
                                'Essais en cours' => ['Essais en cours', 'bi-flask', 'Tests et analyses en laboratoire'],
                                'Essais terminés' => ['Essais terminés', 'bi-check-circle', 'Les analyses sont finalisées'],
                                'En cours de validation et signatures' => ['Validation et signatures', 'bi-send-check', 'Validation finale du rapport'],
                                'Rapport disponible' => ['Rapport disponible', 'bi-file-earmark-pdf-fill', 'Votre rapport est prêt']
                            ];
                            
                            $statuts = array_keys($etapes);
                            $current_statut = $dossier['statut'];
                            $current_index = array_search($current_statut, $statuts);
                            
                            foreach ($etapes as $statut => $info):
                                $step_index = array_search($statut, $statuts);
                                $class = '';
                                $status_text = '';
                                
                                if ($step_index < $current_index) {
                                    $class = 'completed';
                                    $status_text = 'Terminé';
                                } elseif ($step_index == $current_index) {
                                    $class = 'active';
                                    $status_text = 'En cours';
                                    
                                    // Si le statut actuel est "Rapport disponible", on ajoute une classe spéciale
                                    if ($statut == 'Rapport disponible') {
                                        $class = 'completed-available';
                                        $status_text = 'Disponible';
                                    }
                                } else {
                                    $class = '';
                                    $status_text = 'À venir';
                                }
                            ?>
                            <div class="timeline-step <?php echo $class; ?>">
                                <div class="timeline-icon">
                                    <i class="bi <?php echo $info[1]; ?> small"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <div>
                                            <div class="timeline-title"><?php echo $info[0]; ?></div>
                                            <div class="timeline-desc"><?php echo $info[2]; ?></div>
                                        </div>
                                        <div>
                                            <?php if ($step_index < $current_index): ?>
                                                <span class="badge bg-success rounded-pill">
                                                    <i class="bi bi-check-circle-fill"></i> <?php echo $status_text; ?>
                                                </span>
                                            <?php elseif ($step_index == $current_index && $statut == 'Rapport disponible'): ?>
                                                <span class="badge" style="background-color: var(--cetiev-success); color: white;">
                                                    <i class="bi bi-file-earmark-pdf-fill"></i> <?php echo $status_text; ?>
                                                </span>
                                            <?php elseif ($step_index == $current_index): ?>
                                                <span class="badge" style="background-color: var(--cetiev-yellow); color: var(--cetiev-dark-blue);">
                                                    <i class="bi bi-arrow-repeat"></i> <?php echo $status_text; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary rounded-pill">
                                                    <i class="bi bi-clock"></i> <?php echo $status_text; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Section Rapport (si disponible) - AVEC EFFET DE PULSATION -->
                <?php if (!empty($dossier['rapport_fichier'])): ?>
                <div class="card border-0 shadow-sm mb-4 rapport-disponible-pulse" style="border-left: 4px solid #28a745;">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="bi bi-file-earmark-pdf-fill fs-1 text-success"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1" style="color: var(--cetiev-dark-blue);">
                                    <i class="bi bi-check-circle-fill text-success"></i> Rapport final disponible
                                </h6>
                                <p class="mb-2 text-muted small">Votre rapport d'analyse est prêt à être téléchargé.</p>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="<?php echo htmlspecialchars($dossier['rapport_fichier']); ?>" 
                                       class="btn btn-success-cetiev btn-sm" 
                                       target="_blank" 
                                       download>
                                        <i class="bi bi-download"></i> Télécharger le rapport
                                    </a>
                                    <a href="<?php echo htmlspecialchars($dossier['rapport_fichier']); ?>" 
                                       class="btn btn-outline-cetiev btn-sm" 
                                       target="_blank">
                                        <i class="bi bi-eye"></i> Visualiser en ligne
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Informations de contact -->
                <div class="card border-0 bg-light mb-4">
                    <div class="card-body">
                        <h6 class="mb-3" style="color: var(--cetiev-dark-blue);">
                            <i class="bi bi-headset"></i> Besoin d'aide ?
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-telephone-fill" style="color: var(--cetiev-yellow);"></i>
                                <strong> Service client :</strong>  (+212)661 591 469 -(+212)522 583 958
                            </div>
                            <div class="col-md-6 mb-2">
                                <i class="bi bi-envelope-fill" style="color: var(--cetiev-yellow);"></i>
                                <strong> Email :</strong> info@cetiev.ma
                            </div>
                            <div class="col-md-12">
                                <i class="bi bi-clock-fill" style="color: var(--cetiev-yellow);"></i>
                                <strong> Horaires :</strong> Lundi au vendredi, 9h - 18h
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lien de partage -->
                <div class="text-center pt-3 border-top">
                    <p class="text-muted small mb-2">
                        <i class="bi bi-link-45deg"></i> Partager ce lien de suivi
                    </p>
                    <div class="input-group mb-2">
                        <input type="text" 
                               class="form-control" 
                               id="share-link" 
                               value="<?php echo htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" 
                               readonly
                               style="border-radius: 50px 0 0 50px;">
                        <button class="btn btn-cetiev" type="button" onclick="copierLien()" style="border-radius: 0 50px 50px 0;">
                            <i class="bi bi-clipboard"></i> Copier
                        </button>
                    </div>
                    <small class="text-muted">
                        Ce lien expirera le <?php echo date('d/m/Y', strtotime($dossier['date_expiration_lien'])); ?>
                    </small>
                </div>
            </div>

            <!-- Footer -->
            <div class="cetiev-footer">
                <small>&copy; 2026 CETIEV - Tous droits réservés</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction pour copier le lien de partage
        function copierLien() {
            const shareLink = document.getElementById('share-link');
            shareLink.select();
            shareLink.setSelectionRange(0, 99999);
            
            try {
                navigator.clipboard.writeText(shareLink.value).then(() => {
                    const button = event.target;
                    const originalHTML = button.innerHTML;
                    button.innerHTML = '<i class="bi bi-check"></i> Copié !';
                    button.classList.remove('btn-cetiev');
                    button.classList.add('btn-success');
                    
                    setTimeout(() => {
                        button.innerHTML = originalHTML;
                        button.classList.remove('btn-success');
                        button.classList.add('btn-cetiev');
                    }, 2000);
                });
            } catch (err) {
                document.execCommand('copy');
                alert('Lien copié dans le presse-papier !');
            }
        }

        // Auto-refresh toutes les 2 minutes
        let refreshTimer = setTimeout(function() {
            window.location.reload();
        }, 120000);

        document.addEventListener('click', function() {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(function() {
                window.location.reload();
            }, 120000);
        });
    </script>
</body>
</html>