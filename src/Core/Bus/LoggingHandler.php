<?php
/**
 * A singleton class to help logg errors and send to specific medium like file-based or slack..etc
 * For now we will only send to slack.
 * Slack parameters are set in wp-content/app/config/.env
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use Monolog\Handler\SlackWebhookHandler;
use Monolog\Logger;
use RingierBusPlugin\Enum;

class LoggingHandler
{
    /*
     * Singleton implementation
     * Let's make sure this class abide by the rule of this pattern
     */
    private static $_instance = null;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public function __wakeup()
    {
    }

    /**
     * The single globally access point to this class
     *
     * @throws \Monolog\Handler\MissingExtensionException
     *
     * @return Logger|null
     */
    public static function getInstance()
    {
        if (!is_object(self::$_instance)) {

            //create handler(s)
            $slackHandler = new SlackWebhookHandler(
                $_ENV[Enum::ENV_SLACK_HOOK_URL],
                $_ENV[Enum::ENV_SLACK_CHANNEL_NAME],
                $_ENV[Enum::ENV_SLACK_BOT_NAME]
            );

            //build our logger
            self::$_instance = new Logger(Enum::ENV_SLACK_CHANNEL_NAME);

            //push handler into logger's stack
            self::$_instance->pushHandler($slackHandler);
        }

        return self::$_instance;
    }
}
