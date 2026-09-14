<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Renders the schema reference from the migration files.
 *
 * It reads each migration's up() as PHP tokens rather than asking a database,
 * so the output is the same on every machine and driver. Each column shows the
 * Blueprint call and modifiers the migration wrote, and any comment after it.
 */
final class SchemaDocument
{
    private const COLUMN_TYPES = [
        'string', 'char', 'text', 'tinyText', 'mediumText', 'longText',
        'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger',
        'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger', 'unsignedBigInteger',
        'increments', 'tinyIncrements', 'smallIncrements', 'mediumIncrements', 'bigIncrements',
        'decimal', 'float', 'double', 'boolean',
        'date', 'dateTime', 'datetime', 'dateTimeTz', 'time', 'timeTz', 'timestamp', 'timestampTz', 'year',
        'json', 'jsonb', 'enum', 'set', 'uuid', 'ulid', 'binary', 'ipAddress', 'macAddress',
        'foreignId', 'foreignUuid', 'foreignUlid', 'geometry', 'point',
    ];

    private const INDEX_CALLS = ['index', 'unique', 'primary', 'fullText', 'spatialIndex'];

    private const SKIP = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    public static function render(string $directory): string
    {
        $files = glob(rtrim($directory, '/\\') . '/*.php') ?: [];
        sort($files);

        $tables = [];
        $sections = [];

        foreach ($files as $path) {
            $file = basename($path);
            $sections[$file] = ['created' => [], 'changed' => [], 'notes' => []];
            $up = self::functionBody(self::tokens((string) file_get_contents($path)), 'up');

            foreach (self::blueprints($up) as [$verb, $table, $statements]) {
                if ($verb === 'create') {
                    $tables[$table] = ['columns' => [], 'indexes' => [], 'keys' => [], 'other' => [], 'later' => []];
                    $sections[$file]['created'][] = $table;
                } else {
                    $sections[$file]['changed'][] = $table;
                }

                foreach ($statements as [$calls, $comment, $raw]) {
                    if ($verb === 'create') {
                        self::describe($tables[$table], $calls, $comment, $raw);
                    } elseif (isset($tables[$table])) {
                        $tables[$table]['later'][] = "`{$file}`: `" . self::statement($calls, $raw) . '`';
                    } else {
                        $sections[$file]['notes'][] = "`{$table}`: `" . self::statement($calls, $raw) . '`';
                    }
                }
            }

            foreach (self::rawStatements($up) as $sql) {
                if (preg_match('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+\w+\s+ON\s+(\w+)/i', $sql, $m) && isset($tables[$m[1]])) {
                    $tables[$m[1]]['later'][] = "`{$file}`: `{$sql}`";
                    $sections[$file]['changed'][] = $m[1];
                } else {
                    $sections[$file]['notes'][] = "`{$sql}`";
                }
            }
        }

        return self::markdown($tables, $sections);
    }

    private static function describe(array &$table, ?array $calls, ?string $comment, string $raw): void
    {
        if ($calls === null) {
            $table['other'][] = '`' . $raw . '`';

            return;
        }

        [$method, $argText, $args] = $calls[0];
        $details = implode(', ', array_map(
            fn (array $call) => $call[1] === '' ? $call[0] : "{$call[0]}({$call[1]})",
            array_slice($calls, 1),
        ));
        if ($comment !== null) {
            $details = ltrim($details . ' — ' . $comment, ' —');
        }
        $name = isset($args[0]) ? self::unquote($args[0]) : null;

        switch (true) {
            case $method === 'id':
                $table['columns'][] = [$name ?? 'id', 'id', $details];
                break;
            case in_array($method, ['timestamps', 'timestampsTz', 'nullableTimestamps'], true):
                $table['columns'][] = ['created_at', 'timestamp', 'nullable'];
                $table['columns'][] = ['updated_at', 'timestamp', 'nullable'];
                break;
            case in_array($method, ['softDeletes', 'softDeletesTz'], true):
                $table['columns'][] = [$name ?? 'deleted_at', 'timestamp', 'nullable'];
                break;
            case $method === 'rememberToken':
                $table['columns'][] = ['remember_token', 'string(100)', 'nullable'];
                break;
            case in_array($method, ['morphs', 'nullableMorphs', 'uuidMorphs', 'nullableUuidMorphs'], true):
                $nullable = str_starts_with($method, 'nullable') ? 'nullable, ' : '';
                $idType = str_contains($method, 'Uuid') || str_contains($method, 'uuid') ? 'uuid' : 'unsignedBigInteger';
                $table['columns'][] = [$name . '_type', 'string', rtrim($nullable, ', ')];
                $table['columns'][] = [$name . '_id', $idType, rtrim($nullable, ', ')];
                $table['indexes'][] = "`{$method}({$argText})`";
                break;
            case in_array($method, ['uuid', 'ulid'], true) && $name === null:
                $table['columns'][] = [$method, $method, $details];
                break;
            case in_array($method, self::COLUMN_TYPES, true) && $name !== null:
                $rest = array_slice($args, 1);
                $table['columns'][] = [$name, $rest === [] ? $method : $method . '(' . implode(', ', $rest) . ')', $details];
                break;
            case in_array($method, self::INDEX_CALLS, true):
                $table['indexes'][] = '`' . self::statement($calls, $raw) . '`';
                break;
            case $method === 'foreign':
                $table['keys'][] = '`' . self::statement($calls, $raw) . '`';
                break;
            default:
                $table['other'][] = '`' . self::statement($calls, $raw) . '`';
        }
    }

    private static function markdown(array $tables, array $sections): string
    {
        $out = [
            '# Database Schema',
            '',
            'Generated from `database/migrations` by `php artisan schema:doc`. Do not edit',
            'this file by hand: change a migration, then run the command again. Each',
            'column shows the Blueprint call and modifiers the migration wrote.',
            '',
            sprintf('%d tables across %d migrations.', count($tables), count($sections)),
            '',
            '## Contents',
            '',
        ];

        foreach ($sections as $file => $section) {
            $listed = match (true) {
                $section['created'] !== [] => implode(', ', $section['created']),
                $section['changed'] !== [] => 'changes to ' . implode(', ', array_unique($section['changed'])),
                default => 'data only, no schema changes',
            };
            $out[] = "- `{$file}`: {$listed}";
        }

        foreach ($sections as $file => $section) {
            $out[] = '';
            $out[] = "## {$file}";

            if ($section['created'] === [] && $section['changed'] !== []) {
                $out[] = '';
                $out[] = 'Changes tables created earlier: '
                    . implode(', ', array_map(fn (string $t) => "`{$t}`", array_values(array_unique($section['changed'])))) . '.';
            }

            if ($section['created'] === [] && $section['changed'] === [] && $section['notes'] === []) {
                $out[] = '';
                $out[] = 'Changes no table structure; it only reads or writes data.';
            }

            foreach ($section['notes'] as $note) {
                $out[] = '';
                $out[] = "- {$note}";
            }

            foreach ($section['created'] as $name) {
                $table = $tables[$name];
                $out[] = '';
                $out[] = "### {$name}";
                $out[] = '';
                $out[] = '| Column | Type | Details |';
                $out[] = '|---|---|---|';

                foreach ($table['columns'] as [$column, $type, $details]) {
                    $out[] = '| ' . self::cell($column) . ' | `' . self::cell($type) . '` | ' . self::cell($details) . ' |';
                }

                foreach (['indexes' => 'Indexes', 'keys' => 'Foreign keys', 'later' => 'Added by later migrations', 'other' => 'Other'] as $key => $title) {
                    if ($table[$key] === []) {
                        continue;
                    }

                    $out[] = '';
                    $out[] = "{$title}:";
                    $out[] = '';
                    foreach ($table[$key] as $line) {
                        $out[] = "- {$line}";
                    }
                }
            }
        }

        return implode("\n", $out) . "\n";
    }

    /** @return list<array{0: int|null, 1: string}> */
    private static function tokens(string $source): array
    {
        return array_map(fn ($t) => is_array($t) ? [$t[0], $t[1]] : [null, $t], token_get_all($source));
    }

    private static function functionBody(array $tokens, string $name): array
    {
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] !== T_FUNCTION) {
                continue;
            }

            $at = self::next($tokens, $i);
            if ($at === null || $tokens[$at][1] !== $name) {
                continue;
            }

            for ($open = $at; $open < $count && $tokens[$open][1] !== '{'; $open++);

            return self::block($tokens, $open)[0];
        }

        return [];
    }

    /** @return array{0: array, 1: int} the tokens inside the braces, and the index of the closing brace */
    private static function block(array $tokens, int $open): array
    {
        $depth = 0;
        $count = count($tokens);

        for ($i = $open; $i < $count; $i++) {
            if ($tokens[$i][1] === '{' || in_array($tokens[$i][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                $depth++;
            } elseif ($tokens[$i][1] === '}' && --$depth === 0) {
                return [array_slice($tokens, $open + 1, $i - $open - 1), $i];
            }
        }

        return [[], $count];
    }

    /** @return list<array{0: string, 1: string, 2: list<array{0: ?array, 1: ?string, 2: string}>}> */
    private static function blueprints(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] !== T_STRING || $tokens[$i][1] !== 'Schema') {
                continue;
            }

            $colon = self::next($tokens, $i);
            $verb = $colon === null ? null : self::next($tokens, $colon);
            $paren = $verb === null ? null : self::next($tokens, $verb);
            $name = $paren === null ? null : self::next($tokens, $paren);

            if ($name === null
                || $tokens[$colon][0] !== T_DOUBLE_COLON
                || !in_array($tokens[$verb][1], ['create', 'table'], true)
                || $tokens[$name][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            for ($open = $name; $open < $count && $tokens[$open][1] !== '{'; $open++);
            [$body, $close] = self::block($tokens, $open);

            $found[] = [$tokens[$verb][1], self::unquote($tokens[$name][1]), self::statements($body)];
            $i = $close;
        }

        return $found;
    }

    /** @return list<string> the SQL of each DB::statement call */
    private static function rawStatements(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] !== T_STRING || $tokens[$i][1] !== 'DB') {
                continue;
            }

            $colon = self::next($tokens, $i);
            $method = $colon === null ? null : self::next($tokens, $colon);
            $paren = $method === null ? null : self::next($tokens, $method);
            $sql = $paren === null ? null : self::next($tokens, $paren);

            if ($sql !== null && $tokens[$method][1] === 'statement' && $tokens[$sql][0] === T_CONSTANT_ENCAPSED_STRING) {
                $found[] = self::unquote($tokens[$sql][1]);
            }
        }

        return $found;
    }

    /** @return list<array{0: ?array, 1: ?string, 2: string}> each $table statement: its calls, trailing comment and source */
    private static function statements(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i][0] !== T_VARIABLE) {
                continue;
            }

            $depth = 0;
            $statement = [];
            for ($end = $i; $end < $count; $end++) {
                $text = $tokens[$end][1];
                if ($text === '(' || $text === '[') {
                    $depth++;
                } elseif ($text === ')' || $text === ']') {
                    $depth--;
                } elseif ($text === ';' && $depth === 0) {
                    break;
                }
                $statement[] = $tokens[$end];
            }

            $comment = null;
            for ($k = $end + 1; $k < $count; $k++) {
                if ($tokens[$k][0] === T_WHITESPACE && !str_contains($tokens[$k][1], "\n")) {
                    continue;
                }
                if ($tokens[$k][0] === T_COMMENT && str_starts_with($tokens[$k][1], '//')) {
                    $comment = trim(substr($tokens[$k][1], 2));
                }
                break;
            }

            $found[] = [self::calls($statement), $comment, self::source($statement)];
            $i = $end;
        }

        return $found;
    }

    /** @return list<array{0: string, 1: string, 2: list<string>}>|null each call's name, argument text and arguments; null if not a plain call chain */
    private static function calls(array $tokens): ?array
    {
        $tokens = array_values(array_filter($tokens, fn (array $t) => !in_array($t[0], self::SKIP, true)));
        $count = count($tokens);

        if ($count === 0 || $tokens[0][0] !== T_VARIABLE) {
            return null;
        }

        $calls = [];
        $i = 1;

        while ($i < $count) {
            if ($tokens[$i][0] !== T_OBJECT_OPERATOR
                || ($tokens[$i + 1][0] ?? null) !== T_STRING
                || ($tokens[$i + 2][1] ?? null) !== '(') {
                return null;
            }

            $depth = 0;
            $inner = [];
            for ($j = $i + 2; $j < $count; $j++) {
                $text = $tokens[$j][1];
                if ($text === '(' || $text === '[') {
                    if ($depth++ === 0) {
                        continue;
                    }
                } elseif (($text === ')' || $text === ']') && --$depth === 0) {
                    break;
                }
                $inner[] = $tokens[$j];
            }

            $calls[] = [$tokens[$i + 1][1], self::join($inner), self::split($inner)];
            $i = $j + 1;
        }

        return $calls === [] ? null : $calls;
    }

    private static function statement(?array $calls, string $raw): string
    {
        if ($calls === null) {
            return $raw;
        }

        return '$table->' . implode('->', array_map(fn (array $call) => "{$call[0]}({$call[1]})", $calls));
    }

    /** @return list<string> the top-level arguments, each as text */
    private static function split(array $tokens): array
    {
        $args = [];
        $current = [];
        $depth = 0;

        foreach ($tokens as $token) {
            $text = $token[1];
            if ($text === '(' || $text === '[') {
                $depth++;
            } elseif ($text === ')' || $text === ']') {
                $depth--;
            } elseif ($text === ',' && $depth === 0) {
                $args[] = self::join($current);
                $current = [];
                continue;
            }
            $current[] = $token;
        }

        if ($current !== []) {
            $args[] = self::join($current);
        }

        return $args;
    }

    private static function join(array $tokens): string
    {
        $text = '';
        foreach ($tokens as $token) {
            $text .= match (true) {
                $token[1] === ',' => ', ',
                $token[0] === T_DOUBLE_ARROW => ' => ',
                default => $token[1],
            };
        }

        return rtrim($text, ', ');
    }

    private static function source(array $tokens): string
    {
        $text = '';
        foreach ($tokens as $token) {
            if (!in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $text .= $token[1];
            }
        }

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private static function next(array $tokens, int $i): ?int
    {
        $count = count($tokens);
        for ($j = $i + 1; $j < $count; $j++) {
            if (!in_array($tokens[$j][0], self::SKIP, true)) {
                return $j;
            }
        }

        return null;
    }

    private static function unquote(string $literal): string
    {
        return stripslashes(substr($literal, 1, -1));
    }

    private static function cell(string $text): string
    {
        return str_replace('|', '\|', $text);
    }
}
