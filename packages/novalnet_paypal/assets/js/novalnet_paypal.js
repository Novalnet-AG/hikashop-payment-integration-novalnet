/**
* This script is used for displaying masked PayPal form
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
* Script : novalnet_paypal.js
*/

function oneclick_paypal_process() {

    // Get the payment id.
    var payment_id = jQuery('#paypal_paymentid').val();
    // Get the new account message.
    var toggle_label = jQuery('#given_card_msg').val();

    if (jQuery("#novalnet_paypal_new_acc").text() == toggle_label) {
        toggle_label = jQuery('#new_account_msg').val();
        jQuery("#novalnet_paypal_oneclick").val(1);
        jQuery("#paypal_masked_value").val(1);
        jQuery("#paypal_oneclick").val('normal');
        jQuery("#novalnet_paypal_new_acc").text(toggle_label);
        // Set the paypal form height.
        jQuery("#paypal_form").css("height", "150");
        // Show the paypal masked form.
        jQuery('#novalnet_paypal_maskedform').css('display', 'block');
        jQuery('#paypal_oneclick_desc').css('display', 'block');
        jQuery('#paypal_description_one').css('display', 'none');
        jQuery("#oneclick_paypal,.check_paypal").hide();

    } else {
        jQuery("#novalnet_paypal_oneclick").val(0);
        jQuery("#paypal_oneclick").val('');
        jQuery("#paypal_masked_value").val(0);
        // Toggle the paypal new account form.
        jQuery("#novalnet_paypal_new_acc").text(toggle_label);
        jQuery("#paypal_form").css("height", "50");
        // Hide the paypal masked form.
        jQuery('#novalnet_paypal_maskedform').css('display', 'none');
        jQuery('#paypal_description_one').css('display', 'block');
        jQuery('#paypal_oneclick_desc').css('display', 'none');
        jQuery("#oneclick_paypal,.check_paypal").show();
    }
}
