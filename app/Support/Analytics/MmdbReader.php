<?php

namespace App\Support\Analytics;

use RuntimeException;

/**
 * Lettore minimo di archivi MaxMind DB (.mmdb), scritto senza dipendenze.
 *
 * Il server non installa pacchetti dopo il deploy, quindi invece di
 * maxmind-db/reader c'e' questo: legge il file a pezzi (fseek/fread) senza
 * mai caricarlo tutto in memoria, e basta per DB-IP City Lite e GeoLite2.
 * Formato: https://maxmind.github.io/MaxMind-DB/
 */
final class MmdbReader
{
    private const MARKER = "\xAB\xCD\xEFMaxMind.com";

    /** @var resource */
    private $handle;

    private int $nodeCount;

    private int $recordSize;

    private int $ipVersion;

    private int $nodeBytes;

    private int $treeSize;

    private int $dataStart;

    private ?int $ipv4Start = null;

    public function __construct(string $path)
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Archivio geografico non leggibile.');
        }

        $this->handle = $handle;

        $size = filesize($path) ?: 0;
        $tail = $this->read(max(0, $size - 131072), min($size, 131072));
        $at = strrpos($tail, self::MARKER);

        if ($at === false) {
            throw new RuntimeException('Non e\' un archivio MaxMind DB.');
        }

        $offset = 0;
        $meta = $this->decode(substr($tail, $at + strlen(self::MARKER)), $offset, 0);

        $this->nodeCount = (int) ($meta['node_count'] ?? 0);
        $this->recordSize = (int) ($meta['record_size'] ?? 0);
        $this->ipVersion = (int) ($meta['ip_version'] ?? 4);

        if ($this->nodeCount < 1 || ! in_array($this->recordSize, [24, 28, 32], true)) {
            throw new RuntimeException('Archivio MaxMind DB non valido.');
        }

        $this->nodeBytes = intdiv($this->recordSize, 4);
        $this->treeSize = $this->nodeCount * $this->nodeBytes;
        $this->dataStart = $this->treeSize + 16;
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /** I dati di un indirizzo IP, o null se l'archivio non lo conosce. */
    public function get(string $ip): ?array
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        $bits = strlen($packed) * 8;

        if ($bits === 128 && $this->ipVersion === 4) {
            return null;
        }

        $node = 0;
        $start = 0;

        // Un indirizzo IPv4 in un albero IPv6 sta sotto ::/96.
        if ($bits === 32 && $this->ipVersion === 6) {
            $node = $this->ipv4Start();
            $start = 0;
        }

        for ($i = $start; $i < $bits && $node < $this->nodeCount; $i++) {
            $bit = (ord($packed[$i >> 3]) >> (7 - ($i & 7))) & 1;
            $node = $this->record($node, $bit);
        }

        if ($node === $this->nodeCount) {
            return null;
        }

        if ($node < $this->nodeCount) {
            return null;
        }

        $offset = $this->treeSize + ($node - $this->nodeCount);
        $value = $this->valueAt($offset);

        return is_array($value) ? $value : null;
    }

    private function ipv4Start(): int
    {
        if ($this->ipv4Start !== null) {
            return $this->ipv4Start;
        }

        $node = 0;

        for ($i = 0; $i < 96 && $node < $this->nodeCount; $i++) {
            $node = $this->record($node, 0);
        }

        return $this->ipv4Start = $node;
    }

    private function record(int $node, int $side): int
    {
        $bytes = $this->read($node * $this->nodeBytes, $this->nodeBytes);
        $b = array_values(unpack('C*', $bytes));

        return match ($this->recordSize) {
            24 => $side === 0
                ? ($b[0] << 16) | ($b[1] << 8) | $b[2]
                : ($b[3] << 16) | ($b[4] << 8) | $b[5],
            28 => $side === 0
                ? (($b[3] & 0xF0) << 20) | ($b[0] << 16) | ($b[1] << 8) | $b[2]
                : (($b[3] & 0x0F) << 24) | ($b[4] << 16) | ($b[5] << 8) | $b[6],
            default => $side === 0
                ? ($b[0] << 24) | ($b[1] << 16) | ($b[2] << 8) | $b[3]
                : ($b[4] << 24) | ($b[5] << 16) | ($b[6] << 8) | $b[7],
        };
    }

    private function read(int $offset, int $length): string
    {
        fseek($this->handle, $offset);

        return (string) fread($this->handle, $length);
    }

    /** Legge il valore che comincia a `$offset` del file, leggendo il necessario. */
    private function valueAt(int $offset): mixed
    {
        // Una voce di citta' sta in poche centinaia di byte: una lettura sola basta quasi sempre.
        $buffer = $this->read($offset, 4096);
        $pos = 0;

        try {
            return $this->decode($buffer, $pos, $this->dataStart, $offset);
        } catch (\OutOfRangeException) {
            $buffer = $this->read($offset, 65536);
            $pos = 0;

            return $this->decode($buffer, $pos, $this->dataStart, $offset);
        }
    }

    /**
     * Decodifica un valore dal buffer. I puntatori dei dati si riferiscono
     * all'inizio della sezione dati: per seguirli si legge dal file.
     *
     * @param  int  $base  inizio della sezione dati (0 per i metadati)
     * @param  int  $origin  posizione nel file del primo byte del buffer
     */
    private function decode(string $buf, int &$pos, int $base, int $origin = 0, int $depth = 0): mixed
    {
        if ($depth > 8) {
            throw new RuntimeException('Archivio troppo annidato.');
        }

        $ctrl = $this->byte($buf, $pos);
        $type = $ctrl >> 5;

        if ($type === 1) {
            $ss = ($ctrl >> 3) & 3;
            $vvv = $ctrl & 7;
            $n = match ($ss) {
                0 => ($vvv << 8) | $this->byte($buf, $pos),
                1 => ((($vvv << 16) | $this->uint($buf, $pos, 2))) + 2048,
                2 => ((($vvv << 24) | $this->uint($buf, $pos, 3))) + 526336,
                default => $this->uint($buf, $pos, 4),
            };

            $target = $base + $n;
            $chunk = $this->read($target, 4096);
            $inner = 0;

            try {
                return $this->decode($chunk, $inner, $base, $target, $depth + 1);
            } catch (\OutOfRangeException) {
                $chunk = $this->read($target, 65536);
                $inner = 0;

                return $this->decode($chunk, $inner, $base, $target, $depth + 1);
            }
        }

        if ($type === 0) {
            $type = 7 + $this->byte($buf, $pos);
        }

        $size = $ctrl & 31;

        if ($type !== 14) {
            if ($size === 29) {
                $size = 29 + $this->byte($buf, $pos);
            } elseif ($size === 30) {
                $size = 285 + $this->uint($buf, $pos, 2);
            } elseif ($size === 31) {
                $size = 65821 + $this->uint($buf, $pos, 3);
            }
        }

        switch ($type) {
            case 2: // utf8
                return $this->take($buf, $pos, $size);
            case 3: // double
                return unpack('E', $this->take($buf, $pos, 8))[1];
            case 15: // float
                return unpack('G', $this->take($buf, $pos, 4))[1];
            case 4: // bytes
                return $this->take($buf, $pos, $size);
            case 5: // uint16
            case 6: // uint32
            case 8: // int32
            case 9: // uint64
                return $size === 0 ? 0 : $this->uint($buf, $pos, $size);
            case 10: // uint128
                return bin2hex($this->take($buf, $pos, $size));
            case 7: // map
                $map = [];
                for ($i = 0; $i < $size; $i++) {
                    $key = $this->decode($buf, $pos, $base, $origin, $depth + 1);
                    $map[(string) $key] = $this->decode($buf, $pos, $base, $origin, $depth + 1);
                }

                return $map;
            case 11: // array
                $list = [];
                for ($i = 0; $i < $size; $i++) {
                    $list[] = $this->decode($buf, $pos, $base, $origin, $depth + 1);
                }

                return $list;
            case 14: // boolean: il valore e' nella dimensione
                return $size === 1;
            default:
                throw new RuntimeException('Tipo di dato sconosciuto nell\'archivio.');
        }
    }

    private function byte(string $buf, int &$pos): int
    {
        if ($pos >= strlen($buf)) {
            throw new \OutOfRangeException;
        }

        return ord($buf[$pos++]);
    }

    private function take(string $buf, int &$pos, int $length): string
    {
        if ($pos + $length > strlen($buf)) {
            throw new \OutOfRangeException;
        }

        $out = substr($buf, $pos, $length);
        $pos += $length;

        return $out;
    }

    private function uint(string $buf, int &$pos, int $length): int
    {
        $value = 0;

        foreach (str_split($this->take($buf, $pos, $length)) as $char) {
            $value = ($value << 8) | ord($char);
        }

        return $value;
    }
}
