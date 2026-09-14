<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Testi scritti dalle aziende, mostrati sul sito.
 *
 * Il sito originale salvava le descrizioni in HTML, dall'editor; quelle
 * nuove arrivano da una casella di testo. Il testo semplice va a capo dove
 * l'azienda e' andata a capo. L'HTML passa da un filtro che tiene solo la
 * formattazione: niente script, stili, immagini o attributi con codice.
 */
final class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    /** Da mostrare: sempre ripulito, anche se arriva dal database. */
    public static function render(?string $text): HtmlString
    {
        $text = trim((string) $text);

        if ($text === '') {
            return new HtmlString('');
        }

        if (! self::isHtml($text)) {
            return new HtmlString(nl2br(e($text), false));
        }

        return new HtmlString(self::sanitizer()->sanitize($text));
    }

    /** Da salvare: l'HTML dell'editor ripulito, il testo semplice cosi' com'e'. */
    public static function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return null;
        }

        return self::isHtml($text) ? self::sanitizer()->sanitize($text) : $text;
    }

    /** HTML solo se ci sono tag veri: "prezzi < 20" resta testo, e intero. */
    private static function isHtml(string $text): bool
    {
        return preg_match('/<\/?[a-z][a-z0-9]*(\s[^>]*)?\/?>/i', $text) === 1;
    }

    private static function sanitizer(): HtmlSanitizer
    {
        return self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowElement('p')
                ->allowElement('br')
                ->allowElement('strong')
                ->allowElement('b')
                ->allowElement('em')
                ->allowElement('i')
                ->allowElement('u')
                ->allowElement('ul')
                ->allowElement('ol')
                ->allowElement('li')
                ->allowElement('h2')
                ->allowElement('h3')
                ->allowElement('h4')
                ->allowElement('blockquote')
                ->allowElement('a', ['href', 'title'])
                // Contenitori dell'editor: via il tag, resta il testo.
                ->blockElement('div')
                ->blockElement('span')
                ->blockElement('font')
                ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
                ->forceAttribute('a', 'rel', 'noopener nofollow')
                ->withMaxInputLength(100000)
        );
    }
}
