<?php
use RingierBusPlugin\Enum;

$custom_post_types = get_post_types(['_builtin' => false], 'objects');
?>

    <label>
        <input type="checkbox"
               name="<?php echo esc_attr(Enum::SETTINGS_PAGE_OPTION_NAME . '[' . Enum::FIELD_ALLOW_CUSTOM_POST_TYPES . ']'); ?>"
               value="on"
            <?php checked($args['master_checked'] ?? false); ?>>
        Allow events for custom post types
    </label>

<?php if (!empty($args['master_checked']) && !empty($custom_post_types)): ?>
    <fieldset style="margin-top: 10px; padding-left: 15px;">
        <legend><strong>Select custom post types:</strong></legend>
        <?php foreach ($custom_post_types as $post_type => $obj): ?>
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr(Enum::SETTINGS_PAGE_OPTION_NAME . '[' . Enum::FIELD_ENABLED_CUSTOM_POST_TYPE_LIST . "][$post_type]"); ?>"
                       value="on"
                    <?php checked($args['allowed_post_types'][$post_type] ?? '', 'on'); ?>>
                <?php echo esc_html($obj->label); ?>
            </label><br>
        <?php endforeach; ?>
    </fieldset>
<?php endif; ?>
