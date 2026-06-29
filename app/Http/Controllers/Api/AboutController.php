<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Http\Resources\TeamMemberResource;
use App\Models\Faq;
use App\Models\TeamMember;

class AboutController extends Controller
{
    public function index()
    {
        return response()->json([
            'mission' => 'Menghubungkan setiap siswa Indonesia dengan tutor terbaik, kapan pun dan di mana pun mereka belajar.',
            'vision' => 'Menjadi platform bimbingan belajar online & offline nomor satu di Indonesia.',
            'founded_year' => 2024,
            'team' => TeamMemberResource::collection(TeamMember::orderBy('order')->get()),
            'faqs' => FaqResource::collection(Faq::orderBy('order')->get()),
        ]);
    }
}
