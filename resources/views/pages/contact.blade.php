@extends('layouts.app')

@section('title', __('site.nav_contact'))

@section('content')
    <section class="ksm-section">
        <div class="ksm-container ksm-grid ksm-grid--2">
            <form class="ksm-card" style="padding: 24px;" method="POST" action="{{ route('contact.send') }}">
                @csrf
                <h2>{{ __('site.nav_contact') }}</h2>

                <div class="ksm-field">
                    <label class="ksm-label" for="name">Nome</label>
                    <input class="ksm-input" id="name" name="name" value="{{ old('name') }}" required>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email</label>
                    <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email') }}" required>
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="subject">Oggetto</label>
                    <input class="ksm-input" id="subject" name="subject" value="{{ old('subject') }}">
                </div>
                <div class="ksm-field">
                    <label class="ksm-label" for="message">Messaggio</label>
                    <textarea class="ksm-textarea" id="message" name="message" rows="6" required>{{ old('message') }}</textarea>
                </div>

                <button class="ksm-btn ksm-btn--primary" type="submit">Invia</button>
            </form>

            <aside class="ksm-card" style="padding: 24px; height: fit-content;">
                <h2>{{ __('site.contacts') }}</h2>
                <ul class="ksm-meta">
                    @if ($settings->address)<li>{{ $settings->address }}</li>@endif
                    @if ($settings->contact_number)<li>{{ $settings->contact_number }}</li>@endif
                    @if ($settings->website_email)<li>{{ $settings->website_email }}</li>@endif
                </ul>

                @if ($settings->location_map_embed)
                    <div style="margin-top: 16px;">{!! $settings->location_map_embed !!}</div>
                @endif
            </aside>
        </div>
    </section>
@endsection
