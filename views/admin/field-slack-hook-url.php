<input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
       data-custom="<?php echo esc_attr($args['field_custom_data']); ?>"
       name="<?php echo esc_attr($args['field_name']); ?>"
       value="<?php echo esc_attr($args['field_value']); ?>"
       style="width: 22rem;">
<p>Note: is optional and if left blank, will not send error message to your slack</p>
<p>E.g: <code>https://hooks.slack.com/services/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</code></p>
