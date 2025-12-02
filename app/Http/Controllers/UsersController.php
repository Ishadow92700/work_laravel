<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function create(): View
    {
        return view('info');
    }

    public function store(Request $request): string
    {
        return 'Votre nom est ' . $request->input('nom');
    }
}