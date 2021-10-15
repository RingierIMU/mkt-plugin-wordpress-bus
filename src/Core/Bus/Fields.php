<?php
/**
 * Mapping the fields on Admin UI onto this class
 * FYI this is related to our BUS API plugin
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */

namespace RingierBusPlugin\Bus;

use RingierBusPlugin\Enum;
use RingierBusPlugin\Utils;

class Fields
{
    public string $field_bus_status;
    public string $field_venture_config;
    public string $field_bus_api_username;
    public string $field_bus_api_password;
    public string $field_bus_api_endpoint;
    public int $field_bus_backoff_duration;
    public string $field_bus_locale;
    public string $field_app_key;

    //slack
    public string $field_bus_slack_hook_url;
    public string $field_bus_slack_channel_name;
    public string $field_bus_slack_bot_name;

    public bool $is_bus_enabled;
    public bool $is_slack_enabled;

    public function __construct()
    {
        $optionList = get_option(Enum::SETTINGS_PAGE_OPTION_NAME);

        $this->field_bus_status = 'off';
        $this->is_bus_enabled = false;

        if (is_array($optionList) && (Utils::notEmptyOrNull($optionList))) {
            $this->field_bus_status = $optionList['field_bus_status'];
        }

        if ($this->field_bus_status === 'on') {
            ringier_infologthis('[field] bus is ON - set by user');
            $this->is_bus_enabled = true;

            $this->initBusFields($optionList);
            $this->initSlackFields($optionList);
            $this->load_vars_into_env();
        }
    }

    /**
     * Populate all fields that the BUS API Class needs
     * If any of those fields is empty, BUS sync will be turned OFF
     *
     * @param $optionList
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function initBusFields($optionList)
    {
        $this->field_bus_locale = '';
        $this->field_app_key = '';
        $this->field_venture_config = '';
        $this->field_bus_api_username = '';
        $this->field_bus_api_password = '';
        $this->field_bus_api_endpoint = '';
        $this->field_bus_backoff_duration = 0;

        if ($this->is_bus_enabled === true) {
            if (isset($optionList[Enum::FIELD_VENTURE_CONFIG])) {
                $this->field_venture_config = $optionList[Enum::FIELD_VENTURE_CONFIG];
            }
            if (isset($optionList[Enum::FIELD_API_USERNAME])) {
                $this->field_bus_api_username = $optionList[Enum::FIELD_API_USERNAME];
            }
            if (isset($optionList[Enum::FIELD_API_PASSWORD])) {
                $this->field_bus_api_password = $optionList[Enum::FIELD_API_PASSWORD];
            }
            if (isset($optionList[Enum::FIELD_API_ENDPOINT])) {
                $this->field_bus_api_endpoint = $optionList[Enum::FIELD_API_ENDPOINT];
            }
            if (isset($optionList[Enum::FIELD_BACKOFF_DURATION])) {
                $this->field_bus_backoff_duration = $optionList[Enum::FIELD_BACKOFF_DURATION];
            }
            if (isset($optionList[Enum::FIELD_APP_LOCALE])) {
                $this->field_bus_locale = $optionList[Enum::FIELD_APP_LOCALE];
            }
            if (isset($optionList[Enum::FIELD_APP_KEY])) {
                $this->field_app_key = $optionList[Enum::FIELD_APP_KEY];
            }
        }

        $error = '';
        if (!Utils::notEmptyOrNull($this->field_venture_config)) {
            $error .= 'field_venture_config|';
            ringier_infologthis('[fields] Venture config is empty');
        }
        if (!Utils::notEmptyOrNull($this->field_bus_api_username)) {
            $error .= 'field_bus_api_username|';
            ringier_infologthis('[fields] API username is empty');
        }
        if (!Utils::notEmptyOrNull($this->field_bus_api_password)) {
            $error .= 'field_bus_api_password|';
            ringier_infologthis('[fields] API password is empty');
        }
        if (!Utils::notEmptyOrNull($this->field_bus_api_endpoint)) {
            $error .= 'field_bus_api_endpoint|';
            ringier_infologthis('[fields] API endpoint url is empty');
        }

        if (mb_strlen($error) > 0) {
            $this->is_bus_enabled = false;
            ringier_infologthis('[field] setting BUS to OFF - by rule, as one field is empty');

            return false;
        }

        if (!Utils::notEmptyOrNull($this->field_bus_backoff_duration)) {
            $error .= 'field_bus_backoff_duration|';
            ringier_infologthis('[fields] Backoff duration was empty, setting it to default 30mins');
            $this->field_bus_backoff_duration = 30;
        }

        if (!Utils::notEmptyOrNull($this->field_bus_locale)) {
            $error .= 'field_bus_locale|';
            ringier_infologthis('[fields] Locale was empty, setting it to default en_KE');
            $this->field_bus_locale = 'en_KE';
        }

        return true;
    }

    /**
     * Populate all fields that relates to Slack channel
     * This channel will be sent messages in case of error.
     * If any of those fields is empty, Slack sync will be turned OFF
     *
     * @param $optionList
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function initSlackFields($optionList)
    {
        $this->field_bus_slack_hook_url = '';
        $this->field_bus_slack_channel_name = '';
        $this->field_bus_slack_bot_name = '';
        $this->is_slack_enabled = true;

        if ($this->is_bus_enabled === true) {
            $this->field_bus_slack_hook_url = $optionList['field_bus_slack_hook_url'];
            $this->field_bus_slack_channel_name = $optionList['field_bus_slack_channel_name'];
            $this->field_bus_slack_bot_name = $optionList['field_bus_slack_bot_name'];
        }

        $error = '';
        if (!Utils::notEmptyOrNull($this->field_bus_slack_hook_url)) {
            $error .= 'Field Slack Hook URL || ';
            ringier_infologthis('[fields] Slack hook url is empty');
        }
        if (!Utils::notEmptyOrNull($this->field_bus_slack_channel_name)) {
            $error .= 'Field Slack Channel Name';
            ringier_infologthis('[fields] Slack Channel name is empty');
        }

        if (mb_strlen($error) > 0) {
            $this->is_slack_enabled = false;
            ringier_infologthis('[field] setting SLACK LOGGING to OFF - by rule, as one field is empty');
            ringier_errorlogthis('[Slack Fields] - The following appear to be empty:');
            ringier_errorlogthis($error);

            return false;
        }

        if (!Utils::notEmptyOrNull($this->field_bus_slack_bot_name)) {
            $error .= 'field_bus_slack_bot_name|';
            ringier_infologthis('[fields] Slack BOT name is empty, naming it DEFAULT_BLOG_BOT');
            $this->field_bus_slack_bot_name = 'DEFAULT_BLOG_BOT';
        }

        return true;
    }

    /**
     * Load all fields onto the global $_ENV
     * Will only load if bus is enabled..etc
     */
    public function load_vars_into_env()
    {
        if ($this->is_bus_enabled === true) {
            $_ENV[Enum::ENV_BUS_ENDPOINT] = $this->field_bus_api_endpoint;
            $_ENV[Enum::ENV_BACKOFF_FOR_MINUTES] = $this->field_bus_backoff_duration;
            $_ENV[Enum::ENV_VENTURE_CONFIG] = $this->field_venture_config;
            $_ENV[Enum::ENV_BUS_API_USERNAME] = $this->field_bus_api_username;
            $_ENV[Enum::ENV_BUS_API_PASSWORD] = $this->field_bus_api_password;
            $_ENV[Enum::ENV_BUS_API_LOCALE] = $this->field_bus_locale;
            $_ENV[Enum::ENV_BUS_APP_KEY] = $this->field_app_key;
        }

        if ($this->is_slack_enabled === true) {
            $_ENV[Enum::ENV_SLACK_ENABLED] = 'ON';
            $_ENV[Enum::ENV_SLACK_HOOK_URL] = $this->field_bus_slack_hook_url;
            $_ENV[Enum::ENV_SLACK_CHANNEL_NAME] = $this->field_bus_slack_channel_name;
            $_ENV[Enum::ENV_SLACK_BOT_NAME] = $this->field_bus_slack_bot_name;
        }
    }
}
