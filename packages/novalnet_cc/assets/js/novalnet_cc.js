/**
* This script is used to load Credit Card Iframe
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
* Script : novalnet_cc.js
*/

const url = 'https://secure.novalnet.de'


jQuery(document).ready(function ()
{
   if(jQuery('#novalnet_cc_maskedform').is(':visible') == true ) {
     jQuery('#one_click_shopping').val('1');
   }
   jQuery('#cc_toggle_name').click(function () {
        if (jQuery('#cc_toggle_name').css('display') == 'block' && jQuery('#one_click_shopping').val() == '1')
        {
            setNewCardProcess();
        } else {
            setSavedCardProcess();
            getIframeHeight();
        }
    }); 
    
    if ( window.addEventListener ) {
		// addEventListener works for all major browsers
		window.addEventListener('message', function(e) {
			addEvent(e);
		}, false);
	} else {
		// attachEvent works for IE8
		window.attachEvent('onmessage', function (e) {
			addEvent(e);
		});
	}

    jQuery(".hikabtn_checkout_payment_submit").hide();
    if(jQuery('#one_click_shopping').val() == '1' && jQuery('#maskedDetails').val() != '0') {
		jQuery("#oneclick_cc, .check_cc").hide();
	}
    var payment_id = jQuery('#cc_paymentid').val();
	
	if (jQuery('input[name="checkout[payment][id]"]:checked').val() === payment_id) {
		jQuery(".hikabtn_checkout_next").attr("onclick","return getHashValue(event);");
	}
});

// Function to handle Event Listener
function addEvent(e) {
    if ( e.origin === url) {
		// Convert message string to object - eval
		var data = (typeof e.data === 'string' ) ? eval('(' + e.data.replace(/(<([^>]+)>)/gi, "") + ')') : e.data;
        if (data['callBack'] == 'getHash') {
            if (data['result'] == 'success') {
               jQuery("#pan_hash").val(data['hash']);
                jQuery("#nn_cc_uniqueid").val(data['unique_id'])
                var paymentForm = jQuery('#nn_payment').closest('form').attr('id');
                window.checkout.setLoading(document.getElementById('hikashop_checkout'), true);
                document.forms['hikashop_checkout_form'].submit();
            } else {
                alert(jQuery('<textarea />').html(data['error_message']).text());
            }
        } else if (data['callBack'] == 'getHeight') {
            jQuery('#nnIframe').height(data['contentHeight']);
        }
    }
}

jQuery(window).resize(function () {
    getIframeHeight();
});

function loadiframe()
{
    var iframe = (jQuery("#nnIframe")[0].contentWindow) ? jQuery("#nnIframe")[0].contentWindow : jQuery("#nnIframe")[0].contentDocument.defaultView;
    var requestObj = {
        callBack: 'createElements',
    };
    var styleObj = {

            labelStyle: jQuery('#inputLabel').val(),
            inputStyle: jQuery('#inputStyle').val(),
            styleText:  jQuery('#cssText').val(),
    };
    var textObj  = {
            cvcHintText: jQuery("#cvc_hint").val(),
            errorText:   jQuery("#iframe_error").val(),
            card_holder:
            {
                labelText: jQuery("#card_holder_label").val(),
                inputText: jQuery("#card_holder_input").val(),
            },
            card_number:
            {
                labelText: jQuery("#card_number_label").val(),
                inputText: jQuery("#card_number_input").val(),
            },
            expiry_date:
            {
                labelText: jQuery("#expiry_date_label").val(),
            },
            cvc:
            {
                labelText: jQuery("#cvc_label").val(),
                inputText: jQuery("#cvc_input").val(),
            }
      };
        requestObj.customText  = textObj;
        requestObj.customStyle = styleObj;
        iframe.postMessage(requestObj, url);
}


function getIframeHeight()
{
    var iframe = (jQuery("#nnIframe")[0].contentWindow) ? jQuery("#nnIframe")[0].contentWindow : jQuery("#nnIframe")[0].contentDocument.defaultView;
    iframe.postMessage({callBack : 'getHeight'}, url);
}

function getHashValue(event)
{
    var payment_id = jQuery('#cc_paymentid').val();
    if (jQuery('input[name="checkout[payment][id]"]:checked').val() === payment_id && jQuery('#payment_radio_1_3__novalnet_cc_'+ payment_id).val() === payment_id)
    {
        if (jQuery('#pan_hash').val() != '' || jQuery('#one_click_shopping').val() == '1') {
            return true;
		}
        event.preventDefault();
        var iframe = (jQuery("#nnIframe")[0].contentWindow) ? jQuery("#nnIframe")[0].contentWindow : jQuery("#nnIframe")[0].contentDocument.defaultView;
        iframe.postMessage({callBack : 'getHash'}, url);
   }
}

function setSavedCardProcess()
{
    jQuery('#oneclick_cc, .check_cc').hide();
    jQuery('#cc_toggle_name').html(jQuery('#new_card_msg').val());
    jQuery('#masked_form').show();
    jQuery('#novalnet_cc_maskedform').show();
    jQuery('#one_click_shopping').val(1);
    jQuery('#cc_form').hide();
}

function setNewCardProcess()
{
    jQuery('#cc_toggle_name').html(jQuery('#given_card_msg').val());
    jQuery('#one_click_shopping').val(0);
    jQuery('#novalnet_cc_maskedform').hide();
    jQuery('#cc_form').show();
    jQuery('#oneclick_cc, .check_cc').show();
}
