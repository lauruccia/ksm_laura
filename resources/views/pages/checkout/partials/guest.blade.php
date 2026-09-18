{{-- Contatto da ospite: si crea l'account o si accede senza lasciare la cassa.
     Consegna e pagamento restano visibili ma chiusi, cosi' si vede il percorso. --}}
@php
    $registerErrors = $errors->getBag(\App\Http\Controllers\Auth\RegisterController::BAG);
    $loginErrors = $errors->getBag(\App\Http\Controllers\Auth\LoginController::BAG);
    $mode = $loginErrors->any() || (! $registerErrors->any() && request('modulo') === 'accesso') ? 'accesso' : 'registrazione';
@endphp

<section class="ksm-co-section" id="acquisto-accesso">
    @if ($mode === 'registrazione')
        <div class="ksm-co-section__head">
            <h2>Contatto</h2>
            <p>Hai un account? <a href="{{ route('checkout.show', ['modulo' => 'accesso']) }}">Accedi</a></p>
        </div>

        <form method="POST" action="{{ route('register.buyer.store') }}" class="ksm-co-stack">
            @csrf
            <input type="hidden" name="ritorno" value="pagamento">

            <x-checkout.field name="email" type="email" label="Email" :value="old('email')" bag="registrazione"
                              id="reg_email" autocomplete="email" required autofocus />

            <p class="ksm-co-note">Creiamo il tuo account con questi dati: potrai seguire l'ordine e riordinare più in fretta.</p>

            <x-checkout.field name="name" label="Nome e cognome" :value="old('name')" bag="registrazione"
                              id="reg_name" autocomplete="name" required />

            <x-checkout.field name="phone" type="tel" label="Telefono (facoltativo)" :value="old('phone')" bag="registrazione"
                              id="reg_phone" autocomplete="tel" />

            <div class="ksm-co-row">
                <x-checkout.field name="password" type="password" label="Password" bag="registrazione"
                                  id="reg_password" autocomplete="new-password" required minlength="8" />
                <x-checkout.field name="password_confirmation" type="password" label="Conferma password" bag="registrazione"
                                  id="reg_password_confirmation" autocomplete="new-password" required />
            </div>

            <button class="ksm-co-btn" type="submit">Crea account e continua</button>
        </form>
    @else
        <div class="ksm-co-section__head">
            <h2>Accedi</h2>
            <p>Nuovo cliente? <a href="{{ route('checkout.show') }}">Crea un account</a></p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="ksm-co-stack">
            @csrf
            <input type="hidden" name="ritorno" value="pagamento">

            <x-checkout.field name="email" type="email" label="Email" :value="old('email')" bag="accesso"
                              id="log_email" autocomplete="email" required autofocus />

            <x-checkout.field name="password" type="password" label="Password" bag="accesso"
                              id="log_password" autocomplete="current-password" required />

            <div class="ksm-co-between">
                <label class="ksm-co-check">
                    <input type="checkbox" name="remember" value="1"> Ricordami
                </label>
                <a href="{{ route('password.request') }}">Password dimenticata?</a>
            </div>

            <button class="ksm-co-btn" type="submit">Accedi e continua</button>
        </form>
    @endif
</section>

<section class="ksm-co-section ksm-co-section--locked" aria-disabled="true">
    <div class="ksm-co-section__head"><h2>Consegna</h2></div>
    <p class="ksm-co-note">Indirizzo e spedizione dopo l'accesso.</p>
</section>

<section class="ksm-co-section ksm-co-section--locked" aria-disabled="true">
    <div class="ksm-co-section__head"><h2>Pagamento</h2></div>
    <p class="ksm-co-note">Tutte le transazioni sono sicure e si completano sul sito del gestore.</p>
</section>
