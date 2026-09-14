<?php

namespace App\Support\Domains;

/**
 * Apre una connessione HTTPS e lascia a OpenSSL il giudizio sul certificato.
 *
 * Verifica catena e nome, come un browser: un certificato scaduto,
 * autofirmato o emesso per un altro dominio non passa.
 */
final class StreamTlsProbe implements TlsProbe
{
    private const TIMEOUT_SECONDS = 8;

    public function check(string $host): ?string
    {
        $context = stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ]]);

        $socket = @stream_socket_client(
            "ssl://$host:443",
            $code,
            $message,
            self::TIMEOUT_SECONDS,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket === false) {
            return $message ?: (error_get_last()['message'] ?? 'connessione non riuscita');
        }

        fclose($socket);

        return null;
    }
}
