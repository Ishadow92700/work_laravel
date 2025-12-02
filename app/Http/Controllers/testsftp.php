<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class testsftp extends Controller
{
    public function sendTest(Request $request)
    {
        try {
            Storage::disk('sftp')->put('test.txt', 'Ceci est un test');

            return response()->json([
                'message' => 'Connexion SFTP OK, fichier test.txt envoyé !'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur de connexion SFTP : ' . $e->getMessage()
            ], 500);
        }
    }
}
