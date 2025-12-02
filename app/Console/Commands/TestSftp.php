<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestSftp extends Command
{
    protected $signature = 'test:sftp';
    protected $description = 'Tester la connexion SFTP';

    public function handle()
    {
        try {
            Storage::disk('sftp')->put('test.txt', 'Ceci est un test');
            $this->info("Connexion SFTP OK, fichier test.txt envoyé !");
        } catch (\Exception $e) {
            $this->error("Erreur de connexion SFTP : " . $e->getMessage());
        }
    }
}
