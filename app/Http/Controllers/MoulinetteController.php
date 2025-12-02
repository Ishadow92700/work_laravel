<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MoulinetteController extends Controller
{

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

    private $priceColumns = [
        'item-price','item-tax','shipping-price','shipping-tax',
        'vat-exclusive-item-price','vat-exclusive-shipping-price','vat-exclusive-giftwrap-price'
    ];

    public function index()
    {
        return view('moulinette');
    }

    public function process(Request $request)
    {
        $fileAll   = $request->file('file_all');
        $fileNoexp = $request->file('file_noexp');

        if (!$fileAll || !$fileNoexp) {
            return back()->with('error', 'Les deux fichiers sont requis.');
        }

        $allRows   = $this->readAndNormalize($fileAll->getRealPath(), true);
        $noexpRows = $this->readAndNormalize($fileNoexp->getRealPath(), false);

        /** INDEX ALL ROWS BY ORDER-ID */
        $mapAll = [];
        foreach ($allRows as $r) {
            $oid = strtolower(trim($r['order-id'] ?? ''));
            if ($oid !== '') $mapAll[$oid][] = $r;
        }

        /** TAKE FIRST NOEXP ONLY for each order-id */
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

            if (empty($matchingAll)) {
                $merged = $this->mergeRows(null, $rNoexp);
                if (floatval($merged['item-price']) > 0) $result[] = $merged;
                continue;
            }

            foreach ($matchingAll as $rAll) {
                $merged = $this->mergeRows($rAll, $rNoexp);

                // Ignore non-product lines (tax, shipping-only, adjustments)
                if (floatval($merged['item-price']) == 0) continue;

                $result[] = $merged;
            }
        }

        Session::put('yoyoamaz_result', $result);
        Session::put('yoyoamaz_columns', $this->columns);

        return back()->with('success', 'Fusion terminée, fichier prêt.');
    }

    private function mergeRows(?array $all, ?array $noexp): array
{
    $row = [];

    foreach ($this->columns as $col) {
        
        $vAll = $all[$col] ?? '';
        $vNoexp = $noexp[$col] ?? '';

        // Nettoyage
        $vAll = str_replace(['"', "'"], '', trim($vAll));
        $vNoexp = str_replace(['"', "'"], '', trim($vNoexp));

        // Colonnes shipping / adresse → prendre NOEXP
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

        // Colonnes prix
        if (in_array($col, $this->priceColumns, true)) {
            $nAll = $this->normalizeNumeric($vAll);
            $nNoexp = $this->normalizeNumeric($vNoexp);

            if ($nAll !== null) $row[$col] = $nAll;
            elseif ($nNoexp !== null) $row[$col] = $nNoexp;
            else $row[$col] = $vAll !== '' ? $vAll : $vNoexp;

            continue;
        }

        // Par défaut : prendre la valeur ALL (c'est la ligne produit correcte)
        $row[$col] = $vAll !== '' ? $vAll : $vNoexp;
    }

    return $row;
}


    private function normalizeNumeric($val)
{
    if ($val === '' || $val === null) return null;

    // Nettoyer
    $val = str_replace(["\xc2\xa0", ' '], '', trim($val));

    // Si virgules : elles servent de séparateur de milliers -> on les enlève
    if (strpos($val, ',') !== false) {
        $val = str_replace(',', '', $val);
    }

    // Compter les points
    $dotCount = substr_count($val, '.');

    if ($dotCount > 1) {
        // Plusieurs points = points milliers -> on les retire tous
        $val = str_replace('.', '', $val);
    }

    // À ce stade :
    // - soit il reste 0 point -> entier
    // - soit il reste 1 point -> décimal correct

    // Vérification finale
    if (!is_numeric($val)) return null;

    // Formatage final
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

    public function download()
{
    $result = Session::get('yoyoamaz_result');
    $columns = Session::get('yoyoamaz_columns');

    return response()->streamDownload(function () use ($result, $columns) {
        $out = fopen('php://output', 'w');

        // En-tête SANS guillemets
        fwrite($out, implode("\t", $columns) . "\n");

        // Lignes SANS guillemets
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
