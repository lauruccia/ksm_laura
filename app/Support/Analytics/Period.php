<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * L'intervallo di giorni scelto in pagina: un periodo pronto (?periodo=)
 * oppure due date (?dal=&al=). I giorni sono quelli del fuso delle statistiche.
 */
final class Period
{
    public const PRESETS = [
        'oggi' => 'Oggi',
        'ieri' => 'Ieri',
        '7' => '7 giorni',
        '30' => '30 giorni',
        '90' => '90 giorni',
        '365' => '12 mesi',
    ];

    public const DEFAULT = '30';

    /** Oltre due anni i dati sono comunque stati cancellati (analytics:prune). */
    private const MAX_DAYS = 731;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $key,
    ) {}

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('ksm.analytics.timezone'))->startOfDay();
    }

    public static function fromRequest(Request $request): self
    {
        $today = self::today();
        $from = self::date($request->query('dal'));
        $to = self::date($request->query('al'));

        if ($from || $to) {
            $to = min($to ?? $today, $today);
            $from = $from ?? $to->subDays(29);

            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }

            return new self(max($from, $to->subDays(self::MAX_DAYS - 1)), $to, 'personalizzato');
        }

        $key = (string) $request->query('periodo', self::DEFAULT);

        return match (true) {
            $key === 'oggi' => new self($today, $today, 'oggi'),
            $key === 'ieri' => new self($today->subDay(), $today->subDay(), 'ieri'),
            in_array($key, ['7', '30', '90', '365'], true) => new self($today->subDays((int) $key - 1), $today, $key),
            default => new self($today->subDays((int) self::DEFAULT - 1), $today, self::DEFAULT),
        };
    }

    /** Quanti giorni comprende, estremi inclusi. */
    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** Il periodo di pari durata subito prima, per i confronti. */
    public function previous(): self
    {
        $to = $this->from->subDay();

        return new self($to->subDays($this->days() - 1), $to, 'precedente');
    }

    /** hour | day | month: quanto fine dev'essere il grafico dell'andamento. */
    public function granularity(): string
    {
        return match (true) {
            $this->days() <= 2 => 'hour',
            $this->days() <= 92 => 'day',
            default => 'month',
        };
    }

    public function label(): string
    {
        if ($this->from->equalTo($this->to)) {
            return $this->from->translatedFormat('j F Y');
        }

        return $this->from->translatedFormat('j M Y').' – '.$this->to->translatedFormat('j M Y');
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, config('ksm.analytics.timezone'));
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
