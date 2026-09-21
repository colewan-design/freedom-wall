<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IdCheckController extends Controller
{
    /**
     * Suggestions only — the field stays free text so nobody is locked out.
     */
    private const COURSES = [
        'BS Information Technology',
        'BS Computer Science',
        'BS Computer Engineering',
        'BS Civil Engineering',
        'BS Electrical Engineering',
        'BS Mechanical Engineering',
        'BS Electronics Engineering',
        'BS Industrial Engineering',
        'BS Architecture',
        'BS Accountancy',
        'BS Business Administration',
        'BS Hospitality Management',
        'BS Tourism Management',
        'BS Criminology',
        'BS Nursing',
        'BS Psychology',
        'BS Medical Technology',
        'BS Agriculture',
        'BS Fisheries',
        'BS Marine Engineering',
        'BS Office Administration',
        'BS Entrepreneurship',
        'BS Food Technology',
        'BS Environmental Science',
        'BS Mathematics',
        'BS Biology',
        'BSED English',
        'BSED Mathematics',
        'BSED Science',
        'BEED General Education',
        'BS Public Administration',
        'BA Communication',
        'Senior High — STEM',
        'Senior High — ABM',
        'Senior High — HUMSS',
        'Senior High — TVL',
    ];

    private const CAMPUSES = [
        'Alangilan',
        'Pablo Borbon',
        'Lipa',
        'Nasugbu',
        'Malvar',
        'Balayan',
        'Lemery',
        'Rosario',
        'San Juan',
        'Lobo',
        'Mabini',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isStudent = $user?->role === 'student';

        return Inertia::render('IdCheck/Index', [
            'courses' => self::COURSES,
            'campuses' => self::CAMPUSES,
            'defaults' => [
                'name' => $isStudent ? $user->name : '',
                'handle' => $isStudent ? '@'.$user->username : '',
            ],
            'caption' => "BSUkol ID CHECK 🪪 add yours!\n\n#BSUkol #IDCheck #BSUFreedomWall",
        ]);
    }
}
