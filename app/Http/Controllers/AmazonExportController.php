<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AmazonExportController extends Controller
{
    public function download()
    {
        // Chemins vers tes fichiers sources
        $fileAll   = storage_path('app/all_orders.txt');   // place tes fichiers dans storage/app
        $fileNoexp = storage_path('app/no_exp_orders.txt');

        // Colonnes dans l'ordre voulu
        $columns = [
            'order-id','order-item-id','purchase-date','payments-date','buyer-email','buyer-name','buyer-phone-number',
            'sku','product-name','quantity-purchased','currency','item-price','item-tax','shipping-price','shipping-tax',
            'ship-service-level','recipient-name','ship-address-1','ship-address-2','ship-address-3','ship-city','ship-state',
            'ship-postal-code','ship-country','ship-phone-number','delivery-start-date','delivery-end-date','delivery-time-zone',
            'delivery-Instructions','sales-channel','is-business-order','purchase-order-number','price-designation',
            'is-amazon-invoiced','vat-exclusive-item-price','vat-exclusive-shipping-price','vat-exclusive-giftwrap-price'
        ];

        // Fonction pour lire un fichier "txt" avec tabulations
        $readTxtFile = function($filepath) {
            if (!file_exists($filepath)) return [];

            $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!$lines) return [];

            $header = explode("\t", array_shift($lines));
            $records = [];
            foreach ($lines as $line) {
                $data = explode("\t", $line);
                if (count($header) != count($data)) continue;
                $row = array_combine($header, $data);
                $records[$row['order-id']][] = $row;
            }
            return $records;
        };

        // Lire les fichiers
        $allRecords = $readTxtFile($fileAll);
        $noexpRecords = $readTxtFile($fileNoexp);

        // Fusionner
        $result = [];
        foreach (array_unique(array_merge(array_keys($allRecords), array_keys($noexpRecords))) as $orderId) {
            if (isset($allRecords[$orderId])) {
                foreach ($allRecords[$orderId] as $item) $result[] = $item;
            } elseif (isset($noexpRecords[$orderId])) {
                foreach ($noexpRecords[$orderId] as $item) $result[] = $item;
            }
        }

        // Générer TXT en mémoire
        $callback = function() use ($result, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, implode("\t", $columns) . "\n"); // en-têtes
            foreach ($result as $row) {
                $line = [];
                foreach ($columns as $col) {
                    $line[] = $row[$col] ?? '';
                }
                fwrite($out, implode("\t", $line) . "\n");
            }
            fclose($out);
        };

        // Télécharger le fichier TXT
        return Response::streamDownload($callback, 'export_amazon.txt', [
            'Content-Type' => 'text/plain'
        ]);
    }
}
