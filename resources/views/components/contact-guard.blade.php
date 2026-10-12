{{-- Campi di difesa dallo spam dei moduli contatti: vedi App\Support\ContactGuard. --}}
<div aria-hidden="true" style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;">
    <label for="cg-website">Non compilare questo campo</label>
    <input id="cg-website" type="text" name="{{ \App\Support\ContactGuard::FIELD }}" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="{{ \App\Support\ContactGuard::TOKEN }}" value="{{ \App\Support\ContactGuard::token() }}">
