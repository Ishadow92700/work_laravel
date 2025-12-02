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

Route::prefix('sftp')->name('sftp.')->group(function () {
    Route::get('/dashboard', [SftpController::class, 'dashboard'])->name('dashboard');
    Route::get('/import', [SftpController::class, 'import'])->name('import');   // <- important
    Route::get('/export', [SftpController::class, 'export'])->name('export');   // <- important
});

use App\Http\Controllers\OrderProcessorController;

Route::get('/', [OrderProcessorController::class, 'index'])->name('upload.form');
Route::post('/process', [OrderProcessorController::class, 'process'])->name('upload.process');
Route::get('/download/{filename}', [OrderProcessorController::class, 'download'])->name('upload.download');

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
