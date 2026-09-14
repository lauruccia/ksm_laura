@extends('layouts.app')

@section('title', 'Nuova password')

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 440px;">
            <form class="ksm-card" style="padding: 28px;" method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <h1 style="font-size: 1.4rem;">Nuova password</h1>

                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email</label>
                    <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email', request('email')) }}" required>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="password">Password</label>
                    <input class="ksm-input" id="password" name="password" type="password" required>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="password_confirmation">Conferma password</label>
                    <input class="ksm-input" id="password_confirmation" name="password_confirmation" type="password" required>
                </div>

                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Salva</button>
            </form>
        </div>
    </section>
@endsection
