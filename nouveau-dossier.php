<?php
// nouveau-dossier.php - Page d'ajout d'un nouvel échantillon
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

// Récupérer la liste des clients pour le select
$clients = [];
try {
    $stmt = $pdo->query("SELECT id, nom, email, telephone FROM clients ORDER BY nom ASC");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur récupération clients: " . $e->getMessage());
}

// Variables pour stocker les erreurs et les valeurs du formulaire
$errors = [];
$success = false;

// Si le formulaire est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Récupérer et valider les données

    $numero_dossier = secure_data($_POST['numero_dossier'] ?? '');
    $client_id = secure_data($_POST['client_id'] ?? '');
    $client_nom = secure_data($_POST['client_nom'] ?? '');
    $client_email = secure_data($_POST['client_email'] ?? '');
    $client_telephone = secure_data($_POST['client_telephone'] ?? '');
    $date_reception = secure_data($_POST['date_reception'] ?? '');
    $date_prevue_rapport = secure_data($_POST['date_prevue_rapport'] ?? '');
    $numero_rapport = secure_data($_POST['numero_rapport'] ?? '');
    $laboratoire = secure_data($_POST['laboratoire'] ?? '');
    $notes = secure_data($_POST['notes'] ?? '');
    
    // Si un client existant est sélectionné, on utilise ses infos
    if (!empty($client_id)) {
        $stmt = $pdo->prepare("SELECT nom, email, telephone FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $clientInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($clientInfo) {
            $client_nom = $clientInfo['nom'];
            $client_email = $clientInfo['email'];
            $client_telephone = $clientInfo['telephone'];
        }
    }
    
    // Validation
    if (empty($numero_dossier)) {
        $errors[] = "Le numéro de dossier est obligatoire";
    } elseif (!preg_match('/^\d{10}$/', $numero_dossier)) {
        $errors[] = "Le numéro de dossier doit contenir exactement 10 chiffres";
    } else {
        // Vérifier si le numéro de dossier existe déjà dans la base
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dossiers WHERE numero_dossier = ?");
        $stmt->execute([$numero_dossier]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $errors[] = "Ce numéro de dossier existe déjà. Veuillez en choisir un autre.";
        }
    }
    
    if (empty($client_nom)) {
        $errors[] = "Le nom du client est obligatoire";
    }
    
    if (empty($date_reception)) {
        $errors[] = "La date de réception de l'échantillon est obligatoire";
    }
    
    if (empty($date_prevue_rapport)) {
        $errors[] = "La date prévue pour le rapport est obligatoire";
    }
    
    // Si pas d'erreurs, enregistrer dans la base de données
    if (empty($errors)) {
        try {
            // Statut initial
            $statut_initial = 'Reçu';
            $urgence_default = 'normal';
            $type_essai_default = 'Non spécifié';
            
            // Générer un token unique pour le lien de suivi
            $token_unique = bin2hex(random_bytes(32));
            $host="192.168.200.203/";
$lien_partage = "http://" . $host .
    dirname($_SERVER['PHP_SELF']) .
    "/suivi-public.php?token=" . $token_unique;            $date_expiration = date('Y-m-d', strtotime('+30 days'));
            
            // Préparer la requête d'insertion
            $stmt = $pdo->prepare("
                INSERT INTO dossiers 
                (numero_dossier, client_id, client_nom, client_email, client_telephone,
                 date_reception, date_prevue_rapport, notes, statut, urgence, type_essai,
                 token_unique, lien_partage, date_expiration_lien, lien_active, laboratoire, numero_rapport)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
            ");
            
            // Exécuter la requête
            $stmt->execute([
                $numero_dossier, 
                !empty($client_id) ? $client_id : null,
                $client_nom, 
                $client_email, 
                $client_telephone,
                $date_reception, 
                $date_prevue_rapport, 
                $notes, 
                $statut_initial, 
                $urgence_default, 
                $type_essai_default,
                $token_unique, 
                $lien_partage, 
                $date_expiration,
                $laboratoire,
                $numero_rapport
            ]);
            
            // Récupérer l'ID du dossier créé
            $dossier_id = $pdo->lastInsertId();
            
            // Enregistrer le premier statut dans l'historique
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO historique_statuts 
                    (dossier_id, ancien_statut, nouveau_statut, utilisateur_id, date_changement) 
                    VALUES (?, 'nouveau', ?, ?, NOW())
                ");
                $stmt->execute([$dossier_id, $statut_initial, $_SESSION['user_id']]);
            } catch (PDOException $e) {
                // Ignorer l'erreur si la table n'existe pas
            }
            
            // Afficher un message de succès
            $success = true;
            
        } catch (PDOException $e) {
            // Vérifier si c'est une erreur de duplication de numéro de dossier
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'unique_numero_dossier') !== false) {
                $errors[] = "Ce numéro de dossier existe déjà. Veuillez en choisir un autre.";
            } else {
                $errors[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
            }
        }
    }
}

// Fonction pour générer un numéro de dossier unique
function genererNumeroDossierUnique($pdo) {
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

$default_numero = genererNumeroDossierUnique($pdo);
$date_aujourdhui = date('Y-m-d');
$date_rapport_defaut = date('Y-m-d', strtotime('+15 days'));
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CETIEV Express - Nouveau Dossier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        /* Styles existants - gardez les mêmes que dans votre code original */
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
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--cetiev-yellow);
        }
        
        .form-section h5 {
            color: var(--cetiev-blue);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--cetiev-gray);
            font-weight: 600;
        }
        
        .form-section h5 i {
            color: var(--cetiev-yellow);
            margin-right: 0.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--cetiev-blue);
            margin-bottom: 0.5rem;
        }
        
        .required::after {
            content: " *";
            color: #dc3545;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: all 0.3s;
            padding: 0.6rem 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--cetiev-yellow);
            box-shadow: 0 0 0 0.2rem rgba(255, 204, 0, 0.25);
        }
        
        .btn-primary {
            background-color: var(--cetiev-blue);
            border: none;
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--cetiev-dark-blue);
            transform: translateY(-2px);
            color: var(--cetiev-yellow);
        }
        
        .btn-outline-primary {
            border-color: var(--cetiev-blue);
            color: var(--cetiev-blue);
            border-radius: 50px;
            padding: 0.5rem 1.2rem;
            transition: all 0.3s;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--cetiev-blue);
            border-color: var(--cetiev-blue);
            transform: translateY(-2px);
            color: white;
        }
        
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
        
        /* Ajout pour le toggle client */
        .client-toggle {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .client-toggle:hover {
            opacity: 0.8;
        }
        
        .preview-link {
            background: var(--cetiev-gray);
            border-radius: 10px;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.85rem;
            word-break: break-all;
        }
        
        .alert-custom {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        @media (max-width: 768px) {
            .form-section {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-cetiev navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <img src="logo.png" alt="CETIEV " class="logo-img" 
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
                                <a href="gestion-clients.php">
                                    <i class="bi bi-people"></i> Gestion des clients
                                </a>
                            </li>
                            <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                            <li class="list-group-item">
                                <a href="gestion-utilisateurs.php">
                                    <i class="bi bi-people"></i> Utilisateurs
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Contenu principal -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 style="color: var(--cetiev-blue);"><i class="bi bi-plus-circle" style="color: var(--cetiev-yellow);"></i> Nouveau Dossier</h2>
                        <p class="text-muted">Ajoutez un nouvel échantillon pour le suivi</p>
                    </div>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Retour
                        </a>
                    </div>
                </div>

                <!-- Messages d'alerte -->
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show alert-custom" role="alert">
                    <i class="bi bi-check-circle-fill"></i> 
                    <strong>Succès !</strong> Le dossier a été créé avec succès.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    
                    <div class="mt-3">
                        <h6><i class="bi bi-link-45deg"></i> Lien de suivi généré :</h6>
                        <div class="preview-link mt-2">
                            <?php echo $lien_partage; ?>
                        </div>
                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="copierLien('<?php echo $token_unique; ?>')">
                            <i class="bi bi-clipboard"></i> Copier le lien
                        </button>
                        <a href="suivi-public.php?token=<?php echo $token_unique; ?>" 
                           target="_blank" class="btn btn-sm btn-outline-info mt-2">
                            <i class="bi bi-eye"></i> Prévisualiser
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show alert-custom" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Erreurs :</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Formulaire -->
<form method="POST" action="" id="form-nouveau-dossier" class="<?php echo $success ? 'd-none' : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="row">
                        <!-- Colonne de gauche -->
                        <div class="col-lg-6">
                            <!-- Section : Informations du dossier -->
                            <div class="form-section">
                                <h5><i class="bi bi-file-earmark-text"></i> Informations du dossier</h5>
                                
                                <div class="mb-3">
                                    <label for="numero_dossier" class="form-label required">Numéro de dossier (10 chiffres)</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               id="numero_dossier" 
                                               name="numero_dossier" 
                                               value="<?php echo $_POST['numero_dossier'] ?? $default_numero; ?>" 
                                               required
                                               pattern="\d{10}"
                                               maxlength="10"
                                               title="10 chiffres uniquement">
                                        <button type="button" 
                                                class="btn btn-outline-secondary" 
                                                id="generate-numero"
                                                title="Générer un nouveau numéro">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        Numéro unique à 10 chiffres (ex: <?php echo $default_numero; ?>)
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="date_reception" class="form-label required">Date de réception de l'échantillon</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="date_reception" 
                                           name="date_reception" 
                                           value="<?php echo $_POST['date_reception'] ?? $date_aujourdhui; ?>" 
                                           required>
                                </div>

                                <div class="mb-3">
                                    <label for="date_prevue_rapport" class="form-label required">Date prévue pour le rapport</label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="date_prevue_rapport" 
                                           name="date_prevue_rapport" 
                                           value="<?php echo $_POST['date_prevue_rapport'] ?? $date_rapport_defaut; ?>" 
                                           required>
                                    <div class="form-text">
                                        Date à laquelle le rapport sera disponible
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="laboratoire" class="form-label required">Laboratoire</label>
                                    <select name="laboratoire" id="laboratoire" class="form-select" required>
                                        <option value="">-- Sélectionner un laboratoire --</option>
                                        <option value="Batterie" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Batterie') ? 'selected' : ''; ?>>Batterie</option>
                                        <option value="Freinage" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Freinage') ? 'selected' : ''; ?>>Freinage</option>
                                        <option value="intermediaire" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'intermediaire') ? 'selected' : ''; ?>>Freinage intermédiaire</option>
                                        <option value="Pneumatique" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Pneumatique') ? 'selected' : ''; ?>>Pneumatique</option>
                                        <option value="Filtre" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Filtre') ? 'selected' : ''; ?>>Filtre</option>
                                        <option value="Vitrage" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Vitrage') ? 'selected' : ''; ?>>Vitrage</option>
                                        <option value="Casque" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Casque') ? 'selected' : ''; ?>>Casque</option>
                                        <option value="Cable" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Cable') ? 'selected' : ''; ?>>Câble</option>
                                        <option value="PCH" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'PCH') ? 'selected' : ''; ?>>PCH</option>
                                        <option value="developpement" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'developpement') ? 'selected' : ''; ?>>Essais de développement</option>
                                        <option value="Embrayage" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Embrayage') ? 'selected' : ''; ?>>Embrayage</option>
                                        <option value="Joint" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Joint') ? 'selected' : ''; ?>>Joint</option>
                                        <option value="Courroie" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Courroie') ? 'selected' : ''; ?>>Courroie</option>
                                        <option value="Ressort" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Ressort') ? 'selected' : ''; ?>>Ressort</option>
                                        <option value="Flexible" <?php echo (isset($_POST['laboratoire']) && $_POST['laboratoire'] == 'Flexible') ? 'selected' : ''; ?>>Flexible</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="numero_rapport" class="form-label">Code de rapport</label>
                                    <input type="text" class="form-control" id="numero_rapport" name="numero_rapport" value="<?php echo $_POST['numero_rapport'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Colonne de droite -->
                        <div class="col-lg-6">
                            <!-- Section : Informations client avec SELECTION -->
                            <div class="form-section">
                                <h5><i class="bi bi-person-circle"></i> Informations client</h5>
                                
                                <!-- Toggle Client Existant / Nouveau Client -->
                                <div class="mb-3">
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="client_type" id="client_existant" value="existant" checked>
                                        <label class="btn btn-outline-primary" for="client_existant" onclick="toggleClientMode('existant')">
                                            <i class="bi bi-people-fill"></i> Client existant
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="client_type" id="client_nouveau" value="nouveau">
                                        <label class="btn btn-outline-primary" for="client_nouveau" onclick="toggleClientMode('nouveau')">
                                            <i class="bi bi-person-plus-fill"></i> Nouveau client
                                        </label>
                                    </div>
                                </div>

                                <!-- Sélection client existant -->
                                <div id="client_existant_section">
                                    <div class="mb-3">
                                        <label for="client_id" class="form-label required">Sélectionner un client</label>
                                        <select name="client_id" id="client_id" class="form-select">
                                            <option value="">-- Choisir un client --</option>
                                            <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>" 
                                                    data-email="<?php echo htmlspecialchars($client['email']); ?>"
                                                    data-telephone="<?php echo htmlspecialchars($client['telephone']); ?>"
                                                    <?php echo (isset($_POST['client_id']) && $_POST['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['nom']); ?>
                                                <?php if ($client['email']): ?> - <?php echo htmlspecialchars($client['email']); ?><?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <!-- Nouveau client (formulaire) -->
                                <div id="client_nouveau_section" style="display: none;">
                                    <div class="mb-3">
                                        <label for="client_nom" class="form-label required">Nom du client / Entreprise</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="client_nom" 
                                               name="client_nom" 
                                               value="<?php echo $_POST['client_nom'] ?? ''; ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="client_email" class="form-label">Email</label>
                                            <input type="email" 
                                                   class="form-control" 
                                                   id="client_email" 
                                                   name="client_email" 
                                                   value="<?php echo $_POST['client_email'] ?? ''; ?>">
                                            <div class="form-text">
                                                Pour l'envoi automatique du lien de suivi
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="client_telephone" class="form-label">Téléphone</label>
                                            <input type="tel" 
                                                   class="form-control" 
                                                   id="client_telephone" 
                                                   name="client_telephone" 
                                                   value="<?php echo $_POST['client_telephone'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info mt-2">
                                        <i class="bi bi-info-circle"></i>
                                        <small>Ce nouveau client sera automatiquement ajouté à votre liste pour les prochains dossiers.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Section : Description et notes -->
                            <div class="form-section">
                                <h5><i class="bi bi-card-text"></i> Description et notes</h5>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes supplémentaires</label>
                                    <textarea class="form-control" 
                                              id="notes" 
                                              name="notes" 
                                              rows="6"
                                              placeholder="Décrivez l'échantillon, les conditions particulières, les instructions spéciales..."><?php echo $_POST['notes'] ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons de soumission -->
                    <div class="form-section">
                        <div class="d-flex justify-content-between">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise"></i> Réinitialiser
                            </button>
                            
                            <div>
                                <a href="dashboard.php" class="btn btn-outline-secondary">Annuler</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Enregistrer
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Après succès : bouton pour ajouter un autre dossier -->
                <?php if ($success): ?>
                <div class="text-center mt-4">
                    <a href="nouveau-dossier.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Ajouter un autre dossier
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="bi bi-speedometer2"></i> Tableau de bord
                    </a>
                    <a href="dossier-detail.php?id=<?php echo $dossier_id; ?>" class="btn btn-outline-info">
                        <i class="bi bi-eye"></i> Voir le détail
                    </a>
                </div>
                <?php endif; ?>
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
                        <div class="fw-bold">CETIEV<span style="color: var(--cetiev-yellow);">Express</span></div>
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
        // Générer un nouveau numéro de dossier
        document.getElementById('generate-numero').addEventListener('click', function() {
            let numero = '';
            for (let i = 0; i < 10; i++) {
                numero += Math.floor(Math.random() * 10);
            }
            document.getElementById('numero_dossier').value = numero;
            checkDossierExists(numero);
        });

        // Toggle entre client existant et nouveau client
        function toggleClientMode(mode) {
            const existantSection = document.getElementById('client_existant_section');
            const nouveauSection = document.getElementById('client_nouveau_section');
            const clientNomInput = document.getElementById('client_nom');
            const clientEmailInput = document.getElementById('client_email');
            const clientTelInput = document.getElementById('client_telephone');
            const clientIdSelect = document.getElementById('client_id');
            
            if (mode === 'existant') {
                existantSection.style.display = 'block';
                nouveauSection.style.display = 'none';
                
                // Désactiver les champs nouveau client
                clientNomInput.disabled = true;
                clientEmailInput.disabled = true;
                clientTelInput.disabled = true;
                
                // Activer le select
                clientIdSelect.disabled = false;
                
                // Si un client est sélectionné, remplir les champs cachés
                if (clientIdSelect.value) {
                    const selectedOption = clientIdSelect.options[clientIdSelect.selectedIndex];
                    clientNomInput.value = selectedOption.text.split(' - ')[0];
                    clientEmailInput.value = selectedOption.dataset.email || '';
                    clientTelInput.value = selectedOption.dataset.telephone || '';
                }
            } else {
                existantSection.style.display = 'none';
                nouveauSection.style.display = 'block';
                
                // Activer les champs nouveau client
                clientNomInput.disabled = false;
                clientEmailInput.disabled = false;
                clientTelInput.disabled = false;
                
                // Désactiver le select
                clientIdSelect.disabled = true;
                clientIdSelect.value = '';
            }
        }
        
        // Remplir automatiquement les champs quand on sélectionne un client existant
        document.getElementById('client_id').addEventListener('change', function() {
            if (this.value) {
                const selectedOption = this.options[this.selectedIndex];
                const nom = selectedOption.text.split(' - ')[0];
                const email = selectedOption.dataset.email || '';
                const telephone = selectedOption.dataset.telephone || '';
                
                document.getElementById('client_nom').value = nom;
                document.getElementById('client_email').value = email;
                document.getElementById('client_telephone').value = telephone;
            } else {
                document.getElementById('client_nom').value = '';
                document.getElementById('client_email').value = '';
                document.getElementById('client_telephone').value = '';
            }
        });
        
        // Validation du format du numéro de dossier
        document.getElementById('numero_dossier').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });

        // (supprimé) checkDossierExists redéfini plus bas pour éviter l'accumulation de messages.
        // function checkDossierExists(numero) { ... }

        // Fonction pour copier le lien
        function copierLien(token) {
            const lien = window.location.origin + 
                        '<?php echo dirname($_SERVER['PHP_SELF']); ?>' + 
                        '/suivi-public.php?token=' + token;
            
            navigator.clipboard.writeText(lien)
                .then(() => {
                    showToast('Lien copié dans le presse-papier !', 'success');
                })
                .catch(() => {
                    const tempInput = document.createElement('input');
                    tempInput.value = lien;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    showToast('Lien copié dans le presse-papier !', 'success');
                });
        }
        
        // Afficher une notification
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} alert-dismissible fade show`;
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '9999';
            toast.style.minWidth = '300px';
            toast.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'}-fill"></i> 
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Validation du formulaire
        document.getElementById('form-nouveau-dossier').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let hasError = false;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    hasError = true;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Vérifier le format du numéro de dossier
            const numeroDossier = document.getElementById('numero_dossier').value;
            if (!/^\d{10}$/.test(numeroDossier)) {
                document.getElementById('numero_dossier').classList.add('is-invalid');
                hasError = true;
            }
            
            // Vérifier les dates
            const dateReception = document.getElementById('date_reception').value;
            const dateRapport = document.getElementById('date_prevue_rapport').value;
            
            if (dateReception && dateRapport && new Date(dateRapport) < new Date(dateReception)) {
                showToast('La date prévue pour le rapport doit être postérieure à la date de réception', 'danger');
                document.getElementById('date_prevue_rapport').classList.add('is-invalid');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
                showToast('Veuillez corriger les erreurs dans le formulaire', 'danger');
            }
        });
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Vérifier quel mode est actif
            const clientExistantRadio = document.getElementById('client_existant');
            if (clientExistantRadio && clientExistantRadio.checked) {
                toggleClientMode('existant');
            } else {
                toggleClientMode('nouveau');
            }
            
            // Si un client est présélectionné
            if (document.getElementById('client_id').value) {
                document.getElementById('client_id').dispatchEvent(new Event('change'));
            }
        });
    </script>

    <script>
// Variable pour stocker l'état de validation du numéro de dossier
let numeroDossierValide = false;
let verificationEnCours = false;

// Fonction pour vérifier si le numéro de dossier existe déjà
function checkDossierExists(numero) {
    const input = document.getElementById('numero_dossier');

    // Supprimer tous les anciens feedbacks (valid/invalid)
    input.parentNode.querySelectorAll('.invalid-feedback, .valid-feedback').forEach(el => el.remove());

    // Vérifier le format avant d'envoyer la requête
    if (!/^\d{10}$/.test(numero)) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');

        let feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = 'Le numéro doit contenir exactement 10 chiffres';
        input.parentNode.appendChild(feedback);

        numeroDossierValide = false;
        return;
    }

    verificationEnCours = true;

    fetch(`api/check-dossier.php?numero=${encodeURIComponent(numero)}`)
        .then(response => response.json())
        .then(data => {
            // Supprimer encore une fois les anciens feedbacks
            input.parentNode.querySelectorAll('.invalid-feedback, .valid-feedback').forEach(el => el.remove());

            if (data.exists) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');

                let feedback = document.createElement('div');
                feedback.className = 'invalid-feedback';
                feedback.textContent = '❌ Ce numéro de dossier existe déjà. Veuillez en générer un autre.';
                input.parentNode.appendChild(feedback);

                numeroDossierValide = false;
            } else {
                input.classList.remove('is-invalid');
                input.classList.add('is-valid');

                let successFeedback = document.createElement('div');
                successFeedback.className = 'valid-feedback';
                successFeedback.textContent = '✓ Numéro disponible';
                input.parentNode.appendChild(successFeedback);

                numeroDossierValide = true;
            }

            verificationEnCours = false;
        })
        .catch(err => {
            console.log('Erreur vérification:', err);
            verificationEnCours = false;
            numeroDossierValide = false;
        });
}

// Générer un nouveau numéro de dossier unique
/*function genererNumeroUnique() {
    const input = document.getElementById('numero_dossier');
    const generateBtn = document.getElementById('generate-numero');
    
    // Désactiver le bouton pendant la génération
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="bi bi-arrow-repeat spinner"></i> Génération...';
    
    // Appeler l'API pour générer un numéro unique
    fetch('api/generate-unique-numero.php')
        .then(response => response.json())
        .then(data => {
            if (data.numero) {
                input.value = data.numero;
                checkDossierExists(data.numero);
            } else {
                // Fallback : générer localement avec vérification
                genererNumeroLocal();
            }
        })
        .catch(err => {
            console.log('Erreur:', err);
            genererNumeroLocal();
        })
        .finally(() => {
            generateBtn.disabled = false;
            generateBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
        });
}*/

// Génération locale avec vérification
/*function genererNumeroLocal() {
    let numero = '';
    for (let i = 0; i < 10; i++) {
        numero += Math.floor(Math.random() * 10);
    }
    document.getElementById('numero_dossier').value = numero;
    checkDossierExists(numero);
}*/

// Événement au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    const numeroInput = document.getElementById('numero_dossier');
    const generateBtn = document.getElementById('generate-numero');
    
    // Vérifier le numéro par défaut au chargement
   /* if (numeroInput.value) {
        checkDossierExists(numeroInput.value);
    }*/
    
    // Événement de saisie
    numeroInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        checkDossierExists(this.value);
    });
    
    // Événement blur (perte de focus)
    numeroInput.addEventListener('blur', function() {
        if (this.value.length === 10 && /^\d{10}$/.test(this.value)) {
            checkDossierExists(this.value);
        }
    });
    
    // Bouton de génération
    if (generateBtn) {
        generateBtn.addEventListener('click', genererNumeroUnique);
    }
});

// Validation du formulaire avant soumission
document.getElementById('form-nouveau-dossier').addEventListener('submit', function(e) {
    // Vérifier si la vérification est en cours
    if (verificationEnCours) {
        e.preventDefault();
        showToast('Vérification du numéro en cours, veuillez patienter...', 'warning');
        return false;
    }
    
    // Vérifier si le numéro de dossier est valide
    if (!numeroDossierValide) {
        e.preventDefault();
        showToast('Le numéro de dossier n\'est pas valide ou existe déjà', 'danger');
        return false;
    }
    
    const requiredFields = this.querySelectorAll('[required]');
    let hasError = false;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            hasError = true;
        }
    });
    
    if (hasError) {
        e.preventDefault();
        showToast('Veuillez remplir tous les champs obligatoires', 'danger');
    }
});
</script>

<style>
/* Animation pour le spinner */
.spinner {
    animation: spin 1s linear infinite;
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
</body>
</html>