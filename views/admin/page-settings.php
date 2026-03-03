<?php

use RingierBusPlugin\Enum;

?>
<div class="wrap">
    <h1><?php echo esc_html($args['admin_page_title']); ?></h1>
    <form action="options.php" method="post">
        <?php
        settings_fields(Enum::SETTINGS_PAGE_OPTION_GROUP);
        do_settings_sections(Enum::ADMIN_SETTINGS_MENU_SLUG);
        submit_button('Save Settings');
        ?>
    </form>
</div>
