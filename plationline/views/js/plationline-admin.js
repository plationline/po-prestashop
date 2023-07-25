$(function () {
    if ($('#CC_PO').is(':checked')) {
        $('#fieldset_1_1').show();
    } else {
        $('#fieldset_1_1').hide();
    }

    $('input[type="radio"][name="PLATIONLINE_RO_CC"]').on('change', function () {
        if ($(this).val() == "PO") {
            $('#fieldset_1_1').show();
        } else {
            $('#fieldset_1_1').hide();
        }
    });
});
