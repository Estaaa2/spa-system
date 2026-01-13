<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Middleware;

// #[Middleware('auth')]
class PostController extends Controller
{
    // #[Middleware('permission:create posts')]
    public function create() {}

    // #[Middleware('permission:create posts')]
    public function store(Request $request) {}

    // #[Middleware('permission:edit posts')]
    public function edit() {}

    // #[Middleware('permission:edit posts')]
    public function update(Request $request) {}

    // #[Middleware('permission:delete posts')]
    public function destroy() {}
}

