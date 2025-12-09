<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\SftpController;

/*Route::get('/', function () {
    return 'Je suis <br /> Oussad Ilhan !!!';
})->name('home');

Route::get('contact', function() {
    return "C'est moi le contact.";
})->name('contact');
Route::get('{n}', function($n) {
    return 'Je suis la page ' . $n . ' !';
})->name('page');
Route::get('test', function () {
    return 'un test';
})->name('test');
Route::get('test', function () {
    return ['un', 'deux', 'trois'];
});
Route::get('test', function () {
    return response('un test', 206)->header('Content-Type', 'text/plain');
});

Route::get('/', function() {
    return view('vue1');
});

Route::get('article/{n}', function($n) {
    return back();
})->where('n', '[0-9]+');
*/
Route::get('facture/{n}', function($n) {
    return redirect()->route('action');
})->where('n', '[0-9]+');

Route::get('test', function () {
    return response('un test', 206)->header('Content-Type', 'text/plain');
});

Route::get('users/action', function() {
    return view('users.action');
})->name('action');

Route::get('/', function () {
    return view('welcome');
});

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Route::get('welcome', [WelcomeController::class, 'index']);

Route::get('article/{n}', [ArticleController::class, 'show'])->where('n', '[0-9]+');

Route::get('users', [UsersController::class, 'create']);
Route::post('users', [UsersController::class, 'store']);

Route::get('contact', [ContactController::class, 'create']);
Route::post('contact', [ContactController::class, 'store']);


//Route::get('/', [WelcomeController::class, 'index']);

Route::get('/test-contact', function () {
    $contact = [
        'nom' => 'Dupont',
        'email' => 'dupont@example.com',
        'message' => 'Ceci est un test.'
    ];

    return view('emails.contact', compact('contact'));
});

Route::get('/photo', [PhotoController::class, 'create']);
Route::post('/photo', [PhotoController::class, 'store']);

/*
Route::get('/action', function () {
    return view('users.action');
});
*/
Route::get('/action', function () {
    return view('users.action');
});

Route::get('/testsftp', function () {
    try {
        Storage::disk('sftp')->put('test.txt', 'Ceci est un test');
        return "Connexion SFTP OK, fichier test.txt envoyé !";
    } catch (\Exception $e) {
        return "Erreur de connexion SFTP : " . $e->getMessage();
    }
});/*
use App\Http\Controllers\SftpController;

Route::get('/sftp/send-csv', [SftpController::class, 'sendCsv']);


use App\Http\Controllers\SftpController;

Route::get('/sftp-last-csv', [SftpController::class, 'showLastCsv']);
*/

/*Route::prefix('sftp')->name('sftp.')->group(function () {
    Route::get('/dashboard', [SftpController::class, 'dashboard'])->name('dashboard');
    Route::get('/import', [SftpController::class, 'import'])->name('import');   // <- important
    Route::get('/export', [SftpController::class, 'export'])->name('export');   // <- important
});
*/
Route::post('/sftp/upload', [SftpController::class, 'upload'])->name('sftp.upload');
Route::post('/sftp/export', [SftpController::class, 'export'])->name('sftp.export');
Route::get('/sftp/download', [SftpController::class, 'download'])->name('sftp.download');
Route::get('/sftp/dashboard', [SftpController::class, 'dashboard']);

use App\Http\Controllers\OrderProcessorController;

Route::get('/', [OrderProcessorController::class, 'index'])->name('upload.form');
Route::post('/process', [OrderProcessorController::class, 'process'])->name('upload.process');


use App\Http\Controllers\FileProcessController;

Route::get('/file-process', [FileProcessController::class, 'showForm']);
Route::post('/file-process', [FileProcessController::class, 'process'])->name('file.process');


use App\Http\Controllers\MoulinetteController;

Route::get('/moulinette', [MoulinetteController::class, 'index']);
Route::post('/moulinette/process', [MoulinetteController::class, 'process']);
Route::get('/moulinette/download', [MoulinetteController::class, 'download']);

use App\Http\Controllers\NoticeController;

Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');

Route::match(['get','post'], '/notices/import', [NoticeController::class, 'import'])->name('notices.import');

use App\Http\Controllers\NoticeController2;

Route::get('/import-notices', [NoticeController2::class, 'import']);

Route::get('/test-db-config', function() {
    return [
        'DB_HOST' => env('DB_HOST'),
        'DB_DATABASE' => env('DB_DATABASE'),
        'DB_USERNAME' => env('DB_USERNAME'),
    ];
});

Route::get('/notices/export', [NoticeController::class, 'export'])->name('notices.export');

use App\Http\Controllers\AjoutProduitController;

Route::get('/ajout-produits', [AjoutProduitController::class, 'index'])->name('ajout.produits');
Route::post('/ajout-produits', [AjoutProduitController::class, 'traiter'])->name('ajout.produits.traiter');
