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
        // Use global $wpdb for a precise, lightweight query
        global $wpdb;

        $last_id = isset($_POST['last_id']) ? (int) $_POST['last_id'] : 0;

        // Instead of loading ALL terms, we query the database for exactly ONE ID
        // that is higher than our current $last_id.
        $next_term_id = $wpdb->get_var($wpdb->prepare(
            "SELECT t.term_id
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE tt.taxonomy = %s
             AND t.term_id > %d
             ORDER BY t.term_id ASC
             LIMIT 1",
            'category', // The taxonomy
            $last_id    // The cursor
        ));

        // If no ID returned, we are done
        if (!$next_term_id) {
            wp_send_json_success([
                'message' => 'All categories have been synced.',
                'done' => true,
            ]);
        }

        // Now we fetch the full object for just this ONE term
        $next_term = get_term($next_term_id, 'category');

        try {
            BusHelper::triggerTermCreatedEvent(
                $next_term->term_id,
                $next_term->term_taxonomy_id,
                $next_term->taxonomy,
                (array) $next_term
            );

            wp_send_json_success([
                'message' => "Synced Category (ID {$next_term->term_id}) – {$next_term->name}",
                'done' => false,
                // We send back the new ID so the JS knows where to start next time
                'last_id' => $next_term->term_id,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public static function handleTagsSync(): void
    {
        global $wpdb;

        $last_id = isset($_POST['last_id']) ? (int) $_POST['last_id'] : 0;

        // Query the DB directly for the next available Term ID in the 'post_tag' taxonomy.
        // This is significantly faster than loading all terms into an array.
        $next_term_id = $wpdb->get_var($wpdb->prepare(
            "SELECT t.term_id
         FROM {$wpdb->terms} t
         INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
         WHERE tt.taxonomy = %s
         AND t.term_id > %d
         ORDER BY t.term_id ASC
         LIMIT 1",
            'post_tag', // taxo
            $last_id
        ));

        // If no ID is returned, we have reached the end.
        if (!$next_term_id) {
            wp_send_json_success([
                'message' => 'All tags have been synced.',
                'done' => true,
            ]);
        }

        // Fetch the full object for this specific tag
        $next_term = get_term($next_term_id, 'post_tag');

        try {
            BusHelper::triggerTermCreatedEvent(
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

    /**
     * Article Sync (Recent First)
     */
    public static function handleArticlesSync(): void
    {
        global $wpdb;

        // 1. Get parameters
        $last_id = isset($_POST['last_id']) ? (int) $_POST['last_id'] : 0;
        // flexible to accommodate checkboxes in future, but fallback to default 'post'
        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : ['post'];

        // 2. Prepare SQL for Reverse Cursor (Newest First)
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

        $where_clause = "post_status = 'publish' AND post_type IN ($placeholders)";
        $args = $post_types;

        if ($last_id > 0) {
            $where_clause .= ' AND ID < %d';
            $args[] = $last_id;
        }

        // 3. Fetch exactly ONE ID
        $next_post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE $where_clause 
             ORDER BY ID DESC 
             LIMIT 1",
            $args
        ));

        // 4. Termination Condition
        if (!$next_post_id) {
            wp_send_json_success([
                'message' => 'All articles have been synced.',
                'done' => true,
            ]);
        }

        // 5. Fetch Object & Dispatch
        $post_object = get_post($next_post_id);

        if (!$post_object) {
            // Should not happen, but safe fallback
            wp_send_json_error("Could not load post object for ID $next_post_id");
        }

        try {
            // Refactored: Use the Helper to handle Auth & Sending
            $success = BusHelper::dispatchArticlesEvent(
                $post_object->ID,
                $post_object
            );

            if ($success) {
                wp_send_json_success([
                    'message' => "Synced Article (ID {$post_object->ID}) – " . mb_strimwidth($post_object->post_title, 0, 40, '...'),
                    'done' => false,
                    'last_id' => $post_object->ID,
                ]);
            } else {
                // If false, it likely failed auth or validation inside the helper
                wp_send_json_success([
                    'message' => "Failed to sync Article (ID {$post_object->ID}) - Check logs.",
                    'done' => false, // We continue the loop even if one fails
                    'last_id' => $post_object->ID,
                ]);
            }

        } catch (\Throwable $e) {
            wp_send_json_error("Error ID {$next_post_id}: " . $e->getMessage());
        }
    }
}
