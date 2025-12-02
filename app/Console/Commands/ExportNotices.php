<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExportNotices extends Command
{
    protected $signature = 'export:notices';
    protected $description = 'Export 50 dernières notices et envoi SFTP';

    public function handle()
    {
        // Connexion PDO uniquement quand la commande est exécutée
        $pdo = new \PDO(
            "mysql:host=149.202.40.76;port=3306;dbname=laravel_staging;charset=utf8",
            "mariadb",
            "16cba6dbbc2acd25",
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        // Requête SQL
        $sql = "SELECT ean AS EAN13, title AS Titre, ... LIMIT 50";
        $rows = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        if (!$rows) {
            $this->info("Aucune donnée trouvée.");
            return 0;
        }

        // Création CSV
        $filename = "export_notices_" . date("Ymd_His") . ".csv";
        $localPath = storage_path($filename);

        $fp = fopen($localPath, 'w');
        fputcsv($fp, array_keys($rows[0]), ';');
        foreach ($rows as $row) {
            fputcsv($fp, $row, ';');
        }
        fclose($fp);
        $this->info("CSV créé : $localPath");

        // SFTP
        $sftp = new \phpseclib3\Net\SFTP('IP_DU_SERVEUR_SFTP');
        if (!$sftp->login('UTILISATEUR_SFTP', 'MOT_DE_PASSE_SFTP')) {
            $this->error("Connexion SFTP échouée");
            return 1;
        }

        $remotePath = "/Import/{$filename}";
        if ($sftp->put($remotePath, file_get_contents($localPath))) {
            $this->info("✅ Fichier envoyé : $remotePath");
        } else {
            $this->error("❌ Erreur d'envoi SFTP");
        }

        return 0;
    }
}
