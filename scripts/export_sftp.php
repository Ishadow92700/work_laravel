<?php

require 'vendor/autoload.php';
use phpseclib3\Net\SFTP;

// --------------------------------------------------------
// 1. Connexion MariaDB Dokku
// --------------------------------------------------------

$pdo = new PDO(
    "mysql:host=dokku-mariadb-laravel-staging;port=3306;dbname=laravel_staging;charset=utf8",
    "mariadb",
    "16cba6dbbc2acd25",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]
);

// --------------------------------------------------------
// 2. Récupération des 50 dernières notices (ta requête)
// --------------------------------------------------------

$sql = "
SELECT 
    ean AS EAN13,
    title AS Titre,
    title AS TitreMin,
    subtitle AS TitreSous,
    desk_label AS Generique,
    editorial_brand AS Editeur,
    '01/12/2099' AS EditeurMin,
    'Collectif' AS Auteur1,
    '01/12/2099' AS Autueur1Min,
    '' AS Auteur2,
    'UC à mesure fixe' AS Auteur2Min,
    '' AS Illustrateur,
    '' AS IllustrateurMin,
    name AS Diffuseur,
    '' AS ThemeGRP,
    '' AS ThemeID,
    '' AS Theme,
    '' AS Etat,
    '' AS PresentationID,
    '' AS Presentation,
    '' AS Article,
    '' AS Collection,
    '' AS DateParution,
    '' AS DateMaj,
    '' AS Poids,
    '' AS Epaisseur,
    '' AS Hauteur,
    '' AS Largeur,
    '' AS Pages,
    '' AS PrixHT,
    '' AS TVA,
    '' AS `Prix TTC`,
    '' AS Dilicom,
    '' AS Stock,
    '' AS MotCle,
    '' AS Resume,
    '' AS CyberPop,
    '' AS PreCom,
    '' AS IDFournisseur,
    '' AS Zone,
    '' AS Npu,
    '' AS ID_Octave,
    '' AS MarketPlace
FROM notices
ORDER BY id DESC
LIMIT 50
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    die("Aucune donnée trouvée.");
}

// --------------------------------------------------------
// 3. Création du CSV
// --------------------------------------------------------

$filename = "export_notices_" . date("Ymd_His") . ".csv";
$localPath = "/tmp/" . $filename;

$fp = fopen($localPath, 'w');

// Entêtes CSV
fputcsv($fp, array_keys($rows[0]), ';');

// Données
foreach ($rows as $row) {
    fputcsv($fp, $row, ';');
}

fclose($fp);

// --------------------------------------------------------
// 4. Envoi SFTP dans /Import
// --------------------------------------------------------

$sftp = new SFTP('149.202.40.76');

if (!$sftp->login('divalto_test', 'TON_MDP_SFTP')) {
    die("❌ Connexion SFTP échouée");
}

$remotePath = "/Import/" . $filename;

if ($sftp->put($remotePath, file_get_contents($localPath))) {
    echo "✅ Fichier envoyé avec succès : $remotePath\n";
} else {
    echo "❌ Erreur lors de l'envoi SFTP\n";
}

?>
