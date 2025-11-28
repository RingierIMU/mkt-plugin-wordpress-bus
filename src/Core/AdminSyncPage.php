<?php

namespace RingierBusPlugin;

use RingierBusPlugin\Bus\BusHelper;

class AdminSyncPage
{
    public static function register(): void
    {
        self::addMenuPage();
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
    }

    public static function addMenuPage(): void
    {
        add_submenu_page(
            Enum::ADMIN_SETTINGS_MENU_SLUG,
            'BUS Tooling Page',
            'Tools',
            'manage_options',
            Enum::ADMIN_SYNC_EVENTS_MENU_SLUG,
            [self::class, 'renderPage']
        );
    }

    /**
     * Button to flush Auth transient
     */
    public static function handleFlushAllTransients(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized', 'Error', ['response' => 403]);
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'flush_transients_nonce')) {
            wp_die('Invalid nonce specified', 'Error', ['response' => 403]);
        }

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
             OR option_name LIKE %s",
                '_transient_%' . Enum::CACHE_KEY,
                '_transient_timeout_%' . Enum::CACHE_KEY
            )
        );

        // Redirect back with notice
        wp_safe_redirect(
            add_query_arg('flush_success', '1', wp_get_referer())
        );
        exit;
    }

    public static function enqueueAssets(string $hook): void
    {
        /**
         * WordPress constructs the $hook_suffix like:
         *  $hook_suffix = "{$parent_slug}_page_{$menu_slug}";
         * For some reason, the $hook_suffix for our submenu page is not being generated as expected.
         * So appending 'ringier-bus' statically here.
         */
        //$hook_suffix = Enum::ADMIN_SETTINGS_MENU_SLUG . '_page_' . Enum::ADMIN_SYNC_EVENTS_MENU_SLUG;
        $hook_suffix = 'ringier-bus' . '_page_' . Enum::ADMIN_SYNC_EVENTS_MENU_SLUG;
        if ($hook !== $hook_suffix) {
            return;
        }

        wp_enqueue_script(
            'ringier-bus-sync-authors',
            RINGIER_BUS_PLUGIN_DIR_URL . 'assets/js/sync-tools.js',
            ['jquery'],
            _S_CACHE_NONCE,
            true
        );

        wp_localize_script(
            'ringier-bus-sync-authors',
            'SyncAuthorsAjax',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'role_list' => Enum::AUTHOR_ROLE_LIST,
            ]
        );
    }

    public static function renderPage(): void
    {
        Utils::load_tpl(RINGIER_BUS_PLUGIN_VIEWS . 'admin/admin-sync-page.php');
    }

    public static function handleAuthorsSync(): void
    {
        $offset = isset($_POST['offset']) ? (int) $_POST['offset'] : 0;
        $perPage = 1;

        $users = get_users([
            'role__in' => Enum::AUTHOR_ROLE_LIST, // Only get specific roles
            'number' => $perPage,
            'offset' => $offset,
            'fields' => 'all',
        ]);

        // If no users found, we are done
        if (empty($users)) {
            wp_send_json_success(['message' => 'All authors have been synced.', 'done' => true]);
        }

        $user = $users[0];
        $user_id = $user->ID;

        try {
            $was_dispatched = BusHelper::dispatchAuthorEvent(
                $user_id,
                (array) $user->data,
                Enum::EVENT_AUTHOR_CREATED
            );

            if ($was_dispatched) {
                wp_send_json_success([
                    'message' => "Synced Author (ID {$user_id}) - {$user->user_login}",
                    'done' => false,
                    'skipped' => false,
                ]);
            } else {
                wp_send_json_success([
                    'message' => "Skipped User ID {$user_id} - Profile Disabled or no matching role.",
                    'done' => false,
                    'skipped' => true,
                ]);
            }

        } catch (\Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'done' => true,
            ]);
        }
    }

    public static function handleCategoriesSync(): void
    {
        $last_id = isset($_POST['last_id']) ? (int) $_POST['last_id'] : 0;

        $all_terms = get_terms([
            'taxonomy' => 'category',
            'hide_empty' => false,
            'orderby' => 'term_id',
            'order' => 'ASC',
            'fields' => 'all',
        ]);

        $next_term = null;
        foreach ($all_terms as $term) {
            if ($term->term_id > $last_id) {
                $next_term = $term;
                break;
            }
        }

        if (!$next_term) {
            wp_send_json_success([
                'message' => 'All categories have been synced.',
                'done' => true,
            ]);
        }

        try {
            \RingierBusPlugin\Bus\BusHelper::triggerTermCreatedEvent(
                $next_term->term_id,
                $next_term->term_taxonomy_id,
                $next_term->taxonomy,
                (array) $next_term
            );

            wp_send_json_success([
                'message' => "Synced Category (ID {$next_term->term_id}) – {$next_term->name}",
                'done' => false,
                'last_id' => $next_term->term_id,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public static function handleTagsSync(): void
    {
        $last_id = isset($_POST['last_id']) ? (int) $_POST['last_id'] : 0;

        $all_terms = get_terms([
            'taxonomy' => 'post_tag',
            'hide_empty' => false,
            'orderby' => 'term_id',
            'order' => 'ASC',
            'fields' => 'all',
        ]);

        $next_term = null;
        foreach ($all_terms as $term) {
            if ($term->term_id > $last_id) {
                $next_term = $term;
                break;
            }
        }

        if (!$next_term) {
            wp_send_json_success([
                'message' => 'All tags have been synced.',
                'done' => true,
            ]);
        }

        try {
            \RingierBusPlugin\Bus\BusHelper::triggerTermCreatedEvent(
                $next_term->term_id,
                $next_term->term_taxonomy_id,
                $next_term->taxonomy,
                (array) $next_term
            );

            wp_send_json_success([
                'message' => "Synced Tag (ID {$next_term->term_id}) – {$next_term->name}",
                'done' => false,
                'last_id' => $next_term->term_id,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
