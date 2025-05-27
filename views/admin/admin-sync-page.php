<?php
/**
 * Admin View: General Batch Sync Tool
 *
 * This template renders the admin UI for triggering various sync events via AJAX.
 * Currently supports syncing all authors. Future sections can add sync buttons
 * for terms (categories, tags), media, or other data types.
 *
 * Loaded via Utils::load_tpl() from AdminSyncPage::renderPage().
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

    <!-- Author Sync Section -->
    <h2>Author Sync</h2>
    <p>This will sync all WordPress users with at least one of the allowed roles:
    <code>administrator, editor, author, contributor</code>
    </p>
    <button id="sync-authors-button" class="button">Sync All Authors</button>

    <div id="sync-progress" style="margin-top: 20px;"><!-- Placeholder for AJAX process --></div>

    <div style="margin-bottom: 10px;">&nbsp;</div>
    <hr />

    <!-- Category Sync Section -->
    <h2>Category Sync</h2>
    <p>This will sync all WordPress categories as <code>TopicCreated</code> events to the BUS API.</p>
    <button id="sync-categories-button" class="button button-secondary">Sync All Categories</button>

    <div id="sync-progress-cat" style="margin-top: 20px;"><!-- Placeholder for AJAX process --></div>

    <div style="margin-bottom: 10px;">&nbsp;</div>
    <hr />

    <!-- Tag Sync Section -->
    <h2>Tag Sync</h2>
    <p>This will sync all WordPress tags as <code>TopicCreated</code> events to the BUS API.</p>
    <button id="sync-tags-button" class="button">Sync All Tags</button>

    <div id="sync-progress-tag" style="margin-top: 20px;"><!-- Placeholder for AJAX process --></div>

    <div style="margin-bottom: 10px;">&nbsp;</div>
    <hr />
</div>
