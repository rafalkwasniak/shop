<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Support\Renderable;

class DashboardController extends Controller
{
    public function __invoke(): Renderable
    {
        return view('seller.dashboard');
    }
}
