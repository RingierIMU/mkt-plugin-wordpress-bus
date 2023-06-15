<?php
/**
 * Mapping the fields on Admin UI onto this class
 * FYI this is related to our BUS API plugin
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
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
    public string $field_validation_publication_reason;
    public string $field_validation_article_lifetime;

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
        $this->field_validation_publication_reason = 'on';
        $this->field_validation_article_lifetime = 'on';

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

            if (isset($optionList[Enum::FIELD_VALIDATION_PUBLICATION_REASON])) {
                $this->field_validation_publication_reason = $optionList[Enum::FIELD_VALIDATION_PUBLICATION_REASON];
            }
            if (isset($optionList[Enum::FIELD_VALIDATION_ARTICLE_LIFETIME])) {
                $this->field_validation_article_lifetime = $optionList[Enum::FIELD_VALIDATION_ARTICLE_LIFETIME];
            }

            $error = '';
            if (!Utils::notEmptyOrNull($this->field_venture_config)) {
                $error .= 'Venture ID empty ||';
            }
            if (!Utils::notEmptyOrNull($this->field_bus_api_username)) {
                $error .= 'API Username empty ||';
            }
            if (!Utils::notEmptyOrNull($this->field_bus_api_password)) {
                $error .= 'API Password empty ||';
            }
            if (!Utils::notEmptyOrNull($this->field_bus_api_endpoint)) {
                $error .= 'API Endpoint empty ||';
            }

            if (mb_strlen($error) > 0) {
                $this->is_bus_enabled = false;
                ringier_errorlogthis('[API] - Turning BUS Process OFF because of the following error:');
                ringier_errorlogthis($error);

                return false;
            }

            if (!Utils::notEmptyOrNull($this->field_bus_backoff_duration)) {
                $error .= 'field_bus_backoff_duration|';
                $this->field_bus_backoff_duration = 30;
            }

            if (!Utils::notEmptyOrNull($this->field_bus_locale)) {
                $error .= 'field_bus_locale|';
                $this->field_bus_locale = 'en_KE';
            }

            return true;
        }

        return false;
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
            $this->field_bus_slack_hook_url = $optionList[Enum::FIELD_SLACK_HOOK_URL];
            $this->field_bus_slack_channel_name = $optionList[Enum::FIELD_SLACK_CHANNEL_NAME];
            $this->field_bus_slack_bot_name = $optionList[Enum::FIELD_SLACK_BOT_NAME];

            $error = '';
            if (!Utils::notEmptyOrNull($this->field_bus_slack_hook_url)) {
                $error .= 'Field Slack Hook URL || ';
            }
            if (!Utils::notEmptyOrNull($this->field_bus_slack_channel_name)) {
                $error .= 'Field Slack Channel Name';
            }

            if (mb_strlen($error) > 0) {
                $this->is_slack_enabled = false;
                ringier_errorlogthis('[Slack Fields] - The following appear to be empty:');
                ringier_errorlogthis($error);

                return false;
            }

            if (!Utils::notEmptyOrNull($this->field_bus_slack_bot_name)) {
                $error .= 'field_bus_slack_bot_name|';
                $this->field_bus_slack_bot_name = 'DEFAULT_BLOG_BOT';
            }

            return true;
        }

        return false;
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
