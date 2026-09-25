<?php

namespace App\Support\Images;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Salva le immagini caricate gia' pronte per il sito.
 *
 * Il ritaglio lo fa chi carica, nel browser (public/js/image-editor.js). Qui
 * si fa il resto, anche quando lo script non gira: si raddrizza la foto del
 * telefono, si riduce alla misura che serve davvero, si tolgono i dati EXIF
 * e si salva in WebP. Per le immagini che compaiono negli elenchi si scrive
 * anche una copia piccola accanto, "nome@sm.webp", letta da thumb().
 */
final class ImageStore
{
    /**
     * Misure massime per uso: [larghezza, altezza, miniatura].
     * La miniatura e' il lato lungo della copia per gli elenchi, 0 se non serve.
     */
    public const PROFILES = [
        'product' => [1600, 1600, 600],
        'logo' => [800, 800, 240],
        'banner' => [2000, 1200, 800],
        'gallery' => [1600, 1600, 600],
        'advertisement' => [2000, 2000, 0],
        'site_logo' => [1000, 500, 0],
        'favicon' => [256, 256, 0],
    ];

    /** Oltre questa soglia GD rischia di finire la memoria: meglio dirlo subito. */
    private const MAX_PIXELS = 50_000_000;

    private const QUALITY = 82;

    public const THUMB_SUFFIX = '@sm';

    public function __construct(private readonly string $disk = 'public') {}

    /**
     * Salva il file ottimizzato nella cartella e ne restituisce il percorso.
     *
     * @param  string  $field  campo del modulo, per l'errore di validazione
     */
    public function store(UploadedFile $file, string $folder, string $profile, string $field = 'image'): string
    {
        $info = @getimagesize((string) $file->getRealPath());

        if (! $info) {
            throw ValidationException::withMessages([$field => __('Il file non e\' un\'immagine leggibile.')]);
        }

        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages([
                $field => __('L\'immagine e\' troppo grande (:w x :h pixel): ridimensionala o ritagliala prima di caricarla.', ['w' => $info[0], 'h' => $info[1]]),
            ]);
        }

        return $this->write((string) $file->getRealPath(), $folder, $profile) ?? $file->store($folder, $this->disk);
    }

    /**
     * Ottimizza un file gia' sul disco, per le immagini caricate prima.
     * Restituisce il percorso nuovo, o null se il file va lasciato com'e'.
     */
    public function optimize(string $path, string $profile): ?string
    {
        $disk = Storage::disk($this->disk);

        if (! $disk->exists($path)) {
            return null;
        }

        $source = $disk->path($path);
        $info = @getimagesize($source);

        if (! $info || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        return $this->write($source, dirname($path), $profile);
    }

    /**
     * Scrive la miniatura che manca a un'immagine gia' ottimizzata, per
     * esempio ai loghi caricati prima che il profilo ne avesse una.
     */
    public function addThumb(string $path, string $profile): bool
    {
        $disk = Storage::disk($this->disk);
        $thumb = self::PROFILES[$profile][2];
        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! $thumb || ! in_array($format, ['webp', 'png', 'jpg'], true) || ! $disk->exists($path) || $disk->exists(self::thumbPath($path))) {
            return false;
        }

        $image = @imagecreatefromstring((string) $disk->get($path));

        if (! $image instanceof GdImage || ($format === 'webp' && ! function_exists('imagewebp'))) {
            return false;
        }

        return $disk->put(self::thumbPath($path), self::encode(self::fit($image, $thumb, $thumb), $format));
    }

    /** Il lavoro vero; null quando conviene tenere l'originale. */
    private function write(string $source, string $folder, string $profile): ?string
    {
        [$maxWidth, $maxHeight, $thumb] = self::PROFILES[$profile];
        $type = @getimagesize($source)[2] ?? null;

        // Una GIF animata ridisegnata con GD perderebbe l'animazione: resta com'e'.
        if ($type === IMAGETYPE_GIF && self::isAnimatedGif($source)) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($source));

        // Formato che GD non sa leggere: si tiene l'originale piuttosto che perderlo.
        if (! $image instanceof GdImage) {
            return null;
        }

        $image = self::orient($image, $source, (int) $type);
        $name = $folder.'/'.Str::random(40);

        if ($profile === 'favicon') {
            $path = "$name.png";
            Storage::disk($this->disk)->put($path, self::encode(self::fit($image, $maxWidth, $maxHeight), 'png'));

            return $path;
        }

        $format = function_exists('imagewebp') ? 'webp' : (self::hasAlpha($image) ? 'png' : 'jpg');
        $path = "$name.$format";
        Storage::disk($this->disk)->put($path, self::encode(self::fit($image, $maxWidth, $maxHeight), $format));

        if ($thumb) {
            Storage::disk($this->disk)->put(self::thumbPath($path), self::encode(self::fit($image, $thumb, $thumb), $format));
        }

        return $path;
    }

    /** Cancella i file e le loro miniature; i vuoti si saltano. */
    public function delete(string|array|null $paths): void
    {
        $paths = array_filter((array) $paths);

        if ($paths) {
            Storage::disk($this->disk)->delete(array_merge($paths, array_map(self::thumbPath(...), $paths)));
        }
    }

    /** La copia piccola se c'e', altrimenti l'immagine intera (anche per le vecchie). */
    public static function thumb(?string $path, string $disk = 'public'): ?string
    {
        if (! $path) {
            return null;
        }

        $thumb = self::thumbPath($path);

        return Storage::disk($disk)->exists($thumb) ? $thumb : $path;
    }

    public static function thumbPath(string $path): string
    {
        $dot = strrpos($path, '.');

        return $dot === false ? $path.self::THUMB_SUFFIX : substr($path, 0, $dot).self::THUMB_SUFFIX.substr($path, $dot);
    }

    /** Riduce senza mai ingrandire; lavora su una copia. */
    public static function fit(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, $maxWidth / $width, $maxHeight / $height);
        $newWidth = max(1, (int) round($width * $ratio));
        $newHeight = max(1, (int) round($height * $ratio));

        $copy = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagefill($copy, 0, 0, imagecolorallocatealpha($copy, 0, 0, 0, 127));
        imagecopyresampled($copy, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $copy;
    }

    public static function encode(GdImage $image, string $format): string
    {
        ob_start();

        match ($format) {
            'webp' => imagewebp($image, null, self::QUALITY),
            'png' => imagepng($image, null, 9),
            default => imagejpeg(self::flatten($image), null, self::QUALITY),
        };

        return (string) ob_get_clean();
    }

    /** Ruota le foto scattate col telefono girato, come le mostra il browser. */
    private static function orient(GdImage $image, string $source, int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }

    private static function hasAlpha(GdImage $image): bool
    {
        if (! imageistruecolor($image)) {
            return imagecolortransparent($image) >= 0;
        }

        // Basta un campione: un logo trasparente lo e' quasi sempre ai bordi.
        $width = imagesx($image);
        $height = imagesy($image);
        $step = max(1, (int) floor(min($width, $height) / 40));

        for ($x = 0; $x < $width; $x += $step) {
            for ($y = 0; $y < $height; $y += $step) {
                if ((imagecolorat($image, $x, $y) >> 24) & 0x7F) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Il JPEG non ha trasparenza: si appoggia su bianco invece che su nero. */
    private static function flatten(GdImage $image): GdImage
    {
        $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $flat;
    }

    private static function isAnimatedGif(string $source): bool
    {
        $frames = preg_match_all('/\x00\x21\xF9\x04.{4}\x00[\x2C\x21]/s', (string) file_get_contents($source));

        return $frames > 1;
    }
}
