<?php
/**
 * 2009-2023 Plati.Online
 *
 * @author    Plati.Online <support@plationline.ro>
 * @copyright 2023 Plati.Online
 * @license   Plati.Online
 * @version   Release: $Revision: 6.0.6
 * @date      06/03/2023
 */

use PlatiOnlinePO6\Inc\Libraries\PO5 as PO5;

class PlationlineITSNModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        if (!Module::isEnabled('plationline') || !Tools::isSubmit('secure_key') || Tools::getValue('secure_key') != $this->module->secure_key) {
            die(1);
        }

        if (empty(Tools::getValue('f_itsn_message')) || empty(Tools::getValue('f_itsn_message'))) {
            die(1);
        }

        $po = new PO5();
        $po->setRSAKeyDecrypt(Configuration::get('PLATIONLINE_RO_RSA_ITSN'));
        $po->setIVITSN(Configuration::get('PLATIONLINE_RO_IV_ITSN'));

        $call_itsn = $po->itsn(Tools::getValue('f_itsn_message'), Tools::getValue('f_crypt_message'));
        $po->setRSAKeyEncrypt(Configuration::get('PLATIONLINE_RO_RSA_AUTH'));
        $po->setIV(Configuration::get('PLATIONLINE_RO_IV_AUTH'));

        $po->f_login = Tools::getValue('f_login');

        $f_request = array();
        $f_request['f_website'] = $po->f_login;
        $f_request['f_order_number'] = $po->get_xml_tag_content($call_itsn, 'F_ORDER_NUMBER');
        $f_request['x_trans_id'] = $po->get_xml_tag_content($call_itsn, 'X_TRANS_ID');

        $itsn_response = $po->query($f_request, 0);

        if ($po->get_xml_tag_content($itsn_response, 'PO_ERROR_CODE') == 1) {
            die($po->get_xml_tag_content($itsn_response, 'PO_ERROR_REASON'));
        } else {
            $order_itsn = $po->get_xml_tag($itsn_response, 'ORDER');
            $tranzaction = $po->get_xml_tag($order_itsn, 'TRANZACTION');

            $X_TRANS_ID = $po->get_xml_tag_content($tranzaction, 'X_TRANS_ID');
            $statusfin1 = $po->get_xml_tag_content($po->get_xml_tag($tranzaction, 'STATUS_FIN1'), 'CODE');
            $statusfin2 = $po->get_xml_tag_content($po->get_xml_tag($tranzaction, 'STATUS_FIN2'), 'CODE');

            $order = new Order((int)$po->get_xml_tag_content($order_itsn, 'F_ORDER_NUMBER'));
            $currency = new Currency($order->id_currency);

            $was_authorized = !empty($order->getHistory($this->context->language->id, Configuration::get('PO_AUTHORIZED')));

            $status1 = '<f_response_code>1</f_response_code>';
            switch ($statusfin1) {
                case '1':
                    // In curs de autorizare
                    $order->setCurrentState(Configuration::get('PO_PENDING_AUTHORIZATION'));
                    break;
                case '2':
                    // Autorizata
                    if (!$was_authorized) {
                        $order->addOrderPayment(0, $order->payment, $X_TRANS_ID, $currency);
                        $order->setCurrentState(Configuration::get('PO_AUTHORIZED'));
                    }
                    break;
                case '3':
                    // In curs de incasare
                    $order->setCurrentState(Configuration::get('PO_PENDING_SETTLE'));
                    break;
                case '5':
                    // Incasata
                    switch ($statusfin2) {
                        case '1':
                            // In curs de creditare
                            $order->setCurrentState(Configuration::get('PO_PENDING_REFUND'));
                            break;
                        case '2':
                            // Creditata
                            $order->setCurrentState(Configuration::get('PO_REFUND'));
                            break;
                        case '3':
                            // Refuz la plata
                            $order->setCurrentState(Configuration::get('PO_CHARGEBACK'));
                            break;
                        case '4':
                            // Incasata
                            $order->setCurrentState(Configuration::get('PO_SETTLED'));
                            break;
                    }
                    break;
                case '6':
                    // In curs de anulare
                    $order->setCurrentState(Configuration::get('PO_PENDING_VOID'));
                    break;
                case '7':
                    // Anulata
                    $order->setCurrentState(Configuration::get('PO_CANCELED'));
                    break;
                case '8':
                    if (!$was_authorized && $order->getCurrentState() != Configuration::get('PO_DECLINED')) {
                        // Refuzata
                        $order->addOrderPayment(0, $order->payment, $X_TRANS_ID, $currency);
                        $order->setCurrentState(Configuration::get('PO_DECLINED'));
                    }
                    break;
                case '9':
                    // Expirata 30 zile
                    $order->setCurrentState(Configuration::get('PO_EXPIRED'));
                    break;
                case '10':
                case '16':
                case '17':
                    if (!$was_authorized) {
                        // Eroare
                        $order->setCurrentState(Configuration::get('PO_ERROR'));
                    }
                    break;
                case '13':
                    // In proces de verificare / On Hold
                    $order->setCurrentState(Configuration::get('PO_ONHOLD'));
                    break;
                default:
                    $status1 = '<f_response_code>0</f_response_code>';
            }

            header("Content-type: text/xml");

            /* send XML response */
            $xml_response = '<?xml version="1.0" encoding="UTF-8" ?>';
            $xml_response .= '<itsn>';
            $xml_response .= '<x_trans_id>' . $X_TRANS_ID . '</x_trans_id>';
            $xml_response .= '<merchServerStamp>' . date('Y-m-d\TH:i:sP') . '</merchServerStamp>';
            $xml_response .= $status1;
            $xml_response .= '</itsn>';

            echo $xml_response;
            die();
        }
    }
}
