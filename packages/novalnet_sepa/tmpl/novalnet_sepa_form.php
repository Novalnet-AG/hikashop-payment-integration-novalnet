<?php
/**
* This script is used for display the Direct Debit SEPA Form
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
* Script : novalnet_sepa_form.php
*/

defined('_JEXEC') or die('Restricted access');
/**
 * Novalnet sepa form class
 *
 * @package Hikashop_Payment_Plugin
 * @since   11.1
 */
class novalnetSepaForm
{
    /**
     * To display SEPA form fields in front end
     *
     * @param   array  $sepaFormValues sepa    form    values
     * @param   object $method         current payment params
     * @param   int    $amount         amount  value
     * @param   string $formDisplay    display form value
     * @param   object  $order get the order object
     * @return  string
     */
    public static function sepaFormDisplay($sepaFormValues, $method, $amount, $formDisplay, $order)
    {
        $display = '<div class="novalnetSepaForm hkform-horizontal" id="novalnetSepaForm" style="display:' . $formDisplay . '">';
        // Display the sepa form fields.
        if (NovalnetUtilities::handleSession('tid_' . $sepaFormValues['payment_id'], '', 'get') == '')
        {
			$display .= '<div class="hkform-group control-group">
				<label for="nnsepa_holder_' . $sepaFormValues['payment_id'] . '" class="hkc-sm-3 hkcontrol-label">' . JText::_('HKPAYMENT_NOVALNET_SEPA_OWNER'). '<span class="hikashop_field_required_label">*</span></label><div class="hkc-sm-4">
				<input class="inputbox hkform-control" type="text"  name="nnsepa_holder_' . $sepaFormValues['payment_id'] . '" id="nnsepa_holder_' . $sepaFormValues['payment_id'] . '" value="'.$sepaFormValues['name'].'" ></div></div>';
				
			$display .= '<div class="hkform-group control-group"><label for="nnsepa_account_number_' . $sepaFormValues['payment_id'] . '" class="hkc-sm-3 hkcontrol-label">' . JText::_('HKPAYMENT_NOVALNET_SEPA_IBAN') . '<span class="hikashop_field_required_label">*</span></label><div class="hkc-sm-4">
				<input class="inputbox hkform-control" type="text"  name="nnsepa_account_number_' . $sepaFormValues['payment_id'] . '" id="nnsepa_account_number_' . $sepaFormValues['payment_id'] . '" style="text-transform: uppercase;" autocomplete="off" value="" maxlength="41"></div></div></div>';

             // Show the birth date field.
			if ($method->payment_params->guaranteeEnable)
			{
				NovalnetUtilities::handleSession('non_guarantee_with_fraudcheck_' . $method->payment_id, '', 'clear');
				$display .= '<div class="birthday_field_form hkform-horizontal" id="birthday_field_form"' . $formDisplay . '>';
				$display .= NovalnetUtilities::guaranteePaymentImplementation($method, $amount);
				$display .= '</div>';
			}
			else
			{
				NovalnetUtilities::handleSession('guarantee_' . $method->payment_id, '', 'clear');
				NovalnetUtilities::handleSession('error_guarantee_' . $method->payment_id, '', 'clear');
			}
			
			// Check fraud module is enable or not.
			if ((!NovalnetUtilities::handleSession('guarantee_' . $sepaFormValues['payment_id'], '', 'get') && !NovalnetUtilities::handleSession('error_guarantee_' . $sepaFormValues['payment_id'], '', 'get') && NovalnetValidation::fraudModuleCheck($method, $amount, $sepaFormValues['country_code']) && $method->payment_params->guaranteeMethod == '1') || ($method->payment_params->pinbyCallback && $method->payment_params->guaranteeMethod != '1' && $method->payment_params->guaranteeEnable != '1')) {
				if ($method->payment_params->shoppingType == 'ONECLICK_SHOPPING')
				{
					NovalnetUtilities::handleSession(1, 'fraud_module' . $method->payment_id, 'set');
				}
				NovalnetUtilities::handleSession(1, 'non_guarantee_with_fraudcheck_' . $method->payment_id, 'set');
				// Display fraud module Input fields Pin/Sms.
				$display.= '<div id = "fraud_module" class="fraud_module hkform-horizontal" style="display:' . $formDisplay . '">' . NovalnetUtilities::pinByCallCheck($method, $order) . '</div>';
			}
            
            $display.= '<div class="form-group">
                        <a data-toggle="collapse" data-target="#sepa_mandate_information"><strong>' . JText::_('HKPAYMENT_NOVALNET_MANDATE_CONFIRM') . '</strong></a>
                        <div class="collapse panel panel-default" id="sepa_mandate_information">
                        ' . JText::_('HKPAYMENT_NOVALNET_MANDATE_CONFIRM_TEXT') . '  </div></div>';
            $display.= '<input type="checkbox" id="check_sepa" name="check_sepa">';
            $display.= '<p class="check">' . JText::_('HKPAYMENT_NOVALNET_PAYMENT_SEPA_SAVE_CARD') . '</p>';
            $display.= '<input type="hidden" id="sepa_oneclick" name="sepa_oneclick"/>';
            $display.= '<input type="hidden" id="sepa_onclick_value" name="sepa_onclick_value" value="' . $method->payment_params->shoppingType . '"/>   ';
            $display.= '<input type="hidden" id="account_error" name="account_error" value="' . JText::_('HKPAYMENT_NOVALNET_HOLDER_NAME_ERROR') . '"/>';
            $display.= '<input type="hidden" id="sepa_paymentid" name="sepa_paymentid" value="' . $sepaFormValues['payment_id'] . '"/>';
            $display.= '<input type="hidden"  name="sepa_masked_value" id="sepa_masked_value" value="1">';
            $display.= '<input type="hidden"  name="sepa_error_msg" id="sepa_error_msg" value="' . JText::_('HKPAYMENT_NOVALNET_HOLDER_IBAN_ERROR') . '">';
        }
        // Show the fraud module pin field.
        if (NovalnetUtilities::handleSession('fraud_module' . $method->payment_id, '', 'get'))
        {
            $formDisplay = 'block';
        }
        
        $display.= '<script type="text/javascript">
        jQuery(document).ready(function() {
            var payment_id = jQuery("#sepa_paymentid").val();
            jQuery("#payment_radio_1_3__novalnet_sepa_"+payment_id).parent("td").children(".hikabtn_checkout_payment_submit").css("display", "none");
            jQuery("#check_sepa, .check").hide();
            if (jQuery("#sepa_onclick_value").val() == "ONECLICK_SHOPPING") {
                jQuery("#check_sepa, .check").show();
                jQuery(".hikabtn_checkout_payment_submit").css("display","none");
            }
            if(jQuery("#sepa_form").css("display") == "block") {
            if(jQuery("input[class=hikashop_checkout_payment_radio]:checked").val() == payment_id) {
            if (jQuery("#sepa_onclick_value").val() != "ONECLICK_SHOPPING") {
                    jQuery(".hikabtn_checkout_payment_submit").hide();
                    }
                }
            }
            if(jQuery("input[class=hikashop_checkout_payment_radio]:checked").val() == payment_id) {
                if(jQuery("#pin"+payment_id).length)
                    jQuery(".hikabtn_checkout_payment_submit").hide();
                    jQuery(".hikabtn_checkout_next").attr("onclick","return sepa_form_submit(this);");
            }
            if(jQuery("#novalnet_sepa_oneclick").val() == 1) {
                jQuery("#sepa_masked_value").val(1);
                jQuery("#check_sepa, .check").show();
            }
        });
        </script>';
        return $display;
    }
    /**
     * render the masked form
     *
     * @param   array $sepaValues     sepa        form    values
     * @param   array $maskedPatterns get         the     masked pattern
     * @param   int   $amount         get         the     amount
     * @param   array $method         current     payment params
     * @param   array $tid            transaction id
     * @param   object $order   get the order object
     * @return  string
     */
    public static function renderMaskedForm($sepaValues, $maskedPatterns, $amount, $method, $tid, $order)
    {

        NovalnetUtilities::handleSession('fraud_module' . $method->payment_id, '', 'clear');
        $document = JFactory::getDocument();
        $document->addStyleDeclaration('a#sepa_toggle_name:hover {
                    background: none;
                    cursor: pointer;
                  }');
        $html = '<br/><a id="sepa_toggle_name" onclick="sepa_oneclick();"style="color: #095197; text-decoration: underline; font-weight: bold;">' . JTEXT::_('HKPAYMENT_NOVALNET_NEW_CARD_SEPA_DETAILS') . '</a><br/>
        <br/><div id="novalnet_sepa_maskedform_one">
        <div class="table-responsive-lg">
        <table id="novalnet_sepa_maskedform" class="table table-striped" align="left"  style="width: 100%;">
            <tr>
                <td><label>'.JTEXT::_('HKPAYMENT_NOVALNET_SEPA_OWNER').'</label></td>
                <td><input type="text" name="mask_name_' . $sepaValues['payment_id'] . '" value="'.$maskedPatterns['bankaccount_holder'].'"  readonly></td>
            </tr>
            <tr>
                <td><label>' . JTEXT::_('HKPAYMENT_NOVALNET_SEPA_IBAN_ONECLICK') . '</label></td>
                <td><input type="text"  name="mask_iban_' . $sepaValues['payment_id'] . '"  value="'. $maskedPatterns['iban'].'" readonly></td>
            </tr>
            </table></div></div>
        <input type="hidden" name="sepa_hiddenform" id="sepa_hiddenform" value="1"/>
        <input type="hidden" name="novalnet_sepa_oneclick" id="novalnet_sepa_oneclick" value="1"/>
        <input type="hidden" name="payment_ref" id="payment_ref" value="' . $tid . '"/>
        <input type="hidden" id="given_card_msg" name="given_card_msg" value="' . JText::_('HKPAYMENT_NOVALNET_GIVEN_CARD_SEPA_DETAILS') . '"/>
        <input type="hidden" id="sepa_paymentid" name="sepa_paymentid" value="' . $method->payment_id . '"/>
        <input type="hidden" id="new_account_msg" name="new_account_msg" value="' . JText::_('HKPAYMENT_NOVALNET_NEW_CARD_SEPA_DETAILS') . '"/>';
        // Call the sepa form if new account is enabled.
        $html.= self::sepaFormDisplay($sepaValues, $method, $amount, 'none', $order);
        return $html;
    }
}
