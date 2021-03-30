/**
* This script is used for updating merchant credentials and API process
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
* Script : novalnet_api.js
*/
window.hikashop.ready(function() {

	jQuery('#data_payment_payment_images').parent('td').parent('tr').hide();
	
	jQuery('[name="data[payment][payment_params][tariffId]"').change(function() {
	var selected_tariff = jQuery('[name="data[payment][payment_params][tariffId]"').val();
	jQuery('[name="data[payment][payment_params][selectedTariff]"').val(selected_tariff);
	});

	jQuery('[name="data[payment][payment_params][vendor]"], [name="data[payment][payment_params][authCode]"], [name="data[payment][payment_params][productId]"], [name="data[payment][payment_params][tariffType]"], [name="data[payment][payment_params][keyPassword]"], [name="data[payment][payment_params][lang]"], [name="data[payment][payment_params][selectedTariff]"]').parent('td').parent('tr').hide();
	
	jQuery('[name="data[payment][payment_params][vendor]"], [name="data[payment][payment_params][authCode]"], [name="data[payment][payment_params][productId]"], [name="data[payment][payment_params][tariffType]"], [name="data[payment][payment_params][keyPassword]"]').attr('readonly', 'true');

    if (jQuery('[name="data[payment][payment_params][novalnetProductActivationKey]"]').val() != undefined || jQuery('[name="data[payment][payment_params][novalnetProductActivationKey]"]').val() != '') {
        sendAutoConfigRequest();
    } else {
        // If activation key is not present to clear the basic vendor params.
        jQuery('[name="data[payment][payment_params][vendor]"], [name="data[payment][payment_params][authCode]"], [name="data[payment][payment_params][productId]"], [name="data[payment][payment_params][tariffType]"], [name="data[payment][payment_params][keyPassword]"], [name="data[payment][payment_params][selectedTariff]"]').val("");
    }
    jQuery('[name="data[payment][payment_params][novalnetProductActivationKey]"]').change(function() {
            // Send auto config request
            sendAutoConfigRequest();
    });
    // If refund type none is showed adjusted the fieldset.
    if (jQuery('#refund_none').attr("checked") == "checked") {
        jQuery("#fieldset_refund").css("height", "290");
        jQuery("#refund_ref").css("width", "300");
        if (jQuery('#refund_ref').is(':visible') == true) {
            jQuery("#fieldset_refund").css("height", "290");
        }
    }
    if (jQuery('#refund_ref').is(':visible') == true) {
        jQuery("#fieldset_refund").css("height", "290");
    }

    // If refund type is clicked hide the sepa account info details.
    jQuery('#refund_none').click(function() {
        jQuery("#fieldset_refund").css({
            "width": "500",
            "height": "290"
        });
    });
    
    jQuery('#submit_refund'). off('click');
    jQuery('#amount_update'). off('click');
    jQuery('#capture'). off('click');
    jQuery('#void'). off('click');
    jQuery('#zero_amount'). off('click');

    // Validate the due date.
    jQuery('#amount_update').click(function() {
        if (jQuery('#amount').val() == '') {
            alert(jQuery('#amount_error_msg').val());
            return false;
        }
        if (confirm(jQuery('#amount_update_confirm_msg').val()) === false) {
            return false;
        }
    });
    // Refund confirm message.
    jQuery('#submit_refund').click(function() {
        if (jQuery('#refund_amount').val() == '') {
            alert(jQuery('#refund_error_msg').val());
            return false;
        }
        if (confirm(jQuery('#refund_confirm_msg').val()) === false) {
            return false;
        }
    });
    // Capture confirm message.
    jQuery('#capture').click(function() {
        if (confirm(jQuery('#debit_confirm_msg').val()) === false) {
            return false;
        }
    });
    // Cancel confirm message.
    jQuery('#void').click(function() {
        if (confirm(jQuery('#cancel_confirm_msg').val()) === false) {
            return false;
        }
    });

    jQuery('#zero_amount').click(function() {
        if (jQuery('#zero_amount_booking').val() == '' || jQuery('#zero_amount_booking').val() == '0') {
            alert(jQuery('#zero_amount_error_msg').val());
            return false;
        }
        if (confirm(jQuery('#zero_amount_confirm_msg').val()) === false) {
            return false;
        }
    });
    
});

// Send autoconfiguration request to get the merchant params.
function sendAutoConfigRequest() {
    // Get the product activation key.
    var product_activation_key = jQuery.trim(jQuery('[name="data[payment][payment_params][novalnetProductActivationKey]"]').val());
    var path = '../plugins/hikashoppayment/novalnet_payments/api/auto_config.php';

    if (!product_activation_key) {
        jQuery('[name="data[payment][payment_params][vendor]"], [name="data[payment][payment_params][authCode]"], [name="data[payment][payment_params][productId]"], [name="data[payment][payment_params][tariffId]"], [name="data[payment][payment_params][keyPassword]"],[name="data[payment][payment_params][tariffType]"]').val("");
        return false;
    }

    // Form the autoconfiguration request params.
    var params = {
        'hash': product_activation_key,
        'lang': jQuery('[name="data[payment][payment_params][lang]"]').val()
    };

    if ('XDomainRequest' in window && window.XDomainRequest !== null) {
        var xdr = new XDomainRequest(); // Use Microsoft XDR
        xdr.open('POST', path);
        xdr.onload = function() { // Calling the function to autofill the vendor details in the configuration fields
            autofillMerchantDetails(this.responseText);
        };
        xdr.onerror = function() {
            return false;
        };
        xdr.send(jQuery.param(params));
    } else {
        jQuery.ajax({
            url: path,
            type: 'post',
            dataType: 'html',
            data: params,
            global: false,
            async: false,
            success: function(result) {

                // Calling the function to autofill the vendor details in the configuration fields
                autofillMerchantDetails(result);
            },
            error: function() {
                return false;
            }
        });
    }
}

function autofillMerchantDetails(datas) {
    var fill_params = jQuery.parseJSON(datas);

    //Locating the Tariff Object
    for (var tariff_id in fill_params.tariff) {

        // Getting the Tariff Name & Type based on Tariff Id        
        var response_tariff_value = tariff_id;
        var response_tariff_name = fill_params.tariff[tariff_id]['name'];
        var response_tariff_type = fill_params.tariff[tariff_id]['type'];

        jQuery('select[name="data[payment][payment_params][tariffId]"]').append(jQuery('<option>', {
            value: response_tariff_value,
            text: response_tariff_name
        }));

        jQuery('select[name="data[payment][payment_params][tariffId]"]').css("display", "block");
        jQuery('select[name="data[payment][payment_params][tariffId]"]').next().css("display", "none");

    }
	var selected_tariff = jQuery('[name="data[payment][payment_params][selectedTariff]"').val();
	if(selected_tariff != '' && selected_tariff != undefined)
	{
		jQuery('select[name="data[payment][payment_params][tariffId]"]').val(selected_tariff).attr("selected", "selected");
	}


    jQuery('select option').filter(function() {
        return jQuery.trim(this.text).length == 0;
    }).remove();

    // Assign the tariff values.

    if (jQuery('[name="data[payment][payment_params][tariffId]"]').val() != undefined && jQuery('[name="data[payment][payment_params][tariffId]"]').val() != '') {
        jQuery('[name="data[payment][payment_params][tariffId]"]').val(jQuery('[name="data[payment][payment_params][tariffId]"]').val()).attr("selected", "selected");
    }

    // Assign the basic params
    if (fill_params['status'] == '100') {
        jQuery('[name="data[payment][payment_params][vendor]"]').val(fill_params.vendor);
        jQuery('[name="data[payment][payment_params][authCode]"]').val(fill_params.auth_code);
        jQuery('[name="data[payment][payment_params][productId]"]').val(fill_params.product);
        jQuery('[name="data[payment][payment_params][keyPassword]"]').val(fill_params.access_key);
        jQuery('[name="data[payment][payment_params][tariffType]"]').val(response_tariff_type);
    } else {
        alert(fill_params.config_result);
        jQuery('[name="data[payment][payment_params][vendor]"]').val(" ");
        jQuery('[name="data[payment][payment_params][authCode]"]').val(" ");
        jQuery('[name="data[payment][payment_params][productId]"]').val(" ");
        jQuery('[name="data[payment][payment_params][keyPassword]"]').val(" ");
        jQuery('[name="data[payment][payment_params][novalnetProductActivationKey]"]').val(" ");
        jQuery('[name="data[payment][payment_params][tariffId]"]').val(" ");
    }
}

// Check the given value is numeric value.
function isNumberKey(event, allowspace) {
    var keycode = ('which' in event) ? event.which : event.keyCode;
    return (/^(?:[0-9]+$)/.test(String.fromCharCode(keycode)) || keycode === 0 || keycode == 8);

}

// Allow only numeric values.
function isNumber(evt) {
    evt = (evt) ? evt : window.event;
    var char_code = (evt.which) ? evt.which : evt.keyCode;
    return (char_code > 31 && char_code != 39 && char_code != 46 && char_code != 37 && (char_code < 48 || char_code > 57 || char_code == 32)) ? false : true;
}

