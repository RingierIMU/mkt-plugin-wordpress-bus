<select id="<?php echo esc_attr($args['label_for']); ?>"
        data-custom="<?php echo esc_attr($args['field_custom_data']); ?>"
        name="<?php echo esc_attr($args['field_bus_status_name']); ?>"
        style="width: 22rem;">
    <option value="on" <?php echo $args['field_custom_data_selected_on']; ?>>ON</option>
    <option value="off" <?php echo $args['field_custom_data_selected_off']; ?>>OFF</option>
</select>
