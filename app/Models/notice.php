<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    // Nom exact de la table si différent de la convention plurielle
    protected $table = 'notices';

    // Si ta table n'a pas les colonnes created_at et updated_at
    public $timestamps = false;

    // Si tu veux autoriser l'insertion de certaines colonnes
    protected $fillable = [
        'titre', 'contenu', 'date_publication'
    ];
}
