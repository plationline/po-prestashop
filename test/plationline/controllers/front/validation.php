<?php
/**
 * 2009-2020 Plati.Online
 *
 * @author    Plati.Online <support@plationline.ro>
 * @copyright 2020 Plati.Online
 * @license   Plati.Online
 * @version   Release: $Revision: 6.0.1
 * @date      17/07/2018
 */

use PlatiOnlinePO6\Inc\Libraries\PO5 as PO5;

class PlationlineValidationModuleFrontController extends ModuleFrontController
{
	public function postProcess()
	{
		$cart = $this->context->cart;
		if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
			Tools::redirect('index.php?controller=order&step=1');
		}

		$currency = $this->context->currency;
		$currency = new Currency((int)$currency->id);

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module) {
			if ($module['name'] == 'plationline') {
				$f_login = Configuration::get('PLATIONLINE_RO_LOGIN_ID_' . Tools::strtoupper($currency->iso_code));
				if (!empty($f_login)) {
					$authorized = true;
					break;
				}
			}
		}

		if (!$authorized) {
			die($this->module->l('This payment module is not available', 'plationline'));
		}

		$customer = new Customer($cart->id_customer);

		if (!Validate::isLoadedObject($customer)) {
			Tools::redirect('index.php?controller=order&step=1');
		}

		$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
		$this->module->validateOrder((int)$cart->id, Configuration::get('PO_PENDING_AUTHORIZATION'), $total, $this->module->displayName, null, array(), (int)$currency->id, false, $customer->secure_key);
		// urmeaza contructia datelor pentru PO

		$orderNumber = Order::getIdByCartId((int)$cart->id);
		$f_request = array();
		$f_request['f_order_number'] = $orderNumber;
		$f_request['f_amount'] = round($total, 2);

		$f_request['f_currency'] = Tools::strtoupper($currency->iso_code);

		$lang = new Language(Tools::getValue('id_lang'));
		$f_request['f_language'] = $lang->iso_code;

		$invoiceAddress = new Address((int)$cart->id_address_invoice);
		$customer_info = array();

		//contact
		$customer_info['contact']['f_email'] = $customer->email;
		if ($invoiceAddress->phone && Tools::strlen(trim($invoiceAddress->phone)) >= 4) {
			$customer_info['contact']['f_phone'] = $invoiceAddress->phone;
			if (!$invoiceAddress->phone_mobile || Tools::strlen(trim($invoiceAddress->phone_mobile)) < 4) {
				$customer_info['contact']['f_mobile_number'] = $invoiceAddress->phone;
			}
		}
		if ($invoiceAddress->phone_mobile && Tools::strlen(trim($invoiceAddress->phone_mobile)) >= 4) {
			$customer_info['contact']['f_mobile_number'] = $invoiceAddress->phone_mobile;
			if (!$invoiceAddress->phone || Tools::strlen(trim($invoiceAddress->phone)) < 4) {
				$customer_info['contact']['f_phone'] = $invoiceAddress->phone_mobile;
			}
		}

		$customer_info['contact']['f_send_sms'] = 1; // 1 - sms client notification 0 - no notification
		if (trim($invoiceAddress->firstname) != '') {
			$customer_info['contact']['f_first_name'] = $invoiceAddress->firstname;
		} elseif ($customer->firstname) {
			$customer_info['contact']['f_first_name'] = $customer->firstname;
		}
		if (trim($invoiceAddress->lastname) != '') {
			$customer_info['contact']['f_last_name'] = $invoiceAddress->lastname;
		} elseif ($customer->lastname) {
			$customer_info['contact']['f_last_name'] = $customer->lastname;
		}
		//$customer_info['contact']['f_middle_name']     = '';

		//invoice
		if ($invoiceAddress->company) {
			$customer_info['invoice']['f_company'] = $invoiceAddress->company;
		}

		if ($invoiceAddress->vat_number) {
			$customer_info['invoice']['f_cui'] = $invoiceAddress->vat_number;
		}

		if ($invoiceAddress->dni) {
			$customer_info['invoice']['f_reg_com'] = $invoiceAddress->dni;
		}

		$customer_info['invoice']['f_cnp'] = '-';
		$customer_info['invoice']['f_zip'] = $invoiceAddress->postcode ?: '-';

		if ($invoiceAddress->country) {
			$customer_info['invoice']['f_country'] = $invoiceAddress->country;
		}
		if (State::getNameById($invoiceAddress->id_state)) {
			$customer_info['invoice']['f_state'] = State::getNameById($invoiceAddress->id_state);
		}
		if ($invoiceAddress->city) {
			$customer_info['invoice']['f_city'] = $invoiceAddress->city;
		}
		if (Tools::substr($invoiceAddress->address1 . ' ' . $invoiceAddress->address2, 0, 100)) {
			$customer_info['invoice']['f_address'] = Tools::substr($invoiceAddress->address1 . ' ' . $invoiceAddress->address2, 0, 100);
		}

		$f_request['customer_info'] = $customer_info;

		$shippingAddress = new Address((int)$cart->id_address_delivery);
		$shipping_info = array();
		$shipping_info['same_info_as'] = 0; // 0 - different info, 1- same info as customer_info

		//contact
		$shipping_info['contact']['f_email'] = $customer->email;
		if ($shippingAddress->phone && Tools::strlen(trim($shippingAddress->phone)) >= 4) {
			$shipping_info['contact']['f_phone'] = $shippingAddress->phone;
			if (!$shippingAddress->phone_mobile || Tools::strlen(trim($shippingAddress->phone_mobile)) < 4) {
				$shipping_info['contact']['f_mobile_number'] = $shippingAddress->phone;
			}
		}
		if ($shippingAddress->phone_mobile && Tools::strlen(trim($shippingAddress->phone_mobile)) >= 4) {
			$shipping_info['contact']['f_mobile_number'] = $shippingAddress->phone_mobile;
			if (!$shippingAddress->phone || Tools::strlen(trim($shippingAddress->phone)) < 4) {
				$shipping_info['contact']['f_phone'] = $shippingAddress->phone_mobile;
			}
		}
		$shipping_info['contact']['f_send_sms'] = 1; // 1 - sms client notification 0 - no notification
		$shipping_info['contact']['f_first_name'] = trim($shippingAddress->firstname) ? $shippingAddress->firstname: $customer->firstname;
		$shipping_info['contact']['f_last_name'] = trim($shippingAddress->lastname) ? $shippingAddress->lastname: $customer->lastname;
		//$shipping_info['contact']['f_middle_name']     = '';

		//address
		if ($shippingAddress->company) {
			$shipping_info['address']['f_company'] = $shippingAddress->company;
		}
		$shipping_info['address']['f_zip'] = $shippingAddress->postcode ?: '-';
		$shipping_info['address']['f_country'] = $shippingAddress->country ?: '-';
		$shipping_info['address']['f_state'] = State::getNameById($shippingAddress->id_state) ?: '-';
		$shipping_info['address']['f_city'] = $shippingAddress->city ?: '-';
		$shipping_info['address']['f_address'] = Tools::substr($shippingAddress->address1 . ' ' . $shippingAddress->address2, 0, 100) ?: '-';

		$f_request['shipping_info'] = $shipping_info;

		$transaction_relay_response = array();

		$transaction_relay_response['f_relay_response_url'] = $this->context->link->getModuleLink('plationline', 'paymentReturn', array('secure_key' => $this->module->secure_key), $this->module->ssl_enabled);
		$transaction_relay_response['f_relay_method'] = Configuration::get('PLATIONLINE_RO_RELAY_METHOD');
		$transaction_relay_response['f_post_declined'] = 1;
		$transaction_relay_response['f_relay_handshake'] = 1; // default 0
		$f_request['transaction_relay_response'] = $transaction_relay_response;

		$products = $cart->getProducts();
		$f_request['f_order_cart'] = array();

		foreach ($products as $product) {
			$item = array();
			$item['prodid'] = $product['id_product'];
			$item['name'] = Tools::substr(htmlspecialchars($product['name'], ENT_QUOTES), 0, 250);
			$item['description'] = Tools::substr(htmlspecialchars(strip_tags($product['description_short']), ENT_QUOTES), 0, 250);
			$item['qty'] = $product['cart_quantity'];
			$item['itemprice'] = round($product['price'], 2);
			$item['vat'] = round($product['rate'] * $product['price'] * $product['cart_quantity'] / 100, 2);
			$item['stamp'] = date('Y-m-d', strtotime($product['date_add']));
			$item['prodtype_id'] = 0;

			$f_request['f_order_cart'][] = $item;
		}

		if ($cart->gift) {
			$item = array();
			$item['prodid'] = 'gift';
			$item['name'] = Tools::substr(htmlspecialchars('Ambalare tip cadou', ENT_QUOTES), 0, 250);
			$item['description'] = '';
			$item['qty'] = 1;
			$item['itemprice'] = $cart->getGiftWrappingPrice();
			$item['vat'] = 0;
			$item['stamp'] = date('Y-m-d', strtotime($product['date_add']));
			$item['prodtype_id'] = 0;

			$f_request['f_order_cart'][] = $item;
		}

		$shipping_method = new Carrier($cart->id_carrier);

		if ($cart->getDiscounts()) {
			$cupoane = $cart->getDiscounts();
			$i = 0;
			foreach ($cupoane as $cupon) {
				$i++;
				$coupon = array();
				$coupon['key'] = $cupon["id_discount"];
				$coupon['value'] = round($cupon["value_tax_exc"], 2);
				$coupon['percent'] = 0;
				$coupon['workingname'] = $cupon["name"];
				$coupon['type'] = 0;
				$coupon['scop'] = 1;
				$coupon['vat'] = round(((float)$cupon["value_real"] - (float)$cupon["value_tax_exc"]), 2);
				$f_request['f_order_cart']['coupon' . $i] = $coupon;
			}
		}

		//shipping
		$shipping = array();
		$shipping['price'] = round($cart->getTotalShippingCost(null, false), 2);
		$shipping['vat'] = round(1 * ($cart->getTotalShippingCost(null, true) - $cart->getTotalShippingCost(null, false)), 2);
		$shipping['name'] = Tools::substr(htmlspecialchars($shipping_method->name, ENT_QUOTES), 0, 250);
		$shipping['pimg'] = 0;

		$f_request['f_order_cart']['shipping'] = $shipping;
		$f_request['f_order_string'] = 'Comanda nr. ' . $orderNumber . ' pe site-ul ' . (Configuration::get('PS_SSL_ENABLED') ? _PS_BASE_URL_SSL_ : _PS_BASE_URL_);

		$po = new PO5();
		$po->f_login = Configuration::get('PLATIONLINE_RO_LOGIN_ID_' . $f_request['f_currency']);
		$f_request['f_website'] = str_replace('www.', '', $_SERVER['SERVER_NAME']);
		$po->setRSAKeyEncrypt(Configuration::get('PLATIONLINE_RO_RSA_AUTH'));
		$po->setIV(Configuration::get('PLATIONLINE_RO_IV_AUTH'));
		$po->test_mode = (Configuration::get('PLATIONLINE_RO_DEMO') == 'LIVE' ? 0 : 1);
		$auth = $po->auth($f_request, 2);
		if (Validate::isUrl($auth['redirect_url'])) {
			$msg = new Message();

			$msg->message = $auth['redirect_url'];
			$msg->id_cart = (int)$cart->id;
			$msg->id_customer = (int)($cart->id_customer);
			$msg->id_order = (int)$orderNumber;
			$msg->private = 1;
			$msg->add();

			Tools::redirect($auth['redirect_url']);
		} else {
			die($this->module->l('The redirect URL could not be obtained from PlatiOnline', 'plationline'));
		}
	}
}
