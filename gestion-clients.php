<?php
// gestion-clients.php - Gestion de la liste des clients
require_once 'includes/config.php';

// Vérifier la connexion
if (!isset($_SESSION['user_id'])) {
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

// Traitement des actions
$message = '';
$error = '';

// Ajouter un client
if (isset($_POST['ajouter_client'])) {
    $nom = secure_data($_POST['nom']);
    $email = secure_data($_POST['email']);
    $telephone = secure_data($_POST['telephone']);
    $adresse = secure_data($_POST['adresse']);
    $ville = secure_data($_POST['ville']);
    $code_postal = secure_data($_POST['code_postal']);
    $type_client = secure_data($_POST['type_client']);
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO clients (nom, email, telephone, adresse, ville, code_postal, type_client)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nom, $email, $telephone, $adresse, $ville, $code_postal, $type_client]);
        $message = "Client ajouté avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur lors de l'ajout : " . $e->getMessage();
    }
}

// Supprimer un client
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    try {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Client supprimé avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Récupérer la liste des clients
$clients = [];
try {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY nom ASC");
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur de chargement : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CETIEV Express - Gestion des clients</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --cetiev-blue: #0A2540;
            --cetiev-yellow: #FFCC00;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        .navbar-cetiev {
            background-color: var(--cetiev-blue);
        }
        .btn-cetiev {
            background-color: var(--cetiev-blue);
            color: white;
        }
        .btn-cetiev:hover {
            background-color: #061a2e;
            color: var(--cetiev-yellow);
        }
        .card-header {
            background: linear-gradient(135deg, var(--cetiev-blue) 0%, #061a2e 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navbar (similaire à dashboard.php) -->
    <nav class="navbar navbar-cetiev navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand text-white fw-bold" href="dashboard.php">
                CETIEV <span style="color: var(--cetiev-yellow);">Express</span>
            </a>
            <div class="ms-auto">
                <span class="text-white me-3"><?php echo htmlspecialchars($_SESSION['nom_complet']); ?></span>
                <a href="logout.php" class="btn btn-sm btn-outline-light">Déconnexion</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Menu</h5>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="dashboard.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-speedometer2"></i> Tableau de bord
                        </a>
                        <a href="nouveau-dossier.php" class="list-group-item list-group-item-action">
                            <i class="bi bi-plus-circle"></i> Nouveau dossier
                        </a>
                        <a href="gestion-clients.php" class="list-group-item list-group-item-action active">
                            <i class="bi bi-people"></i> Gestion des clients
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-people"></i> Gestion des clients</h2>
                    <button class="btn btn-cetiev" data-bs-toggle="modal" data-bs-target="#modalAjoutClient">
                        <i class="bi bi-plus-circle"></i> Nouveau client
                    </button>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Liste des clients</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nom</th>
                                        <th>Email</th>
                                        <th>Téléphone</th>
                                        <th>Ville</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><?php echo $client['id']; ?></td>
                                        <td><?php echo htmlspecialchars($client['nom']); ?></td>
                                        <td><?php echo htmlspecialchars($client['email']); ?></td>
                                        <td><?php echo htmlspecialchars($client['telephone']); ?></td>
                                        <td><?php echo htmlspecialchars($client['ville']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $client['type_client'] == 'entreprise' ? 'primary' : 'success'; ?>">
                                                <?php echo $client['type_client']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?supprimer=<?php echo $client['id']; ?>" 
                                               class="btn btn-sm btn-danger"
                                               onclick="return confirm('Supprimer ce client ?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Ajout Client -->
    <div class="modal fade" id="modalAjoutClient" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Nom / Entreprise</label>
                            <input type="text" name="nom" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Téléphone</label>
                                <input type="text" name="telephone" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adresse</label>
                            <textarea name="adresse" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ville</label>
                                <input type="text" name="ville" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Code postal</label>
                                <input type="text" name="code_postal" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type de client</label>
                            <select name="type_client" class="form-select">
                                <option value="entreprise">Entreprise</option>
                                <option value="particulier">Particulier</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" name="ajouter_client" class="btn btn-cetiev">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>