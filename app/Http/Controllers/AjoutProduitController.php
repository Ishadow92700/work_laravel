<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class AjoutProduitController extends Controller
{
    // Colonnes standard utilisées pour la moulinette (fusion commandes)
    private $columns = [
        'order-id','order-item-id','purchase-date','payments-date',
        'buyer-email','buyer-name','buyer-phone-number',
        'sku','product-name','quantity-purchased','currency',
        'item-price','item-tax','shipping-price','shipping-tax',
        'ship-service-level','recipient-name','ship-address-1',
        'ship-address-2','ship-address-3','ship-city','ship-state',
        'ship-postal-code','ship-country','ship-phone-number',
        'delivery-start-date','delivery-end-date','delivery-time-zone',
        'delivery-instructions','sales-channel','is-business-order',
        'purchase-order-number','price-designation','is-amazon-invoiced',
        'vat-exclusive-item-price','vat-exclusive-shipping-price','vat-exclusive-giftwrap-price'
    ];

    private $priceColumns = [
        'item-price','item-tax','shipping-price','shipping-tax',
        'vat-exclusive-item-price','vat-exclusive-shipping-price','vat-exclusive-giftwrap-price'
    ];

    /** ---------- VUE DASHBOARD ---------- */
    public function index()
    {
        $viewName = 'Gestion-stock.ajout-produits-form';
        if (view()->exists($viewName)) {
            return view($viewName);
        }
        // Fallback if the expected view is missing (adjust to a view that exists in your app)
        return view('welcome');
    }

    /** ---------- 1) MOULINETTE (fusion ALL & NOEXP) ---------- */
    public function processMoulinette(Request $request)
    {
        $fileAll   = $request->file('file_all');
        $fileNoexp = $request->file('file_noexp');

        if (!$fileAll || !$fileNoexp) {
            return back()->with('error', 'Les deux fichiers sont requis pour la moulinette.');
        }

        $allRows   = $this->readAndNormalize($fileAll->getRealPath(), true);
        $noexpRows = $this->readAndNormalize($fileNoexp->getRealPath(), false);

        // index all rows by order-id (lowercased)
        $mapAll = [];
        foreach ($allRows as $r) {
            $oid = strtolower(trim($r['order-id'] ?? ''));
            if ($oid !== '') $mapAll[$oid][] = $r;
        }

        // take first noexp only for each order-id
        $mapNoexp = [];
        foreach ($noexpRows as $r) {
            $oid = strtolower(trim($r['order-id'] ?? ''));
            if ($oid !== '' && !isset($mapNoexp[$oid])) {
                $mapNoexp[$oid] = $r;
            }
        }

        // build result
        $result = [];
        foreach ($mapNoexp as $oid => $rNoexp) {
            $matchingAll = $mapAll[$oid] ?? [];

            if (empty($matchingAll)) {
                $merged = $this->mergeRows(null, $rNoexp);
                if (floatval($merged['item-price']) > 0) $result[] = $merged;
                continue;
            }

            foreach ($matchingAll as $rAll) {
                $merged = $this->mergeRows($rAll, $rNoexp);
                if (floatval($merged['item-price']) == 0) continue;
                $result[] = $merged;
            }
        }

        Session::put('moulinette_result', $result);
        Session::put('moulinette_columns', $this->columns);

        return back()->with('success', 'Fusion terminée — prêt au téléchargement (section MOULINETTE).');
    }

    /** ---------- 2) AJOUTER NOUVEAUX PRODUITS ---------- 
     *  Attendus : 
     *   - file_produits (Qlik produits: EAN in col1, qty in col2)
     *   - file_ean_price (Qlik ean_price: ean in col1, price in col2)
     *   - file_template_ajouter (template Excel 'Ajouter')
     *   Champs optionnels (form) : type, canal, statut, temps_traitement
     */
    public function processAjouterProduits(Request $request)
    {
        $request->validate([
            'file_produits' => 'required|file',
            'file_ean_price' => 'required|file',
            'file_template_ajouter' => 'required|file|mimes:xlsx,xls',
            'type' => 'required|string',
            'canal' => 'required|string',
            'statut' => 'required|string',
            'temps_traitement' => 'required|numeric'
        ]);

        $pathProduits = $request->file('file_produits')->getRealPath();
        $pathEanPrice = $request->file('file_ean_price')->getRealPath();
        $pathTemplate = $request->file('file_template_ajouter')->getRealPath();

        // 1) Lire Produits (flexible CSV/TSV) -> map EAN -> qty
        $rawProduits = $this->readFlexible($pathProduits);
        $produits_qty = [];
        foreach ($rawProduits as $r) {
            // hypothèse : première colonne contient EAN/Titre
            $possible = array_values($r);
            $eanRaw = $possible[0] ?? '';
            $qtyRaw = $possible[1] ?? '';
            $ean = $this->extractEan($eanRaw);
            if ($ean !== null) {
                $produits_qty[$ean] = intval($this->normalizeNumeric($qtyRaw) ?? 0);
            }
        }

        // 2) Lire Ean_price -> map EAN -> price (float)
        $rawEans = $this->readFlexible($pathEanPrice);
        $ean_price_map = [];
        foreach ($rawEans as $r) {
            $vals = array_values($r);
            $eanRaw = $vals[0] ?? '';
            $priceRaw = $vals[1] ?? '';
            $ean = $this->extractEan($eanRaw);
            if ($ean !== null) {
                // remplacer virgule par point
                $price = $this->normalizeNumeric($priceRaw);
                if ($price !== null) {
                    $ean_price_map[$ean] = number_format((float)$price, 2, '.', '');
                } else {
                    $ean_price_map[$ean] = null;
                }
            }
        }

        // 3) Charger template Excel 'Ajouter'
        $spreadsheet = IOFactory::load($pathTemplate);
        $sheet = $spreadsheet->getActiveSheet(); // on modifie la feuille active; si besoin on peut getSheetByName('Ajouter')

        // Paramètres depuis formulaire
        $typeIdent = $request->input('type', 'EAN');
        $canal = $request->input('canal', 'DEFAULT');
        $statut = $request->input('statut', 'Nouveau');
        $tempsTrait = intval($request->input('temps_traitement', 2));

        // Determine data start row (par défaut: ligne 6 dans ton script Python => index 5 -> spreadsheet rows are 1-indexed)
        $DATA_START_ROW = 6;

        // Créer la liste d'EAN à ajouter (les EAN extraits du fichier Produits)
        $eans_to_add = array_values(array_unique(array_keys($produits_qty)));

        // Construire les nouvelles lignes selon les règles de quantité
        $newRows = [];
        foreach ($eans_to_add as $ean) {
            $original_qty = $produits_qty[$ean] ?? 0;
            $new_qty = $this->bucketQuantity($original_qty);
            $price = $ean_price_map[$ean] ?? '';

            $newRows[] = [
                'SKU' => $ean,
                'TypeIdent' => $typeIdent,
                'IdentifiantProduit' => $ean,
                'QuantiteFR' => $new_qty,
                'PrixEuro' => $price,
                'Canal' => $canal,
                'Etat' => $statut,
                'TempsTraitement' => $tempsTrait,
                'PrixB2B' => $price
            ];
        }

        // S'assurer que la feuille a assez de lignes et colonnes (on écrira colonnes en A..)
        $neededRows = count($newRows);
        $currentHighestRow = $sheet->getHighestRow();

        if ($currentHighestRow < ($DATA_START_ROW - 1 + $neededRows)) {
            $rowsToAdd = ($DATA_START_ROW - 1 + $neededRows) - $currentHighestRow;
            // ajout de lignes vides en bas (PhpSpreadsheet n'a pas addRows direct; on peut écrire en lignes ultérieures sans extension)
            // pas d'opération nécessaire, on écrira directement
        }
        // Colonnes cibles — adapter si besoin
        // On suit le mapping basique : A=SKU, B=Type, C=Identifiant, H=Etat (col 8), AG (col 33), AH (col34), AI(35), AL(38), AU(47)
        foreach ($newRows as $i => $r) {
            $rowIndex = $DATA_START_ROW + $i;
        
            // A = SKU
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(1) . $rowIndex, $r['SKU']);
            // B = Type d'identifiant produit
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(2) . $rowIndex, $r['TypeIdent']);
            // C = Identifiant produit
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(3) . $rowIndex, $r['IdentifiantProduit']);
            // H = Etat (col 8)
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(8) . $rowIndex, $r['Etat']);
            // AG = col 33 (Code canal d'expédition)
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(33) . $rowIndex, $r['Canal']);
            // AH = col 34 (Quantité)
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(34) . $rowIndex, $r['QuantiteFR']);
            // AI = col 35 (Temps traitement B2B)
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(35) . $rowIndex, $r['TempsTraitement']);
            // AL = col 38 (Votre prix EUR)
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(38) . $rowIndex, $r['PrixEuro']);
            // AU = col 47 (Prix B2B)
            $sheet->setCellValue(Coordinate::stringFromColumnIndex(47) . $rowIndex, $r['PrixB2B']);
        }

        // Sauvegarder fichier temporaire et proposer download
        $tempPath = storage_path('app/public/ajouter_produits_result.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        // Stocker chemin en session pour download si besoin
        Session::put('ajouter_produits_path', $tempPath);

        return back()->with('success', "Traitement 'Ajouter produits' terminé — fichier prêt au téléchargement (section AJOUTER).");
    }

    /** Endpoint pour télécharger le fichier Ajouter produits */
    public function downloadAjouter()
    {
        $path = Session::get('ajouter_produits_path');
        if (!$path || !file_exists($path)) {
            return back()->with('error', "Fichier Ajouter introuvable.");
        }
        return response()->download($path)->deleteFileAfterSend(false);
    }

    /** ---------- 3) MISE A JOUR DES STOCKS (depuis Qlik stock + template Maj) ---------- 
     * Attendus :
     *  - file_stock (Qlik stock: EAN col1, qty col2)
     *  - file_template_maj (Excel template pour 'Maj Quantités' ou 'Maj Produits')
     *  Champs optionnels : choose_sheet_name (nom feuille à écrire), price_format_choice etc.
     */
    public function processMajStock(Request $request)
    {
        $request->validate([
            'file_stock' => 'required|file',
            'file_template_maj' => 'required|file|mimes:xlsx,xls',
            'sheet_to_write' => 'nullable|string'
        ]);

        $pathStock = $request->file('file_stock')->getRealPath();
        $pathTemplate = $request->file('file_template_maj')->getRealPath();
        $targetSheetName = $request->input('sheet_to_write', 'Maj Quantités');

        // 1) Lire stock Qlik -> map EAN -> qty
        $rawStock = $this->readFlexible($pathStock);
        $stock_map = [];
        foreach ($rawStock as $r) {
            $vals = array_values($r);
            $eanRaw = $vals[0] ?? '';
            $qtyRaw = $vals[1] ?? '';
            $ean = $this->extractEan($eanRaw);
            if ($ean !== null) {
                $normalized = $this->normalizeNumeric($qtyRaw);
                $qty = $normalized === null ? 0 : intval($normalized);
                $stock_map[$ean] = $qty;
            }
        }

        // 2) Charger template Excel
        $spreadsheet = IOFactory::load($pathTemplate);

        // create or get sheet to write
        if ($spreadsheet->sheetNameExists($targetSheetName)) {
            $sheet = $spreadsheet->getSheetByName($targetSheetName);
        } else {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($targetSheetName);
        }

        // Trouver colonne SKU (on supposera 'sku' en A ou première colonne). Ici on supporte header detection.
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $headerRow = 1;
        $header = [];
        if ($highestRow >= 1) {
            $header = $sheet->rangeToArray("A{$headerRow}:{$highestCol}{$headerRow}", null, true, false)[0] ?? [];
        }

        // Si on trouve 'sku' ou 'sku-vendeur' dans header, on utilisera cette colonne; sinon on assumera colonne A.
        $skuColIndex = 1; // 1-indexed
        foreach ($header as $idx => $val) {
            $v = strtolower(trim((string)$val));
            if (in_array($v, ['sku','sku-vendeur','ean','ean-titre'])) {
                $skuColIndex = $idx + 1;
                break;
            }
        }

        // On écrira à partir de DATA_START_ROW default = 2 (juste après header) si header existant
        $DATA_START_ROW = ($highestRow >= 1 ? 2 : 1);

        // Construire tableau d'écriture : pour chaque ligne existante dans sheet on remappe quantity si EAN match
        $sheetHighestRow = max($sheet->getHighestRow(), $DATA_START_ROW - 1);
        $writeRow = $DATA_START_ROW;
        $writtenCount = 0;

        // Option A: si template contient déjà les SKUs, on mettra à jour la colonne 'quantity' ou col 2 par défaut
        // Option B: si template vide, on écrira lignes nouvelles : SKU (col A) and quantity (col B) and price col maybe.
        // Ici on fait démarche simple : si sheet has header with 'sku' we iterate rows and update quantity column if found;
        // otherwise we append rows with SKU in col A and quantity in col B.

        $hasSkuHeader = true;
        if (empty($header)) $hasSkuHeader = false;
        $quantityColIndex = 2; // default column B

        // try detect quantity column name
        foreach ($header as $idx => $val) {
            $v = strtolower(trim((string)$val));
            if (in_array($v, ['quantity','quantity-purchased','quantité','quantite','quantity (fr)'])) {
                $quantityColIndex = $idx + 1;
                break;
            }
        }
        if ($hasSkuHeader) {
            // iterate existing rows to update
            $lastRow = $sheet->getHighestRow();
            for ($r = $DATA_START_ROW; $r <= $lastRow; $r++) {
                $skuCell = (string)$sheet->getCell(Coordinate::stringFromColumnIndex($skuColIndex) . $r)->getValue();
                $ean = $this->extractEan($skuCell);
                if ($ean !== null && isset($stock_map[$ean])) {
                    $origQtyCell = $sheet->getCell(Coordinate::stringFromColumnIndex($quantityColIndex) . $r)->getValue();
                    $origQty = $this->normalizeNumeric($origQtyCell);
                    $origQty = $origQty === null ? 0 : intval($origQty);
                    $newQty = $this->bucketQuantity($stock_map[$ean]);
                    // write newQty
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($quantityColIndex) . $r, $newQty);
                    $writtenCount++;
                }
            }
        
            // For SKUs in stock_map but not present in template, append
            foreach ($stock_map as $ean => $qty) {
                // search if exists already
                $found = false;
                for ($r = $DATA_START_ROW; $r <= $sheet->getHighestRow(); $r++) {
                    $skuCell = (string)$sheet->getCell(Coordinate::stringFromColumnIndex($skuColIndex) . $r)->getValue();
                    $ean2 = $this->extractEan($skuCell);
                    if ($ean2 === $ean) { $found = true; break; }
                }
                if (!$found) {
                    $appendRow = $sheet->getHighestRow() + 1;
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($skuColIndex) . $appendRow, $ean);
                    $sheet->setCellValue(Coordinate::stringFromColumnIndex($quantityColIndex) . $appendRow, $this->bucketQuantity($qty));
                    $writtenCount++;
                }
            }
        } else {
            // sheet without header -> append all stock_map lines starting at DATA_START_ROW
            $rowIdx = $DATA_START_ROW;
            foreach ($stock_map as $ean => $qty) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(1) . $rowIdx, $ean);
                $sheet->setCellValue(Coordinate::stringFromColumnIndex(2) . $rowIdx, $this->bucketQuantity($qty));
                $rowIdx++;
                $writtenCount++;
            }
        }

        // Sauvegarde fichier temporaire
        $tempPath = storage_path('app/public/maj_stock_result.xlsx');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        Session::put('maj_stock_path', $tempPath);

        return back()->with('success', "Traitement 'Maj Stock' terminé — fichier prêt au téléchargement (section MAJ STOCK). Ecrit: {$writtenCount} lignes.");
    }

    public function downloadMajStock()
    {
        $path = Session::get('maj_stock_path');
        if (!$path || !file_exists($path)) {
            return back()->with('error', "Fichier Maj Stock introuvable.");
        }
        return response()->download($path)->deleteFileAfterSend(false);
    }

    /** ---------- DOWNLOAD MOULINETTE ---------- */
    public function downloadMoulinette()
    {
        $result = Session::get('moulinette_result');
        $columns = Session::get('moulinette_columns');

        if (!$result || !$columns) {
            return back()->with('error', "Aucun résultat moulinette en session.");
        }

        return response()->streamDownload(function () use ($result, $columns) {
            $out = fopen('php://output', 'w');
            // Header (tab separated) WITHOUT quotes
            fwrite($out, implode("\t", $columns) . "\n");
            foreach ($result as $row) {
                $clean = [];
                foreach ($columns as $c) {
                    $v = $row[$c] ?? '';
                    $v = str_replace(["\t", "\n", "\r", '"', "'"], ' ', $v);
                    $clean[] = $v;
                }
                fwrite($out, implode("\t", $clean) . "\n");
            }
            fclose($out);
        }, 'moulinette_result.txt');
    }

    /** ---------- Helpers (mergeRows, normalize, readFlexible, etc.) ---------- */
    private function mergeRows(?array $all, ?array $noexp): array
    {
        $row = [];

        foreach ($this->columns as $col) {
            $vAll = $all[$col] ?? '';
            $vNoexp = $noexp[$col] ?? '';

            $vAll = str_replace(['"', "'"], '', trim($vAll));
            $vNoexp = str_replace(['"', "'"], '', trim($vNoexp));

            // shipping / address -> prefer NOEXP
            $shippingCols = [
                'recipient-name','ship-address-1','ship-address-2','ship-address-3',
                'ship-city','ship-state','ship-postal-code','ship-country',
                'ship-phone-number','delivery-start-date','delivery-end-date',
                'delivery-time-zone','delivery-instructions'
            ];

            if (in_array($col, $shippingCols, true)) {
                $row[$col] = $vNoexp !== '' ? $vNoexp : $vAll;
                continue;
            }

            // price columns
            if (in_array($col, $this->priceColumns, true)) {
                $nAll = $this->normalizeNumeric($vAll);
                $nNoexp = $this->normalizeNumeric($vNoexp);

                if ($nAll !== null) $row[$col] = $nAll;
                elseif ($nNoexp !== null) $row[$col] = $nNoexp;
                else $row[$col] = $vAll !== '' ? $vAll : $vNoexp;

                continue;
            }

            // default: take ALL (product line)
            $row[$col] = $vAll !== '' ? $vAll : $vNoexp;
        }

        return $row;
    }

    private function normalizeNumeric($val)
    {
        if ($val === '' || $val === null) return null;

        $val = trim((string)$val);
        $val = str_replace(["\xc2\xa0", ' '], '', $val);

        // If commas present and are thousands separators -> remove them
        if (strpos($val, ',') !== false && substr_count($val, ',') > 0 && substr_count($val, '.') <= 1) {
            // Heuristic: if there is both comma and dot and comma after dot? keep as needed
            // Simpler approach: remove spaces, then if multiple dots remove them, remove commas if they look like thousands
        }

        // remove non numeric except . and -
        $val = str_replace(',', '.', $val);
        // If still not numeric, try removing everything except digits and dot and minus
        if (!is_numeric($val)) {
            $valClean = preg_replace('/[^0-9\.\-]/', '', $val);
            if ($valClean === '') return null;
            $val = $valClean;
        }

        if (!is_numeric($val)) return null;

        // Format: if integer-like return integer string, else float string
        if ((float)$val == (int)$val) return (string)((int)$val);
        return (string)((float)$val);
    }

    private function readAndNormalize(string $path, bool $isAll): array
    {
        $rawRows = $this->readFlexible($path);
        $res = [];
        foreach ($rawRows as $r) $res[] = $this->normalizeRawRowToStandard($r, $isAll);
        return $res;
    }

    private function readFlexible(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) return [];

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', $content);
        $header = null;
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            // try TSV first
            $cols = str_getcsv($line, "\t");
            if (count($cols) <= 1) {
                $cols2 = str_getcsv($line, ';');
                $cols = count($cols2) > 1 ? $cols2 : str_getcsv($line, ',');
            }

            if ($header === null) {
                $header = array_map('trim', $cols);
                continue;
            }

            if (count($cols) < count($header)) $cols = array_pad($cols, count($header), '');
            $rows[] = array_combine($header, $cols);
        }

        return $rows;
    }

    private function normalizeRawRowToStandard(array $raw, bool $isAll): array
    {
        $aliases = [
            'order-id' => ['order-id','amazon-order-id'],
            'order-item-id' => ['order-item-id','merchant-order-id'],
            'purchase-date' => ['purchase-date'],
            'payments-date' => ['payments-date','last-updated-date','reporting-date'],
            'buyer-email' => ['buyer-email'],
            'buyer-name' => ['buyer-name'],
            'buyer-phone-number' => ['buyer-phone-number'],
            'sku' => ['sku'],
            'product-name' => ['product-name'],
            'quantity-purchased' => ['quantity-purchased','quantity','number-of-items'],
            'currency' => ['currency'],
            'item-price' => ['item-price','item-subtotal','item-subtotal-amount'],
            'item-tax' => ['item-tax','item-tax-amount'],
            'shipping-price' => ['shipping-price','shipping-subtotal','shipping-subtotal-amount'],
            'shipping-tax' => ['shipping-tax','shipping-subtotal-tax'],
            'ship-service-level' => ['ship-service-level'],
            'recipient-name' => ['recipient-name'],
            'ship-address-1' => ['ship-address-1','actual-ship-from-address-field-1','actual-ship-from-address-name'],
            'ship-address-2' => ['ship-address-2','actual-ship-from-address-field-2'],
            'ship-address-3' => ['ship-address-3','actual-ship-from-address-field-3'],
            'ship-city' => ['ship-city','actual-ship-from-city'],
            'ship-state' => ['ship-state','actual-ship-from-state'],
            'ship-postal-code' => ['ship-postal-code','actual-ship-from-postal-code'],
            'ship-country' => ['ship-country','actual-ship-from-country'],
            'ship-phone-number' => ['ship-phone-number'],
            'delivery-start-date' => ['delivery-start-date','promise-date'],
            'delivery-end-date' => ['delivery-end-date'],
            'delivery-time-zone' => ['delivery-time-zone'],
            'delivery-instructions' => ['delivery-instructions'],
            'sales-channel' => ['sales-channel','order-channel'],
            'is-business-order' => ['is-business-order'],
            'purchase-order-number' => ['purchase-order-number'],
            'price-designation' => ['price-designation'],
            'is-amazon-invoiced' => ['is-amazon-invoiced'],
            'vat-exclusive-item-price' => ['vat-exclusive-item-price'],
            'vat-exclusive-shipping-price' => ['vat-exclusive-shipping-price'],
            'vat-exclusive-giftwrap-price' => ['vat-exclusive-giftwrap-price'],
        ];

        $out = [];
        foreach ($this->columns as $col) {
            $out[$col] = '';
            foreach ($aliases[$col] ?? [] as $alias) {
                if (isset($raw[$alias]) && trim($raw[$alias]) !== '') {
                    $out[$col] = str_replace(['"', "'"], '', trim($raw[$alias]));
                    break;
                }
            }
        }
        return $out;
    }

    /** Extract 13-digit EAN at start of string or return null */
    private function extractEan($val)
    {
        if ($val === null) return null;
        $s = trim((string)$val);
        if (preg_match('/^\d{13}/', $s, $m)) return $m[0];
        // also handle if raw is exactly 13-digit
        if (preg_match('/\d{13}/', $s, $m2)) return $m2[0];
        return null;
    }

    /** Bucket quantity according to thresholds used in your Python scripts */
    private function bucketQuantity($qty)
    {
        $qty = intval($qty);
        if ($qty <= 0) return 0;
        if ($qty >= 1 && $qty <= 5) return 1;
        if ($qty >= 6 && $qty <= 10) return 3;
        if ($qty >= 11 && $qty <= 20) return 5;
        if ($qty >= 21 && $qty <= 50) return 10;
        if ($qty >= 51) return 20;
        return $qty;
    }
}
        