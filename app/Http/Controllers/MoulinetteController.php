<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MoulinetteController extends Controller
{
    // Liste finale des colonnes attendues dans la sortie
    public $columns = [
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

    // Colonnes considérées comme des montants (à normaliser)
    private $priceColumns = [
        'item-price','item-tax','shipping-price','shipping-tax',
        'vat-exclusive-item-price','vat-exclusive-shipping-price','vat-exclusive-giftwrap-price'
    ];

    // Affiche la vue d'upload / interface
    public function index()
    {
        return view('moulinette');
    }

    // Point d'entrée principal : reçoit les deux fichiers, fusionne et stocke le résultat en session
    public function process(Request $request)
    {
        $fileAll   = $request->file('file_all');
        $fileNoexp = $request->file('file_noexp');

        // Si un des fichiers manque, retourne avec erreur
        if (!$fileAll || !$fileNoexp) {
            return back()->with('error', 'Les deux fichiers sont requis.');
        }

        // Lit et normalise les deux fichiers en tableaux standardisés
        $allRows   = $this->readAndNormalize($fileAll->getRealPath(), true);
        $noexpRows = $this->readAndNormalize($fileNoexp->getRealPath(), false);

        /** INDEX ALL ROWS BY ORDER-ID */
        $mapAll = [];
        foreach ($allRows as $r) {
            $oid = strtolower(trim($r['order-id'] ?? ''));
            if ($oid !== '') $mapAll[$oid][] = $r;
        }

        /** TAKE FIRST NOEXP ONLY for each order-id */
        // On ne garde qu'une seule ligne NOEXP par order-id (la première trouvée)
        $mapNoexp = [];
        foreach ($noexpRows as $r) {
            $oid = strtolower(trim($r['order-id'] ?? ''));
            if ($oid !== '' && !isset($mapNoexp[$oid])) {
                $mapNoexp[$oid] = $r;
            }
        }

        /** BUILD RESULT */
        $result = [];
        foreach ($mapNoexp as $oid => $rNoexp) {

            $matchingAll = $mapAll[$oid] ?? [];

            // Si aucune ligne ALL correspondante, on merge NOEXP seul
            if (empty($matchingAll)) {
                $merged = $this->mergeRows(null, $rNoexp);
                if (floatval($merged['item-price']) > 0) $result[] = $merged;
                continue;
            }

            // Sinon on fusionne chaque ligne ALL avec la NOEXP correspondante
            foreach ($matchingAll as $rAll) {
                $merged = $this->mergeRows($rAll, $rNoexp);

                // Ignore les lignes sans prix (taxes / shipping / ajustements)
                if (floatval($merged['item-price']) == 0) continue;

                $result[] = $merged;
            }
        }

        // Stocke le résultat et les colonnes en session pour téléchargement ultérieur
        Session::put('yoyoamaz_result', $result);
        Session::put('yoyoamaz_columns', $this->columns);

        return back()->with('success', 'Fusion terminée, fichier prêt.');
    }

    // Fusion logique : priorités et nettoyages appliqués pour chaque colonne
    private function mergeRows(?array $all, ?array $noexp): array
    {
        $row = [];

        foreach ($this->columns as $col) {
            
            $vAll = $all[$col] ?? '';
            $vNoexp = $noexp[$col] ?? '';

            // Nettoyage basique des quotes et trimming
            $vAll = str_replace(['"', "'"], '', trim($vAll));
            $vNoexp = str_replace(['"', "'"], '', trim($vNoexp));

            // Nettoyage renforcé pour le nom du produit (retire caractères indésirables)
            if ($col === 'product-name') {
                $vAll   = preg_replace('/[^a-zA-Z0-9 \-\_\(\)\[\]\p{L}]/u', '', $vAll);
                $vNoexp = preg_replace('/[^a-zA-Z0-9 \-\_\(\)\[\]\p{L}]/u', '', $vNoexp);
            }

            // Colonnes liées à la livraison / adresse : priorité à NOEXP (infos d'expédition)
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

            // Colonnes prix : tenter de normaliser en nombre propre
            if (in_array($col, $this->priceColumns, true)) {
                $nAll = $this->normalizeNumeric($vAll);
                $nNoexp = $this->normalizeNumeric($vNoexp);

                if ($nAll !== null) $row[$col] = $nAll;
                elseif ($nNoexp !== null) $row[$col] = $nNoexp;
                else $row[$col] = $vAll !== '' ? $vAll : $vNoexp;

                continue;
            }

            // Par défaut : prendre la valeur ALL si présente, sinon NOEXP
            $row[$col] = $vAll !== '' ? $vAll : $vNoexp;
        }

        return $row;
    }

    // Normalise un champ numérique : supprime espaces, séparateurs milliers, etc.
    private function normalizeNumeric($val)
    {
        if ($val === '' || $val === null) return null;

        // Supprime espaces insécables et espaces normaux
        $val = str_replace(["\xc2\xa0", ' '], '', trim($val));

        // Si virgules présentes, on les enlève (souvent séparateur milliers)
        if (strpos($val, ',') !== false) {
            $val = str_replace(',', '', $val);
        }

        // Si plusieurs points => points milliers, on les retire
        $dotCount = substr_count($val, '.');

        if ($dotCount > 1) {
            $val = str_replace('.', '', $val);
        }

        // Vérifie que le résultat est bien numérique
        if (!is_numeric($val)) return null;

        // Retourne sous forme de string propre (entier si possible)
        if ((float)$val == (int)$val) return (string)((int)$val);
        return (string)((float)$val);
    }

    // Lit et normalise un fichier en tableau standard (utilise readFlexible puis mapping)
    private function readAndNormalize(string $path, bool $isAll): array
    {
        $rawRows = $this->readFlexible($path);
        $res = [];
        foreach ($rawRows as $r) $res[] = $this->normalizeRawRowToStandard($r, $isAll);
        return $res;
    }

    // Lecture flexible : tente tab, puis ; puis , — retourne tableau associatif par ligne
    private function readFlexible(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) return [];

        // Supprime BOM éventuel (UTF-8 BOM)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split('/\r\n|\n|\r/', $content);

        $header = null;
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') continue;

            // Essaye tabulation
            $cols = str_getcsv($line, "\t");
            // Si une seule colonne, teste ; puis ,
            if (count($cols) <= 1) {
                $cols2 = str_getcsv($line, ';');
                $cols = count($cols2) > 1 ? $cols2 : str_getcsv($line, ',');
            }

            // Première ligne = header
            if ($header === null) {
                $header = array_map('trim', $cols);
                continue;
            }

            // Si la ligne a moins de colonnes, on complète avec des vides
            if (count($cols) < count($header)) $cols = array_pad($cols, count($header), '');

            // Combine header + valeurs en tableau associatif
            $rows[] = array_combine($header, $cols);
        }

        return $rows;
    }

    // Transforme une ligne brute (avec noms d'en-têtes variables) en format standard
    private function normalizeRawRowToStandard(array $raw, bool $isAll): array
    {
        // Alias possibles pour chaque colonne (pour couvrir différents exports)
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

        // Pour chaque colonne attendue, on cherche la première alias présente dans la ligne brute
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

    // Fournit le téléchargement du résultat stocké en session (tab-separated)
    public function download()
    {
        $result = Session::get('yoyoamaz_result');
        $columns = Session::get('yoyoamaz_columns');

        return response()->streamDownload(function () use ($result, $columns) {
            $out = fopen('php://output', 'w');

            // Écrit l'en-tête (sans guillemets)
            fwrite($out, implode("\t", $columns) . "\n");

            // Écrit chaque ligne en échappant tab/newline/quotes
            foreach ($result as $row) {
                $clean = [];
                foreach ($columns as $c) {
                    $v = $row[$c] ?? '';
                    $v = str_replace(["\t", "\n", "\r", '"', "'"], ' ', $v); // sécurité
                    $clean[] = $v;
                }
                fwrite($out, implode("\t", $clean) . "\n");
            }

            fclose($out);
        }, 'yoyoamaz.txt');
    }

}
