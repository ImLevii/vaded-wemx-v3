<?php

namespace App\Services;

use RuntimeException;

class LegacyWemxDumpReader
{
    private const TABLES = ['categories', 'packages', 'package_prices', 'package_features', 'package_settings', 'package_config_options', 'users', 'addresses', 'user_2fa'];

    /** @return array<string, list<array<string, string|null>>> */
    public function read(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read the legacy dump.');
        }

        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = [];
            if (! preg_match('/^CREATE TABLE `'.preg_quote($table, '/').'` \((.*?)^\)/ms', $sql, $schema)) {
                continue;
            }
            preg_match_all('/^\s*`([^`]+)`\s/m', $schema[1], $columns);
            preg_match_all('/^INSERT INTO `'.preg_quote($table, '/').'` VALUES\s*/m', $sql, $inserts, PREG_OFFSET_CAPTURE);
            foreach ($inserts[0] as [$header, $offset]) {
                $position = $offset + strlen($header);
                do {
                    $this->expect($sql, $position, '(');
                    $values = [];
                    do {
                        $values[] = $this->value($sql, $position);
                        $this->whitespace($sql, $position);
                        $delimiter = $sql[$position++] ?? '';
                        if (! in_array($delimiter, [',', ')'], true)) {
                            throw new RuntimeException("Invalid value delimiter in {$table}.");
                        }
                    } while ($delimiter === ',');
                    if (count($values) !== count($columns[1])) {
                        throw new RuntimeException("Column count mismatch in {$table}.");
                    }
                    $tables[$table][] = array_combine($columns[1], $values);
                    $this->whitespace($sql, $position);
                    $delimiter = $sql[$position++] ?? '';
                    if (! in_array($delimiter, [',', ';'], true)) {
                        throw new RuntimeException("Invalid row delimiter in {$table}.");
                    }
                } while ($delimiter === ',');
            }
        }

        return $tables;
    }

    private function value(string $sql, int &$position): ?string
    {
        $this->whitespace($sql, $position);
        if (($sql[$position] ?? '') === "'") {
            $position++;
            $value = '';
            $length = strlen($sql);
            while ($position < $length) {
                $character = $sql[$position++];
                if ($character === "'") {
                    if (($sql[$position] ?? '') === "'") {
                        $value .= "'";
                        $position++;

                        continue;
                    }

                    return $value;
                }
                if ($character === '\\') {
                    $escaped = $sql[$position++] ?? '';
                    $value .= match ($escaped) {
                        '0' => "\0", 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'Z' => "\x1a",
                        '%', '_' => '\\'.$escaped,
                        default => $escaped,
                    };
                } else {
                    $value .= $character;
                }
            }
            throw new RuntimeException('Unterminated SQL string.');
        }
        if (substr($sql, $position, 4) === 'NULL') {
            $position += 4;

            return null;
        }
        if (preg_match('/\G-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/', $sql, $number, 0, $position)) {
            $position += strlen($number[0]);

            return $number[0];
        }
        throw new RuntimeException('Unsupported SQL value; only literals can be imported.');
    }

    private function expect(string $sql, int &$position, string $expected): void
    {
        $this->whitespace($sql, $position);
        if (($sql[$position++] ?? '') !== $expected) {
            throw new RuntimeException('Invalid SQL row.');
        }
    }

    private function whitespace(string $sql, int &$position): void
    {
        $position += strspn($sql, " \r\n\t", $position);
    }
}
