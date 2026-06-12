<?php
require_once 'includes/config.php';

// Vérifier la connexion


// Initialiser les variables
$numero_dossier = '';
$dossier_trouve = null;
$message_erreur = '';
$recherche_effectuee = false;
$acces_non_autorise = false;

// Traitement de la recherche
if (isset($_GET['search']) || isset($_POST['numero_dossier'])) {
    $recherche_effectuee = true;
    
    // Récupérer le numéro de dossier
    $numero_dossier = trim($_GET['search'] ?? $_POST['numero_dossier'] ?? '');
    
    if (!empty($numero_dossier)) {
        // Recherche EXACTE uniquement (pas de recherche partielle pour plus de sécurité)
        $stmt = $pdo->prepare("
            SELECT d.*, u.nom_complet as createur_nom 
            FROM dossiers d
            LEFT JOIN utilisateurs u ON d.createur_id = u.id
            WHERE d.numero_dossier = ?
        ");
        $stmt->execute([$numero_dossier]);
        $dossier_trouve = $stmt->fetch();
        
        if (!$dossier_trouve) {
            // Message générique pour ne pas révéler l'existence ou non du dossier
            $message_erreur = "Aucun dossier trouvé. Vérifiez le numéro saisi.";
        } else {
            // Vérifier les droits d'accès selon le rôle
            $user_role = $_SESSION['role'];
            $user_id = $_SESSION['user_id'];
            $createur_id = $dossier_trouve['createur_id'];
            
            // Règles de confidentialité
            $acces_autorise = false;
            
            if ($user_role == 'admin') {
                // Admin voit tout
                $acces_autorise = true;
            } elseif ($user_role == 'superviseur') {
                // Superviseur voit les dossiers de son équipe (à adapter)
                $acces_autorise = true;
            } elseif ($user_id == $createur_id) {
                // Utilisateur voit uniquement ses propres dossiers
                $acces_autorise = true;
            }
            
            if (!$acces_autorise) {
                $acces_non_autorise = true;
                $dossier_trouve = null;
                $message_erreur = "Accès non autorisé à ce dossier.";
                // Journaliser la tentative d'accès non autorisé
                logAccesNonAutorise($user_id, $numero_dossier);
            }
        }
    } else {
        $message_erreur = "Veuillez saisir un numéro de dossier";
    }
}

// Fonction pour journaliser les accès non autorisés
function logAccesNonAutorise($user_id, $numero_dossier) {
    $log_file = 'logs/acces_log.txt';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . " | Utilisateur ID: $user_id | Tentative accès dossier: $numero_dossier | IP: " . $_SERVER['REMOTE_ADDR'] . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche confidentielle - Tracking Dossiers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .search-container {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .result-card {
            transition: transform 0.3s;
            margin-bottom: 1rem;
            border-left: 4px solid #0d6efd;
        }
        .confidential-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            opacity: 0.7;
        }
        .info-confidentielle {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        /* Masquer les résultats de recherche précédents */
        #resultatsSection {
            display: none;
        }
        #resultatsSection.affiche {
            display: block;
        }
        /* Animation de chargement */
        .loader {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        .loader.show {
            display: block;
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-shield-lock"></i> Tracking Dossiers
            </a>
        
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Barre de recherche unique -->
        <div class="search-container text-white">
            <div class="row justify-content-center">
                <div class="col-md-8 text-center">
                    <i class="bi bi-shield-lock-fill display-4 mb-3"></i>
                    <h2 class="mb-3">Recherche confidentielle</h2>
                    <p class="mb-4">
                        <i class="bi bi-info-circle"></i> 
                        Entrez le numéro exact du dossier pour consulter son avancement
                    </p>
                    
                    <form method="POST" action="" id="searchForm" autocomplete="off">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">
                                <i class="bi bi-folder-lock"></i>
                            </span>
                            <input type="text" 
                                   class="form-control" 
                                   id="numero_dossier" 
                                   name="numero_dossier" 
                                   placeholder="Ex: DOS-2024-001"
                                   value="<?php echo htmlspecialchars($numero_dossier); ?>"
                                   required
                                   autocomplete="off">
                            <button class="btn btn-light" type="submit">
                                <i class="bi bi-search"></i> Rechercher
                            </button>
                        </div>
                        <div class="form-text text-white-50 mt-2">
                            <i class="bi bi-shield-check"></i> 
                            Recherche strictement confidentielle - Vous ne voyez que vos dossiers autorisés
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Zone de chargement -->
        <div id="loader" class="loader">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Recherche en cours...</p>
        </div>

        <!-- Messages d'erreur génériques -->
        <?php if($message_erreur): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i> 
                <?php echo $message_erreur; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Résultats de recherche (affichage conditionnel) -->
        <?php if($recherche_effectuee && $dossier_trouve && !$acces_non_autorise): ?>
            <div id="resultatsSection" class="affiche">
                <!-- Avertissement confidentiel -->
                <div class="info-confidentielle">
                    <i class="bi bi-shield-lock-fill"></i>
                    <strong>Information confidentielle</strong><br>
                    <small>Ces informations sont strictement confidentielles et ne doivent être partagées qu'avec les personnes autorisées.</small>
                </div>

                <div class="card result-card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-folder-fill text-primary"></i>
                            Dossier : <strong><?php echo htmlspecialchars($dossier_trouve['numero_dossier']); ?></strong>
                        </h5>
                        <div>
                            <span class="badge <?php echo getStatutBadgeClass($dossier_trouve['statut']); ?>">
                                <?php echo htmlspecialchars($dossier_trouve['statut']); ?>
                            </span>
                            <button class="btn btn-sm btn-outline-secondary ms-2" 
                                    onclick="window.print()">
                                <i class="bi bi-printer"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="bi bi-person-badge"></i> Informations client</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="35%">Client :</th>
                                        <td><strong><?php echo htmlspecialchars($dossier_trouve['client_nom']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>Contact :</th>
                                        <td><?php echo htmlspecialchars($dossier_trouve['client_contact'] ?? 'Non renseigné'); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Date réception :</th>
                                        <td><?php echo date('d/m/Y H:i', strtotime($dossier_trouve['date_reception'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="bi bi-info-circle"></i> Détails dossier</h6>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <th width="35%">Urgence :</th>
                                        <td>
                                            <span class="badge bg-<?php echo getUrgenceClass($dossier_trouve['urgence']); ?>">
                                                <?php echo htmlspecialchars($dossier_trouve['urgence']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if($dossier_trouve['date_limite']): ?>
                                    <tr>
                                        <th>Date limite :</th>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($dossier_trouve['date_limite'])); ?>
                                            <?php if(estEnRetard($dossier_trouve['date_limite'])): ?>
                                                <span class="text-danger">(Retard)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th>Référence :</th>
                                        <td><?php echo htmlspecialchars($dossier_trouve['reference'] ?? 'Non spécifiée'); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if(!empty($dossier_trouve['description'])): ?>
                        <div class="mt-3">
                            <h6><i class="bi bi-file-text"></i> Description</h6>
                            <div class="border rounded p-2 bg-light">
                                <?php echo nl2br(htmlspecialchars($dossier_trouve['description'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Timeline avancement -->
                        <div class="mt-3">
                            <h6><i class="bi bi-clock-history"></i> Avancement du dossier</h6>
                            <div class="timeline">
                                <?php
                                $etapes = [
                                    'reçu' => 'Dossier reçu',
                                    'en préparation' => 'En préparation',
                                    'en cours essai' => 'En cours d\'essai',
                                    'en vérification' => 'En vérification',
                                    'rapport envoyé' => 'Rapport envoyé'
                                ];
                                $statut_actuel = $dossier_trouve['statut'];
                                $trouve = false;
                                
                                foreach($etapes as $key => $label):
                                    $completed = false;
                                    if(!$trouve && $key == $statut_actuel) {
                                        $trouve = true;
                                        $completed = true;
                                    } elseif(!$trouve) {
                                        $completed = true;
                                    }
                                ?>
                                <div class="timeline-step <?php echo $completed ? 'completed' : ''; ?>">
                                    <strong><?php echo $label; ?></strong>
                                    <?php if($key == $statut_actuel): ?>
                                        <span class="badge bg-success ms-2">En cours</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Pied de page confidentiel -->
                        <div class="mt-4 pt-2 border-top">
                            <small class="text-muted">
                                <i class="bi bi-shield-lock"></i> 
                                Document confidentiel - Généré le <?php echo date('d/m/Y à H:i'); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Bouton nouvelle recherche -->
                <div class="text-center mt-3">
                    <button class="btn btn-outline-primary" onclick="nouvelleRecherche()">
                        <i class="bi bi-plus-circle"></i> Effectuer une nouvelle recherche
                    </button>
                </div>
            </div>
        <?php elseif($recherche_effectuee && !$dossier_trouve && !$acces_non_autorise): ?>
            <div class="text-center py-5">
                <i class="bi bi-search display-1 text-muted"></i>
                <h4 class="mt-3">Aucun résultat</h4>
                <p class="text-muted">Aucun dossier ne correspond à ce numéro</p>
                <button class="btn btn-primary" onclick="nouvelleRecherche()">
                    <i class="bi bi-arrow-repeat"></i> Nouvelle recherche
                </button>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .timeline-step {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }
        .timeline-step:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #dee2e6;
            border: 2px solid #0d6efd;
        }
        .timeline-step.completed:before {
            background-color: #198754;
            border-color: #198754;
        }
        .timeline-step:after {
            content: '';
            position: absolute;
            left: 9px;
            top: 20px;
            width: 2px;
            height: calc(100% - 20px);
            background-color: #dee2e6;
        }
        .timeline-step:last-child:after {
            display: none;
        }
        
        @media print {
            .navbar, .search-container, .btn, .info-confidentielle, .alert {
                display: none !important;
            }
            .result-card {
                border: 1px solid #ddd;
                margin: 0;
                padding: 0;
            }
            body {
                padding: 0;
                margin: 0;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Effacer le formulaire après soumission pour éviter les re-soumissions accidentelles
        document.getElementById('searchForm')?.addEventListener('submit', function() {
            document.getElementById('loader')?.classList.add('show');
            // Optionnel : cacher les résultats précédents
            const resultSection = document.getElementById('resultatsSection');
            if(resultSection) {
                resultSection.classList.remove('affiche');
            }
        });
        
        // Fonction pour nouvelle recherche
        function nouvelleRecherche() {
            document.getElementById('numero_dossier').value = '';
            document.getElementById('numero_dossier').focus();
            // Recharger la page sans paramètres
            window.location.href = 'recherche-dossier.php';
        }
        
        // Empêcher la mise en cache des résultats sensibles
        window.addEventListener('load', function() {
            // Désactiver la mise en cache de la page
            fetch(window.location.href, {
                method: 'HEAD',
                cache: 'no-store'
            });
        });
        
        // Détecter la navigation arrière pour effacer les données sensibles
        window.addEventListener('pageshow', function(event) {
            if (event.persisted) {
                // Page chargée depuis le cache, forcer le rechargement
                window.location.reload();
            }
        });
        
        // Effacement automatique après inactivité (optionnel)
        let inactivityTimer;
        function resetInactivityTimer() {
            clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(function() {
                if(confirm('Session inactive. Voulez-vous continuer ?')) {
                    // Rafraîchir la page pour effacer les données sensibles
                    window.location.href = 'recherche-dossier.php';
                }
            }, 15 * 60 * 1000); // 15 minutes
        }
        
        document.addEventListener('mousemove', resetInactivityTimer);
        document.addEventListener('keypress', resetInactivityTimer);
        resetInactivityTimer();
    </script>
</body>
</html>

<?php
// Fonctions utilitaires
function getStatutBadgeClass($statut) {
    $classes = [
        'reçu' => 'bg-primary',
        'en préparation' => 'bg-warning',
        'en cours essai' => 'bg-success',
        'en vérification' => 'bg-danger',
        'rapport envoyé' => 'bg-info'
    ];
    return $classes[$statut] ?? 'bg-secondary';
}

function getUrgenceClass($urgence) {
    $classes = [
        'normal' => 'secondary',
        'urgent' => 'warning',
        'très urgent' => 'danger'
    ];
    return $classes[$urgence] ?? 'secondary';
}

function estEnRetard($date_limite) {
    if(empty($date_limite)) return false;
    return strtotime($date_limite) < time();
}
?>