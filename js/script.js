// Fonction pour changer le statut d'un dossier
function changerStatut(dossierId) {
    const statuts = [
        'reçu',
        'en préparation', 
        'en cours essai',
        'en vérification',
        'rapport envoyé'
    ];
    
    // Demander confirmation
    if (confirm('Passer ce dossier au statut suivant ?')) {
        fetch(`api/changer-statut.php?id=${dossierId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Statut mis à jour avec succès !');
                location.reload();
            } else {
                alert('Erreur : ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur réseau');
        });
    }
}

// Fonction pour ajouter un nouveau dossier
document.addEventListener('DOMContentLoaded', function() {
    const formNouveauDossier = document.getElementById('form-nouveau-dossier');
    
    if (formNouveauDossier) {
        formNouveauDossier.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/ajouter-dossier.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Dossier créé avec succès !');
                    window.location.href = 'dashboard.php';
                } else {
                    alert('Erreur : ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur réseau');
            });
        });
    }
    
    // Auto-générer un numéro de dossier
    const generateNumeroBtn = document.getElementById('generate-numero');
    if (generateNumeroBtn) {
        generateNumeroBtn.addEventListener('click', function() {
            const date = new Date();
            const numero = 'DOS-' + 
                         date.getFullYear() + 
                         ('0' + (date.getMonth()+1)).slice(-2) + 
                         ('0' + date.getDate()).slice(-2) + 
                         '-' + 
                         Math.floor(Math.random() * 1000);
            
            document.getElementById('numero_dossier').value = numero;
        });
    }
});