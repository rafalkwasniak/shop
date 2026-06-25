<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Support\Renderable;

class DashboardController extends Controller
{
    public function __invoke(): Renderable
    {
        return view('administrator.dashboard', [
            'sellersCount' => User::where('role', UserRole::Seller)->count(),
        ]);
    }
}
