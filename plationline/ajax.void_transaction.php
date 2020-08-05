<?php
/**
 * 2009-2020 Plati.Online
 *
 *  @author    Plati.Online <support@plationline.ro>
 *  @copyright 2020 Plati.Online
 *  @license   Plati.Online
 *  @version   Release: $Revision: 6.0.1
 *  @date      17/07/2018
 */

use PlatiOnlinePO6\Inc\Libraries\PO5 as PO5;

require_once(dirname(__FILE__) . '../../../config/config.inc.php');
require_once(dirname(__FILE__) . '../../../init.php');
include_once(dirname(__FILE__).'/plationline.php');

$module = new Plationline();

if (!Module::isEnabled('plationline') || !Tools::isSubmit('secure_key') || Tools::getValue('secure_key') != $module->secure_key || !Tools::getValue('trans_id') || !Tools::getValue('order_id')) {
    die(1);
}

$po = new PO5();

$po->f_login = Configuration::get('PLATIONLINE_RO_LOGIN_ID_'.Tools::strtoupper(Tools::getValue('currency')));
$po->setRSAKeyEncrypt(Configuration::get('PLATIONLINE_RO_RSA_AUTH'));
$po->setIV(Configuration::get('PLATIONLINE_RO_IV_AUTH'));
$f_request = array();
$f_request['f_website'] = Tools::str_replace_once('www.', '', $_SERVER['SERVER_NAME']);
$f_request['f_order_number'] = (int)Tools::getValue('order_id');
$f_request['x_trans_id'] = (int)Tools::getValue('trans_id'); // transaction ID

$response_void = $po->void($f_request, 7);

if ($po->get_xml_tag_content($response_void, 'PO_ERROR_CODE') == 1) {
    echo json_encode(array('status'=>'error', 'message'=>$po->get_xml_tag_content($response_void, 'PO_ERROR_REASON')));
} else {
    switch ($po->get_xml_tag_content($response_void, 'X_RESPONSE_CODE')) {
        case '7':
            echo json_encode(array('status'=>'success', 'message'=>$module->l('Transaction sucessfully VOIDED!', 'plationline')));
            break;
        case '10':
            echo json_encode(array('status'=>'error', 'message'=>$module->l('Errors occured, transaction NOT VOIDED!', 'plationline')));
            break;
    }
}
