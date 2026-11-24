<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Applica le regole di resources/domain-proxy/htaccess.stub (RewriteCond + RewriteRule [F])
 * a una richiesta finta, per verificare cosa viene respinto con 403 prima di PHP.
 */
class DomainProxyHtaccessTest extends TestCase
{
    #[DataProvider('blocked')]
    public function test_scanner_requests_get_403(string $uri): void
    {
        $this->assertTrue($this->isForbidden($uri), "$uri doveva essere bloccato");
    }

    #[DataProvider('allowed')]
    public function test_real_requests_reach_the_app(string $uri): void
    {
        $this->assertFalse($this->isForbidden($uri), "$uri non doveva essere bloccato");
    }

    public function test_empty_user_agent_and_known_crawlers_are_still_blocked(): void
    {
        $this->assertTrue($this->isForbidden('/', userAgent: ''));
        $this->assertTrue($this->isForbidden('/', userAgent: 'Mozilla/5.0 (compatible; GPTBot/1.0)'));
    }

    public static function blocked(): array
    {
        return array_map(fn ($u) => [$u], [
            '/.env', '/.env.backup', '/.env.save', '/.git/config', '/.htaccess', '/.aws/credentials',
            '/wp-admin/admin-ajax.php', '/wp-login.php', '/blog/wp-content/x.js', '/xmlrpc.php',
            '/phpmyadmin/index.php', '/cgi-bin/test', '/actuator/health',
            '/login.aspx', '/admin.jsp', '/dump.sql', '/site.bak', '/shell.php', '/admin.php', '/info.php7', '/config.ini',
            '/a/../../etc/passwd', '/a/%2e%2e/b', '/a/..%2fb',
        ]);
    }

    public static function allowed(): array
    {
        return array_map(fn ($u) => [$u], [
            '/', '/index.php', '/robots.txt', '/store-list', '/aziende', '/i/mario-rossi',
            '/css/app.css', '/js/app.js', '/storage/foto.jpg', '/sitemap.xml', '/favicon.ico',
            '/.well-known/acme-challenge/abc123', '/.well-known/pki-validation/file.txt',
            '/prodotti/vino-rosso.html', '/negozio/pippo',
        ]);
    }

    private function isForbidden(string $uri, string $userAgent = 'Mozilla/5.0 Firefox/130.0'): bool
    {
        $vars = [
            'REQUEST_URI' => $uri,
            'THE_REQUEST' => "GET $uri HTTP/1.1",
            'HTTP_USER_AGENT' => $userAgent,
        ];

        $conds = [];
        foreach (file(__DIR__.'/../../resources/domain-proxy/htaccess.stub', FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);

            if (preg_match('/^RewriteCond\s+%\{(\w+)\}\s+(\S+)(?:\s+\[(.*)\])?$/', $line, $m)) {
                $conds[] = [$m[1], $m[2], $m[3] ?? ''];

                continue;
            }

            if (! str_starts_with($line, 'RewriteRule')) {
                continue;
            }

            $forbid = str_contains($line, '[F');
            $matched = $this->conditionsMatch($conds, $vars);
            $conds = [];

            if ($matched && $forbid) {
                return true;
            }
        }

        return false;
    }

    private function conditionsMatch(array $conds, array $vars): bool
    {
        $result = true;
        $orPending = false;
        $orResult = false;

        foreach ($conds as [$var, $pattern, $flags]) {
            $negate = str_starts_with($pattern, '!');
            $regex = '#'.str_replace('#', '\#', ltrim($pattern, '!')).'#'.(stripos($flags, 'NC') !== false ? 'i' : '');
            $hit = preg_match($regex, $vars[$var] ?? '') === 1;
            $hit = $negate ? ! $hit : $hit;

            if (stripos($flags, 'OR') !== false) {
                $orPending = true;
                $orResult = $orResult || $hit;

                continue;
            }

            if ($orPending) {
                $hit = $orResult || $hit;
                $orPending = false;
                $orResult = false;
            }

            $result = $result && $hit;
        }

        return $result;
    }
}
