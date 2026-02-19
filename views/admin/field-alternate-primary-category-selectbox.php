<style type="text/css">
    .alt-category-field.first {}
    .alt-category-field {}
    .alt-category-field th {padding-left: 10px;}
    tr.alt-category-field.first {border-top: 1px solid #252424;}
</style>
<select id="<?php echo esc_attr($args['label_for']); ?>"
        data-custom="<?php echo esc_attr($args['field_custom_data']); ?>"
        name="<?php echo esc_attr($args['field_bus_status_name']); ?>"
        style="width: 22rem;">
    <option value="off" <?php echo $args['field_custom_data_selected_off']; ?>>OFF</option>
    <option value="on" <?php echo $args['field_custom_data_selected_on']; ?>>ON</option>
</select>
