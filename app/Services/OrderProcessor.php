<?php
namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Str;

class OrderProcessor
{
    protected $log = [];

    public function run(string $pathAll, string $pathNoExp): array
    {
        try {
            $this->log[] = "Chargement ALL: $pathAll";
            $all = $this->loadFile($pathAll);
            $this->log[] = "Chargement NO EXP: $pathNoExp";
            $noexp = $this->loadFile($pathNoExp);

            $this->log[] = "Indexation des fichiers...";
            $indices = $this->buildIndices($all, $noexp);

            $this->log[] = "Application des règles et génération de la sortie TXT tabulé...";
            $outputLines = $this->buildOutput($noexp, $indices, $all);

            // créer dossier processed
            $dir = storage_path('app/processed');
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $filename = 'a_envoyer_' . date('Ymd_His') . '.txt';
            $filepath = $dir . '/' . $filename;

            // écriture TSV (tabulation)
            $fh = fopen($filepath, 'w');
            foreach ($outputLines as $line) {
                // chaque $line est un array of values
                fputcsv($fh, $line, "\t");
            }
            fclose($fh);

            $this->log[] = "Fichier généré : $filepath";

            return ['success' => true, 'filepath' => $filename, 'log' => $this->log];
        } catch (\Exception $e) {
            $this->log[] = "Erreur : " . $e->getMessage();
            return ['success' => false, 'error' => $e->getMessage(), 'log' => $this->log];
        }
    }

    protected function loadFile(string $path): array
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        // supporte txt/tab, csv, xlsx, xls
        if (in_array($ext, ['xls','xlsx','csv'])) {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
            // première ligne = headers
            $headers = array_map(function($h){ return $this->normalizeHeader($h); }, array_values($rows[1]));
            $data = [];
            foreach ($rows as $rindex => $row) {
                if ($rindex == 1) continue;
                $assoc = [];
                $i = 0;
                foreach ($row as $cell) {
                    $assoc[$headers[$i]] = is_null($cell) ? '' : trim((string)$cell);
                    $i++;
                }
                // skip empty rows
                if ($this->isEmptyRow($assoc)) continue;
                $data[] = $assoc;
            }
            return ['headers' => $headers, 'rows' => $data];
        } else {
            // txt / tab-separated or csv autodetect
            $content = file_get_contents($path);
            // detect delimiter: tab or semicolon or comma
            $firstLine = strtok($content, "\n");
            $delimiter = (strpos($firstLine, "\t") !== false) ? "\t" : ((strpos($firstLine, ';') !== false) ? ';' : ',');
            $lines = array_map('rtrim', explode("\n", $content));
            $headers = str_getcsv(array_shift($lines), $delimiter);
            $headers = array_map([$this, 'normalizeHeader'], $headers);
            $data = [];
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $cells = str_getcsv($line, $delimiter);
                $assoc = [];
                foreach ($headers as $i => $h) $assoc[$h] = isset($cells[$i]) ? trim($cells[$i]) : '';
                if ($this->isEmptyRow($assoc)) continue;
                $data[] = $assoc;
            }
            return ['headers' => $headers, 'rows' => $data];
        }
    }

    protected function normalizeHeader($h)
    {
        // rendre les en-têtes comparables (minuscule, sans espaces, sans accents)
        $h = (string)$h;
        $h = trim($h);
        $h = Str::ascii($h); // supprime accents
        $h = strtolower($h);
        $h = str_replace([' ', '-', '__', '/'], '_', $h);
        $h = preg_replace('/[^a-z0-9_]/', '', $h);
        return $h;
    }

    protected function isEmptyRow(array $row)
    {
        foreach ($row as $v) if ($v !== '' && $v !== null) return false;
        return true;
    }

    protected function buildIndices($all, $noexp)
    {
        $idx = [
            'all_order_id' => [],
            'all_item_id' => [],
            'all_colL' => [],
            'noexp_order_id' => [],
            'noexp_item_id' => []
        ];

        // essayer détecter le nom réel de la colonne order-id / amazon-order-id
        $allHeaders = $all['headers'];
        $noexpHeaders = $noexp['headers'];

        $orderIdNamesAll = $this->possibleNames(['amazon_order_id','order_id','orderid','order-id','order_id']);
        $orderIdNamesNoexp = $this->possibleNames(['order-id','orderid','order_id','amazon_order_id']);

        // colonne L de ALL -> on ne sait pas le header exact. On va utiliser l'index 11 (L=12ème colonne) si disponible,
        // sinon tenter un nom probable (order_item_id, merchant_order_id, etc.)
        $colLHeader = $allHeaders[11] ?? null; // index 11 = 12ème colonne
        if (!$colLHeader) {
            // fallback : chercher header contenant 'item' ou 'order_item'
            foreach ($allHeaders as $h) {
                if (strpos($h, 'item') !== false || strpos($h, 'sku') !== false) { $colLHeader = $h; break; }
            }
        }

        // indexer ALL par order-id et par order-item-id et par colL
        foreach ($all['rows'] as $row) {
            // tenter trouver order-id parmi headers
            $orderIdKey = $this->findFirstKey($row, $orderIdNamesAll);
            $itemIdKey = $this->findFirstKey($row, $this->possibleNames(['order_item_id','orderitemid','orderitem','order_itemid','merchant_order_id','merchant_orderid']));
            if ($orderIdKey) {
                $idx['all_order_id'][$row[$orderIdKey]][] = $row;
            }
            if ($itemIdKey) {
                $idx['all_item_id'][$row[$itemIdKey]][] = $row;
            }
            if ($colLHeader && isset($row[$colLHeader])) {
                $idx['all_colL'][$row[$colLHeader]][] = $row;
            }
        }

        // indexer NO EXP similarly
        foreach ($noexp['rows'] as $row) {
            $orderIdKey = $this->findFirstKey($row, $orderIdNamesNoexp);
            $itemIdKey = $this->findFirstKey($row, $this->possibleNames(['order_item_id','orderitemid','orderitem','order_itemid','item_id']));
            if ($orderIdKey) $idx['noexp_order_id'][$row[$orderIdKey]][] = $row;
            if ($itemIdKey) $idx['noexp_item_id'][$row[$itemIdKey]][] = $row;
        }

        return array_merge($idx, [
            'all_headers' => $allHeaders,
            'noexp_headers' => $noexpHeaders,
            'colLHeader' => $colLHeader
        ]);
    }

    protected function possibleNames(array $names)
    {
        return array_map(function($n){ return $this->normalizeHeader($n); }, $names);
    }

    protected function findFirstKey(array $row, array $candidates)
    {
        foreach ($candidates as $c) {
            if (array_key_exists($c, $row)) return $c;
        }
        return null;
    }

    /**
     * Reproduit les RECHERCHEV que tu as listées :
     * - on itère chacune des lignes de NO EXP, on récupère les colonnes demandées dans ALL ou NOEXP
     */
    protected function buildOutput($noexp, $indices, $all)
    {
        $out = [];

        // Construire l'entête finale : utiliser l'entête du fichier "résultat" que tu m'as envoyé comme modèle.
        // Ici on génère une entête basée sur l'exemple; adapte si besoin.
        $header = [
            'order-id','order-item-id','purchase-date','payments-date','buyer-email','buyer-name','buyer-phone-number',
            'sku','product-name','quantity-purchased','currency','item-price','item-tax','shipping-price','shipping-tax',
            'ship-service-level','recipient-name','ship-address-1','ship-address-2','ship-address-3','ship-city','ship-state',
            'ship-postal-code','ship-country','ship-phone-number','delivery-start-date','delivery-end-date','delivery-time-zone',
            'delivery-Instructions','sales-channel','is-business-order','purchase-order-number','price-designation','is-amazon-invoiced',
            'vat-exclusive-item-price','vat-exclusive-shipping-price','vat-exclusive-giftwrap-price'
        ];
        $out[] = $header;

        // detect keys in noexp rows for order-id and item-id
        foreach ($noexp['rows'] as $i => $row) {
            $this->log[] = "Traitement ligne NO EXP #" . ($i+1);
            // trouver order-id et item-id
            $orderIdKey = $this->findFirstKey($row, $this->possibleNames(['order-id','orderid','order_id','amazon_order_id']));
            $itemIdKey = $this->findFirstKey($row, $this->possibleNames(['order_item_id','orderitemid','order_itemid','item_id']));

            $orderId = $orderIdKey ? $row[$orderIdKey] : '';
            $itemId = $itemIdKey ? $row[$itemIdKey] : '';

            // appliquons les filtres: si NO EXP contient pending=true ou cancelled etc -> ne pas envoyer
            $block = $this->shouldBlockRow($row);

            if ($block) {
                $this->log[] = "Exclusion order-id={$orderId} (statut bloquant)";
                continue;
            }

            // now fill output columns using RECHERCHEV rules
            // for each output header, map to source values (first check NOEXP row, then ALL index)
            $outRow = [];
            foreach ($header as $col) {
                $value = $this->fetchValueForColumn($col, $row, $orderId, $itemId, $indices, $all);
                $outRow[] = $value;
            }

            $out[] = $outRow;
        }

        return $out;
    }

    protected function shouldBlockRow(array $row)
    {
        // Règles d'exclusion basiques (telles que décrites) :
        // if any of these fields (normalized) exist and are indicative => block
        $blockFields = ['pending','cancelled','cancel','verge_of_cancellation','verge_of_lateshipment','buyer_requested_cancel','buyer-requested-cancel'];
        foreach ($blockFields as $f) {
            $n = $this->normalizeHeader($f);
            if (isset($row[$n])) {
                $v = strtolower($row[$n]);
                if (in_array($v, ['true','1','yes'])) return true;
            }
        }

        // also if quantity_to_ship == 0 or quantity_shipped > 0? (based on your NO EXP)
        if (isset($row['quantity_to_ship']) && $row['quantity_to_ship'] === '0') return true;

        // else allow
        return false;
    }

    protected function fetchValueForColumn($col, $noexpRow, $orderId, $itemId, $indices, $all)
    {
        // implement mapping according to your RECHERCHEV examples.
        // Important: we try in this order:
        // 1) If column exists in NOEXP row, use it
        // 2) Else lookup in ALL by order-id
        // 3) Else lookup in ALL by item-id
        // 4) Else lookup in ALL by colL key if available
        $colNorm = $this->normalizeHeader($col);

        // direct from NOEXP if present
        if (array_key_exists($colNorm, $noexpRow) && $noexpRow[$colNorm] !== '') {
            return $noexpRow[$colNorm];
        }

        // try in ALL by order-id
        if ($orderId && isset($indices['all_order_id'][$orderId])) {
            // take first match
            $row = $indices['all_order_id'][$orderId][0];
            if (isset($row[$colNorm]) && $row[$colNorm] !== '') return $row[$colNorm];
        }

        // try ALL by item-id
        if ($itemId && isset($indices['all_item_id'][$itemId])) {
            $row = $indices['all_item_id'][$itemId][0];
            if (isset($row[$colNorm]) && $row[$colNorm] !== '') return $row[$colNorm];
        }

        // try ALL by colL (clé secondaire) when present
        if (!empty($indices['colLHeader']) && isset($noexpRow[$indices['colLHeader']])) {
            $key = $noexpRow[$indices['colLHeader']];
            if ($key && isset($indices['all_colL'][$key])) {
                $row = $indices['all_colL'][$key][0];
                if (isset($row[$colNorm]) && $row[$colNorm] !== '') return $row[$colNorm];
            }
        }

        // fallback empty
        return '';
    }
}
