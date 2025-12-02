<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FileProcessController extends Controller
{
    public function showForm()
    {
        return view('file_process');
    }

    public function process(Request $request)
    {
        $request->validate([
            'all' => 'required|file',
            'noexp' => 'required|file',
        ]);

        $logs = [];

        $files = [
            'ALL' => $request->file('all'),
            'NOEXP' => $request->file('noexp'),
        ];

        foreach ($files as $type => $file) {
            try {
                $extension = $file->getClientOriginalExtension();

                if (in_array($extension, ['txt','csv'])) {
                    $content = file_get_contents($file->getRealPath());
                    $lines = explode(PHP_EOL, $content);
                    foreach ($lines as $line) {
                        $cols = str_getcsv($line, ";"); // ou "\t" si tabulé
                        // traitement en mémoire ici
                    }
                } elseif ($extension === 'xlsx') {
                    $spreadsheet = IOFactory::load($file->getRealPath());
                    $sheet = $spreadsheet->getActiveSheet();
                    $data = $sheet->toArray();
                    // traitement en mémoire ici
                } else {
                    throw new \Exception("Type de fichier non supporté");
                }

                $logs[] = ['type' => 'OK', 'message' => "Fichier $type traité"];
            } catch (\Exception $e) {
                $logs[] = ['type' => 'ERR', 'message' => "Erreur sur $type : " . $e->getMessage()];
            }
        }

        return view('file_process', compact('logs'));
    }
}
