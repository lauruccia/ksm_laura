<?php

namespace App\Support\Legacy;

use Generator;
use RuntimeException;

/**
 * Legge un dump MySQL esportato da phpMyAdmin senza caricarlo in memoria.
 *
 * Restituisce le righe degli INSERT delle sole tabelle richieste, come
 * colonna => valore. I valori arrivano come stringa o null: il tipo lo
 * decide chi importa. Non serve un server MySQL.
 */
class MysqlDumpReader
{
    private const ESCAPES = ['0' => "\0", 'b' => "\x08", 'n' => "\n", 'r' => "\r", 't' => "\t", 'Z' => "\x1a"];

    public function __construct(private string $path) {}

    /**
     * @param  array<int, string>  $tables
     * @return Generator<int, array{0: string, 1: array<string, ?string>}>
     */
    public function rows(array $tables): Generator
    {
        $handle = @fopen($this->path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Impossibile aprire il dump: {$this->path}");
        }

        try {
            $table = null;
            $columns = [];
            $buffer = '';

            while (($line = fgets($handle)) !== false) {
                if ($table === null) {
                    if (! str_starts_with($line, 'INSERT INTO `')
                        || ! preg_match('/^INSERT INTO `([^`]+)` \((.*?)\) VALUES\s*(.*)$/s', $line, $m)
                        || ! in_array($m[1], $tables, true)) {
                        continue;
                    }

                    $table = $m[1];
                    $columns = array_map(fn ($column) => trim($column, ' `'), explode(',', $m[2]));
                    $buffer = $m[3];
                } else {
                    $buffer .= $line;
                }

                // Una riga del file di solito e' una tupla, ma un testo puo'
                // andare a capo: se la tupla non si chiude si aspetta la riga dopo.
                while (($buffer = ltrim($buffer)) !== '') {
                    if ($buffer[0] === ',') {
                        $buffer = substr($buffer, 1);

                        continue;
                    }

                    if ($buffer[0] === ';') {
                        $table = null;
                        $buffer = '';

                        break;
                    }

                    if ($buffer[0] !== '(') {
                        throw new RuntimeException("Contenuto inatteso nella tabella $table: ".substr($buffer, 0, 40));
                    }

                    $offset = 0;
                    $values = $this->tuple($buffer, $offset);

                    if ($values === null) {
                        break;
                    }

                    if (count($values) !== count($columns)) {
                        throw new RuntimeException("Tabella $table: ".count($values).' valori per '.count($columns).' colonne.');
                    }

                    $buffer = substr($buffer, $offset);

                    yield [$table, array_combine($columns, $values)];
                }
            }
        } finally {
            fclose($handle);
        }
    }

    /** I valori di una tupla, o null se il testo finisce prima della parentesi di chiusura. */
    private function tuple(string $s, int &$i): ?array
    {
        $n = strlen($s);
        $values = [];
        $i = 1;

        while (true) {
            $i += strspn($s, " \t\r\n", $i);

            if ($i >= $n) {
                return null;
            }

            if ($s[$i] === "'") {
                $i++;
                $value = '';

                while (true) {
                    $length = strcspn($s, "'\\", $i);
                    $value .= substr($s, $i, $length);
                    $i += $length;

                    if ($i + 1 >= $n) {
                        return null;
                    }

                    if ($s[$i] === '\\') {
                        $value .= self::ESCAPES[$s[$i + 1]] ?? $s[$i + 1];
                        $i += 2;

                        continue;
                    }

                    if ($s[$i + 1] === "'") {
                        $value .= "'";
                        $i += 2;

                        continue;
                    }

                    $i++;

                    break;
                }

                $values[] = $value;
            } else {
                $length = strcspn($s, ',)', $i);

                if ($i + $length >= $n) {
                    return null;
                }

                $token = trim(substr($s, $i, $length));
                $i += $length;
                $values[] = strcasecmp($token, 'NULL') === 0 ? null : $token;
            }

            $i += strspn($s, " \t\r\n", $i);

            if ($i >= $n) {
                return null;
            }

            if ($s[$i] === ',') {
                $i++;

                continue;
            }

            if ($s[$i] === ')') {
                $i++;

                return $values;
            }

            throw new RuntimeException('Carattere inatteso nel dump: '.substr($s, $i, 40));
        }
    }
}
