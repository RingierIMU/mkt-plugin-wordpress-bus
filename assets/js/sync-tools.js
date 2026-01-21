jQuery(document).ready(function ($) {
    const progressDiv = $('#sync-progress');
    const progressDivCat = $('#sync-progress-cat');
    const progressDivTag = $('#sync-progress-tag');
    const progressDivArticle = $('#sync-progress-article');

    function syncAuthors() {
        let offset = 0;
        let successCount = 0;
        let skippedCount = 0;

        progressDiv.show();
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
                            progressDiv.append(`<div style="color: red; font-weight: bold;">${message}</div>`);
                        } else {
                            successCount++;
                            progressDiv.append(`<div>${message}</div>`);
                        }

                        // Move to next offset and recurse
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
                        progressDivTag.append(`<div style="color: purple;">[${syncedCount}] ${message}</div>`);

                        // Recurse with new ID
                        syncNextTag(last_id);
                    }
                } else {
                    progressDivTag.append(`<div style="color:red;">Error syncing tag: ${response.data || 'Unknown error'}</div>`);
                }
            }).fail(function(xhr) {
                // Catch Network/Server Errors
                progressDivTag.append(`<div style="color:red;">Network/Server Error: ${xhr.status} - ${xhr.statusText}</div>`);
            });
        }

        syncNextTag(lastId);
    }

    function syncArticles() {
        const selectedType = $('input[name="bus_sync_post_type"]:checked').val();

        if (!selectedType) {
            alert('Please select a post type.');
            return;
        }

        let syncedCount = 0;
        progressDivArticle.show();
        progressDivArticle.html(`
            <h3>Starting article sync (Recent First)...</h3>
            <p><strong>Type:</strong> ${selectedType}</p>
            <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window.</p>
            <hr />
        `);

        // Recursion
        function syncNextArticle(lastIdCursor) {
            $.post(SyncAuthorsAjax.ajax_url, {
                action: 'sync_articles',
                last_id: lastIdCursor,
                // Send array as the PHP backend expects 'post_types' array
                post_types: [selectedType]
            }, function (response) {
                if (response.success && response.data) {
                    const { message, done, last_id } = response.data;

                    if (done) {
                        progressDivArticle.append(`
                            <hr />
                            <h3>Article Sync Complete:</h3>
                            <ul>
                                <li><strong>${syncedCount}</strong> articles successfully synced.</li>
                            </ul>
                            <strong style="color: green;">Process finished.</strong>
                        `);
                    } else {
                        syncedCount++;
                        progressDivArticle.append(`<div style="color: #333;">[${syncedCount}] ${message}</div>`);
                        progressDivArticle.scrollTop(progressDivArticle[0].scrollHeight);
                        syncNextArticle(last_id);
                    }
                } else {
                    progressDivArticle.append(`<div style="color:red;">Error: ${response.data || 'Unknown'}</div>`);
                }
            }).fail(function(xhr) {
                progressDivArticle.append(`<div style="color:red;">Server Error: ${xhr.status} ${xhr.statusText}</div>`);
            });
        }

        syncNextArticle(0);
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
