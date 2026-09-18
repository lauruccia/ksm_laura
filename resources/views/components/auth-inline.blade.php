@props([
    'ritorno' => 'pagamento',
    'title' => 'Crea il tuo account e completa l\'ordine',
])

{{-- Accesso e registrazione dentro l'acquisto: chi compra non deve uscire
     dal carrello per farsi un account. Le due schede sono link normali,
     cosi' funzionano anche senza javascript. --}}
@php
    $registerErrors = $errors->getBag(\App\Http\Controllers\Auth\RegisterController::BAG);
    $loginErrors = $errors->getBag(\App\Http\Controllers\Auth\LoginController::BAG);

    // Si apre la registrazione, salvo che l'ospite abbia chiesto l'accesso
    // o che l'accesso appena tentato sia fallito.
    $active = $loginErrors->any() || (! $registerErrors->any() && request('modulo') === 'accesso')
        ? 'accesso'
        : 'registrazione';

    $tabUrl = fn (string $modulo) => request()->fullUrlWithQuery(['modulo' => $modulo]).'#acquisto-accesso';
@endphp

<div {{ $attributes->merge(['class' => 'ksm-card ksm-inline-auth']) }} id="acquisto-accesso">
    <h2 class="ksm-inline-auth__title">{{ $title }}</h2>
    <p class="ksm-muted ksm-inline-auth__lead">
        Il carrello resta com'è: ti registri o accedi qui e prosegui subito con il pagamento.
    </p>

    <div class="ksm-inline-auth__tabs" role="tablist">
        <a class="ksm-inline-auth__tab @if ($active === 'registrazione') is-active @endif"
           href="{{ $tabUrl('registrazione') }}" aria-current="{{ $active === 'registrazione' ? 'true' : 'false' }}">
            Sono nuovo
        </a>
        <a class="ksm-inline-auth__tab @if ($active === 'accesso') is-active @endif"
           href="{{ $tabUrl('accesso') }}" aria-current="{{ $active === 'accesso' ? 'true' : 'false' }}">
            Ho già un account
        </a>
    </div>

    @if ($active === 'registrazione')
        <form method="POST" action="{{ route('register.buyer.store') }}">
            @csrf
            <input type="hidden" name="ritorno" value="{{ $ritorno }}">

            <div class="ksm-field">
                <label class="ksm-label" for="reg_name">Nome e cognome</label>
                <input class="ksm-input" id="reg_name" name="name" value="{{ old('name') }}" required>
                @if ($registerErrors->has('name'))<span class="ksm-error">{{ $registerErrors->first('name') }}</span>@endif
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="reg_email">Email</label>
                <input class="ksm-input" id="reg_email" name="email" type="email" value="{{ old('email') }}" required>
                @if ($registerErrors->has('email'))<span class="ksm-error">{{ $registerErrors->first('email') }}</span>@endif
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="reg_phone">Telefono <span class="ksm-muted">(facoltativo)</span></label>
                <input class="ksm-input" id="reg_phone" name="phone" value="{{ old('phone') }}">
                @if ($registerErrors->has('phone'))<span class="ksm-error">{{ $registerErrors->first('phone') }}</span>@endif
            </div>

            <div class="ksm-inline-auth__row">
                <div class="ksm-field">
                    <label class="ksm-label" for="reg_password">Password</label>
                    <input class="ksm-input" id="reg_password" name="password" type="password" required autocomplete="new-password">
                    @if ($registerErrors->has('password'))<span class="ksm-error">{{ $registerErrors->first('password') }}</span>@endif
                </div>

                <div class="ksm-field">
                    <label class="ksm-label" for="reg_password_confirmation">Conferma password</label>
                    <input class="ksm-input" id="reg_password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>
            </div>

            <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Registrati e prosegui</button>

            <p class="ksm-muted ksm-inline-auth__note">
                Ti arriverà un codice di verifica per email: l'ordine puoi completarlo subito.
            </p>
        </form>
    @else
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <input type="hidden" name="ritorno" value="{{ $ritorno }}">

            <div class="ksm-field">
                <label class="ksm-label" for="log_email">Email</label>
                <input class="ksm-input" id="log_email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                @if ($loginErrors->has('email'))<span class="ksm-error">{{ $loginErrors->first('email') }}</span>@endif
            </div>

            <div class="ksm-field">
                <label class="ksm-label" for="log_password">Password</label>
                <input class="ksm-input" id="log_password" name="password" type="password" required autocomplete="current-password">
                @if ($loginErrors->has('password'))<span class="ksm-error">{{ $loginErrors->first('password') }}</span>@endif
            </div>

            <label class="ksm-inline-auth__remember">
                <input type="checkbox" name="remember" value="1"> Ricordami
            </label>

            <button class="ksm-btn ksm-btn--primary ksm-btn--block" type="submit">Accedi e prosegui</button>

            <p class="ksm-inline-auth__note">
                <a href="{{ route('password.request') }}">Password dimenticata</a>
            </p>
        </form>
    @endif
</div>
