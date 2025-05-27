<?php
/**
 * Admin View: General Batch Sync Tool
 *
 * This template renders the admin UI for triggering various sync events via AJAX.
 * Currently supports syncing all authors. Future sections can add sync buttons
 * for terms (categories, tags), media, or other data types.
 *
 * Loaded via Utils::load_tpl() from AdminSyncPage::renderPage().
 *
 * @package RingierBusPlugin
 */
?>

<div class="wrap">
    <h1>BUS Batch Sync Dashboard</h1>

    <p>
        Use the tools below to batch-sync different types of WordPress entities
        with the BUS API. Each sync operation will trigger a <strong>Created</strong> event
        per item (e.g. authors, categories, tags).
    </p>

    <p style="font-weight: bold; color: #0073aa;">
        Please do NOT close this window while a sync operation is running.
    </p>

    <hr />

    <h2>Author Sync</h2>
    <p>This will sync all WordPress users with at least one of the allowed roles:
    <code>administrator, editor, author, contributor</code>
    </p>
    <button id="sync-authors-button" class="button button-primary">Sync All Authors</button>

    <div id="sync-progress" style="margin-top: 20px;"></div>

    <!-- Future: Add term sync buttons here -->
</div>
