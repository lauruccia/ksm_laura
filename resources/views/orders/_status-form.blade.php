{{--
    Stato e spedizione di un ordine, uguale in amministrazione e nell'area azienda.
    Vuole $order e $action; $withNotes per le note interne (solo amministrazione).
--}}
<form method="POST" action="{{ $action }}" class="ksm-orderstatus" style="margin-top: 16px; display: grid; gap: 12px;">
    @csrf @method('PATCH')

    <div class="ksm-field" style="margin: 0;">
        <label class="ksm-label" for="status">Stato</label>
        <select class="ksm-select" id="status" name="status">
            @foreach (\App\Models\Order::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ \App\Models\Order::STATUS_LABELS[$status] ?? $status }}</option>
            @endforeach
        </select>
        <small class="ksm-muted">Annullando un ordine pagato la merce torna disponibile; riattivandolo si riprende.</small>
    </div>

    <div class="ksm-formgrid" style="margin: 0;">
        <div class="ksm-field" style="margin: 0;">
            <label class="ksm-label" for="carrier">Corriere</label>
            <input class="ksm-input" id="carrier" name="carrier" maxlength="120" placeholder="BRT, Poste, GLS..." value="{{ old('carrier', $order->carrier) }}">
        </div>
        <div class="ksm-field" style="margin: 0;">
            <label class="ksm-label" for="tracking_number">Numero di spedizione</label>
            <input class="ksm-input" id="tracking_number" name="tracking_number" maxlength="120" value="{{ old('tracking_number', $order->tracking_number) }}">
        </div>
    </div>
    <div class="ksm-field" style="margin: 0;">
        <label class="ksm-label" for="tracking_url">Link per seguire la spedizione</label>
        <input class="ksm-input" id="tracking_url" name="tracking_url" type="url" maxlength="500" placeholder="https://" value="{{ old('tracking_url', $order->tracking_url) }}">
        @error('tracking_url')<span class="ksm-error">{{ $message }}</span>@enderror
    </div>

    @if (! empty($withNotes))
        <div class="ksm-field" style="margin: 0;">
            <label class="ksm-label" for="admin_notes">Note interne</label>
            <textarea class="ksm-input" id="admin_notes" name="admin_notes" rows="3" maxlength="5000">{{ old('admin_notes', $order->admin_notes) }}</textarea>
            <small class="ksm-muted">Le vede solo l'amministrazione.</small>
        </div>
    @endif

    <label style="display: flex; gap: 8px; align-items: center;">
        <input type="checkbox" name="notify" value="1" @checked(old('notify', true))>
        Avvisa il cliente per email se l'ordine passa a Spedito o Annullato
    </label>

    <div>
        <button class="ksm-btn ksm-btn--primary" type="submit">Salva</button>
    </div>
</form>
