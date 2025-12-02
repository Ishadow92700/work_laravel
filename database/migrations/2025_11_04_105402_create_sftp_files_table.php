<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    Schema::create('sftp_files', function (Blueprint $table) {
    $table->id();
    $table->string('filename');       // nom du fichier
    $table->text('content');          // contenu du fichier 
    $table->enum('status', ['pending', 'uploaded', 'failed'])->default('pending');
    $table->text('error')->nullable(); // pour stocker l’erreur si besoin
    $table->timestamps();
});

        Artisan::call('migrate');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sftp_files');
    }
};
