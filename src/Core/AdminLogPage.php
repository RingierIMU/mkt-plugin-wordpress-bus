<?php
/**
 * To handle everything regarding the main Admin LOG Page
 *
 * @author Wasseem Khayrattee <wasseemk@ringier.co.za>
 *
 * @github wkhayrattee
 */

namespace RingierBusPlugin;

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

        $txtlog_value = self::fetchLogData($error_log_file);

        Utils::load_tpl(
            RINGIER_BUS_PLUGIN_VIEWS . 'admin' . RINGIER_BUS_DS . 'page-log.php',
            [
                'admin_page_title' => $title,
                'error_msg' => $error_msg,
                'txtlog_value' => $txtlog_value,
            ]
        );
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
        $tail_bytes = 65536; // 64KB — plenty for 10 log entries

        if (!file_exists($log_file)) {
            return 'The log seems empty!';
        }

        if (!is_writable($log_file)) {
            return '[NOTICE] the log is not writable. Please chmod it to 0777';
        }

        $file_size = filesize($log_file);
        if ($file_size === 0) {
            return 'The log is empty.';
        }

        // Only read the tail of the file — O(1) memory regardless of file size
        $fp = fopen($log_file, 'r');
        if ($fp === false) {
            return 'Unable to open the log for read operation!';
        }

        $read_bytes = min($file_size, $tail_bytes);
        fseek($fp, -$read_bytes, SEEK_END);
        $tail = fread($fp, $read_bytes);
        fclose($fp);

        if ($tail === false) {
            return 'Unable to read the log file!';
        }

        $lines = explode("\n", $tail);
        $lines = array_filter($lines, fn ($line) => $line !== '');

        if (count($lines) === 0) {
            return 'The log is empty.';
        }

        // Take only the last N lines
        $lines = array_slice($lines, -$max_lines);

        $log_data = '';
        foreach ($lines as $line) {
            $log_data .= htmlentities($line) . "\n";
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
