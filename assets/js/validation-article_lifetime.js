(function($, undefined){
    var selectFieldId = "article_lifetime";
    var check_key = 'ringier_article_lifetime';
    var notice_message = 'Please select an article lifetime';

    //blocks Publish/Update (not Save draft) until valid - see publish-gate.js
    function validate() {
        if (jQuery("#" + selectFieldId).val() === "-1") {
            ringierPublishGate.fail(check_key, notice_message);
        } else {
            ringierPublishGate.pass(check_key);
        }
    }

    $( document ).ready(function() {
        var itemObject = jQuery("#" + selectFieldId);

        //On first page load
        wp.domReady( () => {
        if (typeof (itemObject.val()) !== "undefined") {
            validate();

            //Whenever the value changes
            itemObject.change(validate);
        }
        });
    });
})(jQuery);
