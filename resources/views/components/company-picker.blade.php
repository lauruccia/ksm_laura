{{--
    Scelta di un'azienda con ricerca mentre si scrive.

    Con migliaia di aziende un <select> non regge: qui l'elenco arriva a pezzi
    da admin.products.companies. Si manda l'id dell'azienda scelta (`idName`); se si
    scrive un nome senza sceglierlo dall'elenco si manda il testo (`textName`), che il
    server legge come nome. `submit` invia il modulo appena si sceglie.
    Vedi public/js/company-picker.js.
--}}
@props(['idName' => 'azienda', 'textName' => 'nome_azienda', 'id' => null, 'text' => '', 'placeholder' => 'Azienda', 'label' => 'Cerca un\'azienda', 'submit' => false])

<span {{ $attributes->merge(['class' => 'ksm-cpick']) }} data-company-picker data-url="{{ route('admin.products.companies') }}"
      data-text-name="{{ $textName }}" @if ($submit) data-submit @endif>
    <input type="hidden" name="{{ $idName }}" value="{{ $id }}" data-cpick-id>
    <input class="ksm-input" type="search" @unless ($id) name="{{ $textName }}" @endunless value="{{ $text }}"
           placeholder="{{ $placeholder }}" aria-label="{{ $label }}" autocomplete="off" maxlength="255"
           role="combobox" aria-autocomplete="list" aria-expanded="false" data-cpick-text>
    <ul class="ksm-cpick__list" role="listbox" hidden data-cpick-list></ul>
</span>

@once
    <script src="{{ asset('js/company-picker.js') }}?v={{ filemtime(public_path('js/company-picker.js')) }}" defer></script>
@endonce
