<?php
/**
 * Simple .env loader for PHP
 * Loads environment variables from .env file
 */

function loadEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        throw new Exception('.env file not found at: ' . $path);
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse line
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Set environment variable
            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}

/**
 * Get environment variable value
 * 
 * @param string $key Variable name
 * @param mixed $default Default value if not found
 * @return mixed
 */
function env($key, $default = null) {
    $value = getenv($key);
    
    if ($value === false) {
        $value = $_ENV[$key] ?? $default;
    }
    
    return $value;
}

// Auto-load .env file when this file is included
try {
    loadEnv();
} catch (Exception $e) {
    // Silently fail if .env not found (use .env.example as template)
    error_log('Warning: ' . $e->getMessage());
}
?>
