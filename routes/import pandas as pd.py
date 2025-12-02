import pandas as pd

def traiter_commandes(all_file, noexp_file, id_col, output_file="commandes_communes.txt"):
    # Charger les deux fichiers Excel
    all_df = pd.read_excel(all_file)
    noexp_df = pd.read_excel(noexp_file)

    # Nettoyage : enlever les vides, transformer en texte propre
    all_ids = set(all_df[id_col].dropna().astype(str).str.strip())
    noexp_ids = set(noexp_df[id_col].dropna().astype(str).str.strip())

    # Intersection
    communes = sorted(all_ids & noexp_ids)

    # Écrire le fichier texte
    with open(output_file, "w", encoding="utf-8") as f:
        f.write(f"Nombre de commandes communes : {len(communes)}\\n")
        f.write("-----------------------------------\\n")
        for commande in communes:
            f.write(commande + "\\n")

    print(f"✅ Fichier généré : {output_file} ({len(communes)} commandes communes)")

# Exemple d'appel
traiter_commandes("all_orders.xlsx", "noexp_orders.xlsx", id_col="Order Number")
