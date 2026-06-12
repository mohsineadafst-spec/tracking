<?php
// includes/sidebar.php - Sidebar commune
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="card mb-3">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bi bi-menu-button"></i> Navigation</h6>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <a href="dashboard.php" 
               class="list-group-item list-group-item-action <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> Tableau de bord
            </a>
            <a href="nouveau-dossier.php" 
               class="list-group-item list-group-item-action <?php echo $current_page == 'nouveau-dossier.php' ? 'active' : ''; ?>">
                <i class="bi bi-plus-circle"></i> Nouveau dossier
            </a>
            <a href="recherche-dossiers.php" 
               class="list-group-item list-group-item-action <?php echo $current_page == 'recherche-dossiers.php' ? 'active' : ''; ?>">
                <i class="bi bi-search"></i> Rechercher
            </a>
            
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div class="list-group-item">
                <small class="text-muted">ADMINISTRATION</small>
            </div>
            <a href="gestion-utilisateurs.php" 
               class="list-group-item list-group-item-action <?php echo $current_page == 'gestion-utilisateurs.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> Utilisateurs
            </a>
            <a href="rapports-statistiques.php" 
               class="list-group-item list-group-item-action <?php echo $current_page == 'rapports-statistiques.php' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up"></i> Statistiques
            </a>
            <?php endif; ?>
            
            <div class="list-group-item">
                <small class="text-muted">DOSSIERS</small>
            </div>
            <a href="dossiers-en-cours.php" 
               class="list-group-item list-group-item-action">
                <i class="bi bi-clock"></i> En cours
                <span class="badge bg-primary float-end"><?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut NOT IN ('rapport envoyé')");
                    echo $stmt->fetchColumn();
                ?></span>
            </a>
            <a href="dossiers-termines.php" 
               class="list-group-item list-group-item-action">
                <i class="bi bi-check-circle"></i> Terminés
                <span class="badge bg-success float-end"><?php
                    $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut = 'rapport envoyé'");
                    echo $stmt->fetchColumn();
                ?></span>
            </a>
        </div>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="card">
    <div class="card-header bg-light">
        <h6 class="mb-0"><i class="bi bi-bar-chart"></i> Aujourd'hui</h6>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <span>Nouveaux dossiers</span>
            <span class="badge bg-primary rounded-pill"><?php
                $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE DATE(date_reception) = CURDATE()");
                echo $stmt->fetchColumn();
            ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span>En attente</span>
            <span class="badge bg-warning rounded-pill"><?php
                $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE statut = 'en vérification'");
                echo $stmt->fetchColumn();
            ?></span>
        </div>
        <div class="d-flex justify-content-between">
            <span>Urgents</span>
            <span class="badge bg-danger rounded-pill"><?php
                $stmt = $pdo->query("SELECT COUNT(*) FROM dossiers WHERE urgence = 'très urgent'");
                echo $stmt->fetchColumn();
            ?></span>
        </div>
    </div>
</div>