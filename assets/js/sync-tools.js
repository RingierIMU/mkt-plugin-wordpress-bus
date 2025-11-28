jQuery(document).ready(function ($) {
    const progressDiv = $('#sync-progress');
    const progressDivCat = $('#sync-progress-cat');
    const progressDivTag = $('#sync-progress-tag');

    function syncAuthors() {
        let offset = 0;
        let successCount = 0;
        let skippedCount = 0;

        progressDiv.html(`
            <h3>Starting to sync all authors having the following roles:</h3>
            <pre><code>${SyncAuthorsAjax.role_list.join(', ')}</code></pre>
            <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window until the sync is completed.</p>
            <hr />
        `);

        function syncNextAuthor() {
            $.post(SyncAuthorsAjax.ajax_url, {
                action: 'sync_authors',
                offset: offset
            }, function (response) {
                if (response.success && response.data) {
                    const { message, done, skipped } = response.data;

                    // 1. Check if we are done first
                    if (done) {
                        // If done, we stop the loop and print totals immediately.
                        // We do NOT increment successCount here because this is the "termination" packet.
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
                        // 2. If NOT done, it means we definitely processed a user row.
                        if (skipped) {
                            skippedCount++;
                            progressDiv.append(`<div style="color: red; font-weight: bold;">${message}</div>`);
                        } else {
                            successCount++;
                            progressDiv.append(`<div>${message}</div>`);
                        }

                        // 3. Move to next offset and recurse
                        offset++;
                        syncNextAuthor();
                    }
                } else {
                    progressDiv.append(`<div style="color: red;">Error: ${response.data}</div>`);
                }
            }).fail(function (xhr) {
                progressDiv.append(`<div style="color:red;">AJAX Error: ${xhr.status} - ${xhr.statusText}</div>`);
            });
        }

        syncNextAuthor();
    }

    function syncCategories(lastId = 0) {
        // 1. Initialize the counter
        let syncedCount = 0;

        progressDivCat.html(`
        <h3>Starting category sync...</h3>
        <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window until the sync is completed.</p>
        <hr />
    `);

        function syncNextCategory(currentId) {
            $.post(SyncAuthorsAjax.ajax_url, {
                action: 'sync_categories',
                last_id: currentId
            }, function (response) {
                if (response.success && response.data) {
                    const { message, done, last_id } = response.data;

                    if (done) {
                        // 3. Display the final count in the summary
                        progressDivCat.append(`
                        <hr />
                        <h3>Category Sync Complete:</h3>
                        <ul>
                            <li><strong>${syncedCount}</strong> categories successfully synced.</li>
                        </ul>
                        <strong style="color: green;">Process finished.</strong>
                    `);
                    } else {
                        // 2. Increment the counter
                        syncedCount++;

                        progressDivCat.append(`<div style="color: teal;">[${syncedCount}] ${message}</div>`);
                        syncNextCategory(last_id);
                    }
                } else {
                    progressDivCat.append('<div style="color:red;">Error syncing category: ' + (response.data || 'Unknown error') + '</div>');
                }
            }).fail(function(xhr) {
                progressDivCat.append('<div style="color:red;">Network/Server Error: ' + xhr.status + ' ' + xhr.statusText + '</div>');
            });
        }

        syncNextCategory(lastId);
    }

    function syncTags(lastId = 0) {
        // 1. Initialize Sync Counter
        let syncedCount = 0;

            progressDivTag.html(`
            <h3>Starting tag sync...</h3>
            <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window until the sync is completed.</p>
            <hr />
        `);

        function syncNextTag(currentId) {
            $.post(SyncAuthorsAjax.ajax_url, {
                action: 'sync_tags',
                last_id: currentId
            }, function (response) {
                if (response.success && response.data) {
                    const { message, done, last_id } = response.data;

                    // 2. Logic Check: Are we done?
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
                        // 3. Not done: Increment count and display row
                        syncedCount++;
                        progressDivTag.append(`<div style="color: purple;">[${syncedCount}] ${message}</div>`);

                        // 4. Recurse with new ID
                        syncNextTag(last_id);
                    }
                } else {
                    progressDivTag.append(`<div style="color:red;">Error syncing tag: ${response.data || 'Unknown error'}</div>`);
                }
            }).fail(function(xhr) {
                // 5. Catch Network/Server Errors
                progressDivTag.append(`<div style="color:red;">Network/Server Error: ${xhr.status} - ${xhr.statusText}</div>`);
            });
        }

        syncNextTag(lastId);
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
});
