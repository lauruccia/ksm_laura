<?php

namespace Tests\Unit;

use App\Support\WorkingHours;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Aperto adesso: il confronto guarda il giorno e l'ora italiana.
 */
class WorkingHoursTest extends TestCase
{
    private array $hours = [
        'Wednesday' => ['start' => '09:00', 'end' => '18:00'],
    ];

    public function test_dentro_l_orario_e_aperto(): void
    {
        $status = WorkingHours::status($this->hours, Carbon::parse('2026-09-16 09:00', 'Europe/Rome'));

        $this->assertTrue($status['open']);
        $this->assertSame('Wednesday', $status['day']);
    }

    public function test_alla_chiusura_e_gia_chiuso(): void
    {
        $this->assertFalse(WorkingHours::status($this->hours, Carbon::parse('2026-09-16 18:00', 'Europe/Rome'))['open']);
        $this->assertFalse(WorkingHours::status($this->hours, Carbon::parse('2026-09-16 08:59', 'Europe/Rome'))['open']);
    }

    public function test_un_giorno_senza_orari_e_chiuso(): void
    {
        $status = WorkingHours::status($this->hours, Carbon::parse('2026-09-17 10:00', 'Europe/Rome'));

        $this->assertFalse($status['open']);
        $this->assertNull($status['today']);
    }

    public function test_senza_orari_non_e_mai_aperto(): void
    {
        $this->assertFalse(WorkingHours::status([], Carbon::parse('2026-09-16 10:00', 'Europe/Rome'))['open']);
    }
}
