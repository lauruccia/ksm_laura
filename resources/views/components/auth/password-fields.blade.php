{{-- Password e conferma affiancate, con la regola scritta prima dell'errore. --}}
<div class="ksm-auth__row">
    <div class="ksm-field">
        <label class="ksm-label" for="password">Password</label>
        <input class="ksm-input" id="password" name="password" type="password" autocomplete="new-password" required>
        @error('password')<span class="ksm-error">{{ $message }}</span>@enderror
    </div>
    <div class="ksm-field">
        <label class="ksm-label" for="password_confirmation">Conferma password</label>
        <input class="ksm-input" id="password_confirmation" name="password_confirmation" type="password"
               autocomplete="new-password" required>
    </div>
</div>
<p class="ksm-auth__hint">Almeno 8 caratteri.</p>
