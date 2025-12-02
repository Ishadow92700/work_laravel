<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ArticleController extends Controller
{
    public function show($n): View
    {
        return view('article')->with('numero', $n);
    }
}
