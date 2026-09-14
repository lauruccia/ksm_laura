@extends('layouts.app')

@section('title', 'Recupero password')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 440px;">
            <form class="ksm-card" style="padding: 28px;" method="POST" action="{{ route('password.email') }}">
                @csrf
                <h1 style="font-size: 1.4rem;">Recupero password</h1>
                <p class="ksm-muted">Ti inviamo un collegamento per reimpostarla.</p>

                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email</label>
                    <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email') }}" required>
                </div>

                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Invia</button>
            </form>
        </div>
    </section>
@endsection
