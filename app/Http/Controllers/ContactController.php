<?php 

namespace App\Http\Controllers;

use Illuminate\View\View;
use App\Http\Requests\ContactRequest;
use Illuminate\Support\Facades\Mail;
use App\Mail\Contact;

class ContactController extends Controller
{
    public function create(): View
    {
        return view('contact');
    }

    public function store(ContactRequest $request): View
    {
        Mail::to('administrateur@chezmoi.com')
            ->queue(new Contact());

        return view('confirm');
    }
}