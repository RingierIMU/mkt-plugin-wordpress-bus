<?php

use RingierBusPlugin\Enum;

?>
<input type="checkbox"
       id="<?php echo esc_attr($args['label_for']); ?>"
       name="<?php echo esc_attr(Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']'); ?>"
       value="on"
    <?php checked($args['is_checked']); ?>>
<label for="<?php echo esc_attr($args['label_for']); ?>">Allow sending of events for Category and Tag</label>
