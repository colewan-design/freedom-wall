<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Feed/Index', [
            'profile' => $request->user()->only(['name', 'username', 'avatar_url']),
        ]);
    }
}
