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

                    if (skipped) {
                        skippedCount++;
                        progressDiv.append(`<div style="color: red; font-weight: bold;">${message}</div>`);
                    } else {
                        successCount++;
                        progressDiv.append(`<div>${message}</div>`);
                    }

                    if (!done) {
                        offset++;
                        syncNextAuthor();
                    } else {
                        progressDiv.append(`
                            <hr />
                            <h3>All authors have been synced:</h3>
                            <ul>
                                <li><strong>${successCount}</strong> authors successfully synced</li>
                                <li><strong>${skippedCount}</strong> authors skipped (no matching role)</li>
                            </ul>
                            <strong style="color: green;">Sync complete!</strong>
                        `);
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
                    progressDivCat.append('<div style="color: teal;">[Category] ' + message + '</div>');

                    if (!done) {
                        syncNextCategory(last_id);
                    } else {
                        progressDivCat.append('<strong style="color: green;">All categories have been synced.</strong>');
                    }
                } else {
                    progressDivCat.append('<div style="color:red;">Error syncing category: ' + response.data + '</div>');
                }
            });
        }

        syncNextCategory(lastId);
    }

    function syncTags(lastId = 0) {
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
                    progressDivTag.append('<div style="color: purple;">[Tag] ' + message + '</div>');

                    if (!done) {
                        syncNextTag(last_id);
                    } else {
                        progressDivTag.append('<strong style="color: green;">All tags have been synced.</strong>');
                    }
                } else {
                    progressDivTag.append('<div style="color:red;">Error syncing tag: ' + response.data + '</div>');
                }
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
