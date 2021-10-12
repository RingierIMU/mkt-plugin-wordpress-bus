<?php
/**
 * A some handy functions to use directly without namespace
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use RingierBusPlugin\Enum;

/**
 * Wrapper to log Messages in a custom log file
 * @param $message
 * @throws \Exception
 */
function infologthis($message)
{
    $log = new Logger('ringier_bus_plugin_log');
    $stream = new StreamHandler(WP_CONTENT_DIR . DS . 'bus_plugin.log', Logger::INFO);
    $log->pushHandler($stream);
    $log->info($message);
    unset($log);
    unset($stream);
}

/**
 * Wrapper to log Error Messages in a custom log file
 * @param $message
 * @throws \Exception
 */
function errorlogthis($message)
{
    $log = new Logger('ringier_bus_plugin_error_log');
    $stream = new StreamHandler(WP_CONTENT_DIR . DS . 'bus_plugin_error.log', Logger::ERROR);
    $log->pushHandler($stream);
    $log->error($message);
    unset($log);
    unset($stream);
}

/**
 * The WordPress locale is not necessary the locale we want
 * So we are kinda manually setting it for use, mainly in API request
 *
 * @return mixed|string
 */
function getLocale()
{
    if (isset($_ENV[Enum::ENV_BUS_API_LOCALE])) {
        return $_ENV[Enum::ENV_BUS_API_LOCALE];
    }

    return 'en_KE';
}
