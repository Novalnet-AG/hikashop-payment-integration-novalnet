<?php
/**
* This script is used to display the extension form
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
* Script : novalnet_api_form.php
*/

if (!defined('_VALID_MOS') && !defined('_JEXEC'))
{
    die('Direct Access to ' . basename(__FILE__) . ' is not allowed.');
}

/**
 * Novalnet API class
 *
 * @package Hikashop_Payment_Plugin
 */
class PlgHikashoppaymentnovalnet_ApiForm extends hikashopPaymentPlugin
{
    /**
     * Display Refund form template
     *
     * @param  object $order get the order object
     * @return string
     */
    public static function displayRefundForm($order)
    {
        $displayForm = '';
        $orderDetails = json_decode(($order->payment_specific_data), true);
        $dueDate = (!empty($orderDetails['due_date']) ? $orderDetails['due_date'] : '');
        
        // Gets order class.
        $orderClass = hikashop_get('class.order');

        // Loads order object using the order id.
        $orderDetails = $orderClass->loadFullOrder($order->hika_order_id, false, false);

        // Check the amount to show the refund option.
        if (!empty($order->order_amount != 0) && $order->gateway_status == '100')
        {
            $displayForm.= '<fieldset id ="fieldset_refund" style="width:500px;height:230px;margin-top:20px;margin-bottom:20px;margin-left:20px;"><legend>' . JText::_('HKPAYMENT_NOVALNET_ADMIN_REFUND_TITLE') . '</legend><div style="margin-left: 3%;margin-top: 20px;margin-bottom: 3%;" id="partial_amount"><label style="margin-right:18px;font-size: 13px;">' . JText::_('HKPAYMENT_NOVALNET_ADMIN_REFUND_LABEL') . '</label><input type="text" id="refund_amount" onkeypress="return isNumberKey(event, true)"; autocomplete="off" name="refund_amount" style="background-color:#dcdcdc;margin-right:1%;padding: 5px;width: 80px;" value="' . NovalnetUtilities::doFormatAmount($order->amount) . '" /><br>' . JText::_('HKPAYMENT_NOVALNET_CENT') . '</div>';
            // Show refund reference block.
            if (date('Y-m-d') !== date('Y-m-d', $orderDetails->order_created))
            {
                $displayForm.= '</br><table ><div style="margin-top:5px;" id="refund_ref_label">
                <tr><td style="margin-left: 22%;width: 32.5%;"><label style="">' . JText::_('HKPAYMENT_NOVALNET_ADMIN_REFUND_FORMULAR_LABEL') . '</label></td>
                <td><input type="text" id="refund_ref" autocomplete="off" name="refund_ref" style="padding:5px;background-color:#dcdcdc;border-radius: 1px;margin-left: 32%;" /></td></tr>
                    </div></table>';
            }
            $displayForm.= '<input type="hidden" id="refund_confirm_msg" value="' . JText::_('HKPAYMENT_NOVALNET_REFUND_CONFIRM_MSG') . '" /><input type="hidden" id="refund_error_msg" value="' . JText::_('HKPAYMENT_NOVALNET_AMOUNT_VALIDATE_MSG') . '" /><div class="submit-button" style="margin-top:20px;margin-left:184px;"><input type="submit" style="padding:5px;background-color:#dcdcdc;border-radius: 1px;margin-left: 11%;padding: 5px;" name="refund" ' . ($order->order_amount == 0 ? 'disabled' : '') . ' id="submit_refund" value="' . JText::_('HKPAYMENT_NOVALNET_ADMIN_DEBIT_BUTTON') . '" /></div></fieldset>';
            return $displayForm;
        }
    }

    /**
     * Display Amount Update template
     *
     * @param  object $order get the order object
     * @return string
     */
    public static function displayAmountUpdate($order)
    {
        $orderDetails = json_decode(($order->additional_data), true);
        $callbackAmount = NovalnetUtilities::getCallbackAmount($order->hika_order_id, $orderDetails['payment_method']);
        $callbackAmount = (isset($callbackAmount->total_amount) ? $callbackAmount->total_amount : $callbackAmount->callback_amount);
        $orderAmount = NovalnetUtilities::doFormatAmount($order->amount);
        $displayForm = '';
        $dueDate = json_decode($order->payment_specific_data);
        $dueDate = in_array($orderDetails['payment_method'], array('novalnet_invoice', 'novalnet_prepayment')) ? (!empty($dueDate->invoice_duedate) ? $dueDate->invoice_duedate : $dueDate->due_date) : (!empty($dueDate->cashpayment_due_date) ? $dueDate->cashpayment_due_date : (!empty($dueDate) ? $dueDate->due_date : ''));

        if (in_array($orderDetails['payment_method'], array('novalnet_invoice', 'novalnet_prepayment', 'novalnet_cashpayment', 'novalnet_sepa')))
        {
            // Check order amount need to show amount update block.
            if ($orderAmount <= $callbackAmount)
            {
                return $displayForm;
            }
            else
            {
                $message = (in_array($orderDetails['payment_method'], array('novalnet_invoice', 'novalnet_prepayment'))) ? JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_DUE_DATE_UPDATE_TITLE') : (($orderDetails['payment_method'] == 'novalnet_cashpayment') ? JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_SLIP_DATE_UPDATE_TITLE') : JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_UPDATE_TITLE'));
                $displayForm = '<fieldset style=width:500px;margin-left:50px;margin-bottom:20px;margin-top:20px;height:230px;><legend>' . $message . '</legend>
                        <div id="content">
                <table><tr><td><label>' . JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_UPDATE_LABEL') . '</label></td>
                        <td><input type="text" autocomplete="off" onkeypress="return isNumberKey(event, true)"; style="background-color: #dcdcdc;padding: 5px;" name="amount" id="amount" value="' . NovalnetUtilities::doFormatAmount($order->amount) . '" /><br>' . JText::_('HKPAYMENT_NOVALNET_CENT') . ' <br /></td></tr>';
                $message = ($orderDetails['payment_method'] == 'novalnet_cashpayment') ? JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_SLIP_EXPIRY_DATE_UPDATE_TITLE') : JText::_('HKPAYMENT_NOVALNET_INVOICE_ADMIN_DUE_DATE');
                if (in_array($orderDetails['payment_method'], array('novalnet_invoice', 'novalnet_prepayment', 'novalnet_cashpayment'))) {
                    $displayForm.= '<tr><td><label>' . $message . '</label></td>
                    <td><input type="text" autocomplete="off" style="background-color: #dcdcdc;padding: 5px;" name="due_date" ' . ($order->amount < 0 ? 'disabled' : '') . ' id="due_date" value="' . $dueDate . '" /></td></tr>';
                }
                $displayForm.= '<tr><td>&nbsp;</td>
                <td><input type="hidden" id="amount_update_confirm_msg" value="' . (($orderDetails['payment_method'] == 'novalnet_sepa') ? JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_SEPA_CONFIRM_MSG') : (($orderDetails['payment_method'] == 'novalnet_cashpayment') ? JText::_('HKPAYMENT_NOVALNET_CASH_PAYMENT_AMOUNT_CONFIRM_MSG') : JText::_('HKPAYMENT_NOVALNET_ADMIN_AMOUNT_CONFIRM_MSG'))) . '" /><input type="hidden" id="amount_error_msg" value="' . JText::_('HKPAYMENT_NOVALNET_AMOUNT_VALIDATE_MSG') . '" />
                <input type="hidden" id="nn_due_date_error" value="' . JText::_('HKPAYMENT_NOVALNET_ADMIN_DUEDATE_EMPTY_ERROR') . '" />
                <input type="hidden" id="payment_method" value="' . $orderDetails['payment_method'] . '" />
                <input type="submit" style="background-color: #dcdcdc; border-radius: 1px; margin-left: 50px; padding: 5px;" name="amount_update" id="amount_update" value="' . JText::_('HKPAYMENT_NOVALNET_ADMIN_BUTTON_UPDATE') . '" /></td></tr></table></div></fieldset>';
                return $displayForm;
            }
        }
    }

    /**
     * Display debit and cancel template
     *
     * @param  object $order get the order object
     * @return string
     */
    public static function displayVoidCapture($order)
    {
        return '<fieldset style="width:400px;margin-left:20px;height:200px;margin-bottom:20px;margin-top:20px;height:200px;"><legend>' . JText::_('HKPAYMENT_NOVALNET_ADMIN_MANAGE_TRANSACTION') . '</legend></div>
        <div id="content" style="float:center;margin-bottom:0;"><br />
        <input type="submit" class = "btn btn-small" style="padding:5px;background-color:#dcdcdc;border-radius: 1px;margin-left: 10%;" name="capture" id="capture" value="' . JText::_('HKPAYMENT_NOVALNET_ADMIN_DEBIT_BUTTON') . '" />
        <input type="hidden" id="debit_confirm_msg" value="' . JText::_('HKPAYMENT_NOVALNET_CONFIRM_MSG') . '" />
        <input type="hidden" id="cancel_confirm_msg" value="' . JText::_('HKPAYMENT_NOVALNET_CANCEL_MSG') . '" />
        <input type="submit" class = "btn btn-small" style="padding:5px;background-color:#dcdcdc;border-radius: 1px;margin-left: 10%;" name="void" id="void" value="' . JText::_('HKPAYMENT_NOVALNET_ADMIN_CANCEL_BUTTON') . '" />
        </div></fieldset>';
    }

    /**
     * Render the zero amount booking form
     *
     * @param  object $order get the order object
     * @return string
     */
    public static function displayZeroAmountBooking($order)
    {
        if (!in_array($order->gateway_status, array('99', '98', '85')))
        {
            return '<fieldset id = "fieldset_booking" style = text-align:left;width:500px;height:200px;margin-top:20px;margin-bottom:20px;margin-left:20px;><legend>' . JText::_('HKPAYMENT_NOVALNET_ZERO_AMOUNT_BOOKING_TITLE') . '</legend><div style="margin-left: 3%;margin-top: 20px;margin-bottom: 3%;" id="zero_amount_booking_label"><label style="margin-right:18px;font-size: 13px;">' . JText::_('HKPAYMENT_NOVALNET_ZERO_AMOUNT_BOOKING_LABEL') . '</label><input type="text" id="zero_amount_booking" onkeypress="return isNumberKey(event, true)"; autocomplete="off" name="zero_amount_booking" style="background-color: #dcdcdc;margin-right: 1%;padding: 5px;width: 80px;" value="' . NovalnetUtilities::doFormatAmount($order->order_amount) . '" /><br>' . JText::_('HKPAYMENT_NOVALNET_CENT') . '</div><input type="hidden" id="zero_amount_error_msg" value="' . JText::_('HKPAYMENT_NOVALNET_AMOUNT_VALIDATE_MSG') . '" /><input type="hidden" id="zero_amount_confirm_msg" value="' . JText::_('HKPAYMENT_NOVALNET_ZEROAMOUNT_CONFIRM_MSG') . '" /><input type="submit" style="background-color: #dcdcdc; border-radius: 1px; margin-left: 27%; padding: 5px;" name="zero_amount" id="zero_amount" value="' . JText::_('HKPAYMENT_NOVALNET_ADMIN_BUTTON_BOOK') . '" /></td></tr></table></div></fieldset>';
        }
    }
}
