<?php
/**
 * 2009-2023 Plati.Online
 *
 *  @author    Plati.Online <support@plationline.ro>
 *  @copyright 2023 Plati.Online
 *  @license   Plati.Online
 *  @version   Release: $Revision: 6.0.6
 *  @date     06/03/2023
 */

use PlatiOnlinePO6\Inc\Libraries\PO5 as PO5;

require_once(__DIR__ . '../../../config/config.inc.php');
require_once(__DIR__ . '../../../init.php');
include_once(__DIR__ .'/plationline.php');

$module = new Plationline();

if (!Module::isEnabled('plationline') || !Tools::isSubmit('secure_key') || Tools::getValue('secure_key') != $module->secure_key || !Tools::getValue('trans_id') || !Tools::getValue('order_id') || !Tools::getValue('amount')) {
    die(1);
}

$order = new Order(Tools::getValue('order_id'));

$po = new PO5();

switch ($order->payment) {
    case 'plationline_additional':
        $po->f_login = Configuration::get('PLATIONLINE_RO_LOGIN_ID_' . Tools::strtoupper(Tools::getValue('currency')) . '_ADDITIONAL');
        break;
    default:
        $po->f_login = Configuration::get('PLATIONLINE_RO_LOGIN_ID_' . Tools::strtoupper(Tools::getValue('currency')));
        break;
}

$po->setRSAKeyEncrypt(Configuration::get('PLATIONLINE_RO_RSA_AUTH'));
$po->setIV(Configuration::get('PLATIONLINE_RO_IV_AUTH'));
$f_request = array();
$f_request['f_website'] = Tools::str_replace_once('www.', '', $_SERVER['SERVER_NAME']);
$f_request['f_order_number'] = (int)Tools::getValue('order_id');
$f_request['x_trans_id'] = (int)Tools::getValue('trans_id'); // transaction ID
$f_request['f_amount'] = (float)Tools::getValue('amount');

$response_refund = $po->refund($f_request, 1);

if ($po->get_xml_tag_content($response_refund, 'PO_ERROR_CODE') == 1) {
    echo json_encode(array('status'=>'error', 'message'=>$po->get_xml_tag_content($response_refund, 'PO_ERROR_REASON')));
} else {
    switch ($po->get_xml_tag_content($response_refund, 'X_RESPONSE_CODE')) {
        case '1':
            echo json_encode(array('status'=>'success', 'message'=>sprintf($module->l('The amount of %s sucessfully Refunded!', 'plationline'), ((float)Tools::getValue('amount').' '.Tools::getValue('currency')))));
            break;
        case '10':
            echo json_encode(array('status'=>'error', 'message'=>$module->l('Errors occured, selected amount NOT REFUNDED!', 'plationline')));
            break;
    }
}
