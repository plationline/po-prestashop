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

class PlationlinePaymentReturnModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		if (!Module::isEnabled('plationline') || !Tools::isSubmit('secure_key') || Tools::getValue('secure_key') != $this->module->secure_key) {
			die(1);
		}

		$relay_method = Configuration::get('PLATIONLINE_RO_RELAY_METHOD');

		$url = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?controller=order-detail&id_order=';
		$po = new PO5();
		$po->setRSAKeyDecrypt(Configuration::get('PLATIONLINE_RO_RSA_ITSN'));
		$po->setIVITSN(Configuration::get('PLATIONLINE_RO_IV_ITSN'));

		if ($relay_method == "PTOR" || $relay_method == "POST_S2S_PO_PAGE") {
			$authorization_response = $po->auth_response(Tools::getValue('F_Relay_Message'), Tools::getValue('F_Crypt_Message'));
		} elseif ($relay_method == "SOAP_PO_PAGE" || $relay_method == "SOAP_MT_PAGE") {
			$soap_xml = Tools::file_get_contents("php://input");
			$soap_parsed = $po->parse_soap_response($soap_xml);
			$authorization_response = $po->auth_response($po->get_xml_tag_content($soap_parsed, 'F_RELAY_MESSAGE'), $po->get_xml_tag_content($soap_parsed, 'F_CRYPT_MESSAGE'));
		}

		$X_RESPONSE_CODE = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_CODE');
		$message = $po->get_xml_tag_content($authorization_response, 'X_RESPONSE_REASON_TEXT');
		$order_id = (int)$po->get_xml_tag_content($authorization_response, 'F_ORDER_NUMBER');
		$order = new Order($order_id);

		$customer = new Customer((int)$order->id_customer);
		$currency = new Currency($order->id_currency);

		$processed_response = true;

		$was_authorized = !empty($order->getHistory($this->context->language->id, Configuration::get('PO_AUTHORIZED')));

		switch ($X_RESPONSE_CODE) {
			case '2':
				// Authorized
				if (!$was_authorized) {
					$order->setCurrentState(Configuration::get('PO_AUTHORIZED'));
					$order->addOrderPayment(0, $order->payment, $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID'), $currency);
				}
				$text = sprintf($this->module->l('Congratulations, the transaction for order #%s was successfully authorized!', 'paymentreturn'), $order_id);
				$text_color = 'text-success';

				$module = Module::getInstanceByName('plationline');

				Tools::redirect('index.php?controller=order-confirmation&id_cart='.$order->id_cart.'&id_module='.(int)$module->id.'&id_order='.$order_id.'&key='.$customer->secure_key);

				break;
			case '8':
				// Declined
				if (!$was_authorized) {
					$order->setCurrentState(Configuration::get('PO_DECLINED'));
					$order->addOrderPayment(0, $order->payment, $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID'), $currency);
				}
				$text = sprintf($this->module->l('The transaction for order #%s was declined! Reason: %s', 'paymentreturn'), $order_id, $message);
				$text_color = 'text-danger';
				$url .= $order_id . '&key=' . $customer->secure_key;
				break;
			case '10':
			case '16':
			case '17':
				// Error
				if (!$was_authorized) {
					$order->setCurrentState(Configuration::get('PO_ERROR'));
					$order->addOrderPayment(0, $order->payment, $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID'), $currency);
				}
				$text = sprintf($this->module->l('An error was encountered in authorization process for order #%s', 'paymentreturn'), $order_id);
				$text_color = 'text-danger';
				$url .= $order_id . '&key=' . $customer->secure_key;
				break;
			case '13':
				// On Hold
				if (!$was_authorized) {
					$order->setCurrentState(Configuration::get('PO_ONHOLD'));
					$order->addOrderPayment(0, $order->payment, $po->get_xml_tag_content($authorization_response, 'X_TRANS_ID'), $currency);
				}
				$text = sprintf($this->module->l('The transaction for order #%s is on hold, additional verification is needed!', 'paymentreturn'), $order_id);
				$text_color = 'text-warning';
				$url .= $order_id . '&key=' . $customer->secure_key;
				break;
			default:
				$processed_response = true;
				break;
		}

		if (Configuration::get('PLATIONLINE_RO_RELAY_METHOD') != "PTOR") {
			header('User-Agent:Mozilla/5.0 (Plati Online Relay Response Service)');
			if ($processed_response) {
				header('PO_Transaction_Response_Processing: true');
			} else {
				header('PO_Transaction_Response_Processing: retry');
			}
		}

		$this->context->smarty->assign(array(
			'shop_name'    => $this->context->shop->name,
			'text'         => $text,
			'text_color'   => $text_color,
			'url_redirect' => $url,
			'see_order'    => $this->module->l('See order', 'paymentreturn'),
		));
		$this->setTemplate('module:plationline/views/templates/front/payment_return.tpl');
	}
}
