<?php
// index.php - Page d'accueil du système de tracking
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Système de Suivi des Échantillons - Laboratoire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        .card-hover {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card-hover:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .step-timeline {
            position: relative;
            padding-left: 30px;
        }
        .step-timeline:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #667eea;
        }
        .step-number {
            position: absolute;
            left: -15px;
            top: 0;
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .login-form-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            background: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="#">
                <i class="bi bi-clipboard-data me-2"></i>LabTrack Pro
            </a>
            <div class="navbar-nav ms-auto">
                <a href="#features" class="nav-link">Fonctionnalités</a>
                <a href="#workflow" class="nav-link">Processus</a>
                <a href="#login" class="nav-link">Connexion</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">
                        Suivi Intelligent de vos Échantillons
                    </h1>
                    <p class="lead mb-4">
                        Gérez, tracez et suivez l'ensemble de vos échantillons de laboratoire 
                        en temps réel. Du dépôt à la livraison du rapport.
                    </p>
                    <div class="d-flex gap-3">
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <a href="dashboard.php" class="btn btn-light btn-lg">
                                <i class="bi bi-speedometer2 me-2"></i>Tableau de bord
                            </a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-light btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                            </a>
                            <a href="#features" class="btn btn-outline-light btn-lg">
                                <i class="bi bi-info-circle me-2"></i>Découvrir
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <img src="https://cdn.pixabay.com/photo/2017/08/06/22/01/books-2596809_960_720.png" 
                         alt="Suivi d'échantillons" class="img-fluid" style="max-height: 400px;">
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Fonctionnalités Principales</h2>
                <p class="text-muted">Tout ce dont vous avez besoin pour gérer vos échantillons</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 card-hover">
                        <div class="card-body text-center">
                            <i class="bi bi-qr-code feature-icon"></i>
                            <h5 class="card-title">Tracking en Temps Réel</h5>
                            <p class="card-text">
                                Suivez chaque échantillon à chaque étape : réception, préparation, 
                                analyse, vérification et livraison.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 card-hover">
                        <div class="card-body text-center">
                            <i class="bi bi-link-45deg feature-icon"></i>
                            <h5 class="card-title">Liens de Suivi Uniques</h5>
                            <p class="card-text">
                                Partagez un lien sécurisé avec vos clients pour qu'ils suivent 
                                l'avancement de leurs échantillons.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 card-hover">
                        <div class="card-body text-center">
                            <i class="bi bi-graph-up feature-icon"></i>
                            <h5 class="card-title">Tableaux de Bord</h5>
                            <p class="card-text">
                                Visualisez les statistiques, les délais et la productivité 
                                avec des graphiques et rapports détaillés.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Workflow -->
    <section id="workflow" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Processus de Suivi</h2>
                <p class="text-muted">Le parcours complet de vos échantillons</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="step-timeline">
                        <div class="mb-5 position-relative">
                            <div class="step-number">1</div>
                            <h5>Réception de l'échantillon</h5>
                            <p class="text-muted mb-0">
                                Enregistrement, numérotation et création du dossier avec 
                                génération automatique d'un lien de suivi unique.
                            </p>
                        </div>
                        <div class="mb-5 position-relative">
                            <div class="step-number">2</div>
                            <h5>Préparation en laboratoire</h5>
                            <p class="text-muted mb-0">
                                Préparation des échantillons pour les analyses, attribution 
                                aux techniciens et planification.
                            </p>
                        </div>
                        <div class="mb-5 position-relative">
                            <div class="step-number">3</div>
                            <h5>Essais et analyses</h5>
                            <p class="text-muted mb-0">
                                Réalisation des tests, saisie des résultats et contrôles 
                                intermédiaires.
                            </p>
                        </div>
                        <div class="mb-5 position-relative">
                            <div class="step-number">4</div>
                            <h5>Vérification qualité</h5>
                            <p class="text-muted mb-0">
                                Validation des résultats par les responsables qualité 
                                et contrôle final.
                            </p>
                        </div>
                        <div class="position-relative">
                            <div class="step-number">5</div>
                            <h5>Livraison du rapport</h5>
                            <p class="text-muted mb-0">
                                Génération du rapport final, envoi au client et 
                                désactivation automatique du lien de suivi.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Login (visible seulement si non connecté) -->
    <?php if(!isset($_SESSION['user_id'])): ?>
    <section id="login" class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="login-form-container">
                        <h3 class="text-center mb-4">Accès au système</h3>
                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                            </button>
                            <div class="mt-3 text-center">
                                <small class="text-muted">
                                    Identifiants de démo : admin / admin123
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="bi bi-clipboard-data me-2"></i>LabTrack Pro</h5>
                    <p class="mb-0">
                        Système de suivi des échantillons de laboratoire<br>
                        Version 1.0 &copy; <?php echo date('Y'); ?>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <h5>Contact</h5>
                    <p class="mb-0">
                        <i class="bi bi-telephone me-2"></i>01 23 45 67 89<br>
                        <i class="bi bi-envelope me-2"></i>support@labtrack.com
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scroll pour les ancres
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Vérifier si l'utilisateur est déjà connecté
        <?php if(isset($_SESSION['user_id'])): ?>
        console.log('Utilisateur connecté : <?php echo $_SESSION['nom_complet']; ?>');
        <?php endif; ?>
    </script>
</body>
</html>