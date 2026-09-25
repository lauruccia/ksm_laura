@extends('layouts.app')

@section('title', $page->meta_title ?: $page->title)
@section('meta_description', $page->meta_description)
@if ($page->canonical_url)
    @section('canonical', $page->canonical_url)
@endif

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 860px;">
            <h1>{{ $page->title }}</h1>

            @if ($page->banner_image)
                @php
                    $banner = \App\Support\Images\ImageStore::responsive($page->banner_image);
                @endphp
                <img src="{{ $banner['src'] }}" alt="{{ $page->title }}"
                     @if ($banner['srcset']) srcset="{{ $banner['srcset'] }}" sizes="(max-width: 900px) 100vw, 860px" @endif
                     @if ($banner['width']) width="{{ $banner['width'] }}" height="{{ $banner['height'] }}" @endif
                     style="border-radius: var(--ksm-radius); margin-bottom: 24px; height: auto;">
            @endif

            <div class="ksm-card" style="padding: 26px;">
                {!! $page->content !!}
            </div>
        </div>
    </section>
@endsection
