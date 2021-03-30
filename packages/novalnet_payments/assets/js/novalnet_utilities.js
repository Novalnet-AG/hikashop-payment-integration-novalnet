/**
* This script is used for back-end configuration validations
*
* @author Novalnet AG
* @copyright Copyright (c) Novalnet
* @license https://www.novalnet.de/payment-plugins/kostenlos/lizenz
* @link https://www.novalnet.de
*
* This free contribution made by request.
*
* If you have found this script useful a small
* recommendation as well as a comment on merchant
*
* Script : novalnet_utilities.js
*/

window.hikashop.ready(function() {

    if (jQuery('[name="data[payment][payment_params][onholdAction]"]').val() == "AUTHORIZE") {
        jQuery('[name="data[payment][payment_params][onHold]"]').show();
        jQuery('[name="data[payment][payment_params][onHold]"]').parent('td').parent('tr').show();
    } else {
        jQuery('[name="data[payment][payment_params][onHold]"]').hide();
        jQuery('[name="data[payment][payment_params][onHold]"]').parent('td').parent('tr').hide();

    }
    // Change the onhold Status
    jQuery('[name="data[payment][payment_params][onholdAction]"]').change(function() {

        if (jQuery('[name="data[payment][payment_params][onholdAction]"]').val() == "AUTHORIZE") {
            jQuery('[name="data[payment][payment_params][onHold]"]').show();
            jQuery('[name="data[payment][payment_params][onHold]"]').parent('td').parent('tr').show();
        } else {
            jQuery('[name="data[payment][payment_params][onHold]"]').hide();
            jQuery('[name="data[payment][payment_params][onHold]"]').parent('td').parent('tr').hide();
        }
    });

    // Allowed only Numbers
    jQuery('[name="data[payment][payment_params][paymentDuration]"], [name="data[payment][payment_params][callbackAmount]"], [name="data[payment][payment_params][minimumAmount]"], [name="data[payment][payment_params][onHold]"]').keypress(function (event) {
     if (event.which != 8 && event.which != 0 && (event.which < 48 || event.which > 57))
     {
        event.preventDefault();
     }
    });
});
