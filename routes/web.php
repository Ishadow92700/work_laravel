<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/', function() {
return 'Je suis '.'<br />'.'Oussad Ilhan !!!';
})->name('home');
Route::get('{n}', function($n) {
return 'Je suis la page ' . $n . ' !';
})->name('page');
Route::get('contact', function() {
return "C'est moi le contact.";
})->name('contact');
Route::get('{n}', function($n) {
    return 'Je suis la page ' . $n . ' !';
})->name('page');
Route::get('contact', function() {
    return "C'est moi le contact.";
})->name('contact');Route::get('/', function() {
return 'Je suis '.'<br />'.'Oussad Ilhan !!!';
})->name('home');
Route::get('{n}', function($n) {
return 'Je suis la page ' . $n . ' !';
})->name('page');
Route::get('contact', function() {
return "C'est moi le contact.";
})->name('contact');
Route::get('{n}', function($n) {
    return 'Je suis la page ' . $n . ' !';
})->name('page');
Route::get('contact', function() {
    return "C'est moi le contact.";
})->name('contact');