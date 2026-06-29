<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ForumCategoryResource;
use App\Models\ForumCategory;

class ForumCategoryController extends Controller
{
    public function index()
    {
        return ForumCategoryResource::collection(ForumCategory::orderBy('name')->get());
    }
}
