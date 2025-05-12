<?php

use RingierBusPlugin\Enum;

?>
<style type="text/css">
    tr.ringier-bus-row.first {border-top: 1px solid #252424;}
</style>
<input type="checkbox"
       id="<?php echo esc_attr($args['label_for']); ?>"
       name="<?php echo esc_attr(Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']'); ?>"
       value="on"
    <?php echo $args['checked']; ?>>
<label for="<?php echo esc_attr($args['label_for']); ?>">Show the Quick Edit action in post list</label>
