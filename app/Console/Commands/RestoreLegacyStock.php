<?php

namespace App\Console\Commands;

use App\Support\Legacy\MysqlDumpReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreLegacyStock extends Command
{
    protected $signature = 'legacy:restore-stock {file=database/ksmdb_databaseoriginale.sql} {--apply : Ripristina lo stock non gestito}';

    protected $description = 'Ripristina gli stock NULL e il tipo dei prodotti variabili dal dump originale';

    public function handle(): int
    {
        $count = 0;
        $variableCount = 0;
        foreach ((new MysqlDumpReader($this->argument('file')))->rows(['products']) as [, $row]) {
            $identity = DB::table('products')->where('id', $row['id'])->where('slug', $row['slug'])
                ->where('company_id', $row['company_id'])->where('updated_at', $row['updated_at']);
            if (($row['product_type'] ?? null) === 'variant') {
                $variables = (clone $identity)->where('product_type', 'variant');
                $variableCount += $this->option('apply') ? $variables->update(['product_type' => 'variable']) : $variables->count();
            }
            if ($row['stock'] !== null) {
                continue;
            }
            // Matching identity and timestamp avoids overwriting products edited since import.
            $query = $identity->where('stock', 0);
            $count += $this->option('apply') ? $query->update(['stock' => null]) : $query->count();
        }
        $this->info(($this->option('apply') ? 'Prodotti ripristinati: ' : 'Prodotti da ripristinare: ').$count);
        $this->info(($this->option('apply') ? 'Tipi variabili corretti: ' : 'Tipi variabili da correggere: ').$variableCount);

        return self::SUCCESS;
    }
}
