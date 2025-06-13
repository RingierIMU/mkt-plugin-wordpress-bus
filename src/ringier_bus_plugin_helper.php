<?php
/**
 * A some handy functions to use directly without namespace
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */
use RingierBusPlugin\Enum;

/**
 * Wrapper to log Error Messages in a custom log file
 *
 * @param string $message
 * @param string $level
 */
function ringier_errorlogthis(string $message, string $level = 'ERROR'): void
{
    try {
        $log_file = RINGIER_BUS_PLUGIN_ERROR_LOG_FILE;

        // Ensure the directory exists
        if (!file_exists(dirname($log_file))) {
            wp_mkdir_p(dirname($log_file));
        }

        // Append the error message to the log file
        $timestamp = date('Y-m-d H:i:s');
        $formatted_message = "[{$timestamp}] ERROR: {$message}" . PHP_EOL;
        if ($level !== 'ERROR') {
            $formatted_message = "[{$timestamp}] {$level}: {$message}" . PHP_EOL;
        }

        file_put_contents($log_file, $formatted_message, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { // fallback
        error_log('BUS_PLUGIN:: Logging failure in ringier_errorlogthis(): ' . $e->getMessage());
    }
}

/**
 * The WordPress locale is not necessary the locale we want
 * So we are kinda manually setting it for use, mainly in API request
 *
 * @return mixed|string
 */
function ringier_getLocale(): mixed
{
    if (isset($_ENV[Enum::ENV_BUS_API_LOCALE])) {
        return $_ENV[Enum::ENV_BUS_API_LOCALE];
    }

    return 'en_KE';
}
