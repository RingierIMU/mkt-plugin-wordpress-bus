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
            infologthis('--- --- ---');
            infologthis('--- --- ---');
            infologthis('[field] bus is ON - set by user');
            infologthis('--- --- ---');
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
     * @return bool
     * @throws \Exception
     */
    public function initBusFields($optionList)
    {
        $this->field_venture_config = '';
        $this->field_bus_api_username = '';
        $this->field_bus_api_password = '';
        $this->field_bus_api_endpoint = '';
        $this->field_bus_backoff_duration = '';

        if ($this->is_bus_enabled === true) {
            $this->field_venture_config = $optionList['field_venture_config'];
            $this->field_bus_api_username = $optionList['field_bus_api_username'];
            $this->field_bus_api_password = $optionList['field_bus_api_password'];
            $this->field_bus_api_endpoint = $optionList['field_bus_api_endpoint'];
            $this->field_bus_backoff_duration = $optionList['field_bus_backoff_duration'];
            $this->field_bus_locale = $optionList['field_bus_locale'];
        }

        $error = '';
        if (!Utils::notEmptyOrNull($this->field_venture_config)) {
            $error .= 'field_venture_config|';
            infologthis('[fields] Venture config is empty');
        }
        if (!Utils::notEmptyOrNull($this->field_bus_api_username)) {
            $error .= 'field_bus_api_username|';
            infologthis('[fields] API username is empty');
        }
        if (!Utils::notEmptyOrNull($this->field_bus_api_password)) {
            $error .= 'field_bus_api_password|';
            infologthis('[fields] API password is empty');
        }
        if (!Utils::notEmptyOrNull($this->field_bus_api_endpoint)) {
            $error .= 'field_bus_api_endpoint|';
            infologthis('[fields] API endpoint url is empty');
        }

        if (strlen($error > 0)) {
            $this->is_bus_enabled = false;
            infologthis('--- --- ---');
            infologthis('[field] setting BUS to OFF - by rule, as one field is empty');
            infologthis('--- --- ---');

            return false;
        }

        if (!Utils::notEmptyOrNull($this->field_bus_backoff_duration)) {
            $error .= 'field_bus_backoff_duration|';
            infologthis('[fields] Backoff duration was empty, setting it to default 30mins');
            $this->field_bus_backoff_duration = 30;
        }

        if (!Utils::notEmptyOrNull($this->field_bus_locale)) {
            $error .= 'field_bus_locale|';
            infologthis('[fields] Locale was empty, setting it to default en_KE');
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
     * @return bool
     * @throws \Exception
     */
    public function initSlackFields($optionList)
    {
        $this->is_slack_enabled = true;
        if ($this->field_bus_status === true) {
            $this->field_bus_slack_hook_url = $optionList['field_bus_slack_hook_url'];
            $this->field_bus_slack_channel_name = $optionList['field_bus_slack_channel_name'];
            $this->field_bus_slack_bot_name = $optionList['field_bus_slack_bot_name'];
        }

        $error = '';
        if (!Utils::notEmptyOrNull($this->field_bus_slack_hook_url)) {
            $error .= 'field_bus_slack_hook_url|';
            infologthis('[fields] Slack hook url is empty');
        }
        if (!Utils::notEmptyOrNull($this->field_bus_slack_channel_name)) {
            $error .= 'field_bus_slack_channel_name|';
            infologthis('[fields] Slack Channel name is empty');
        }

        if (strlen($error > 0)) {
            $this->is_slack_enabled = false;
            infologthis('--- --- ---');
            infologthis('[field] setting BUS to OFF - by rule, as one field is empty');
            infologthis('--- --- ---');

            return false;
        }

        if (!Utils::notEmptyOrNull($this->field_bus_slack_bot_name)) {
            $error .= 'field_bus_slack_bot_name|';
            infologthis('[fields] Slack BOT name is empty, naming it DEFAULT_BLOG_BOT');
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
        }

        if ($this->is_slack_enabled === true) {
            $_ENV[Enum::ENV_SLACK_HOOK_URL] = $this->field_bus_slack_hook_url;
            $_ENV[Enum::ENV_SLACK_CHANNEL_NAME] = $this->field_bus_slack_channel_name;
            $_ENV[Enum::ENV_SLACK_BOT_NAME] = $this->field_bus_slack_bot_name;
        }
    }
}
