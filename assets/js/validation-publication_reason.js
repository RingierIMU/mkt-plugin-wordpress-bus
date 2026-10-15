(function($, undefined){
    var selectFieldId = "publication_reason";
    var check_key = 'ringier_publication_reason';
    var notice_message = 'Please select a Publication reason';

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
