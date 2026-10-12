/**
 * Publish gate shared by the editor validators of the Ringier plugins.
 *
 * The validators report their failing checks here instead of locking post saving, so authors can
 * always "Save draft". The checks are only enforced when a save would leave the post published,
 * scheduled or private (Publish, Schedule, Update). A refused save shows the editor's own "Publishing
 * failed." notice with one notice per failing check under it; each goes away once its check passes,
 * and the editor's notice once they all have.
 *
 * Relies on the async "editor.preSavePost" filter (WordPress 6.7+). On older versions it falls back
 * to the previous behaviour: lock every save and show the notice straight away.
 *
 * The same file ships in ringier-checklist and ringier-bus (assets/js/publish-gate.js); keep the
 * copies identical. Whichever loads first defines the gate, so one refused Publish lists the
 * failing checks of both plugins.
 */
(function(wp, undefined){
    if (window.ringierPublishGate) {
        return;
    }

    //use the global @wordpress/data
    const {
        select,
        dispatch
    } = wp.data;

    var gatedStatuses = ['publish', 'future', 'private'];
    var failingChecks = {};
    var refusedSaveNotice = null; //content of the editor's notice for the save this gate last refused
    var canGateOnSave = typeof wp.hooks.applyFiltersAsync === 'function';

    window.ringierPublishGate = {
        //Both are called on every editor change, so only act when the state of the check changes
        fail: function(key, message) {
            if (failingChecks[key] === message) {
                return;
            }
            failingChecks[key] = message;

            if (!canGateOnSave) {
                dispatch('core/editor').lockPostSaving(key);
                dispatch('core/notices').createNotice('error', message, { id: key, isDismissible: false });
            }
        },
        pass: function(key) {
            if (!(key in failingChecks)) {
                return;
            }
            delete failingChecks[key];
            dispatch('core/notices').removeNotice(key);

            //Everything is fixed: drop the editor's "Publishing failed." too, unless a later save replaced it
            if (refusedSaveNotice !== null && Object.keys(failingChecks).length === 0) {
                var saveNotice = select('core/notices').getNotices().find(function(notice) {
                    return notice.id === 'editor-save';
                });
                if (saveNotice && saveNotice.content === refusedSaveNotice) {
                    dispatch('core/notices').removeNotice('editor-save');
                }
                refusedSaveNotice = null;
            }

            if (!canGateOnSave) {
                dispatch('core/editor').unlockPostSaving(key);
            }
        }
    };

    if (!canGateOnSave) {
        return;
    }

    //Throwing here aborts the save, and the editor shows its "Publishing failed." / "Updating failed." notice
    wp.hooks.addFilter('editor.preSavePost', 'ringier/publish-gate', function(edits, options) {
        var keys = Object.keys(failingChecks);

        if (options.isAutosave || options.isPreview || keys.length === 0) {
            return edits;
        }

        var currentStatus = select('core/editor').getCurrentPost().status;
        if (gatedStatuses.indexOf(edits.status || currentStatus) === -1) {
            return edits;
        }

        //Publish sets the status before saving; undo it so the post stays a draft and "Save draft" comes back
        if (edits.status) {
            dispatch('core/editor').editPost({ status: currentStatus }, { undoIgnore: true });
        }

        //One notice per failing check, so each reason is on its own line. Notices are listed newest first and
        //the editor's own is created once this filter has thrown, so adding these in reverse puts it on top
        //and the checks under it in order
        keys.slice().reverse().forEach(function(key) {
            dispatch('core/notices').createNotice('error', failingChecks[key], { id: key });
        });

        //Remember the editor's notice for this refusal (created right after the throw) so pass() can clear it
        setTimeout(function() {
            var saveNotice = select('core/notices').getNotices().find(function(notice) {
                return notice.id === 'editor-save';
            });
            refusedSaveNotice = saveNotice && saveNotice.status === 'error' ? saveNotice.content : null;
        });

        //No message: the editor would append it to its own notice, all on one line
        throw new Error();
    });

})(window.wp);
