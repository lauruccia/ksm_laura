<?php

namespace App\Console\Commands;

use App\Support\Images\ImageStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Porta le immagini gia' caricate allo stesso formato di quelle nuove.
 *
 * Foto dei prodotti, loghi, banner, gallerie e banner pubblicitari arrivati
 * prima di ImageStore (o dal vecchio sito) pesano spesso qualche MB. Ognuna
 * diventa un WebP ridotto con la sua miniatura, e il database punta al file
 * nuovo. L'originale si cancella solo con --delete-originals.
 *
 * Si puo' rilanciare: i WebP si saltano.
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize
        {--only= : Un solo gruppo: prodotti, aziende, pubblicita, sito}
        {--limit= : Si ferma dopo queste immagini}
        {--delete-originals : Cancella i file vecchi dopo la conversione}
        {--dry-run : Conta soltanto, senza toccare nulla}';

    protected $description = 'Converte in WebP ridotto le immagini gia caricate e aggiorna il database';

    private const GROUPS = ['prodotti', 'aziende', 'pubblicita', 'sito'];

    private int $done = 0;

    private int $before = 0;

    private int $after = 0;

    private int $thumbs = 0;

    public function __construct(private readonly ImageStore $images)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $only = $this->option('only');

        if ($only && ! in_array($only, self::GROUPS, true)) {
            $this->error('Gruppo sconosciuto. Scegli tra: '.implode(', ', self::GROUPS));

            return self::FAILURE;
        }

        $groups = $only ? [$only] : self::GROUPS;
        $this->done = $this->before = $this->after = $this->thumbs = 0;

        if (in_array('prodotti', $groups, true)) {
            $this->column('products', 'featured_image', 'product');
        }

        if (in_array('aziende', $groups, true)) {
            $this->column('companies', 'logo', 'logo');
            $this->column('companies', 'banner', 'banner');
            $this->gallery();
        }

        if (in_array('pubblicita', $groups, true)) {
            $this->column('advertisements', 'img', 'advertisement');
        }

        if (in_array('sito', $groups, true)) {
            $this->column('admin_settings', 'site_logo', 'site_logo');
            $this->column('admin_settings', 'favicon', 'favicon');
        }

        $verb = $this->option('dry-run') ? 'da convertire' : 'convertite';
        $this->info("Immagini $verb: $this->done.");

        if ($this->thumbs) {
            $this->line("Miniature aggiunte: $this->thumbs.");
        }

        if ($this->option('dry-run')) {
            $this->line('Peso attuale: '.self::size($this->before).'.');
        } elseif ($this->before) {
            $saved = $this->before - $this->after;
            $this->line(sprintf('Peso: %s → %s (−%d%%).', self::size($this->before), self::size($this->after), $saved * 100 / $this->before));
        }

        return self::SUCCESS;
    }

    private function column(string $table, string $column, string $profile): void
    {
        DB::table($table)->whereNotNull($column)->where($column, '!=', '')->orderBy('id')
            ->select('id', $column)
            ->each(function ($row) use ($table, $column, $profile) {
                $new = $this->convert($row->$column, $profile);

                if ($new) {
                    DB::table($table)->where('id', $row->id)->update([$column => $new]);
                }
            });
    }

    private function gallery(): void
    {
        DB::table('companies')->whereNotNull('offer_gallery')->orderBy('id')
            ->select('id', 'offer_gallery')
            ->each(function ($row) {
                $paths = (array) json_decode((string) $row->offer_gallery, true);
                $changed = false;

                foreach ($paths as $i => $path) {
                    if (is_string($path) && ($new = $this->convert($path, 'gallery'))) {
                        $paths[$i] = $new;
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('companies')->where('id', $row->id)->update(['offer_gallery' => json_encode(array_values($paths))]);
                }
            });
    }

    /** Il percorso nuovo, o null se l'immagine resta com'e'. */
    private function convert(string $path, string $profile): ?string
    {
        $limit = (int) $this->option('limit');
        $disk = Storage::disk('public');

        if (($limit && $this->done >= $limit) || ! $disk->exists($path)) {
            return null;
        }

        // Gia' in WebP: al massimo manca la miniatura (i loghi non l'avevano).
        if ($this->isOptimized($path, $profile)) {
            if (! $this->option('dry-run') && $this->images->addThumb($path, $profile)) {
                $this->thumbs++;
            }

            return null;
        }

        if ($this->option('dry-run')) {
            $this->done++;
            $this->before += $disk->size($path);
            $this->line("  $path");

            return null;
        }

        $new = $this->images->optimize($path, $profile);

        if (! $new) {
            return null;
        }

        $this->done++;
        $this->before += $disk->size($path);
        $this->after += $disk->size($new);
        $this->line(sprintf('  %s → %s (%s → %s)', $path, $new, self::size($disk->size($path)), self::size($disk->size($new))));

        if ($this->option('delete-originals')) {
            $disk->delete($path);
        }

        return $new;
    }

    private function isOptimized(string $path, string $profile): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $profile === 'favicon' ? $extension === 'png' || $extension === 'ico' : $extension === 'webp';
    }

    private static function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1).' MB' : max(1, round($bytes / 1024)).' KB';
    }
}
