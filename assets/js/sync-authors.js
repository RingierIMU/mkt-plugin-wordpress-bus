jQuery(document).ready(function ($) {
    $('#sync-authors-button').on('click', function () {
        const progressDiv = $('#sync-progress');
        let offset = 0;
        let successCount = 0;
        let skippedCount = 0;

        progressDiv.html(`
            <h3>Starting to sync all authors having the following roles:</h3>
            <pre><code>${SyncAuthorsAjax.role_list.join(', ')}</code></pre>
            <p style="font-weight: bold; color: #0073aa;">Please do NOT close this window until the sync is completed.</p>
            <hr />
        `);

        function syncAuthors() {
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
                        syncAuthors();
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
            }).fail(function (xhr, status, error) {
                progressDiv.append(`<div style="color:red;">AJAX Error: ${xhr.status} - ${xhr.statusText}</div>`);
            });
        }

        syncAuthors();
    });
});
