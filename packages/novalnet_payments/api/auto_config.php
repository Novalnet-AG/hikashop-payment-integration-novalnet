<?php
/**
* This script is used for Novalnet auto confgiuration of merchant details
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
* Script : auto_config.php
*/

class curl_process
{
    function curl_process($data)
    {
        $params = array('lang' => $data['lang'], 'hash' => $data['hash']);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://payport.novalnet.de/autoconfig");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded; charset=utf-8'));
        curl_setopt($curl, CURLOPT_TIMEOUT, 240);
        $result = curl_exec($curl);
        curl_close($curl);
        echo $result;
    }
}
new curl_process($_POST);
?>
