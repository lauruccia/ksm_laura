<?php

namespace App\Support;

use Illuminate\Http\Request;

class HeaderPresentation
{
    public function resolve(Request $request, ?string $override = null): array
    {
        $host = preg_replace('/^www\./', '', strtolower($request->getHost()));
        $options = config('header.domains', [])[$host] ?? [];
        $domain = app(TenantContext::class)->domain();
        if ($domain) {
            foreach (['variant', 'background', 'color', 'accent', 'tagline', 'subline'] as $key) {
                if ($value = $domain->{'header_'.$key}) {
                    $options[$key] = $value;
                }
            }
        }

        foreach (config('header.pages', []) as $rule) {
            if ((! isset($rule['host']) || $rule['host'] === $host)
                && $request->is($rule['path'])) {
                $options = array_replace($options, $rule);
                break;
            }
        }

        $variant = $override ?? $options['variant'] ?? config('header.default', 'marketplace');
        if (! in_array($variant, ['marketplace', 'shop', 'artisan'], true)) {
            $variant = 'marketplace';
        }

        $styles = [];
        foreach (['background' => '--brand-background', 'color' => '--brand-ink', 'accent' => '--brand-green'] as $key => $variable) {
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $options[$key] ?? '')) {
                $styles[] = $variable.':'.$options[$key];
            }
        }
        return array_replace($options, ['variant' => $variant, 'shop' => $variant !== 'marketplace', 'style' => implode(';', $styles)]);
    }
}
