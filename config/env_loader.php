<?php

class EnvLoader
{
    /**
     * Load key=value pairs from a .env style file into the current process environment.
     */
    public static function load(string $path, bool $override = false): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || self::startsWith($line, '#')) {
                continue;
            }

            // Allow leading "export " syntax.
            if (self::startsWith($line, 'export ')) {
                $line = substr($line, 7);
            }

            $delimiterPos = strpos($line, '=');
            if ($delimiterPos === false) {
                continue;
            }

            $name = trim(substr($line, 0, $delimiterPos));
            if ($name === '') {
                continue;
            }

            $value = trim(substr($line, $delimiterPos + 1));
            $value = self::parseValue($value);

            if (!$override && getenv($name) !== false) {
                continue;
            }

            self::setEnv($name, $value);
        }
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $inner = substr($value, 1, -1);
            if ($first === '"') {
                return stripcslashes($inner);
            }

            return $inner;
        }

        // Remove inline comments (everything after an unescaped #)
        $hashPos = strpos($value, '#');
        if ($hashPos !== false) {
            $value = rtrim(substr($value, 0, $hashPos));
        }

        return $value;
    }

    private static function setEnv(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    private static function startsWith(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}
