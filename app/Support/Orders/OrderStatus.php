<?php

namespace App\Support\Orders;

use App\Mail\OrderStatusChanged;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Unico punto in cui cambia lo stato di un ordine: amministrazione (uno o
 * in blocco) e area azienda.
 *
 * Insieme allo stato: la disponibilita' dei prodotti (OrderStock), la data
 * di spedizione e, se richiesto, l'email al cliente per spedito e annullato.
 */
class OrderStatus
{
    public function __construct(private OrderStock $stock)
    {
    }

    /** Il modulo "Stato e spedizione", uguale in amministrazione e nell'area azienda. */
    public static function rules(): array
    {
        return [
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
            'carrier' => ['nullable', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url:http,https', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Salva spedizione e stato dal modulo; restituisce il messaggio da mostrare.
     * Prima la spedizione: l'email di "spedito" la contiene.
     *
     * @return array{0: string, 1: string}
     */
    public function applyForm(Order $order, array $data, bool $notify): array
    {
        $order->update([
            'carrier' => $data['carrier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'tracking_url' => $data['tracking_url'] ?? null,
        ]);

        return match ($this->change($order, $data['status'], $notify)) {
            true => ['success', __('Ordine aggiornato. Il cliente e\' stato avvisato per email.')],
            false => ['error', __('Ordine aggiornato, ma l\'email al cliente non e\' partita.')],
            null => ['success', __('Ordine aggiornato.')],
        };
    }

    /**
     * @return bool|null  true se l'email e' partita, false se non e' partita,
     *                    null se non c'era niente da spedire
     */
    public function change(Order $order, string $status, bool $notify = true): ?bool
    {
        $changed = DB::transaction(function () use ($order, $status) {
            $fresh = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->status === $status) {
                return false;
            }

            $fresh->status = $status;

            if ($status === 'shipped' && ! $fresh->shipped_at) {
                $fresh->shipped_at = now();
            }

            $fresh->save();
            $this->stock->sync($fresh);

            return true;
        });

        $order->refresh();

        if (! $changed || ! $notify || ! in_array($status, Order::NOTIFIED_STATUSES, true)) {
            return null;
        }

        return $this->notify($order);
    }

    /**
     * L'email al cliente, con il nome del sito su cui ha comprato: le
     * intestazioni del modello di Laravel leggono app.name e app.url, che
     * qui sono quelli del sito da cui parte la richiesta.
     */
    public function notify(Order $order): bool
    {
        if (blank($order->billing_email)) {
            return false;
        }

        $original = ['app.name' => config('app.name'), 'app.url' => config('app.url'), 'mail.from.name' => config('mail.from.name')];

        try {
            config(['app.name' => $order->siteName(), 'app.url' => $order->siteUrl(), 'mail.from.name' => $order->siteName()]);
            Mail::to($order->billing_email, $order->billing_name)->send(new OrderStatusChanged($order->loadMissing('items', 'company', 'domain')));

            return true;
        } catch (\Throwable $e) {
            Log::error('Email di stato ordine non spedita', ['order' => $order->id, 'error' => $e->getMessage()]);

            return false;
        } finally {
            config($original);
        }
    }
}
