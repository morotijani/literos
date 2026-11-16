<?php
/**
 * LITEROS — Zero-Dependency .env Loader & Application Environment Helpers
 * Loads variables from project-root `.env` without overriding real OS environment variables.
 */

declare(strict_types=1);

if (!function_exists('loadLiterosEnv')) {
    function loadLiterosEnv(?string $envPath = null): void {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $path = $envPath ?? (__DIR__ . '/../.env');
        if (!is_file($path)) {
            return;
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            if ($key === '') {
                continue;
            }

            $val = trim(substr($line, $eqPos + 1));
            $len = strlen($val);
            if ($len >= 2) {
                $first = $val[0];
                $last = $val[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                } else {
                    // Strip unquoted inline comment (` # ...`)
                    $hashPos = strpos($val, ' #');
                    if ($hashPos !== false) {
                        $val = trim(substr($val, 0, $hashPos));
                    }
                }
            }

            // Respect existing OS-level environment variables if already defined
            if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string {
        loadLiterosEnv();
        if (array_key_exists($key, $_ENV)) {
            return (string)$_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string)$_SERVER[$key];
        }
        $val = getenv($key);
        return ($val !== false) ? (string)$val : $default;
    }
}

if (!function_exists('getAppEnv')) {
    function getAppEnv(): string {
        $raw = strtolower(trim((string)env('APP_ENV', 'development')));
        return ($raw === 'production' || $raw === 'prod') ? 'production' : 'development';
    }
}

if (!function_exists('isProductionMode')) {
    function isProductionMode(): bool {
        return getAppEnv() === 'production';
    }
}

loadLiterosEnv();
