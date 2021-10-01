<?php
namespace RingierBusPlugin;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

function logthis($message)
{
    $log = new Logger('ringier_bus_plugin_log');
    $stream = new StreamHandler(WP_CONTENT_DIR . DS . 'bus_plugin.log', Logger::INFO);
    $log->pushHandler($stream);
//    $log->info($message);
    unset($log);
    unset($stream);
}
