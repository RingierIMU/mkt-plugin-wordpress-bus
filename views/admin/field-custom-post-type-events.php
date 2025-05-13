<?php
use RingierBusPlugin\Enum;

$custom_post_types = get_post_types(['_builtin' => false], 'objects');
$master_id = 'enable-cpt-master-toggle';
$sub_section_id = 'enable-cpt-subsection';
?>

<label>
    <input type="checkbox"
           id="<?php echo esc_attr($master_id); ?>"
           name="<?php echo esc_attr(Enum::SETTINGS_PAGE_OPTION_NAME . '[' . Enum::FIELD_ALLOW_CUSTOM_POST_TYPES . ']'); ?>"
           value="on"
        <?php checked($args['master_checked'] ?? false); ?>>
    Allow events for custom post types
</label>

<div id="<?php echo esc_attr($sub_section_id); ?>" style="margin-top: 10px; padding-left: 15px; <?php echo empty($args['master_checked']) ? 'display: none;' : ''; ?>">
    <fieldset>
        <legend><strong>Select custom post types:</strong></legend>

        <?php if (!empty($custom_post_types)): ?>
            <?php foreach ($custom_post_types as $post_type => $obj): ?>
                <label>
                    <input type="checkbox"
                           name="<?php echo esc_attr(Enum::SETTINGS_PAGE_OPTION_NAME . '[' . Enum::FIELD_ENABLED_CUSTOM_POST_TYPE_LIST . "][$post_type]"); ?>"
                           value="on"
                        <?php checked($args['allowed_post_types'][$post_type] ?? '', 'on'); ?>>
                    <?php echo esc_html($obj->name) . ' – ' . esc_html($obj->label); ?>
                </label><br>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #666;"><em>No custom post types found.</em></p>
        <?php endif; ?>
    </fieldset>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const masterCheckbox = document.getElementById('<?php echo esc_js($master_id); ?>');
        const subSection = document.getElementById('<?php echo esc_js($sub_section_id); ?>');

        if (masterCheckbox && subSection) {
            masterCheckbox.addEventListener('change', function () {
                subSection.style.display = this.checked ? 'block' : 'none';
            });
        }
    });
</script>
