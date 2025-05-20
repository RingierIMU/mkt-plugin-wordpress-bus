<?php
use RingierBusPlugin\Enum;

?>
<input type="text"
       id="<?php echo esc_attr($args['label_for']); ?>"
       name="<?php echo esc_attr(Enum::SETTINGS_PAGE_OPTION_NAME . '[' . $args['label_for'] . ']'); ?>"
       value="<?php echo esc_attr($args['value']); ?>"
       class="regular-text">
<p class="description">
    Specify the current author base slug used on your site (e.g., <code>author</code>, <code>auteur</code>, etc.). <br>This field does not change the base, it is used just for us to detect and construct the author URLs correctly.
</p>
