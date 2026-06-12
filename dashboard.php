<?php
require_once 'includes/config.php';

// Vérifier la connexion
if (!isset($_SESSION['user_id']) ) {
    header('Location: login.php');
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

// Traitement du changement de statut
$message_success = '';
$message_erreur = '';

if (isset($_POST['changer_statut']) && isset($_POST['id']) && isset($_POST['nouveau_statut'])) {
    csrf_check();
    $id_dossier = intval($_POST['id']);
    $nouveau_statut = $_POST['nouveau_statut'];
    
    // Vérifier si le dossier existe
    $stmt = $pdo->prepare("SELECT statut FROM dossiers WHERE id = ?");
    $stmt->execute([$id_dossier]);
    $dossier_check = $stmt->fetch();
    
    if ($dossier_check) {
        $ancien_statut = $dossier_check['statut'];
        
        // Mettre à jour le statut
        $stmt = $pdo->prepare("UPDATE dossiers SET statut = ?, date_modification = NOW() WHERE id = ?");
        if ($stmt->execute([$nouveau_statut, $id_dossier])) {
            $message_success = "Statut du dossier mis à jour avec succès !";
            
            // Enregistrer dans l'historique (si la table existe)
            try {
                $stmt_hist = $pdo->prepare("
                    INSERT INTO historique_statuts (dossier_id, ancien_statut, nouveau_statut, utilisateur_id, date_changement) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt_hist->execute([$id_dossier, $ancien_statut, $nouveau_statut, $_SESSION['user_id']]);
            } catch (PDOException $e) {
                // Ignorer les erreurs d'historique
            }
        } else {
            $message_erreur = "Erreur lors de la mise à jour du statut.";
        }
    } else {
        $message_erreur = "Dossier non trouvé.";
    }
}

// Initialisation des variables de recherche
$search_numero = '';
$search_client = '';
$search_statut = '';
$search_urgence = '';
$search_date_debut = '';
$search_date_fin = '';
$search_resultats = [];
$recherche_effectuee = false;

// Traitement de la recherche (unifié GET et POST)
if (isset($_GET['rechercher']) || isset($_POST['rechercher']) || isset($_GET['statut'])) {
    $recherche_effectuee = true;
    
    // Récupérer les valeurs (GET ou POST)
    $search_numero = trim($_GET['numero_dossier'] ?? $_POST['numero_dossier'] ?? '');
    $search_client = trim($_GET['client_nom'] ?? $_POST['client_nom'] ?? '');
    $search_statut = $_GET['statut'] ?? $_POST['statut'] ?? '';
    $search_urgence = $_GET['urgence'] ?? $_POST['urgence'] ?? '';
    $search_date_debut = $_GET['date_debut'] ?? $_POST['date_debut'] ?? '';
    $search_date_fin = $_GET['date_fin'] ?? $_POST['date_fin'] ?? '';
    
    // Construction de la requête de recherche
    $sql = "SELECT * FROM dossiers WHERE 1=1";
    $params = [];
    
    if (!empty($search_numero)) {
        $sql .= " AND numero_dossier LIKE ?";
        $params[] = "%$search_numero%";
    }
    
    if (!empty($search_client)) {
        $sql .= " AND client_nom LIKE ?";
        $params[] = "%$search_client%";
    }
    
    if (!empty($search_statut)) {
        $sql .= " AND statut = ?";
        $params[] = $search_statut;
    }
    
    if (!empty($search_urgence)) {
        $sql .= " AND urgence = ?";
        $params[] = $search_urgence;
    }
    
    if (!empty($search_date_debut)) {
        $sql .= " AND date_reception >= ?";
        $params[] = $search_date_debut;
    }
    
    if (!empty($search_date_fin)) {
        $sql .= " AND date_reception <= ?";
        $params[] = $search_date_fin . " 23:59:59";
    }
    
    $sql .= " ORDER BY date_reception DESC";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $search_resultats = $stmt->fetchAll();
    } catch (PDOException $e) {
        $message_erreur = "Erreur lors de la recherche : " . $e->getMessage();
        $search_resultats = [];
    }
}

// Récupérer tous les dossiers pour l'affichage normal
$stmt = $pdo->query("SELECT * FROM dossiers ORDER BY date_reception DESC");
$dossiers = $stmt->fetchAll();

// Compter par statut - AJOUT DU STATUT RAPPORT DISPONIBLE
$stats = [];
$statuts_list = ['Reçu', 'Dossier à completer', 'En préparation', 'Essais en cours', 'Essais terminés', 'En cours de validation et signatures', 'Rapport disponible'];
foreach ($statuts_list as $statut) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE statut = ?");
    $stmt->execute([$statut]);
    $stats[$statut] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CETIEV Express - Tableau de Bord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --cetiev-blue: #0A2540;
            --cetiev-dark-blue: #061a2e;
            --cetiev-yellow: #FFCC00;
            --cetiev-white: #FFFFFF;
            --cetiev-gray: #F5F5F5;
            --cetiev-success: #28a745;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--cetiev-gray);
        }
        
        /* Navbar CETIEV Express */
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
        
        .navbar-cetiev .nav-link i {
            font-size: 1.1rem;
        }
        
        .navbar-cetiev .nav-link:hover {
            color: var(--cetiev-yellow) !important;
            transform: translateY(-2px);
        }
        
        /* Sidebar */
        .sidebar-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .sidebar-card .card-header {
            background: linear-gradient(135deg, var(--cetiev-blue) 0%, var(--cetiev-dark-blue) 100%);
            color: white;
            border: none;
            padding: 1rem;
        }
        
        .sidebar-card .card-header h6,
        .sidebar-card .card-header h5 {
            color: white;
            margin: 0;
        }
        
        .sidebar-card .list-group-item {
            border: none;
            padding: 0.8rem 1rem;
            transition: all 0.3s;
        }
        
        .sidebar-card .list-group-item:hover {
            background-color: var(--cetiev-gray);
            padding-left: 1.5rem;
        }
        
        .sidebar-card .list-group-item a {
            color: var(--cetiev-blue);
            font-weight: 500;
            text-decoration: none;
        }
        
        .sidebar-card .list-group-item i {
            margin-right: 0.5rem;
            color: var(--cetiev-blue);
        }
        
        /* Search Card */
        .search-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .search-card .card-header {
            background: linear-gradient(135deg, var(--cetiev-blue) 0%, var(--cetiev-dark-blue) 100%);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .search-card .card-header:hover {
            background: linear-gradient(135deg, var(--cetiev-dark-blue) 0%, var(--cetiev-blue) 100%);
        }
        
        .search-card .card-header i {
            transition: transform 0.3s;
        }
        
        .search-card .card-header.collapsed i {
            transform: rotate(-90deg);
        }
        
        .search-card .card-body {
            background: white;
            padding: 1.5rem;
        }
        
        .search-form .form-label {
            font-weight: 600;
            color: var(--cetiev-blue);
            margin-bottom: 0.5rem;
        }
        
        .search-form .form-control,
        .search-form .form-select {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .search-form .form-control:focus,
        .search-form .form-select:focus {
            border-color: var(--cetiev-yellow);
            box-shadow: 0 0 0 0.2rem rgba(255, 204, 0, 0.25);
        }
        
        .btn-search {
            background-color: var(--cetiev-blue);
            color: white;
            border-radius: 10px;
            padding: 0.6rem 2rem;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-search:hover {
            background-color: var(--cetiev-dark-blue);
            transform: translateY(-2px);
            color: var(--cetiev-yellow);
        }
        
        .btn-reset {
            background-color: #6c757d;
            color: white;
            border-radius: 10px;
            padding: 0.6rem 2rem;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-reset:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        
        /* Statut Cards */
        .statut-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            cursor: pointer;
        }
        
        .statut-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .statut-card .card-body {
            padding: 1.5rem 1rem;
        }
        
        .statut-card i {
            font-size: 2rem;
        }
        
        .statut-card h5 {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0.5rem 0;
            color: var(--cetiev-blue);
        }
        
        .statut-card small {
            font-size: 0.75rem;
        }
        
        /* Table */
        .table-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table-card .card-header {
            background: linear-gradient(135deg, var(--cetiev-blue) 0%, var(--cetiev-dark-blue) 100%);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .table-card .card-header h5 {
            color: white;
            margin: 0;
        }
        
        .table-card .card-header a {
            color: var(--cetiev-yellow);
            text-decoration: none;
        }
        
        .table-card .card-header a:hover {
            color: white;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: var(--cetiev-gray);
            color: var(--cetiev-blue);
            font-weight: 600;
            border-bottom: 2px solid var(--cetiev-blue);
        }
        
        .table tbody tr:hover {
            background-color: rgba(10, 37, 64, 0.05);
        }
        
        .badge-statut {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
        }
        
        .btn-custom {
            border-radius: 50px;
            padding: 0.5rem 1rem;
            transition: all 0.3s;
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
        }
        
        /* Search results info */
        .search-info {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem;
            border-left: 4px solid var(--cetiev-yellow);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        /* Alert */
        .alert-custom {
            border-radius: 10px;
            margin-bottom: 1rem;
            animation: slideDown 0.5s ease-out;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Modal changement statut */
        .modal-statut .modal-header {
            background: linear-gradient(135deg, var(--cetiev-blue) 0%, var(--cetiev-dark-blue) 100%);
            color: white;
            border: none;
        }
        
        .modal-statut .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .statut-option-modal {
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
        }
        
        .statut-option-modal:hover {
            background: var(--cetiev-gray);
            transform: translateX(5px);
        }
        
        .statut-option-modal.selected {
            border-color: var(--cetiev-yellow);
            background: linear-gradient(135deg, rgba(10,37,64,0.05) 0%, rgba(10,37,64,0.02) 100%);
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar-cetiev .navbar-brand {
                margin-bottom: 0.5rem;
            }
            
            .statut-card h5 {
                font-size: 1.2rem;
            }
            
            .statut-card i {
                font-size: 1.5rem;
            }
            
            .btn-search, .btn-reset {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar CETIEV Express -->
    <nav class="navbar navbar-cetiev navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="logo.png" alt="CETIEV Express" class="logo-img" 
                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 50%22%3E%3Ctext x=%2210%22 y=%2235%22 font-family=%22Arial%22 font-size=%2225%22 font-weight=%22bold%22 fill=%22white%22%3ECETIEV%3C/text%3E%3Ctext x=%22120%22 y=%2235%22 font-family=%22Arial%22 font-size=%2225%22 font-weight=%22bold%22 fill=%22%23FFCC00%22%3EExpress%3C/text%3E%3C/svg%3E'; this.style.filter='none';">
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

        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 mb-4">
                <div class="sidebar-card card">
                    <div class="card-header">
                        <h5 class="mb-0">Menu</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <a href="dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Tableau de bord
                                </a>
                            </li>
                            <li class="list-group-item">
                                <a href="nouveau-dossier.php">
                                    <i class="bi bi-plus-circle"></i> Nouveau dossier
                                </a>
                            </li>
                            <li class="list-group-item">
                                <a href="#" onclick="toggleSearch()">
                                    <i class="bi bi-search"></i> Rechercher
                                </a>
                            </li>
                            <?php if($_SESSION['role'] == 'admin'): ?>
                            <li class="list-group-item">
                                <a href="gestion-utilisateurs.php">
                                    <i class="bi bi-people"></i> Utilisateurs
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Statistiques générales -->
                <div class="sidebar-card card">
                    <div class="card-header">
                        <h6 class="mb-0">Statistiques générales</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach($stats as $statut => $count): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted"><?php echo ucfirst($statut); ?></span>
                            <span class="badge" style="background-color: var(--cetiev-blue); border-radius: 50px; padding: 0.5rem 1rem;">
                                <?php echo $count; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Total</strong>
                            <strong class="text-primary"><?php echo array_sum($stats); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="col-md-9">
                <h2 class="mb-4" style="color: var(--cetiev-blue);">Tableau de Bord</h2>
                
                <!-- Cartes de statut - AJOUT DU STATUT RAPPORT DISPONIBLE -->
                <div class="row mb-4">
                    <?php 
                    $statuts_config = [
                        'Reçu' => ['icon' => 'bi-inbox', 'color' => '#2196f3', 'label' => 'Reçu'],
                        'Dossier à completer' => ['icon' => 'bi-pencil-square', 'color' => '#ff9800', 'label' => 'Dossier à compléter'],
                        'En préparation' => ['icon' => 'bi-gear', 'color' => '#4caf50', 'label' => 'En préparation'],
                        'Essais en cours' => ['icon' => 'bi-arrow-down-left-circle', 'color' => '#9c27b0', 'label' => 'Essais en cours'],
                        'Essais terminés' => ['icon' => 'bi-check-circle', 'color' => '#00bcd4', 'label' => 'Essais terminés'],
                        'En cours de validation et signatures' => ['icon' => 'bi-send-check', 'color' => '#e91e63', 'label' => 'Validation'],
                        'Rapport disponible' => ['icon' => 'bi-file-earmark-pdf-fill', 'color' => '#28a745', 'label' => 'Rapport dispo']
                    ];
                    
                    foreach($statuts_config as $key => $info): 
                        $count = $stats[$key] ?? 0;
                    ?>
                    <div class="col-md-2 col-6 mb-3">
                        <div class="statut-card card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-bottom: 4px solid <?php echo $info['color']; ?>;" onclick="filtrerParStatut('<?php echo addslashes($key); ?>')">
                            <div class="card-body text-center">
                                <i class="bi <?php echo $info['icon']; ?>" style="color: <?php echo $info['color']; ?>"></i>
                                <h5><?php echo $count; ?></h5>
                                <small class="text-muted"><?php echo $info['label']; ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Section Recherche -->
                <div class="search-card card" id="searchCard">
                    <div class="card-header" data-bs-toggle="collapse" data-bs-target="#searchCollapse" role="button">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-search"></i> 
                                <strong>Recherche avancée</strong>
                            </div>
                            <i class="bi bi-chevron-down"></i>
                        </div>
                    </div>
                    <div class="collapse <?php echo $recherche_effectuee ? 'show' : ''; ?>" id="searchCollapse">
                        <div class="card-body">
                            <form method="GET" action="" class="search-form" id="searchForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-upc-scan"></i> Numéro dossier
                                        </label>
                                        <input type="text" class="form-control" name="numero_dossier" id="numero_dossier"
                                               value="<?php echo htmlspecialchars($search_numero); ?>"
                                               placeholder="Ex: DOS-2024-001">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-person"></i> Nom du client
                                        </label>
                                        <input type="text" class="form-control" name="client_nom" id="client_nom"
                                               value="<?php echo htmlspecialchars($search_client); ?>"
                                               placeholder="Nom du client">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-tag"></i> Statut
                                        </label>
                                        <select class="form-select" name="statut" id="statut">
                                            <option value="">Tous les statuts</option>
                                            <option value="Reçu" <?php echo $search_statut == 'Reçu' ? 'selected' : ''; ?>>Reçu</option>
                                            <option value="Dossier à completer" <?php echo $search_statut == 'Dossier à completer' ? 'selected' : ''; ?>>Dossier à completer</option>
                                            <option value="En préparation" <?php echo $search_statut == 'En préparation' ? 'selected' : ''; ?>>En préparation</option>
                                            <option value="Essais en cours" <?php echo $search_statut == 'Essais en cours' ? 'selected' : ''; ?>>Essais en cours</option>
                                            <option value="Essais terminés" <?php echo $search_statut == 'Essais terminés' ? 'selected' : ''; ?>>Essais terminés</option>
                                            <option value="En cours de validation et signatures" <?php echo $search_statut == 'En cours de validation et signatures' ? 'selected' : ''; ?>>En cours de validation</option>
                                            <option value="Rapport disponible" <?php echo $search_statut == 'Rapport disponible' ? 'selected' : ''; ?>>Rapport disponible</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-exclamation-triangle"></i> Urgence
                                        </label>
                                        <select class="form-select" name="urgence" id="urgence">
                                            <option value="">Toutes</option>
                                            <option value="normal" <?php echo $search_urgence == 'normal' ? 'selected' : ''; ?>>Normal</option>
                                            <option value="urgent" <?php echo $search_urgence == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                            <option value="très urgent" <?php echo $search_urgence == 'très urgent' ? 'selected' : ''; ?>>Très urgent</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-calendar"></i> Période
                                        </label>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <input type="date" class="form-control" name="date_debut" id="date_debut"
                                                       value="<?php echo $search_date_debut; ?>"
                                                       placeholder="Date début">
                                            </div>
                                            <div class="col-6">
                                                <input type="date" class="form-control" name="date_fin" id="date_fin"
                                                       value="<?php echo $search_date_fin; ?>"
                                                       placeholder="Date fin">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <button type="submit" name="rechercher" value="1" class="btn btn-search">
                                                <i class="bi bi-search"></i> Rechercher
                                            </button>
                                            <button type="button" class="btn btn-reset" onclick="resetRecherche()">
                                                <i class="bi bi-arrow-repeat"></i> Réinitialiser
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Résultats de recherche ou liste des dossiers -->
                <div class="table-card card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <?php if($recherche_effectuee): ?>
                                <i class="bi bi-search"></i> Résultats de recherche
                            <?php else: ?>
                                <i class="bi bi-files"></i> Dossiers récents
                            <?php endif; ?>
                        </h5>
                        <a href="nouveau-dossier.php" class="btn btn-sm" style="background-color: var(--cetiev-yellow); color: var(--cetiev-blue);">
                            <i class="bi bi-plus"></i> Nouveau dossier
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if($recherche_effectuee): ?>
                            <div class="search-info">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <i class="bi bi-info-circle-fill text-primary"></i>
                                        <strong><?php echo count($search_resultats); ?> dossier(s) trouvé(s)</strong>
                                    </div>
                                    <div>
                                        <a href="dashboard.php" class="text-decoration-none me-3">
                                            <i class="bi bi-x-circle"></i> Voir tous les dossiers
                                        </a>
                                        <a href="#" onclick="toggleSearch()" class="text-decoration-none">
                                            <i class="bi bi-search"></i> Nouvelle recherche
                                        </a>
                                    </div>
                                </div>
                                <?php if(!empty($search_numero) || !empty($search_client) || !empty($search_statut) || !empty($search_urgence) || !empty($search_date_debut) || !empty($search_date_fin)): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Critères utilisés :</small>
                                    <div class="mt-1">
                                        <?php if(!empty($search_numero)): ?>
                                            <span class="badge bg-secondary me-1">N°: <?php echo htmlspecialchars($search_numero); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($search_client)): ?>
                                            <span class="badge bg-secondary me-1">Client: <?php echo htmlspecialchars($search_client); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($search_statut)): ?>
                                            <span class="badge bg-secondary me-1">Statut: <?php echo htmlspecialchars($search_statut); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($search_urgence)): ?>
                                            <span class="badge bg-secondary me-1">Urgence: <?php echo htmlspecialchars($search_urgence); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($search_date_debut) || !empty($search_date_fin)): ?>
                                            <span class="badge bg-secondary me-1">
                                                Période: <?php echo !empty($search_date_debut) ? $search_date_debut : 'début'; ?> 
                                                → <?php echo !empty($search_date_fin) ? $search_date_fin : 'aujourd\'hui'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive">
                            <?php 
                            $affichage_dossiers = $recherche_effectuee ? $search_resultats : $dossiers;
                            ?>
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>N° Dossier</th>
                                        <th>Client</th>
                                        <th>Date réception</th>
                                        <th>Statut</th>
                                        <th>Urgence</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($affichage_dossiers) > 0): ?>
                                        <?php foreach($affichage_dossiers as $dossier): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($dossier['numero_dossier']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($dossier['client_nom']); ?>
                                            <td><?php echo date('d/m/Y', strtotime($dossier['date_reception'])); ?>
                                            <td>
                                                <?php
                                                $badge_class = [
                                                    'Reçu' => 'primary',
                                                    'Dossier à completer' => 'info',
                                                    'En préparation' => 'warning',
                                                    'Essais en cours' => 'success',
                                                    'Essais terminés' => 'info',
                                                    'En cours de validation et signatures' => 'danger',
                                                    'Rapport disponible' => 'success'
                                                ];
                                                $badge = $badge_class[$dossier['statut']] ?? 'secondary';
                                                ?>
                                                <span class="badge badge-statut bg-<?php echo $badge; ?>">
                                                    <?php echo htmlspecialchars($dossier['statut']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-statut bg-<?php 
                                                    echo $dossier['urgence'] == 'urgent' ? 'warning' : 
                                                           ($dossier['urgence'] == 'très urgent' ? 'danger' : 'secondary'); 
                                                ?>">
                                                    <?php echo strtoupper(htmlspecialchars($dossier['urgence'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo $dossier['lien_partage']; ?>" 
                                                   class="btn btn-sm btn-outline-primary btn-custom">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-success btn-custom" 
                                                        onclick="ouvrirModalChangement(<?php echo $dossier['id']; ?>, '<?php echo htmlspecialchars($dossier['numero_dossier']); ?>', '<?php echo htmlspecialchars($dossier['statut']); ?>')">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5">
                                                <i class="bi bi-folder2-open display-1 text-muted"></i>
                                                <p class="mt-3 mb-0">Aucun dossier trouvé</p>
                                                <?php if(!$recherche_effectuee): ?>
                                                    <a href="nouveau-dossier.php" class="btn btn-primary mt-3">Créer un dossier</a>
                                                <?php else: ?>
                                                    <button class="btn btn-primary mt-3" onclick="resetRecherche()">
                                                        <i class="bi bi-arrow-repeat"></i> Effacer les filtres
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de changement de statut - AJOUT DU STATUT RAPPORT DISPONIBLE -->
    <div class="modal fade modal-statut" id="modalChangementStatut" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-repeat"></i> Changer le statut
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="dossier_id">
                        <p class="mb-3">
                            <strong>Dossier :</strong> <span id="dossier_numero"></span>
                            <br>
                            <strong>Statut actuel :</strong> <span id="statut_actuel" class="badge bg-secondary"></span>
                        </p>
                        <label class="form-label fw-bold">Nouveau statut :</label>
                        <div id="liste_statuts">
                            <?php foreach($statuts_list as $statut): ?>
                            <div class="statut-option-modal" onclick="selectionnerStatut('<?php echo addslashes($statut); ?>')">
                                <input type="radio" name="nouveau_statut" value="<?php echo htmlspecialchars($statut); ?>" id="statut_<?php echo md5($statut); ?>" style="display: none;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="statut-icon-sm">
                                        <?php
                                        $icon = match($statut) {
                                            'Reçu' => 'bi-inbox',
                                            'Dossier à completer' => 'bi-pencil-square',
                                            'En préparation' => 'bi-gear',
                                            'Essais en cours' => 'bi-flask',
                                            'Essais terminés' => 'bi-check-circle',
                                            'En cours de validation et signatures' => 'bi-send-check',
                                            'Rapport disponible' => 'bi-file-earmark-pdf-fill',
                                            default => 'bi-question-circle'
                                        };
                                        ?>
                                        <i class="bi <?php echo $icon; ?> fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($statut); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Annuler
                        </button>
                        <button type="submit" name="changer_statut" class="btn" style="background-color: var(--cetiev-blue); color: white;">
                            <i class="bi bi-check-circle"></i> Confirmer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="cetiev-footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <div class="d-flex align-items-center gap-2 justify-content-center justify-content-md-start">
                        <img src="logo.png" alt="CETIEV Express" style="height: 30px; filter: brightness(0) invert(1);" 
                             onerror="this.style.display='none'">
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
        let modal;
        
        // Ouvrir le modal de changement de statut
        function ouvrirModalChangement(id, numero, statutActuel) {
            if (!modal) {
                modal = new bootstrap.Modal(document.getElementById('modalChangementStatut'));
            }
            
            document.getElementById('dossier_id').value = id;
            document.getElementById('dossier_numero').innerText = numero;
            document.getElementById('statut_actuel').innerText = statutActuel;
            
            // Réinitialiser la sélection
            document.querySelectorAll('.statut-option-modal').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.querySelectorAll('input[name="nouveau_statut"]').forEach(radio => {
                radio.checked = false;
            });
            
            modal.show();
        }
        
        // Sélectionner un statut dans le modal
        function selectionnerStatut(statut) {
            document.querySelectorAll('.statut-option-modal').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            const selectedOption = document.querySelector(`.statut-option-modal:has(input[value="${statut.replace(/"/g, '\\"')}"])`);
            if (selectedOption) {
                selectedOption.classList.add('selected');
            }
            
            const radio = document.querySelector(`input[name="nouveau_statut"][value="${statut.replace(/"/g, '\\"')}"]`);
            if (radio) {
                radio.checked = true;
            }
        }
        
        // Filtrer par statut (clic sur les cartes)
        function filtrerParStatut(statut) {
            document.getElementById('statut').value = statut;
            document.getElementById('searchForm').submit();
        }
        
        // Réinitialiser la recherche
        function resetRecherche() {
            window.location.href = 'dashboard.php';
        }
        
        // Afficher/masquer la recherche
        function toggleSearch() {
            var searchCollapse = document.getElementById('searchCollapse');
            var bsCollapse = new bootstrap.Collapse(searchCollapse, {
                toggle: true
            });
            
            setTimeout(function() {
                document.getElementById('searchCard').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 100);
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if(alert) alert.style.display = 'none';
                    }, 300);
                }, 3000);
            });
        }, 100);
        
        // Garder la recherche ouverte si des résultats sont affichés
        <?php if($recherche_effectuee): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var searchCollapse = document.getElementById('searchCollapse');
            if(searchCollapse && !searchCollapse.classList.contains('show')) {
                searchCollapse.classList.add('show');
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>