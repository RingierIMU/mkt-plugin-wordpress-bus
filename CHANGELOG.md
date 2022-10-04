# Changelog Details

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
