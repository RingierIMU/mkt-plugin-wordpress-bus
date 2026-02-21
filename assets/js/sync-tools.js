jQuery(document).ready(function ($) {
    const progressDiv = $('#sync-progress');
    const progressDivCat = $('#sync-progress-cat');
    const progressDivTag = $('#sync-progress-tag');
    const progressDivArticle = $('#sync-progress-article');

    /**
     * Escape HTML special characters to prevent XSS when inserting
     * server response data into the DOM via .append().
     */
    function escHtml(str) {
        if (typeof str !== 'string') {
            return String(str);
        }
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function syncAuthors() {
        let offset = 0;
        let successCount = 0;
        let skippedCount = 0;

        progressDiv.show();
        progressDiv.html(`
            <h3>Starting to sync all authors having the following roles:</h3>
            <pre><code>${escHtml(SyncAuthorsAjax.role_list.join(', '))}</code></pre>
            <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window until the sync is completed.</p>
            <hr />
        `);

        function syncNextAuthor() {
            $.post(SyncAuthorsAjax.ajax_url, {
                action: 'sync_authors',
                nonce: SyncAuthorsAjax.nonce,
                offset: offset
            }, function (response) {
                if (response.success && response.data) {
                    const { message, done, skipped } = response.data;

                    if (done) {
                        progressDiv.append(`
                            <hr />
                            <h3>All authors have been synced:</h3>
                            <ul>
                                <li><strong>${successCount}</strong> authors successfully synced</li>
                                <li><strong>${skippedCount}</strong> authors skipped (Profile Disabled or no matching role)</li>
                            </ul>
                            <strong style="color: green;">Sync complete!</strong>
                        `);
                    } else {
                        // If NOT done, means we definitely processed a user row.
                        if (skipped) {
                            skippedCount++;
                            progressDiv.append(`<div style="color: red; font-weight: bold;">${escHtml(message)}</div>`);
                        } else {
                            successCount++;
                            progressDiv.append(`<div>${escHtml(message)}</div>`);
                        }

                        // Move to next offset and recurse
                        offset++;
                        syncNextAuthor();
                    }
                } else {
                    progressDiv.append(`<div style="color: red;">Error: ${escHtml(response.data)}</div>`);
                }
            }).fail(function (xhr) {
                progressDiv.append(`<div style="color:red;">AJAX Error: ${escHtml(xhr.status)} - ${escHtml(xhr.statusText)}</div>`);
            });
        }

        syncNextAuthor();
    }

    function syncCategories(lastId = 0) {
        let syncedCount = 0;

        progressDivCat.show();
        progressDivCat.html(`
        <h3>Starting category sync...</h3>
        <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window until the sync is completed.</p>
        <hr />
    `);

        function syncNextCategory(currentId) {
            $.post(SyncAuthorsAjax.ajax_url, {
                action: 'sync_categories',
                nonce: SyncAuthorsAjax.nonce,
                last_id: currentId
            }, function (response) {
                if (response.success && response.data) {
                    const { message, done, last_id } = response.data;

                    if (done) {
                        progressDivCat.append(`
                        <hr />
                        <h3>Category Sync Complete:</h3>
                        <ul>
                            <li><strong>${syncedCount}</strong> categories successfully synced.</li>
                        </ul>
                        <strong style="color: green;">Process finished.</strong>
                    `);
                    } else {
                        syncedCount++;

                        progressDivCat.append(`<div style="color: teal;">[${syncedCount}] ${escHtml(message)}</div>`);
                        syncNextCategory(last_id);
                    }
                } else {
                    progressDivCat.append(`<div style="color:red;">Error syncing category: ${escHtml(response.data || 'Unknown error')}</div>`);
                }
            }).fail(function(xhr) {
                progressDivCat.append(`<div style="color:red;">Network/Server Error: ${escHtml(xhr.status)} ${escHtml(xhr.statusText)}</div>`);
            });
        }

        syncNextCategory(lastId);
    }

    function syncTags(lastId = 0) {
        let syncedCount = 0;

        progressDivTag.show();
        progressDivTag.html(`
            <h3>Starting tag sync...</h3>
            <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window until the sync is completed.</p>
            <hr />
        `);

        function syncNextTag(currentId) {
            $.post(SyncAuthorsAjax.ajax_url, {
                action: 'sync_tags',
                nonce: SyncAuthorsAjax.nonce,
                last_id: currentId
            }, function (response) {
                if (response.success && response.data) {
                    const { message, done, last_id } = response.data;

                    if (done) {
                        progressDivTag.append(`
                        <hr />
                        <h3>Tag Sync Complete:</h3>
                        <ul>
                            <li><strong>${syncedCount}</strong> tags successfully synced.</li>
                        </ul>
                        <strong style="color: green;">Process finished.</strong>
                    `);
                    } else {
                        // Not done: Increment count and display row
                        syncedCount++;
                        progressDivTag.append(`<div style="color: purple;">[${syncedCount}] ${escHtml(message)}</div>`);

                        // Recurse with new ID
                        syncNextTag(last_id);
                    }
                } else {
                    progressDivTag.append(`<div style="color:red;">Error syncing tag: ${escHtml(response.data || 'Unknown error')}</div>`);
                }
            }).fail(function(xhr) {
                // Catch Network/Server Errors
                progressDivTag.append(`<div style="color:red;">Network/Server Error: ${escHtml(xhr.status)} - ${escHtml(xhr.statusText)}</div>`);
            });
        }

        syncNextTag(lastId);
    }

    function syncArticles() {
        const selectedType = $('input[name="bus_sync_post_type"]:checked').val();

        // Get the resume ID from the input box
        const startFromIdInput = $('#bus_sync_start_id').val();
        // If input is empty, default to 0 (Newest). If set, parse as Integer.
        let initialCursor = startFromIdInput ? parseInt(startFromIdInput, 10) : 0;

        if (!selectedType) {
            alert('Please select a post type.');
            return;
        }

        let syncedCount = 0;
        progressDivArticle.show();

        // Update UI message to confirm where we are starting
        let startMsg = initialCursor > 0
            ? `Resuming sync from last known ID <strong>${initialCursor}</strong>...`
            : `Starting fresh sync (Newest First)...`;

        progressDivArticle.html(`
            <h3>${startMsg}</h3>
            <p><strong>Type:</strong> ${escHtml(selectedType)}</p>
            <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window.</p>
            <hr />
        `);

        // Recursion
        function syncNextArticle(lastIdCursor) {
            $.post(SyncAuthorsAjax.ajax_url, {
                action: 'sync_articles',
                nonce: SyncAuthorsAjax.nonce,
                last_id: lastIdCursor,
                post_types: [selectedType]
            }, function (response) {
                if (response.success && response.data) {
                    const { message, done, last_id } = response.data;

                    if (done) {
                        progressDivArticle.append(`
                            <hr />
                            <h3>Article Sync Complete:</h3>
                            <ul>
                                <li><strong>${syncedCount}</strong> articles successfully synced in this session.</li>
                            </ul>
                            <strong style="color: green;">Process finished.</strong>
                        `);
                    } else {
                        syncedCount++;
                        progressDivArticle.append(`<div style="color: #333;">[${syncedCount}] ${escHtml(message)}</div>`);
                        progressDivArticle.scrollTop(progressDivArticle[0].scrollHeight);
                        syncNextArticle(last_id);
                    }
                } else {
                    progressDivArticle.append(`<div style="color:red;">Error: ${escHtml(response.data || 'Unknown')}</div>`);
                }
            }).fail(function(xhr) {
                progressDivArticle.append(`<div style="color:red;">Server Error: ${escHtml(xhr.status)} ${escHtml(xhr.statusText)}</div>`);
            });
        }

        // Start the loop with the ID from the input box (or 0)
        syncNextArticle(initialCursor);
    }

    $('#sync-authors-button').on('click', function () {
        syncAuthors();
    });

    $('#sync-categories-button').on('click', function () {
        syncCategories();
    });

    $('#sync-tags-button').on('click', function () {
        syncTags();
    });

    $('#sync-articles-button').on('click', function () {
        syncArticles();
    });
});
