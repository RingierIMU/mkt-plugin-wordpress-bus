<?php
/**
 * To handle everything regarding our main Admin LOG Page
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 * @github wkhayrattee
 */
namespace RingierBusPlugin;

use Timber\Timber;

class AdminLogPage
{
    public function __construct()
    {
    }

    /**
     * Main method for handling the admin pages
     */
    public function handleAdminUI()
    {
        $this->addAdminPages();
    }

    public static function logSectionCallback($args)
    {
        //silence for now
    }

    public function addAdminPages()
    {
        //The "Log" sub-PAGE
        add_submenu_page(
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            Enum::ADMIN_LOG_PAGE_TITLE,
            Enum::ADMIN_LOG_MENU_TITLE,
            'manage_options',
            Enum::ADMIN_LOG_MENU_SLUG,
            [self::class, 'renderLogPage']
        );

        //Fields for the LOG Page
    }

    /**
     * Handle & Render our Admin LOG Page
     */
    public static function renderLogPage()
    {
        global $title;

        if (! current_user_can('manage_options')) {
            return;
        }

        $timber = new Timber();
        $log_page_tpl = WP_BUS_RINGIER_PLUGIN_VIEWS . 'admin' . DS . 'page_log.twig';

        if (file_exists($log_page_tpl)) {
            $context['admin_page_title'] = $title;

            $timber::render($log_page_tpl, $context);
        }
        unset($timber);
    }
}
