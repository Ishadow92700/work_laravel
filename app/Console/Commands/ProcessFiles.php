<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessFiles extends Command
{
    protected $signature = 'process:files';
    protected $description = 'Traitement des fichiers ALL et NOEXP directement en mémoire';

    public function handle()
    {
        // -------------------------------------
        // Fonction pour afficher les logs stylés
        // -------------------------------------
        $displayLog = function($type, $message) {
            $circle = match($type) {
                'OK' => '🟢',
                'WARN' => '🟠',
                'ERR' => '🔴',
                default => '⚪',
            };
            $this->line("$circle $message");
        };

        // -------------------------------------
        // Exemple de fichiers ALL et NOEXP (simulé)
        // -------------------------------------
        $files = [
            'all' => "EAN13;Titre;TitreMin\n12345;Livre A;Livre A",
            'noexp' => "EAN13;Titre\n67890;Livre B",
        ];

        foreach ($files as $type => $content) {
            try {
                $lines = explode(PHP_EOL, $content);
                foreach ($lines as $line) {
                    $cols = str_getcsv($line, ";"); // séparateur CSV
                    // Ici tu peux traiter les colonnes directement
                }
                $displayLog('OK', "Fichier $type traité avec succès");
            } catch (\Exception $e) {
                $displayLog('ERR', "Erreur sur $type : " . $e->getMessage());
            }
        }

        return 0;
    }
}
