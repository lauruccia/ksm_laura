@extends('layouts.app')

@section('title', __('site.register_company'))

@section('content')
    <section class="ksm-section">
        <div class="ksm-container" style="max-width: 520px;">
            <form class="ksm-card" style="padding: 28px;" method="POST" action="{{ route('register.store') }}">
                @csrf
                <h1 style="font-size: 1.5rem;">Crea un account</h1>

                <div class="ksm-field">
                    <label class="ksm-label" for="name">Nome</label>
                    <input class="ksm-input" id="name" name="name" value="{{ old('name') }}" required>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="email">Email</label>
                    <input class="ksm-input" id="email" name="email" type="email" value="{{ old('email') }}" required>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="phone">Telefono</label>
                    <input class="ksm-input" id="phone" name="phone" value="{{ old('phone') }}">
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="user_type">Tipo di account</label>
                    <select class="ksm-select" id="user_type" name="user_type">
                        <option value="buyer" @selected(old('user_type', $chosen ? 'vendor' : 'buyer') === 'buyer')>Acquirente</option>
                        <option value="vendor" @selected(old('user_type', $chosen ? 'vendor' : 'buyer') === 'vendor')>Azienda</option>
                    </select>
                </div>

                @if ($plans->isNotEmpty())
                    <div class="ksm-field">
                        <label class="ksm-label" for="piano">Piano, se ti registri come azienda</label>
                        <select class="ksm-select" id="piano" name="piano">
                            <option value="">Decido dopo</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->slug }}" @selected(old('piano', $chosen) === $plan->slug)>
                                    {{ $plan->name }} ·
                                    {{ $plan->isFree() ? 'gratis' : \App\Support\Money::format($plan->price) }}
                                </option>
                            @endforeach
                        </select>
                        <small class="ksm-muted">Lo paghi dopo la verifica dell'indirizzo email.</small>
                        @error('piano')<span class="ksm-error">{{ $message }}</span>@enderror
                    </div>
                @endif

                <div class="ksm-field">
                    <label class="ksm-label" for="password">Password</label>
                    <input class="ksm-input" id="password" name="password" type="password" required>
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="password_confirmation">Conferma password</label>
                    <input class="ksm-input" id="password_confirmation" name="password_confirmation" type="password" required>
                </div>

                <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Registrati</button>
            </form>
        </div>
    </section>
@endsection
