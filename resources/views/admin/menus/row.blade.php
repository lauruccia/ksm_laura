{{-- Una voce di menu: etichetta, link, nuova scheda e comandi per spostarla. --}}
<div class="ksm-menus__row">
    <input class="ksm-input" name="items[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}"
           placeholder="Etichetta" aria-label="Etichetta" maxlength="60">
    <input class="ksm-input" name="items[{{ $i }}][url]" value="{{ $row['url'] ?? '' }}"
           placeholder="/pagina o https://…" aria-label="Link" list="ksm-menu-destinations" maxlength="500">
    <label class="ksm-menus__tab">
        <input type="checkbox" name="items[{{ $i }}][new_tab]" value="1" @checked(! empty($row['new_tab']))>
        Nuova scheda
    </label>
    <div class="ksm-menus__tools">
        <button type="button" data-menu-move="up" title="Sposta su" aria-label="Sposta su">↑</button>
        <button type="button" data-menu-move="down" title="Sposta giù" aria-label="Sposta giù">↓</button>
        <button type="button" data-menu-move="remove" title="Togli" aria-label="Togli">✕</button>
    </div>
</div>
