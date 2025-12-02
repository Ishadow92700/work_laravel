<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\OrderProcessor;

class OrderProcessorController extends Controller
{
    public function index()
    {
        return view('upload');
    }

    public function process(Request $request)
    {
        $request->validate([
            'file_all' => 'required|file',
            'file_noexp' => 'required|file',
        ]);

        // stocker temporairement
        $pathAll = $request->file('file_all')->store('uploads');
        $pathNoExp = $request->file('file_noexp')->store('uploads');

        $processor = new OrderProcessor();
        $result = $processor->run(storage_path('app/' . $pathAll), storage_path('app/' . $pathNoExp));

        if ($result['success']) {
            // result.filepath is absolute path in storage/app/processed
            $fileForDownload = basename($result['filepath']);
            return view('upload', [
                'log' => $result['log'],
                'success' => true,
                'download' => route('upload.download', ['filename' => $fileForDownload])
            ]);
        }

        return view('upload', [
            'log' => $result['log'],
            'success' => false,
            'error' => $result['error'] ?? 'Erreur inconnue'
        ]);
    }

    public function download($filename)
    {
        $path = storage_path('app/processed/' . $filename);
        if (!file_exists($path)) abort(404);
        return response()->download($path, $filename, ['Content-Type' => 'text/plain']);
    }
}
