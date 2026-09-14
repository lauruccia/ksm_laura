<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Contracts\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('pages.plans.index', [
            'plans' => Plan::active()->byRank()->get(),
        ]);
    }
}
