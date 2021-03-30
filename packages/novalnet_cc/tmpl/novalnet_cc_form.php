<?php
/**
* This script is used to display Credit Card form
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
* Script : novalnet_cc_form.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

require_once JPATH_PLUGINS . DS . 'hikashoppayment' . DS . 'novalnet_payments' . DS . 'helper' . DS . 'novalnet_utilities.php';

/**
 * Credit Card form class
 *
 * @package Hikashop_Payment_Plugin
 */
class NovalnetCreditCardForm
{
    /**
     * To render the Credit Card masking pattern
     *
     * @param  array $maskedPatterns masked values
     * @param  object $method current method object
     * @param  array $configDetails config details
     * @param  int $tid transaction id
     *
     * @return string
     */
    public static function renderMaskedForm($maskedPatterns, $method, $configDetails, $tid)
    {
        $document = JFactory::getDocument();
        // Adding style script
        $document->addStyleDeclaration('a#cc_toggle_name:hover {
                    background: none;
                    cursor: pointer;
                  }');
       $display =
       $html = '<a id="cc_toggle_name"  style="display:block;">' . JText::_('HKPAYMENT_NOVALNET_NEW_CARD_DETAILS') . '</a>
        <table id="novalnet_cc_maskedform" border="0" cellspacing="0" cellpadding="2" width="80%">
            <tr valign="top">
                <td nowrap align="right" style="padding:1%">
                    <label>' . JText::_('HKPAYMENT_NOVALNET_CC_TYPE') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['cc_type'] . '</td>
            </tr>
            <tr valign="top">
                <td nowrap align="right" style="padding:1%">
                    <label>' . JText::_('HKPAYMENT_NOVALNET_CC_OWNER') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['cc_holder'] . '</td>
            </tr>
            <tr valign="top">
                <td nowrap align="right" style="padding:1%">
                    <label>' . JText::_('HKPAYMENT_NOVALNET_CC_NUMBER') . '</label>
                </td>
                <td style="padding:1%">' . $maskedPatterns['cc_no'] . '</td>
            </tr>
            <tr>
                <td nowrap align="right" style="padding:1%"> <label>' . JText::_('HKPAYMENT_NOVALNET_CC_EXPIRATION') . '</label></td>
                <td style="padding:1%">' . $maskedPatterns['cc_exp_month'] . '/' . $maskedPatterns['cc_exp_year'] . '</td>
            </tr>';

        $html.= '<input type="hidden" name="payment_ref_'.$method->payment_id.'" id="payment_ref_'.$method->payment_id.'" value="' . $tid . '"/></table>';
        // Show new card detail form
        $html.= self::renderIframeForm($method, $configDetails, 'none');
        return $html;
    }

    /**
     * To render the Credit Card iframe form
     *
     * @param  array $paymentDetails payment details
     * @param  object $configDetails config  details
     * @param  string $display display the form
     *
     * @return string
     */
    public static function renderIframeForm($paymentDetails, $configDetails, $display)
    {
        // Get the shop language using JFactory method
        $shopLanguage = JFactory::getLanguage();
        $language = strtolower(substr($shopLanguage->getTag(), 0, 2));
        $server_ip = hikashop_getIP();
        try
        {
            // Get the signature value using base64_encode function
            $signature = 'https://secure.novalnet.de/cc?api=' . base64_encode("vendor=$configDetails->vendor&product=$configDetails->productId&server_ip=$server_ip&lang=$language");
        }
        catch(Exception $e)
        {
            echo 'Caught exception: ' . $e->getMessage();
            exit;
        }
        $html = "";
        $html.= '<div id="cc_form" style="display:' . $display . '">
        <iframe id="nnIframe" src="' . $signature . '" style=" border-style:none !important;" onload="loadiframe()" width="100%" ></iframe></div>';
        $oneclick = 0;
        if ($paymentDetails->payment_params->shoppingType == "ONECLICK_SHOPPING" && $paymentDetails->payment_params->cc3d != 1 && $paymentDetails->payment_params->cc3d_force != 1)
        {
			$oneclick = 1;
            $html.= '<input type="checkbox" id="oneclick_cc" name="oneclick_cc">';
            $html.= '<p class="check_cc">' . JText::_('HKPAYMENT_NOVALNET_PAYMENT_CC_SAVE_CARD') . '</p>';
        }
        $cc3d = ($paymentDetails->payment_params->cc3d == 1 || $paymentDetails->payment_params->cc3d_force == 1) ? 1 : 0;
        $html.= '</div>';
        $id =  array('cc_3d', 'card_holder_label', 'card_holder_input', 'card_number_label', 'card_number_input', 'expiry_date_label', 'expiry_date_input', 'cvc_label', 'cvc_input', 'cvc_hint', 'iframe_error', 'inputLabel', 'inputStyle', 'cssText', 'cc_onclick_value', 'cc_masked_value', 'pan_hash', 'nn_cc_uniqueid', 'novalnet_cc_id', 'cc_oneclick', 'one_click_shopping', 'given_card_msg', 'new_card_msg', 'cc_paymentid');
        $values = array($cc3d, JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_HOLDER_LABEL_TEXT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_HOLDER_INPUT_TEXT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_NUMBER_LABEL_TEXT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_NUMBER_INPUT_TEXT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_EXPIRYDATE_LABEL_TEXT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_EXPIRYDATE_INPUT_TEXT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_CVC_LABEL_TEXT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_CVC_INPUT_TEXT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_CVC_HINT'), JText::_('HKPAYMENT_NOVALNET_CC_IFRAME_ERROR'), $paymentDetails->payment_params->inputLabel, $paymentDetails->payment_params->inputStyle, $paymentDetails->payment_params->cssText, $paymentDetails->payment_params->shoppingType, '1', '', '', $paymentDetails->payment_id, $oneclick, '0', JText::_('HKPAYMENT_NOVALNET_GIVEN_CARD_DETAILS'), JText::_('HKPAYMENT_NOVALNET_NEW_CARD_DETAILS'), $paymentDetails->payment_id);
        foreach($values as $index => $code )
        {
          $html .= '<input type="hidden" value="' . $code . '" id ="' . $id[$index]. '"  name ="' . $id[$index]. '">';
        }
        return $html;
    }
}
