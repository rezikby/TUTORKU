<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;

class SubjectController extends Controller
{
    public function index()
    {
        return SubjectResource::collection(Subject::orderBy('name')->get());
    }
}
