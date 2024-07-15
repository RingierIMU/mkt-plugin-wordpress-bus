# Changelog Details

### 3.0.0 (Jul 15, 2024) ###

* [BREAKING] PHP Version | The code base now requires a minimum version of PHP 8.1+
* [NEW] Added three new custom filters to allow for more flexibility in the plugin's behavior (see readme file):
    - `ringier_bus_get_publication_reason` - allows you to filter the publication reason before it is sent to the BUS API
    - `ringier_bus_get_article_lifetime` - allows you to filter the article lifetime before it is sent to the BUS API
    - `ringier_build_article_payload` - allows you to filter the entire article payload before it is sent to the BUS API
* [UPDATE]: Changed the way events are sent:
    - on new article creation, an event will now be immediately sent (this is a requirement for internal CIET)
    - the event will still be queued to run or re-run (in the case of an article update) after the default 1 minute
* [UPDATE]: Harmonised page title and menu
* [UPDATE]: Updated composer dependencies
* [UPDATE]: Cache nonce now defaults to the plugin version number for consistency
* [UPDATE]: Add more intuitive prompts to guide user, for e.g provide the STAGING and PROD endpoints right there in the UI to be handy for them

### 2.3.0 (Oct 9, 2023) ###

* [UPDATE]: Transitioned from relying on the rest_after_insert_post hook to the more universally available transition_post_status hook.

*Reason*: We identified that some blogs were disabling the Gutenberg editor and as a result, not utilizing the new WordPress REST API. This meant that the rest_after_insert_post hook wasn't being triggered for those instances. To ensure consistent and robust post update handling across all blogs, regardless of their editor choice, we've shifted to the transition_post_status hook.

*Impact*: This change ensures that our logic remains consistent even in environments where Gutenberg is disabled or the REST API isn't being leveraged.

* [UPDATE]: Improved JSON handling and compression for Slack logging
  * Ensured safe JSON encoding with error checks
  * Utilized gzcompress for payload compression when available to prevent truncation in Slack notifications channel

### 2.2.0 (Oct 9, 2023) ###

* [NEW] Introduction of the possibility to add a custom Top level primary category - can ENABLE/DISABLED when needed
  * Addition of two new fields on the Settings page for the below
  * use-case: when you have several wordpress instance on the same root domain
  * by default, it will use the full domain as the primary category when enabled, with the flexibility for you to change it on the *settings page*

* [UPDATE] Refactored the logic for saving custom fields (on gutenberg) to work as soon as the plugin is active, irrespective if the BUS sync is OFF
* [FIX] There was a bug that could prevent the primary category of an article from being fetched from the fallback method if the one from Yoast fails

### 2.1.0 (Jul 18, 2023) ###

* [UPDATE] General updates to the JSON structure to match the new BUS Specs (See [PR#5](https://github.com/RingierIMU/mkt-plugin-wordpress-bus/pull/5)

  i) Check for the presence of the following new/updated variables:
    - images
    - lifetime
    - source_detail
    - publication_reason

  ii) the following variables simply had size limit adjustments
    - og_title
    - description
    - og_description
    - teaser

* [UPDATE] New widget for the new field publication reason on the Gutenberg editor
* [UPDATE] Updated composer dependencies:
  - guzzlehttp/guzzle to v7.5.3
  - symfony/cache to v6.0.19
  - ramsey/uuid to v4.7.4

### 2.0.0 (Dec 23, 2022) ###

* [BREAKING] PHP Version | The code base now requires a minimum version of PHP 8.0.2
* [BREAKING] PHP Version | The code base has been refactored to be PHP 8 compatible - but no PHP 8.1+ support yet since WordPress itself is not officially PHP 8.0 compatible to-date.
* [UPDATE] API | New field `Categories[]` has been introduced to the JSON request - see commit#e857e083fb33a9bd58374482105e2d3215bbd5f1
* [REFACTOR] Removal of the ACF plugin 3rd-party plugin in favor of doing things in native WordPress, see commit#b2e489b156ed12187403bb4599107972a61b4493

### 1.3.1 (Oct 18, 2022) ###
* [UPDATE] JSON | change page_type to content_type for sailthru vars

### 1.3.0 (Oct 12, 2022) ###
* [NEW] custom post_type event | handle triggering of events separately for custom post_type
* [NEW] custom fields on admin UI | allow showing of acf custom fields on custom post_type as well, excluding page for now

### 1.2.0 (Oct 04, 2022) ###
* [FIX] Events should not be triggered when "saving draft"
* [NEW] Logging | Add additional log message when an Event is not sent to know why
* [NEW] Addition of new logic for new field: primary_media_type

### 1.1.1 (Aug 16, 2022) ###
* [JSON Request] The API's field `description` field truncated to 2500 chars since the BUS API request will fail on more than 3000 chars.
* [Doc] The readme has been given some polishing


### 1.1.0 (Jul 27, 2022) ###
* [vendor] update ACF to v5.12.3
* Added Sailthru Tags & Vars to the JSON request
* Changes to BUS API
  * update BUS API version to v2.0.0
  * Main JSON - rename venture_config_id to node_id
  * Article JSON - rename venture_config_id to from
  * Article JSON rename venture_reference to reference

### 1.0.3 (April 14, 2022) ###
* update ACF to v5.12.2

### 1.0.2 (December 06, 2021) ###
* update symfony/cache to v5.4.0 - we will stick to 5.x for now because v6.x focuses on php v8+
* update ACF to v5.11.4

### 1.0.1 (November 25, 2021) ###
* Update ACF to latest v5.11.3

### 1.0.0 (November 19, 2021) ###
* Initial release onto WordPress.org plugin repo with the initial code from phase 1 of this plugin

### 0.1.0 (September 26, 2021) ###
* Initial commit of working code for the benefit of everyone who needs this plugin
