<?php
/**
* This script is used to redirect to Giropay
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
* Script : novalnet_giropay_end.php
*/

// No direct access
defined('_JEXEC') or die('Restricted access');

?><div class="hikashop_novalnet_giropay_end" id="hikashop_novalnet_giropay_end">
    <span id="hikashop_novalnet_giropay_end_message" class="hikashop_novalnet_giropay_end_message">
        <?php echo JText::sprintf('HKPAYMENT_NOVALNET_REDIRECTING_MESSAGE', $this->payment_name) . '<br/>' . JText::_('HKPAYMENT_NOVALNET_REDIRECT_DESC');?>
    </span>
    <form id="hikashop_novalnet_giropay_form" name="hikashop_novalnet_giropay_form" action="https://payport.novalnet.de/giropay" method="post">
        <div id="hikashop_novalnet_giropay_end_image" class="hikashop_novalnet_giropay_end_image">
            <input id="hikashop_novalnet_giropay_end_button" type="submit" style="display:none;" class="btn btn-primary" value="<?php echo JText::_('PAY_NOW');?>"  alt="<?php echo JText::_('PAY_NOW');?>" />
        </div>
        <?php
        foreach ($this->vars as $name => $value)
        {
            echo '<input type="hidden" name="' . $name . '" value="' . htmlspecialchars((string) $value) . '" />';
        }
            $doc = JFactory::getDocument();
            $doc->addScriptDeclaration("window.hikashop.ready( function() {document.getElementById('hikashop_novalnet_giropay_form').submit();});");
            JRequest::setVar('noform', 1);
        ?>
    </form>
</div>
