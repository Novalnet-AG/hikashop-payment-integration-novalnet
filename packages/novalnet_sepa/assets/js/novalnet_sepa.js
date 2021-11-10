/**
* This script is used for Direct Debit SEPA payment form
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
* Script : novalnet_sepa.js
*/
window.hikashop.ready(
        function() {
			
			if(jQuery('#novalnet_sepa_maskedform').is(':visible') == true ) {
              jQuery("#check_sepa, .check").hide();
			}
            jQuery(".hikabtn_checkout_payment_submit").hide();
            // Replace the payment name.
            var payment_name = jQuery("input[name=hikashop_payment]:checked").val();
            var payment_id = jQuery.trim(jQuery('#sepa_paymentid').val());
            
            jQuery('#nnsepa_account_number_' + payment_id).keyup(function (event) {
				this.value = this.value.toUpperCase();
				var field = this.value;
				var value = "";
				for(var i = 0; i < field.length;i++){
					if(i <= 1){
						if(field.charAt(i).match(/^[A-Za-z]/)){
						value += field.charAt(i);
						}
					}
					if(i > 1){
						if(field.charAt(i).match(/^[0-9]/)){
						value += field.charAt(i);
						}
					}
				}
				field = this.value = value;
			});
			

            jQuery("#sepa_oneclick").val('normal');
            var toggeleLabel = jQuery.trim(jQuery('#new_account_msg').val());
            var oneclick = jQuery("#sepa_onclick_value").val();

            if (oneclick == 'ONECLICK_SHOPPING' && jQuery("#sepa_toggle_name").text() == toggeleLabel) {
                jQuery("#sepa_oneclick").val('');
            }
            if (jQuery('#pin_' + payment_id).length) {
                jQuery(".hikashop_checkout_payment_submit").hide();
            }
        }
    );
function sepa_oneclick() {
        var payment_id = jQuery.trim(jQuery('#sepa_paymentid').val());
        var toggeleLabel = jQuery.trim(jQuery('#new_account_msg').val());

        if (jQuery("#sepa_toggle_name").text() == toggeleLabel) {
            toggeleLabel = jQuery.trim(jQuery('#given_card_msg').val());
            jQuery(".hikashop_checkout_payment_submit").hide();
            jQuery("#novalnet_sepa_oneclick").val(0);
            jQuery("#sepa_masked_value").val(0);
            jQuery("#sepa_oneclick").val('normal');
            jQuery("#check_sepa, .check").show();
        }
        else {
            jQuery("#novalnet_sepa_oneclick").val(1);
            jQuery("#sepa_masked_value").val(1);
            jQuery("#check_sepa, .check").hide();
            jQuery(".hikashop_checkout_payment_submit").hide();
            jQuery("#sepa_oneclick").val('');
        }

        jQuery("#sepa_toggle_name").text(toggeleLabel);
        jQuery("#novalnet_sepa_maskedform, #novalnetSepaForm, #fraud_module").toggle();
    }


function sepa_form_submit(event) {

    var payment_id = jQuery.trim(jQuery('#sepa_paymentid').val());
    var oneclick = jQuery("#novalnet_sepa_oneclick").val();

    if(jQuery("input[class=hikashop_checkout_payment_radio]:checked").val() == payment_id) {
        if ((jQuery("#nnsepa_account_number_" + payment_id).val() == "") && (oneclick == 0 || oneclick == undefined)) {
            alert(jQuery("#sepa_error_msg").val());
            return false;
        }
    }
    window.checkout.setLoading(document.getElementById('hikashop_checkout'), true);
    event.form.submit();
}


