<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class NoticeController2 extends Controller
{
    public function import()
    {
        $result = DB::select(
            'SELECT 
                ean AS EAN13, 
                title AS Titre, 
                title AS TitreMin, 
                subtitle AS TitreSous, 
                desk_label AS Generique, 
                editorial_brand AS Editeur, 
                "01/12/2099" AS EditeurMin, 
                "Collectif" AS Auteur1, 
                "01/12/2099" AS Autueur1Min, 
                "" AS Auteur2, 
                "UC à mesure fixe" AS Auteur2Min, 
                "" AS Illustrateur, 
                "" AS IllustrateurMin, 
                name AS Diffuseur, 
                "" AS ThemeGRP, "" AS ThemeID, "" AS Theme, "" AS Etat, "" AS PresentationID, "" AS Presentation, "" AS Article, "" AS Collection, "" AS DateParution, "" AS DateMaj, "" AS Poids, "" AS Epaisseur, "" AS Hauteur, "" AS Largeur, "" AS Pages, "" AS PrixHT, "" AS TVA, "" AS Prix TTC, "" AS Dilicom, "" AS Stock, "" AS MotCle, "" AS Resume, "" AS CyberPop, "" AS PreCom, "" AS IDFournisseur, "" AS Zone, "" AS Npu, "" AS ID_Octave, "" AS MarketPlace
            FROM notices
            LEFT JOIN editors ON notices.editor_id = editors.id
            LEFT JOIN diffusers ON editors.diffuser_id = diffusers.name
            WHERE notices.updated_at > ? AND notices.updated_at < ? AND is_not_working_with_amazon = 0',
            ["2025-09-20 00:00:00", "2025-09-31 00:00:00"]
        );

        return response()->json($result);
    }
}
