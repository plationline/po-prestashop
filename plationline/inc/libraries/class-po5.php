<?php

namespace PlatiOnlinePO6\Inc\Libraries;

/**
 * @link              http://plationline.eu
 * @since             6.0.0
 * @package           PlatiOnlinePO6
 *
 */

use PlatiOnlinePO6\Inc\Libraries\phpseclib\Crypt\AES as AES;
use PlatiOnlinePO6\Inc\Libraries\phpseclib\Crypt\RSA as RSA;
use PlatiOnlinePO6\Inc\Libraries\sylouuu\Curl\Method as Curl;

class PO5
{
	// private
	private $f_request;
	private $f_secure;
	private $aes_key;
	private $iv;
	private $iv_itsn;
	private $rsa_key_enc;
	private $rsa_key_dec;
	public static $url = 'https://secure.plationline.ro/';
	public static $url_login_plationline = 'https://oauth.plationline.ro/oauth/';
	public static $url_sv_request_xml = 'https://secure.plationline.ro/xml_validation/po.request.v5.xsd';         // any call
	public static $url_sv_auth_xml = 'https://secure.plationline.ro/xml_validation/f_message.auth.v5.xsd';     // auth
	public static $url_sv_auth_url_xml = 'https://secure.plationline.ro/xml_validation/auth.url.response.v5.xsd'; // auth url
	public static $url_sv_auth_response_xml = 'https://secure.plationline.ro/xml_validation/auth.response.v5.xsd';  // auth response
	public static $url_sv_auth_response_soap_xml = 'https://secure.plationline.ro/xml_validation/auth.soap.response.v5.xsd';// auth response soap
	public static $url_sv_itsn_xml = 'https://secure.plationline.ro/xml_validation/itsn.v5.xsd';               // itsn
	public static $url_sv_query_xml = 'https://secure.plationline.ro/xml_validation/f_message.query.v5.xsd';    // query
	public static $url_sv_itsn_response_xml = 'https://secure.plationline.ro/xml_validation/query.response.v5.xsd';     // query response
	public static $url_sv_query_by_date_xml = 'https://secure.plationline.ro/xml_validation/f_message.query-by-date.v5.xsd';    // query by date
	public static $url_sv_query_by_date_response_xml = 'https://secure.plationline.ro/xml_validation/query-by-date.response.v5.xsd';     // query response
	public static $url_sv_settle_xml = 'https://secure.plationline.ro/xml_validation/f_message.settle.v5.xsd';   // settle
	public static $url_sv_settle_response_xml = 'https://secure.plationline.ro/xml_validation/settle.response.v5.xsd';    // settle response
	public static $url_sv_void_xml = 'https://secure.plationline.ro/xml_validation/f_message.void.v5.xsd';     // void
	public static $url_sv_void_response_xml = 'https://secure.plationline.ro/xml_validation/void.response.v5.xsd';  // void response
	public static $url_sv_refund_xml = 'https://secure.plationline.ro/xml_validation/f_message.refund.v5.xsd';   // refund
	public static $url_sv_refund_response_xml = 'https://secure.plationline.ro/xml_validation/refund.response.v5.xsd';    // refund response
	public static $url_sv_paylink_xml = 'https://secure.plationline.ro/xml_validation/v5/f_message.paylink.xsd';  // paylink
	public static $url_sv_paylink_response_xml = 'https://secure.plationline.ro/xml_validation/v5/pay.link.by.trxid.url.response.xsd';     // paylink response

	// public
	public $f_login;
	public $version;
	public $test_mode;

	public function __construct()
	{
		$this->version = "PO 5.1.0 XML";
		$this->test_mode = 0;
	}

	//////////////////////////////////////////////////////////////
	//                      PUBLIC METHODS                      //
	//////////////////////////////////////////////////////////////

	// setez cheia RSA pentru criptare
	public function setRSAKeyEncrypt($rsa_key_enc)
	{
		$this->rsa_key_enc = $rsa_key_enc;
	}

	// setez cheia RSA pentru decriptare
	public function setRSAKeyDecrypt($rsa_key_dec)
	{
		$this->rsa_key_dec = $rsa_key_dec;
	}

	// setez initial vector
	public function setIV($iv)
	{
		$this->iv = $iv;
	}

	// setez initial vector ITSN
	public function setIVITSN($iv)
	{
		$this->iv_itsn = $iv;
	}

	public function paylink($f_request, $f_action = 21)
	{
		// ne asiguram ca stergem tot ce e in campul f_request
		$this->f_request = null;
		$f_request['f_action'] = $f_action;
		$request = $this->setFRequest($f_request, 'po_payment_link_by_trxid', self::$url_sv_paylink_xml);

		$opts = array(
			'http' => array(
				'user_agent' => 'PlatiOnline-SOAP',
			),
		);
		$context = \stream_context_create($opts);
		$client = new \SoapClient(null, array(
			'location'       => self::$url,
			'uri'            => 'pay-link-by-trxid',
			'stream_context' => $context,
		));

		$response = $client->__doRequest($request, self::$url, 'pay-link-by-trxid', 1);

		if (empty($response)) {
			throw new \Exception('ERROR: Nu am putut comunica cu serverul PO pentru operatiunea de autorizare!');
		}
		$this->validate_xml($response, self::$url_sv_paylink_response_xml);
		$paylink_response = $this->xml_to_object($response);

		if ($this->get_xml_tag_content($paylink_response, 'PO_ERROR_CODE') == 1) {
			throw new \Exception($this->get_xml_tag_content($paylink_response, 'PO_ERROR_REASON'));
		} else {
			$redirect_url = $this->get_xml_tag_content($paylink_response, 'PO_REDIRECT_URL');
			$X_TRANS_ID = $this->get_xml_tag_content($paylink_response, 'X_TRANS_ID');
			if (!empty($redirect_url)) {
				return $redirect_url;
			} else {
				throw new \Exception('ERROR: Serverul nu a intors URL-ul pentru a finaliza tranzactia!');
			}
		}
	}

	public function auth($f_request, $f_action = 2)
	{
		// ne asiguram ca stergem tot ce e in campul f_request
		$this->f_request = null;
		$f_request['f_action'] = $f_action;
		$request = $this->setFRequest($f_request, 'po_auth_request', self::$url_sv_auth_xml);

		$opts = array(
			'http' => array(
				'user_agent' => 'PlatiOnline-SOAP',
			),
		);
		$context = \stream_context_create($opts);
		$client = new \SoapClient(null, array(
			'location'       => self::$url,
			'uri'            => 'auth-only',
			'stream_context' => $context,
		));

		$response = $client->__doRequest($request, self::$url, 'auth-only', 1);
		if (empty($response)) {
			throw new \Exception('ERROR: Nu am putut comunica cu serverul PO pentru operatiunea de autorizare!');
		}

		$this->validate_xml($response, self::$url_sv_auth_url_xml);
		$auth_response = $this->xml_to_object($response);
		if ($this->get_xml_tag_content($auth_response, 'PO_ERROR_CODE') == 1) {
			throw new \Exception($this->get_xml_tag_content($auth_response, 'PO_ERROR_REASON'));
		} else {
			$redirect_url = $this->get_xml_tag_content($auth_response, 'PO_REDIRECT_URL');
			$x_trans_id = $this->get_xml_tag_content($auth_response, 'X_TRANS_ID');
			if (!empty($redirect_url)) {
				return array('redirect_url' => $redirect_url, 'x_trans_id' => $x_trans_id);
			} else {
				throw new \Exception('ERROR: Serverul nu a intors URL-ul pentru a finaliza tranzactia!');
			}
		}
	}

	// obtin raspunsul pentru cererea de autorizare
	public function auth_response($f_relay_message, $f_crypt_message)
	{
		return $this->decrypt_response($f_relay_message, $f_crypt_message, self::$url_sv_auth_response_xml);
	}

	// obtin datele din notificarea ITSN
	public function itsn($f_relay_message, $f_crypt_message)
	{
		return $this->decrypt_response($f_relay_message, $f_crypt_message, self::$url_sv_itsn_xml);
	}

	// interogare
	public function query($f_request, $f_action = 0)
	{
		// ne asiguram ca stergem tot ce e in campul f_request
		$this->f_request = null;
		$f_request['f_action'] = $f_action;
		$request = $this->setFRequest($f_request, 'po_query', self::$url_sv_query_xml);

		$opts = array(
			'http' => array(
				'user_agent' => 'PlatiOnline-SOAP',
			),
		);
		$context = \stream_context_create($opts);
		$client = new \SoapClient(null, array(
			'location'       => self::$url,
			'uri'            => 'query',
			'stream_context' => $context,
		));
		$response = $client->__doRequest($request, self::$url, 'query', 1);

		if (empty($response)) {
			throw new \Exception('ERROR: Nu am putut comunica cu serverul PO pentru operatiunea de interogare!');
		}
		// validez xml-ul primit ca raspuns de la PO
		$this->validate_xml($response, self::$url_sv_itsn_response_xml);
		return $this->xml_to_object($response);
	}

	public function query_by_date($f_request, $f_action = 0)
	{
		// ne asiguram ca stergem tot ce e in campul f_request
		$this->f_request = null;
		$f_request['f_action'] = $f_action;
		$request = $this->setFRequest($f_request, 'po_query', self::$url_sv_query_by_date_xml);

		$opts = array(
			'http' => array(
				'user_agent' => 'PlatiOnline-SOAP',
			),
		);
		$context = \stream_context_create($opts);
		$client = new \SoapClient(null, array(
			'location'       => self::$url,
			'uri'            => 'query-by-date',
			'stream_context' => $context,
		));
		$response = $client->__doRequest($request, self::$url, 'query-by-date', 1);
		if (empty($response)) {
			throw new \Exception('ERROR: Nu am putut comunica cu serverul PO pentru operatiunea de interogare!');
		}
		// validez xml-ul primit ca raspuns de la PO
		$this->validate_xml($response, self::$url_sv_query_by_date_response_xml);
		return $this->xml_to_object($response);
	}

	public function settle($f_request, $f_action = 3)
	{
		// ne asiguram ca stergem tot ce e in campul f_request
		$this->f_request = null;
		$f_request['f_action'] = $f_action;
		$request = $this->setFRequest($f_request, 'po_settle', self::$url_sv_settle_xml);

		$opts = array(
			'http' => array(
				'user_agent' => 'PlatiOnline-SOAP',
			),
		);
		$context = \stream_context_create($opts);
		$client = new \SoapClient(null, array(
			'location'       => self::$url,
			'uri'            => 'settle',
			'stream_context' => $context,
		));
		$response = $client->__doRequest($request, self::$url, 'settle', 1);

		if (empty($response)) {
			throw new \Exception('ERROR: Nu am putut comunica cu serverul PO pentru operatiunea de incasare!');
		}

		// validez xml-ul primit ca raspuns de la PO
		$this->validate_xml($response, self::$url_sv_settle_response_xml);

		return $this->xml_to_object($response);
	}

	public function void($f_request, $f_action = 7)
	{
		// ne asiguram ca stergem tot ce e in campul f_request
		$this->f_request = null;
		$f_request['f_action'] = $f_action;
		$request = $this->setFRequest($f_request, 'po_void', self::$url_sv_void_xml);

		$opts = array(
			'http' => array(
				'user_agent' => 'PlatiOnline-SOAP',
			),
		);
		$context = \stream_context_create($opts);
		$client = new \SoapClient(null, array(
			'location'       => self::$url,
			'uri'            => 'void',
			'stream_context' => $context,
		));

		$response = $client->__doRequest($request, self::$url, 'void', 1);

		if (empty($response)) {
			throw new \Exception('ERROR: Nu am putut comunica cu serverul PO pentru operatiunea de anulare!');
		}

		// validez xml-ul primit ca raspuns de la PO
		$this->validate_xml($response, self::$url_sv_void_response_xml);

		return $this->xml_to_object($response);
	}

	public function refund($f_request, $f_action = 1)
	{
		// ne asiguram ca stergem tot ce e in campul f_request
		$this->f_request = null;
		$f_request['f_action'] = $f_action;
		$request = $this->setFRequest($f_request, 'po_refund', self::$url_sv_refund_xml);

		$opts = array(
			'http' => array(
				'user_agent' => 'PlatiOnline-SOAP',
			),
		);
		$context = \stream_context_create($opts);
		$client = new \SoapClient(null, array(
			'location'       => self::$url,
			'uri'            => 'refund',
			'stream_context' => $context,
		));
		$response = $client->__doRequest($request, self::$url, 'refund', 1);

		if (empty($response)) {
			throw new \Exception('ERROR: Nu am putut comunica cu serverul PO pentru operatiunea de creditare!');
		}

		// validez xml-ul primit ca raspuns de la PO
		$this->validate_xml($response, self::$url_sv_refund_response_xml);

		return $this->xml_to_object($response);
	}

	public function get_xml_tag($object, $tag)
	{
		$children = $object->children;
		foreach ($children as $child) {
			if (\trim(\strtoupper($child->name)) == \trim(\strtoupper($tag))) {
				return $child;
			}
		}
		return false;
	}

	public function get_xml_tag_content($object, $tag)
	{
		$children = $object->children;
		foreach ($children as $child) {
			if (\trim(\strtoupper($child->name)) == \trim(\strtoupper($tag))) {
				return $child->content;
			}
		}
		return false;
	}

	public function parse_soap_response($soap)
	{
		return $this->xml_to_object($soap);
	}

	//////////////////////////////////////////////////////////////
	//                  END PUBLIC METHODS                      //
	//////////////////////////////////////////////////////////////

	//////////////////////////////////////////////////////////////
	//                    PRIVATE METHODS                       //
	//////////////////////////////////////////////////////////////

	// criptez f_request cu AES
	private function AESEnc()
	{
		$this->aes_key = substr(hash('sha256', uniqid(), 0), 0, 32);
		$aes = new AES();
		$aes->setIV($this->iv);
		$aes->setKey($this->aes_key);
		$this->f_request = \bin2hex(\base64_encode($aes->encrypt($this->f_request)));
	}

	// criptez cheia AES cu RSA
	private function RSAEnc()
	{
		$rsa = new RSA();
		$rsa->loadKey($this->rsa_key_enc);
		$rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
		$this->f_secure = \base64_encode($rsa->encrypt($this->aes_key));
	}

	// setez f_request, criptez f_request cu AES si cheia AES cu RSA
	private function setFRequest($f_request, $type, $validation_url)
	{
		// aici construiesc XML din array
		$xml = new \SimpleXMLElement('<' . $type . '/>');

		// test mode
		if ($type == 'po_auth_request') {
			if ($this->test_mode == 0) {
				$f_request['f_test_request'] = 0;
			} else {
				$f_request['f_test_request'] = 1;
			}

			$f_request['f_sequence'] = \rand(1, 1000);
			$f_request['f_customer_ip'] = $_SERVER['REMOTE_ADDR'];
		}

		$f_request['f_timestamp'] = \date('Y-m-d\TH:i:sP');
		// set f_login
		$f_request['f_login'] = $this->f_login;

		// sortez parametrii alfabetic
		ksort($f_request);

		$this->array2xml($f_request, $xml);
		$this->f_request = $xml->asXML();

		// validez XML conform schemei (parametrul 2)
		$this->validate_xml($this->f_request, $validation_url);

		$this->AESEnc();
		$this->RSAEnc();

		$request = array();

		$request['f_login'] = $this->f_login;
		$request['f_message'] = $this->f_request;
		$request['f_crypt_message '] = $this->f_secure;

		$xml_auth_soap = new \SimpleXMLElement('<po_request/>');
		$this->array2xml($request, $xml_auth_soap);
		$xml_auth_soap = $xml_auth_soap->asXML();
		$this->validate_xml($xml_auth_soap, self::$url_sv_request_xml);
		return $xml_auth_soap;
	}

	// function definition to convert array to xml
	private function array2xml($arr, &$xml_arr)
	{
		foreach ($arr as $key => $value) {
			if (\is_array($value)) {
				if (!\is_numeric($key)) {
					if (\strpos($key, 'coupon') !== false) {
						$subnode = $xml_arr->addChild("coupon");
					} else {
						$subnode = $xml_arr->addChild("$key");
					}
					$this->array2xml($value, $subnode);
				} else {
					$subnode = $xml_arr->addChild("item");
					$this->array2xml($value, $subnode);
				}
			} else {
				$xml_arr->addChild("$key", htmlspecialchars("$value"));
			}
		}
	}

	private function validate_xml($poxml, $url)
	{
		\libxml_use_internal_errors(true);
		\libxml_disable_entity_loader(true);
		$xml = new \DOMDocument();
		$xml->loadXML($poxml);
		$request = new Curl\Get($url);

		$request->send();
		if ($request->getStatus() !== 200) {
			throw new \Exception('Nu am putut obtine schema de validare de la PlatiOnline');
		}
		$schemaPO5 = $request->getResponse();
		if (!$xml->schemaValidateSource($schemaPO5)) {
			$errors = \libxml_get_errors();
			$finalmsg = array();
			foreach ($errors as $error) {
				echo $this->libxml_display_error($error);
			}
			\libxml_clear_errors();
			throw new \Exception('INVALID XML');
		}
	}

	private function decrypt_response($f_relay_message, $f_crypt_message, $validation_url)
	{
		if (empty($f_relay_message)) {
			throw new \Exception('Decriptare raspuns - nu se primeste [criptul AES]');
		}
		if (empty($f_crypt_message)) {
			throw new \Exception('Decriptare raspuns - nu se primeste [criptul RSA]');
		}
		$rsa = new RSA();
		$rsa->loadKey($this->rsa_key_dec);
		$rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
		$aes_key = $rsa->decrypt(\base64_decode($f_crypt_message));
		if (empty($aes_key)) {
			throw new \Exception('Nu am putut decripta cheia AES din RSA');
		}
		$aes = new AES();
		$aes->setIV($this->iv_itsn);
		$aes->setKey($aes_key);
		$response = $aes->decrypt(\base64_decode($this->hex2str($f_relay_message)));
		if (empty($response)) {
			throw new \Exception('Nu am putut decripta mesajul din criptul AES');
		}
		$this->validate_xml($response, $validation_url);
		return $this->xml_to_object($response);
	}

	private function libxml_display_error($error)
	{
		$return = "\n";
		switch ($error->level) {
			case LIBXML_ERR_WARNING:
				$return .= "Warning $error->code: ";
				break;
			case LIBXML_ERR_ERROR:
				$return .= "Error $error->code: ";
				break;
			case LIBXML_ERR_FATAL:
				$return .= "Fatal Error $error->code: ";
				break;
		}
		$return .= trim($error->message);
		$return .= "\n";
		return $return;
	}

	private function hex2str($hex)
	{
		$str = '';
		for ($i = 0; $i < \strlen($hex); $i += 2) {
			$str .= \chr(\hexdec(\substr($hex, $i, 2)));
		}
		return $str;
	}

	private function xml_to_object($xml)
	{
		$parser = \xml_parser_create();
		\xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		\xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		\xml_parse_into_struct($parser, $xml, $tags);
		\xml_parser_free($parser);

		$elements = array();  // the currently filling [child] XmlElement array
		$stack = array();
		foreach ($tags as $tag) {
			$index = count($elements);
			if ($tag['type'] == "complete" || $tag['type'] == "open") {
				$elements[$index] = new XmlElement;
				$elements[$index]->name = isset($tag['tag']) ? $tag['tag'] : '';
				$elements[$index]->attributes = isset($tag['attributes']) ? $tag['attributes'] : '';
				$elements[$index]->content = isset($tag['value']) ? $tag['value'] : '';
				if ($tag['type'] == "open") {  // push
					$elements[$index]->children = array();
					$stack[count($stack)] = &$elements;
					$elements = &$elements[$index]->children;
				}
			}
			if ($tag['type'] == "close") {  // pop
				$elements = &$stack[count($stack) - 1];
				unset($stack[count($stack) - 1]);
			}
		}
		return $elements[0];  // the single top-level element
	}

	//////////////////////////////////////////////////////////////
	//                    END PRIVATE METHODS                   //
	//////////////////////////////////////////////////////////////
}

class XmlElement
{
	public $name;
	public $attributes;
	public $content;
	public $children;
}
