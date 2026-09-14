@extends('layouts.app')

@section('title', __('site.sign_in'))

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 460px;">
            <form class="ksm-card" style="padding: 28px;" method="POST" action="{{ route('login.store') }}">
                @csrf
                <h1 style="font-size: 1.5rem;">{{ __('site.sign_in') }}</h1>

                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email</label>
                    <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="password">Password</label>
                    <input class="ksm-input" id="password" name="password" type="password" required>
                </div>

                <label style="display: flex; gap: 8px; align-items: center; margin-bottom: 18px;">
                    <input type="checkbox" name="remember" value="1"> Ricordami
                </label>

                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">{{ __('site.sign_in') }}</button>

                <p class="ksm-center" style="margin: 16px 0 0; font-size: .9rem;">
                    <a href="{{ route('password.request') }}">Password dimenticata</a>
                    ·
                    <a href="{{ route('register') }}">Registrati</a>
                </p>
            </form>
        </div>
    </section>
@endsection
