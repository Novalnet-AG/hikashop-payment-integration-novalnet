/**
* This script is used for front-end payment validations
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
* Script : novalnet_formscript.js
*/
 window.hikashop.ready(function() {
	 
			jQuery('input[name="checkout[payment][id]"]').change(function() {
				var data = JSON.parse(jQuery(this).attr('data-hk-checkout'));
				 var payment_name = jQuery('input[name="checkout[payment][id]"]:checked').val();
				jQuery('#birthdate_' + payment_name).on('keypress',function(e){
					if(e.charCode < 48 || e.charCode > 57) return false;
					if (e.keyCode != 8 ) {
						validateAge(this, e);
					}
				});
				if(data.type == 'novalnet_sepa') {
					 if(jQuery('#novalnet_sepa_maskedform').is(':visible') == true ) {
						  jQuery("#check_sepa, .check").hide();
					  }
					 jQuery(".hikabtn_checkout_next").attr("onclick","return sepa_form_submit(this);");
				} else if (data.type == 'novalnet_cc'){
					if(jQuery('#novalnet_cc_maskedform').is(':visible') == true ) {
						 jQuery('#one_click_shopping').val('1');
					}
					if(jQuery('#one_click_shopping').val() == '1' && jQuery('#maskedDetails').val() != '0') {
						jQuery("#oneclick_cc, .check_cc").hide();
				     }
				     jQuery(".hikabtn_checkout_next").attr("onclick","return getHashValue(event);");
				} else {
					 jQuery(".hikabtn_checkout_next").attr("onclick","return window.checkout.submitStep(this);");
				}
			});

            var payment_name = ['sepa', 'invoice'];
            for (var i = 0; i < payment_name.length; i++) {
                if (document.getElementById(payment_name[i] + '_fraud_module')) {
                    var module = jQuery('#' + payment_name[i] + '_fraud_module').val().split('#');
                    if (module[1] == 1) {
                        jQuery('#radio_' + module[0]).closest('tr').css({'display': 'none'});
                    }
                }
            }

			var payment_name = jQuery('input[name="checkout[payment][id]"]:checked').val();
			jQuery('#birthdate_' + payment_name).on('keypress',function(e){
				if(e.charCode < 48 || e.charCode > 57) return false;
				if (e.keyCode != 8 ) {
					validateAge(this, e);
				}  
			});
					
            jQuery('input[name=hikashop_payment]').click(
            function() {
                    jQuery('input[type="radio"]').each(
                    function() {
                            if (jQuery(this).is(':checked')) {
                                var value = jQuery(this).val();
                                if (value.test(/novalnet/)) {
                                    if (value.test(/novalnet_sepa/)) {
                                        jQuery('#sepa_form').css({'display': 'block'});
                                         // Default form elements automatically selected and showed.
                                        jQuery('#hikashop_credit_card_' + value).css({'height' : 'auto', 'display': 'block'});
                                    }
                                    else if (value.test(/novalnet_invoice/)) {
                                        jQuery('#invoice_form').css({'display': 'block'});
                                        // Default form elements automatically selected and showed.
                                        jQuery('#hikashop_credit_card_' + value).css({'height' : '30px', 'display': 'block'});
                                    }
                                }
                            }
                        });
                });
        });
    function validateAge(text, e){
		var placeholder = jQuery(text).attr('placeholder');
		var segregator = placeholder.indexOf("/") > 0 ? '/' : '-';
		if (e.keyCode != 8 ) {
			if (jQuery(text).val().length == 1 || jQuery(text).val().length == 2) {
				jQuery(text).val(checkValue(jQuery(text).val(),31));
			} else if (jQuery(text).val().length == 4) {
				jQuery(text).val(jQuery(text).val().substring(0, 3) + checkValue(jQuery(text).val().substring(3, 4),12));
			} else if (jQuery(text).val().length == 5) {
				jQuery(text).val(jQuery(text).val().substring(0, 3) + checkValue(jQuery(text).val().substring(3, 5),12));
			}
			if (jQuery(text).val().length == 2) {
				jQuery(text).val(jQuery(text).val() + segregator);
			} else if (jQuery(text).val().length == 5) {
				jQuery(text).val(jQuery(text).val() + segregator);
			}
		}
	}
        
    // Validate the birth date field.
    function getAge(payment_id) {
		
		if (jQuery("#birthdate_" + payment_id).val() != '') {
		var dateAr = jQuery("#birthdate_" + payment_id).val().split('-');
		var newDate = dateAr[2] + '-' + dateAr[1] + '-' + dateAr[0];

        if (newDate != "") {
            var today = new Date();
            var birth_date = new Date(newDate);
            var age = today.getFullYear() - birth_date.getFullYear();
            var month = today.getMonth() - birth_date.getMonth();
            var day = today.getDate() - birth_date.getDate();

            if (month < 0 || (month === 0 && today.getDate() < birth_date.getDate())) {
                age--;
            }

            if (month < 0) {
                month += 12;
            }

            if (day < 0) {
                day += 30;
            }

            if (isNaN(age)) {
                alert(jQuery("#date_format").val());
                jQuery("#birthdate_" + payment_id).val('');
            }
        }
	}
    }
    // Validate the telephone number.
    function validateTelno(payment_id) {
        if (! / ^\d + $ / .test(jQuery("#pinby_tel_" + payment_id).val())) {
            alert(jQuery.trim(jQuery('#tel_error').val()));
            jQuery("#pinby_tel_" + payment_id).val('');
            return false;
        }
    }

function checkValue(str, max) {
	if (str.charAt(0) !== '0') {
		var num = parseInt(str);
		if (isNaN(num) || num <= 0 || num > max) num = 1;
			str = num > parseInt(max.toString().charAt(0)) 
			  && num.toString().length == 1 ? '0' + num : ((max == 12) && (parseInt(str) > max)) ? '12' : ((max == 31) && (parseInt(str) > max)) ? '31' : num.toString();
	} else if (str == '00') {
		str = '01';
	}
	return str;
};
