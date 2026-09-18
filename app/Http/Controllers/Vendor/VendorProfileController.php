<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\CompanyCategory;
use App\Support\Images\ImageStore;
use App\Support\Maps\CompanyLocation;
use App\Support\RichText;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VendorProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('vendor.profile', [
            'company' => $request->user()->company,
            'categories' => CompanyCategory::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, CompanyLocation $location, ImageStore $images): RedirectResponse
    {
        $company = $request->user()->company;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:company_categories,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', Rule::in(config('ksm.regions'))],
            'company_location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'company_description' => ['nullable', 'string', 'max:10000'],
            'base_shipping_rate' => ['nullable', 'numeric', 'min:0'],
            'per_kg_rate' => ['nullable', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:12288'],
        ]);

        $replaced = [];

        foreach (['logo', 'banner'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $images->store($request->file($field), "companies/$company->id", $field, $field);
                $replaced[] = $company->$field;
            }
        }

        $data['slug'] = $company->slug ?: Str::slug($data['name']);
        $data['company_description'] = RichText::clean($data['company_description'] ?? null);
        $data = array_merge($data, $location->coordinates($company, $data));

        $company->update($data);
        $images->delete($replaced);

        return back()->with('success', __('Profilo aggiornato.'));
    }
}
