<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MoulinetteController extends Controller
{
    // Colonnes EXACTES dans l'ordre Amazon
    public $columns = [
        'order-id','order-item-id','purchase-date','payments-date','buyer-email','buyer-name','buyer-phone-number',
        'sku','product-name','quantity-purchased','currency','item-price','item-tax','shipping-price','shipping-tax',
        'ship-service-level','recipient-name','ship-address-1','ship-address-2','ship-address-3','ship-city','ship-state',
        'ship-postal-code','ship-country','ship-phone-number','delivery-start-date','delivery-end-date','delivery-time-zone',
        'delivery-Instructions','sales-channel','is-business-order','purchase-order-number','price-designation',
        'is-amazon-invoiced','vat-exclusive-item-price','vat-exclusive-shipping-price','vat-exclusive-giftwrap-price'
    ];

    public function index()
    {
        return view('moulinette');
    }

    public function process(Request $request)
    {
        $fileAll = $request->file('file_all');
        $fileNoexp = $request->file('file_noexp');

        if (!$fileAll || !$fileNoexp) {
            return back()->with('error', 'Les deux fichiers sont requis.');
        }

        $all = $this->readTabFile($fileAll->getRealPath());
        $noexp = $this->readTabFile($fileNoexp->getRealPath());

        // Indexation par order-id + order-item-id
        $allMap = [];
        foreach ($all as $row) {
            $key = $row['order-id'] . '#' . $row['order-item-id'];
            $allMap[$key] = $row;
        }

        // Fusion des fichiers
        $result = [];
        $skipped = 0;

        foreach ($noexp as $row) {
            if (empty($row['order-id'])) {
                $skipped++;
                continue;
            }

            $key = $row['order-id'] . '#' . $row['order-item-id'];

            $merged = [];
            foreach ($this->columns as $col) {
                $merged[$col] = $allMap[$key][$col] ?? $row[$col] ?? '';
            }

            $result[] = $merged;
        }

        Session::put('yoyoamaz_result', $result);
        Session::put('yoyoamaz_columns', $this->columns);

        $message = "Fichier traité, prêt au téléchargement.";
        if ($skipped > 0) {
            $message .= " $skipped ligne(s) ignorées car elles n'avaient pas d'order-id.";
        }

        return back()->with('success', $message);
    }

    public function download()
    {
        $result = Session::get('yoyoamaz_result');
        $columns = Session::get('yoyoamaz_columns');

        if (!$result || !$columns) {
            return back()->with('error', 'Aucun fichier à télécharger.');
        }

        return response()->streamDownload(function () use ($result, $columns) {
            echo implode("\t", $columns) . "\n";

            foreach ($result as $row) {
                $line = array_map(fn($c) => $row[$c] ?? '', $columns);
                echo implode("\t", $line) . "\n";
            }
        }, 'yoyoamaz.txt');
    }

    // Lecture robuste d’un fichier TAB avec normalisation des colonnes
private function readTabFile($path)
{
    $content = file_get_contents($path);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = preg_split('/\r\n|\n|\r/', $content);

    $rows = [];
    $header = null;
    $expectedCols = 0;
    $mapHeaderToColumn = [];

    foreach ($lines as $index => $line) {
        if (trim($line) === '') continue;
        $cols = str_getcsv($line, "\t");

        // Première ligne = header
        if ($header === null) {
            $header = $cols;
            $expectedCols = count($header);

            // Créer mapping : colonne normalisée => colonne réelle
            foreach ($header as $h) {
                $hNorm = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $h)));
                foreach ($this->columns as $col) {
                    if ($hNorm === strtolower($col)) {
                        $mapHeaderToColumn[$col] = $h;
                        break;
                    }
                }
            }
            continue;
        }

        // Compléter si ligne courte
        if (count($cols) < $expectedCols) {
            $cols = array_pad($cols, $expectedCols, '');
        }

        $assoc = array_combine($header, $cols);

        // Créer tableau normalisé
        $assocNorm = [];
        foreach ($this->columns as $col) {
            $assocNorm[$col] = isset($mapHeaderToColumn[$col]) ? $assoc[$mapHeaderToColumn[$col]] : '';
        }

        // Colonnes de prix vides = 0
        $priceColumns = [
            'item-price','item-tax','shipping-price','shipping-tax',
            'vat-exclusive-item-price','vat-exclusive-shipping-price','vat-exclusive-giftwrap-price'
        ];
        foreach ($priceColumns as $pcol) {
            if (isset($assocNorm[$pcol]) && $assocNorm[$pcol] === '') {
                $assocNorm[$pcol] = '0';
            }
        }

        if (!empty($assocNorm['order-id'])) {
            $rows[] = $assocNorm;
        }
    }

    return $rows;
}
}