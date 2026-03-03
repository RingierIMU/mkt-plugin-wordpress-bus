<input type="text" id="<?php echo esc_attr($args['label_for']); ?>"
       data-custom="<?php echo esc_attr($args['field_custom_data']); ?>"
       name="<?php echo esc_attr($args['field_name']); ?>"
       value="<?php echo esc_attr($args['field_value']); ?>"
       style="width: 22rem;">
<p>Staging: <code>https://bus.staging.ritdu.tech/v1/</code></p>
<p>PROD: <code>https://bus.ritdu.net/v1/</code></p>
<p><strong>Note:</strong> the url need to end with a trailing slash</p>
