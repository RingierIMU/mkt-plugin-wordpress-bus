(function($, undefined){
    //use the global @wordpress/data
    const {
        select,
        dispatch,
        subscribe
    } = wp.data;

    //extract vars
    var editor = dispatch( 'core/editor' );
    var notices = dispatch( 'core/notices' );

    var postLocked = false;

    var selectFieldId = "article_lifetime";
    var lockKeyName = 'ringier_article_lifetime_lock';
    var notice_message_id = 'ringer_notice_article_lifetime';
    var notice_message = 'Please select an article lifetime';


    $( document ).ready(function() {
        var itemObject = jQuery("#" + selectFieldId);

        //On first page load
        wp.domReady( () => {
        if (typeof (itemObject.val()) !== "undefined") {
            if (itemObject.val() === "-1") {
                if (!postLocked) {
                    postLocked = true;
                    editor.lockPostSaving(lockKeyName);

                    //show notice
                    notices.createNotice(
                        'error',
                        notice_message,
                        {
                            id: notice_message_id,
                            isDismissible: false,
                        }
                    );
                }
            }

            //Whenever the checkboxes are being checked/unchecked
            itemObject.change(function() {
                var itemObject = jQuery("#" + selectFieldId);

                if (itemObject.val() === "-1") {
                    if (!postLocked) {
                        postLocked = true;
                        editor.lockPostSaving(lockKeyName);

                        //show notice
                        notices.createNotice(
                            'error',
                            notice_message,
                            {
                                id: notice_message_id,
                                isDismissible: false,
                            }
                        );
                    }
                } else if (postLocked) { //value === "on"
                    postLocked = false;
                    editor.unlockPostSaving(lockKeyName);

                    //remove notice
                    notices.removeNotice(notice_message_id);
                }
            });
        }
        });

    });

})(jQuery);
