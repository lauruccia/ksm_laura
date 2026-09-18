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
                <img src="{{ asset('storage/'.$page->banner_image) }}" alt="{{ $page->title }}"
                     style="border-radius: var(--ksm-radius); margin-bottom: 24px;">
            @endif

            <div class="ksm-card" style="padding: 26px;">
                {!! $page->content !!}
            </div>
        </div>
    </section>
@endsection
