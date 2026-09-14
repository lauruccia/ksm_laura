<?php

namespace App\Support;

use App\Models\CmsPage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Navigation
{
    /** Pagine CMS da mostrare in una data posizione: header o footer. */
    public static function pages(string $location): Collection
    {
        return Cache::remember(
            "cms.pages.$location",
            now()->addMinutes(10),
            fn () => CmsPage::query()
                ->published()
                ->inLocation($location)
                ->orderBy('sort_order')
                ->get(['id', 'title', 'slug'])
        );
    }

    public static function flush(): void
    {
        foreach (['header', 'footer'] as $location) {
            Cache::forget("cms.pages.$location");
        }
    }
}
