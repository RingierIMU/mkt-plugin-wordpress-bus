<?php
use RingierBusPlugin\Enum;

?>
<input type="text"
       id="<?php echo esc_attr($args['label_for']); ?>"
       name="<?php echo esc_attr(Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']'); ?>"
       value="<?php echo esc_attr($args['value']); ?>"
       class="regular-text">
<p class="description">Custom base slug for author URLs (e.g., "author" → "auteur").</p>
