<?php

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Area dell'inserzionista esterno: le sue campagne, con statistiche e scadenze. */
class AdvertiserAreaController extends Controller
{
    public function index(Request $request): View
    {
        $advertiser = $request->user()->advertiser;

        return view('advertiser.dashboard', [
            'advertiser' => $advertiser,
            'campaigns' => $advertiser->advertisements()->with('advertiser')->orderByDesc('id')->get(),
        ]);
    }

    public function show(Request $request, Advertisement $advertisement): View
    {
        // Le campagne degli altri non esistono, per chi guarda da qui.
        abort_unless($advertisement->advertiser_id === $request->user()->advertiser->id, 404);

        return view('advertiser.campaign', [
            'campaign' => $advertisement->load('advertiser'),
            'stats' => $advertisement->recentStats(),
        ]);
    }
}
