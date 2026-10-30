<?php

declare(strict_types=1);

namespace Tests\Characterization\Support;

final class SqlScript
{
    /**
     * Divide scripts SQL sin romper punto y coma dentro de cadenas o comentarios.
     * Las migraciones actuales no contienen procedimientos ni DELIMITER.
     *
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $protectedSemicolon = "\x1DCLUBCHECK_SQL_SEMICOLON\x1D";
        $sql = preg_replace_callback(
            '/DELIMITER\s+\/\/\s*(.*?)\/\/\s*DELIMITER\s*;/is',
            static fn (array $match): string => str_replace(';', $protectedSemicolon, trim($match[1])) . ';',
            $sql
        ) ?? $sql;

        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= $char;
                }
                continue;
            }

            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                }
                continue;
            }

            if ($quote === null && $char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
                $lineComment = true;
                $i++;
                continue;
            }

            if ($quote === null && $char === '#') {
                $lineComment = true;
                continue;
            }

            if ($quote === null && $char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = str_replace($protectedSemicolon, ';', $statement);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = str_replace($protectedSemicolon, ';', $statement);
        }

        return $statements;
    }
}
