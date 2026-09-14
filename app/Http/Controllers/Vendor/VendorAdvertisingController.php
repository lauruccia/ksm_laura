<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Le campagne banner di un'azienda del sito, nella sua area. */
class VendorAdvertisingController extends Controller
{
    public function index(Request $request): View
    {
        $advertiser = $request->user()->company->advertiser;

        return view('vendor.advertising.index', [
            'advertiser' => $advertiser,
            'campaigns' => $advertiser
                ? $advertiser->advertisements()->with('advertiser')->orderByDesc('id')->get()
                : collect(),
        ]);
    }

    public function show(Request $request, Advertisement $advertisement): View
    {
        $advertiser = $request->user()->company->advertiser;

        abort_unless($advertiser && $advertisement->advertiser_id === $advertiser->id, 404);

        return view('vendor.advertising.show', [
            'campaign' => $advertisement->load('advertiser'),
            'stats' => $advertisement->recentStats(),
        ]);
    }
}
