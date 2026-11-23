{{-- Banner di consenso ai cookie di misurazione (Google). Compare solo se il tag Google e' attivo. --}}
@if (config('services.google_tag.id'))
<div class="ksm-consent" id="ksm-consent" role="dialog" aria-live="polite" aria-label="Cookie" hidden>
    <p class="ksm-consent__text">
        Usiamo cookie di misurazione (Google) per capire come viene usato il sito e migliorarlo.
        Si attivano solo se li accetti; puoi cambiare idea quando vuoi.
    </p>
    <div class="ksm-consent__actions">
        <button type="button" class="ksm-btn ksm-btn--ghost" data-ksm-consent="denied">Rifiuta</button>
        <button type="button" class="ksm-btn ksm-btn--primary" data-ksm-consent="granted">Accetta</button>
    </div>
</div>
<script src="{{ asset('js/consent.js') }}?v={{ filemtime(public_path('js/consent.js')) }}" defer></script>
@endif
