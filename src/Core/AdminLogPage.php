<?php
/**
 * To handle everything regarding the main Admin LOG Page
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
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
            'Ringier Bus - Error Log',
            'Log',
            'manage_options',
            Enum::ADMIN_LOG_MENU_SLUG,
            [self::class, 'renderLogPage']
        );
    }

    /**
     * Handle & Render our Admin LOG Page
     */
    public static function renderLogPage()
    {
        global $title;
        $error_log_file = RINGIER_BUS_PLUGIN_ERROR_LOG_FILE;
        $error_msg = '';

        if (!current_user_can('manage_options')) {
            return;
        }

        //Clear error log
        if (isset($_POST['clearlog_btn'])) {
            $error_msg = self::clearErrorLog($error_log_file);
        }

        $log_page_tpl = RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'page_log.twig';
        $txtlog_value = self::fetchLogData($error_log_file);

        if (file_exists($log_page_tpl)) {
            $context['admin_page_title'] = $title;
            $context['error_msg'] = $error_msg;
            $context['txtlog_value'] = $txtlog_value;

            Timber::render($log_page_tpl, $context);
        }
    }

    /**
     * Fetches all error lines from the log files
     * Method Will always return a message, an error message in case of any failure
     *
     * @param $log_file_path
     *
     * @return string
     */
    public static function fetchLogData($log_file_path)
    {
        $log_file = $log_file_path;
        $max_lines = 10;
        $log_data = '';

        if (!file_exists($log_file)) {
            return 'The log seems empty!';
        }

        if (!is_writable($log_file)) {
            return '[NOTICE] the log is not writable. Please chmod it to 0777';
        }

        $log_data_array = file($log_file, FILE_SKIP_EMPTY_LINES);

        if ($log_data_array === false) {
            return 'Unable to open the log for read operation!';
        }

        $lines = count($log_data_array);
        if ($lines == 0) {
            return 'The log is empty.';
        }

        //We only want to display the latest 10 entries
        if ($max_lines < $lines) {
            for ($i = 0; $i < ($lines - $max_lines); ++$i) {
                unset($log_data_array[$i]);
            }
        }

        //now fetch all lines
        foreach ($log_data_array as $line) {
            $log_data .= htmlentities($line . "\n");
        }

        return $log_data;
    }

    /**
     * Util to help clear the log file
     *
     * @param $log_file_path
     *
     * @return string|void
     */
    public static function clearErrorLog($log_file_path)
    {
        if (!file_exists($log_file_path)) {
            return 'The log seems empty!';
        }

        if (!is_writable($log_file_path)) {
            return '[NOTICE] the log is not writable. Please chmod it to 0777';
        }

        if (file_exists($log_file_path)) {
            unlink($log_file_path);

            return '[done] the log was cleared';
        }
    }
}
