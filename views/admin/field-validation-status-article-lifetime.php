<select id="<?php echo esc_attr($args['label_for']); ?>"
        data-custom="<?php echo esc_attr($args['field_custom_data']); ?>"
        name="<?php echo esc_attr($args['field_bus_status_name']); ?>"
        style="width: 22rem;">
    <option value="on" <?php selected($args['field_selected_value'], 'on'); ?>>ON</option>
    <option value="off" <?php selected($args['field_selected_value'], 'off'); ?>>OFF</option>
</select>
<p>Note: This field is located on the post/article editor (Gutenberg) on the left, widget named "Ringier BUS"</p>
