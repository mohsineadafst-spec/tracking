<?php
require_once 'includes/config.php';

// Initialiser les variables
$numero_colis = '';
$colis_trouve = null;
$message_erreur = '';
$recherche_effectuee = false;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $recaptchaResponse = $_POST['g-recaptcha-response'];
    $secretKey = "6Lf5qbosAAAAADdW-zBWKcapNq-iHrJE_JWMbYou";

    $verifyResponse = file_get_contents(
        "https://www.google.com/recaptcha/api/siteverify?secret={$secretKey}&response={$recaptchaResponse}"
    );
    $responseData = json_decode($verifyResponse);

    
}
// Traitement de la recherche
if (isset($_GET['search']) || isset($_POST['numero_colis'])) {
    $recherche_effectuee = true;
    
    // Récupérer le numéro de colis
    $numero_colis = trim($_GET['search'] ?? $_POST['numero_colis'] ?? '');
    
    if (!empty($numero_colis)) {
        // Recherche du dossier par numéro de dossier
        $stmt = $pdo->prepare("
            SELECT numero_dossier, lien_partage 
            FROM dossiers 
            WHERE numero_dossier = ?
        ");
        $stmt->execute([$numero_colis]);
        $dossier_trouve = $stmt->fetch();
        
        if ($dossier_trouve && !empty($dossier_trouve['lien_partage'])) {
            // Redirection vers le lien de partage
            header("Location: " . $dossier_trouve['lien_partage']);
            exit();
        } elseif ($dossier_trouve && empty($dossier_trouve['lien_partage'])) {
            $message_erreur = "Aucun lien de partage trouvé pour ce dossier.";
        } else {
            $message_erreur = "Aucun dossier trouvé avec ce numéro. Vérifiez le numéro saisi.";
        }
    } else {
        $message_erreur = "Veuillez saisir un numéro de dossier";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CETIEV Express - Suivi de dossier</title>
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
        
        /* Dropdown langue */
        .lang-dropdown .dropdown-toggle {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
        }
        
        .lang-dropdown .dropdown-toggle:hover {
            border-color: var(--cetiev-yellow);
            color: var(--cetiev-yellow);
        }
        
        .lang-dropdown .dropdown-menu {
            background-color: var(--cetiev-blue);
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            margin-top: 0.5rem;
        }
        
        .lang-dropdown .dropdown-item {
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1rem;
        }
        
        .lang-dropdown .dropdown-item:hover {
            background-color: var(--cetiev-yellow);
            color: var(--cetiev-blue);
        }
        
        .lang-dropdown .dropdown-item i {
            font-size: 1rem;
        }
        
        /* Section de recherche - Couleur blanche */
        .tracking-section {
            background: linear-gradient(135deg, var(--cetiev-white) 0%, #f0f0f0 100%);
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
            border-bottom: 3px solid var(--cetiev-yellow);
            min-height: 70vh;
            display: flex;
            align-items: center;
        }
        
        .tracking-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect fill="rgba(0,0,0,0.02)" width="100" height="100"/><path d="M20 20 L80 20 M20 40 L80 40 M20 60 L80 60 M20 80 L80 80" stroke="rgba(0,0,0,0.05)" stroke-width="1"/></svg>') repeat;
            pointer-events: none;
        }
        
        .tracking-container {
            position: relative;
            z-index: 1;
        }
        
        .tracking-section h1,
        .tracking-section p {
            color: var(--cetiev-blue);
        }
        
        .search-box {
            background: white;
            border-radius: 50px;
            padding: 0.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }
        
        .search-box input {
            border: none;
            padding: 0.8rem 1.5rem;
            font-size: 1.1rem;
            border-radius: 50px;
        }
        
        .search-box input:focus {
            outline: none;
            box-shadow: none;
        }
        
        .search-box button {
            border-radius: 50px;
            padding: 0.8rem 2rem;
            background-color: var(--cetiev-blue);
            border: none;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .search-box button:hover {
            background-color: var(--cetiev-dark-blue);
            transform: translateY(-1px);
        }
        
        /* Alert styles */
        .alert-custom {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            animation: slideInRight 0.5s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Loader */
        .loader-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        
        .loader-overlay.show {
            display: flex;
        }
        
        .loader-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* Footer */
        .cetiev-footer {
            background: var(--cetiev-blue);
            color: white;
            padding: 3rem 0 2rem;
            margin-top: 0;
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
            
            .navbar-cetiev .navbar-collapse {
                text-align: center;
                padding: 1rem 0;
            }
            
            .navbar-cetiev .nav-link {
                justify-content: center;
            }
            
            .lang-dropdown {
                display: inline-block;
            }
            
            .alert-custom {
                left: 20px;
                right: 20px;
                min-width: auto;
            }
        }
        .logo-img-white {
    height: 45px;
    width: auto;
    filter: brightness(0) invert(1); /* Rend l'image blanche */
}

/* Alternative avec saturate et brightness */
.logo-img-white-alt {
    height: 45px;
    width: auto;
    filter: saturate(0) brightness(10);
}
    </style>
</head>
<body>

<!-- Loader overlay -->
<div id="loaderOverlay" class="loader-overlay">
    <div class="loader-content">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>
        <p class="mt-3 mb-0">Recherche en cours...</p>
        <p class="small text-muted mt-2">Redirection vers le lien de partage...</p>
    </div>
</div>

<!-- Navbar CETIEV Express -->
<nav class="navbar navbar-cetiev navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="tracking.php">
           <img src="logo.png" alt="CETIEV Express" class="logo-img" 
     style="filter: brightness(0) invert(1);" 
     onerror="this.src='https://via.placeholder.com/120x45?text=CETIEV+Express'; this.style.filter='none';"> 
           
            <div>
               
               
            </div>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCETIEV" style="background: rgba(255,255,255,0.2); border: none;">
            <i class="bi bi-list" style="color: white; font-size: 1.5rem;"></i>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarCETIEV">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                <li class="nav-item dropdown lang-dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-globe2"></i> Langue
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="bi bi-flag-fill"></i> Français</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-flag-fill"></i> English</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-flag-fill"></i> Español</a></li>
                        <li><a class="dropdown-item" href="#"><i class="bi bi-flag-fill"></i> العربية</a></li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="tracking.php">
                        <i class="bi bi-search"></i> Suivi
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">
                        <i class="bi bi-envelope"></i> Contact
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Section de suivi -->
<section class="tracking-section">
    <div class="container tracking-container">
        <div class="row justify-content-center">
            <div class="col-md-8 text-center mb-4">
                <i class="bi bi-folder2-open display-1 mb-3" style="color: var(--cetiev-blue);"></i>
                <h1 class="display-5 fw-bold mb-3">Suivi de Rapport</h1>
                <p class="lead">Entrez votre numéro de dossier pour accéder à votre espace sécurisé</p>
            </div>
            <div class="col-md-8">
                <form method="POST" action="" id="trackingForm">
                    <div class="search-box">
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   id="numero_dossier" 
                                   name="numero_colis" 
                                   placeholder="Ex: DOS-2024-001"
                                   value="<?php echo htmlspecialchars($numero_colis); ?>"
                                   required>
                            <button class="btn" type="submit" id="submitBtn">
                                <i class="bi bi-search"></i> Accéder
                            </button>
                        </div>
                    </div>
                    <p class="text-muted text-center mt-3 small">
                        <i class="bi bi-info-circle"></i> 
                        Entrez votre numéro de dossier pour accéder à votre espace personnel
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Messages d'erreur -->
<?php if($message_erreur): ?>
<div class="alert-custom">
    <div class="alert alert-warning alert-dismissible fade show mb-0 shadow" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i> 
        <?php echo $message_erreur; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>

<!-- Footer -->
<footer class="cetiev-footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="logo.png" alt="CETIEV Express" style="height: 35px; width: auto;" onerror="this.src='https://via.placeholder.com/100x35?text=CETIEV'">
                    <div>
                        <div class="logo-text" style="font-size: 1.2rem;"><span style="color: var(--cetiev-yellow);"></span></div>
                    </div>
                </div>
                <p>Service de suivi de dossiers en temps réel</p>
            </div>
            <div class="col-md-4 mb-3">
                <h5 class="mb-3">Besoin d'aide ?</h5>
                <p><i class="bi bi-telephone"></i> Service client: 05 22 </p>
                <p><i class="bi bi-envelope"></i> info@cetiev.ma</p>
            </div>
            <div class="col-md-4 mb-3">
                <h5 class="mb-3">Horaires</h5>
                <p>Lun-Ven: 8h - 19h</p>
            </div>
        </div>
        <hr class="bg-secondary">
        <div class="text-center">
            <small>&copy; 2026 CETIEV  - Tous droits réservés</small>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Gestion du loader et soumission du formulaire
    document.getElementById('trackingForm')?.addEventListener('submit', function(e) {
        const numeroDossier = document.getElementById('numero_dossier').value.trim();
        
        if (numeroDossier !== '') {
            // Afficher le loader
            document.getElementById('loaderOverlay').classList.add('show');
            
            // Désactiver le bouton
            const submitBtn = document.getElementById('submitBtn');
            if(submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Recherche...';
            }
        }
    });
    
    // Cacher le loader si la page est chargée avec des erreurs
    window.addEventListener('load', function() {
        const loader = document.getElementById('loaderOverlay');
        if(loader) {
            loader.classList.remove('show');
        }
        
        const submitBtn = document.getElementById('submitBtn');
        if(submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-search"></i> Accéder';
        }
    });
    
    // Auto-hide alert after 5 seconds
    setTimeout(function() {
        const alert = document.querySelector('.alert-custom');
        if (alert) {
            alert.style.display = 'none';
        }
    }, 5000);
</script>

</body>
</html>