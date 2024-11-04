<?php
/**
 * 2009-2024 Plati.Online
 *
 *  @author    Plati.Online <support@plationline.ro>
 *  @copyright 2023 Plati.Online
 *  @license   Plati.Online
 *  @version   Release: $Revision: 6.0.7
 *  @date      29/04/2024
 */

use PlatiOnlinePO6\Inc\Libraries\PO5 as PO5;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Plationline extends PaymentModule
{
    private $html = '';
    private static $relay_methods = array(
        array(
            'id' => 'PTOR',
            'name' => 'PTOR',
        ),
        array(
            'id' => 'POST_S2S_PO_PAGE',
            'name' => 'POST_S2S_PO_PAGE',
        ),
        array(
            'id' => 'SOAP_PO_PAGE',
            'name' => 'SOAP_PO_PAGE',
        ),
        array(
            'id' => 'SOAP_MT_PAGE',
            'name' => 'SOAP_MT_PAGE',
        ),
    );

    private static $account_types = array(
        array(
            'id' => 'DEMO',
            'name' => 'DEMO',
        ),
        array(
            'id' => 'LIVE',
            'name' => 'LIVE',
        ),
    );
    private static $loginpo_activate;

    public $ssl_enabled;
    private $itsn_url;
    public $absolutePath;
    public $absoluteUrl;
    public static $po_order_map;

    public function __construct()
    {
        $this->name = 'plationline';
        $this->tab = 'payments_gateways';
        $this->version = '6.0.7';
        $this->author = 'PlatiOnline';
        $this->controllers = array('payment', 'validation');

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('PlatiOnline Payments');
        $this->description = $this->l('Online payment by card and Login with Plati.Online account');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall Plati.Online module?');
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

        $this->ssl_enabled = Configuration::get('PS_SSL_ENABLED');
        $this->module_key = '583161afe7960745ab59333da961fa92';
        $this->secure_key = Tools::hash($this->name);

        $this->itsn_url = $this->context->link->getModuleLink('plationline', 'itsn', array('secure_key' => $this->secure_key), $this->ssl_enabled);

        $this->absolutePath = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $this->name . DIRECTORY_SEPARATOR;
        $this->absoluteUrl = (Configuration::get('PS_SSL_ENABLED') ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . _MODULE_DIR_ . $this->name . '/';
        require_once($this->absolutePath . 'inc/libraries/autoloader.php');

        self::$loginpo_activate = array(
            array(
                'id' => 'no',
                'name' => $this->l('NO'),
            ),
            array(
                'id' => 'yes',
                'name' => $this->l('YES'),
            ),
        );

        self::$po_order_map = array(
            'PO_AUTHORIZED' => array('2'),
            'PO_PENDING_AUTHORIZATION' => array('1'),
            'PO_DECLINED' => array('8'),
            'PO_ERROR' => array('10', '16', '17'),
            'PO_ONHOLD' => array('13'),
            'PO_PENDING_SETTLE' => array('3'),
            'PO_PENDING_REFUND' => array('5-1'),
            'PO_PENDING_REFUND' => array('5-2'),
            'PO_CHARGEBACK' => array('5-3'),
            'PO_SETTLED' => array('5-4'),
            'PO_PENDING_VOID' => array('6'),
            'PO_CANCELED' => array('7'),
            'PO_EXPIRED' => array('9'),
        );
    }

    private static function recursiveArraySearch($needle, $haystack)
    {
        foreach ($haystack as $key => $value) {
            $current_key = $key;
            if ($needle === $value || (\is_array($value) && self::recursiveArraySearch($needle, $value) !== false)) {
                return $current_key;
            }
        }
        return false;
    }

    public function getPrestaStatusByPoStatus($primary, $secondary = '')
    {
        return self::recursiveArraySearch($primary . ($secondary ? '-' . $secondary : ''), self::$po_order_map);
    }

    public function addOrderStatus($configKey, $statusName, $stateConfig)
    {
        if (!Configuration::get($configKey)) {
            $orderState = new OrderState();
            $orderState->name = array();
            $orderState->module_name = $this->name;
            $orderState->send_email = ($configKey == 'PO_AUTHORIZED' ? true : false);
            $orderState->color = $stateConfig['color'];
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = true;
            $orderState->invoice = false;
            $orderState->paid = ($configKey == 'PO_AUTHORIZED' ? true : false);
            foreach (Language::getLanguages() as $language) {
                $orderState->template[$language['id_lang']] = 'payment';
                $orderState->name[$language['id_lang']] = $statusName;
            }

            if ($orderState->add()) {
                $plationlineIcon = dirname(__FILE__) . '/logo.gif';
                $newStateIcon = dirname(__FILE__) . '/../../img/os/' . (int)$orderState->id . '.gif';
                copy($plationlineIcon, $newStateIcon);
            }

            Configuration::updateValue($configKey, (int)$orderState->id);
        }
    }

    public function deleteOrderStatus($status)
    {
        $orderState = new OrderState(Configuration::get($status));
        return $orderState->delete();
    }

    public function addPlatiOnlineOrderStatus()
    {
        $stateConfig = array();
        try {
            $stateConfig['color'] = '#389300';
            $this->addOrderStatus(
                'PO_AUTHORIZED',
                $this->l('PO Authorized'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $stateConfig['paid'] = true;
            $this->addOrderStatus(
                'PO_PENDING_SETTLE',
                $this->l('PO Pending Settle'),
                $stateConfig
            );
            $stateConfig['color'] = '#3cc4ff';
            $this->addOrderStatus(
                'PO_SETTLED',
                $this->l('PO Settled'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $this->addOrderStatus(
                'PO_CANCELED',
                $this->l('PO Voided'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $this->addOrderStatus(
                'PO_CHARGEBACK',
                $this->l('PO Chargeback'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $this->addOrderStatus(
                'PO_ERROR',
                $this->l('PO Error'),
                $stateConfig
            );
            $stateConfig['color'] = '#fbbb22';
            $this->addOrderStatus(
                'PO_ONHOLD',
                $this->l('PO OnHold'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $this->addOrderStatus(
                'PO_EXPIRED',
                $this->l('PO Expired'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $this->addOrderStatus(
                'PO_PENDING_VOID',
                $this->l('PO Pending Void'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $this->addOrderStatus(
                'PO_REFUND',
                $this->l('PO Refunded'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $this->addOrderStatus(
                'PO_PENDING_REFUND',
                $this->l('PO Pending refund'),
                $stateConfig
            );
            $stateConfig['color'] = '#cccccc';
            $this->addOrderStatus(
                'PO_PENDING_AUTHORIZATION',
                $this->l('PO Pending Authorization'),
                $stateConfig
            );
            $stateConfig['color'] = '#ff8f80';
            $this->addOrderStatus(
                'PO_DECLINED',
                $this->l('PO Declined'),
                $stateConfig
            );
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    public function install()
    {
        $this->addPlatiOnlineOrderStatus();
        // default plationline settings.
        Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_RON', '');
        Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL', '');
        Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_EUR', '');
        Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL', '');
        Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_USD', '');
        Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL', '');
        Configuration::updateValue('PLATIONLINE_RO_RSA_AUTH', '');
        Configuration::updateValue('PLATIONLINE_RO_RSA_ITSN', '');
        Configuration::updateValue('PLATIONLINE_RO_IV_AUTH', '');
        Configuration::updateValue('PLATIONLINE_RO_IV_ITSN', '');
        if ($this->ssl_enabled) {
            Configuration::updateValue('PLATIONLINE_RO_RELAY_METHOD', 'PTOR');
        } else {
            Configuration::updateValue('PLATIONLINE_RO_RELAY_METHOD', 'POST_S2S_PO_PAGE');
        }
        Configuration::updateValue('PLATIONLINE_RO_DEMO', 'DEMO');
        Configuration::updateValue('PLATIONLINE_RO_CC', 'PO');
        Configuration::updateValue('PLATIONLINE_RO_LOGINPO_ACTIVATE', 'NO');
        Configuration::updateValue('PLATIONLINE_RO_RSA_AUTH_LOGINPO', '');
        Configuration::updateValue('PLATIONLINE_RO_LOGINPO_DEMO', 'DEMO');
        Configuration::updateValue('PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL', '');

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('updateOrderStatus')
            && $this->registerHook('displayAdminOrder')
            && $this->registerHook('displayCustomerLoginFormAfter')
            && $this->registerHook('displayOrderDetail')
            && $this->registerHook('displayHeader')
            && $this->registerHook('backOfficeHeader')
            && $this->registerHook('displayBackOfficeHeader');
    }

    public function uninstall()
    {
        if (!Configuration::deleteByName('PLATIONLINE_RO_LOGIN_ID_RON')
            || !Configuration::deleteByName('PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL')
            || !Configuration::deleteByName('PLATIONLINE_RO_LOGIN_ID_EUR')
            || !Configuration::deleteByName('PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL')
            || !Configuration::deleteByName('PLATIONLINE_RO_LOGIN_ID_USD')
            || !Configuration::deleteByName('PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL')
            || !Configuration::deleteByName('PLATIONLINE_RO_RSA_AUTH')
            || !Configuration::deleteByName('PLATIONLINE_RO_RSA_ITSN')
            || !Configuration::deleteByName('PLATIONLINE_RO_IV_AUTH')
            || !Configuration::deleteByName('PLATIONLINE_RO_IV_ITSN')
            || !Configuration::deleteByName('PLATIONLINE_RO_RELAY_METHOD')
            || !Configuration::deleteByName('PLATIONLINE_RO_DEMO')
            || !Configuration::deleteByName('PLATIONLINE_RO_CC')
            || !Configuration::deleteByName('PLATIONLINE_RO_LOGINPO_ACTIVATE')
            || !Configuration::deleteByName('PLATIONLINE_RO_RSA_AUTH_LOGINPO')
            || !Configuration::deleteByName('PLATIONLINE_RO_LOGINPO_DEMO')
            || !Configuration::deleteByName('PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL')

            || !$this->deleteOrderStatus('PO_AUTHORIZED')
            || !$this->deleteOrderStatus('PO_PENDING_SETTLE')
            || !$this->deleteOrderStatus('PO_SETTLED')
            || !$this->deleteOrderStatus('PO_CANCELED')
            || !$this->deleteOrderStatus('PO_CHARGEBACK')
            || !$this->deleteOrderStatus('PO_ERROR')
            || !$this->deleteOrderStatus('PO_ONHOLD')
            || !$this->deleteOrderStatus('PO_EXPIRED')
            || !$this->deleteOrderStatus('PO_PENDING_VOID')
            || !$this->deleteOrderStatus('PO_REFUND')
            || !$this->deleteOrderStatus('PO_PENDING_REFUND')
            || !$this->deleteOrderStatus('PO_PENDING_AUTHORIZATION')
            || !$this->deleteOrderStatus('PO_DECLINED')

            || !Configuration::deleteByName('PO_AUTHORIZED')
            || !Configuration::deleteByName('PO_PENDING_SETTLE')
            || !Configuration::deleteByName('PO_SETTLED')
            || !Configuration::deleteByName('PO_CANCELED')
            || !Configuration::deleteByName('PO_CHARGEBACK')
            || !Configuration::deleteByName('PO_ERROR')
            || !Configuration::deleteByName('PO_ONHOLD')
            || !Configuration::deleteByName('PO_EXPIRED')
            || !Configuration::deleteByName('PO_PENDING_VOID')
            || !Configuration::deleteByName('PO_REFUND')
            || !Configuration::deleteByName('PO_PENDING_REFUND')
            || !Configuration::deleteByName('PO_PENDING_AUTHORIZATION')
            || !Configuration::deleteByName('PO_DECLINED')

            || !$this->unregisterHook('paymentReturn')
            || !$this->unregisterHook('updateOrderStatus')
            || !$this->unregisterHook('paymentOptions')
            || !$this->unregisterHook('displayAdminOrder')
            || !$this->unregisterHook('displayCustomerLoginFormAfter')
            || !$this->unregisterHook('displayOrderDetail')
            || !$this->unregisterHook('displayHeader')
            || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    private function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('PLATIONLINE_RO_LOGIN_ID_RON') && !Tools::getValue('PLATIONLINE_RO_LOGIN_ID_EUR') && !Tools::getValue('PLATIONLINE_RO_LOGIN_ID_USD')) {
                $this->postErrors[] = $this->l('At least one F_LOGIN (RON, EUR, USD) field is mandatory, depending on the currency of your shop');
            } elseif (!Tools::getValue('PLATIONLINE_RO_RSA_AUTH')) {
                $this->postErrors[] = $this->l('The field RSA AUTH is mandatory.');
            } elseif (!Tools::getValue('PLATIONLINE_RO_IV_AUTH')) {
                $this->postErrors[] = $this->l('The field IV AUTH is mandatory.');
            } elseif (!Tools::getValue('PLATIONLINE_RO_RSA_ITSN')) {
                $this->postErrors[] = $this->l('The field RSA ITSN is mandatory.');
            } elseif (!Tools::getValue('PLATIONLINE_RO_IV_ITSN')) {
                $this->postErrors[] = $this->l('The field IV ITSN is mandatory.');
            }
        }
    }

    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_RON', Tools::getValue('PLATIONLINE_RO_LOGIN_ID_RON'));
            Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL', Tools::getValue('PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL'));
            Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_EUR', Tools::getValue('PLATIONLINE_RO_LOGIN_ID_EUR'));
            Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL', Tools::getValue('PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL'));
            Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_USD', Tools::getValue('PLATIONLINE_RO_LOGIN_ID_USD'));
            Configuration::updateValue('PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL', Tools::getValue('PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL'));
            Configuration::updateValue('PLATIONLINE_RO_RSA_AUTH', Tools::getValue('PLATIONLINE_RO_RSA_AUTH'));
            Configuration::updateValue('PLATIONLINE_RO_RSA_ITSN', Tools::getValue('PLATIONLINE_RO_RSA_ITSN'));
            Configuration::updateValue('PLATIONLINE_RO_IV_AUTH', Tools::getValue('PLATIONLINE_RO_IV_AUTH'));
            Configuration::updateValue('PLATIONLINE_RO_IV_ITSN', Tools::getValue('PLATIONLINE_RO_IV_ITSN'));
            Configuration::updateValue('PLATIONLINE_RO_RELAY_METHOD', Tools::getValue('PLATIONLINE_RO_RELAY_METHOD'));
            Configuration::updateValue('PLATIONLINE_RO_DEMO', Tools::getValue('PLATIONLINE_RO_DEMO'));
            Configuration::updateValue('PLATIONLINE_RO_CC', Tools::getValue('PLATIONLINE_RO_CC'));
            Configuration::updateValue('PLATIONLINE_RO_LOGINPO_ACTIVATE', Tools::getValue('PLATIONLINE_RO_LOGINPO_ACTIVATE'));
            Configuration::updateValue('PLATIONLINE_RO_RSA_AUTH_LOGINPO', Tools::getValue('PLATIONLINE_RO_RSA_AUTH_LOGINPO'));
            Configuration::updateValue('PLATIONLINE_RO_LOGINPO_DEMO', Tools::getValue('PLATIONLINE_RO_LOGINPO_DEMO'));
            Configuration::updateValue('PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL', Tools::getValue('PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL'));
        }
        $this->html .= $this->displayConfirmation($this->trans('Settings updated', array(), 'Admin.Notifications.Success'));
    }

    private function displayPlatiOnline()
    {
        $this->context->smarty->assign(array(
            'itsn_url' => $this->itsn_url,
        ));
        return $this->display(__FILE__, './views/templates/hook/infos.tpl');
    }

    public function getContent()
    {
        $this->html = '';

        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (empty($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        }

        $this->html .= $this->displayPlatiOnline();
        $this->html .= $this->renderForm();

        return $this->html;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return [];
        }
        if (!$this->checkCurrency($params['cart'])) {
            return [];
        }

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $newOption = new PaymentOption();
        $newOption->setModuleName($this->name);

        $payment_text = $this->l('Online payment by card (Visa/Maestro/Mastercard)');
        $newOption->setAction($this->context->link->getModuleLink($this->name, 'validation', array('payment_method' => $this->name), $this->ssl_enabled));

        $newOption->setCallToActionText($payment_text)->setAdditionalInformation($this->fetch('module:plationline/views/templates/front/' . $this->name . '_info.tpl'));


        if (Configuration::get('PLATIONLINE_RO_CC') == 'PO' && !empty(Configuration::get('PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL'))) {
            if (
                !empty(Configuration::get('PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL')) ||
                !empty(Configuration::get('PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL')) ||
                !empty(Configuration::get('PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL'))
            ) {
                $newOptionAdditional = new PaymentOption();
                $newOptionAdditional->setModuleName($this->name . '_additional');

                $payment_text = Configuration::get('PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL');
                $newOptionAdditional->setAction($this->context->link->getModuleLink($this->name, 'validation', array('payment_method' => $this->name . '_additional'), $this->ssl_enabled));

                $newOptionAdditional->setCallToActionText($payment_text);
                //->setAdditionalInformation($this->fetch('module:plationline/views/templates/front/' . $this->name . '_info.tpl'));
            }
        }

        $paymentOptions = array($newOption);

        if (!empty($newOptionAdditional)) {
            $paymentOptions[] = $newOptionAdditional;
        }

        return $paymentOptions;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configure Plati.Online module') . ' [' . $this->version . ']',
                    'icon' => 'icon-envelope',
                    'desc' => $this->l('please obtain this value from merchants account, after setting a secret question/answer combination.'),
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('F_LOGIN_RON'),
                        'desc' => $this->l('please obtain this value from merchants account, after setting a secret question/answer combination.'),
                        'name' => 'PLATIONLINE_RO_LOGIN_ID_RON',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('F_LOGIN_EUR'),
                        'desc' => $this->l('please fill in this field if you process EURO payments.'),
                        'name' => 'PLATIONLINE_RO_LOGIN_ID_EUR',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('F_LOGIN_USD'),
                        'desc' => $this->l('please fill in this field if you process USD payments.'),
                        'name' => 'PLATIONLINE_RO_LOGIN_ID_USD',
                        'required' => true,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('RSA AUTH'),
                        'desc' => $this->l('please obtain this value from merchants account, after setting a secret question/answer combination.'),
                        'name' => 'PLATIONLINE_RO_RSA_AUTH',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('IV AUTH'),
                        'desc' => $this->l('please obtain this value from merchants account, after setting a secret question/answer combination.'),
                        'name' => 'PLATIONLINE_RO_IV_AUTH',
                        'required' => true,
                    ),
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('RSA ITSN'),
                        'desc' => $this->l('please obtain this value from merchants account, after setting a secret question/answer combination.'),
                        'name' => 'PLATIONLINE_RO_RSA_ITSN',
                        'required' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('IV ITSN'),
                        'desc' => $this->l('please obtain this value from merchants account, after setting a secret question/answer combination.'),
                        'name' => 'PLATIONLINE_RO_IV_ITSN',
                        'required' => true,
                    ),
                    $this->getSelectForm(
                        $this->l('Relay Response Method'),
                        'PLATIONLINE_RO_RELAY_METHOD',
                        self::$relay_methods,
                        $this->l('The Relay Method has been automatically set up based on your shop SSL config. More info ') . ' <a rel="noopener norefferer" href="http://wiki.plationline.eu/index.php?title=Authorization_relay_response" target="_blank">' . '<b>' . $this->l('HERE') . '</b></a>'
                    ),
                    $this->getSelectForm($this->l('Operating mode'), 'PLATIONLINE_RO_DEMO', self::$account_types),
                    array(
                        'type' => 'radio',
                        'label' => $this->l('I signed a contract with'),
                        'name' => 'PLATIONLINE_RO_CC',
                        'required' => true,
                        'br' => true,
                        'values' => array(
                            array(
                                'id' => 'CC_PO',
                                'value' => 'PO',
                                'label' => $this->l('PlatiOnline'),
                            ),
                            array(
                                'id' => 'CC_RZB',
                                'value' => 'RZB',
                                'label' => $this->l('Raiffeisen Bank'),
                            ),
                            array(
                                'id' => 'CC_BT',
                                'value' => 'BT',
                                'label' => $this->l('Transilvania Bank'),
                            ),
                            array(
                                'id' => 'CC_BRD',
                                'value' => 'BRD',
                                'label' => $this->l('BRD'),
                            ),
                            array(
                                'id' => 'CC_ALPHA',
                                'value' => 'ALPHA',
                                'label' => $this->l('Alpha Bank'),
                            ),
                        ),
                    ),
                    /*$this->getSelectForm($this->l('Activate Login with Plati.Online'), 'PLATIONLINE_RO_LOGINPO_ACTIVATE', self::$loginpo_activate),
                    array(
                        'type'     => 'textarea',
                        'label'    => $this->l('RSA AUTH LOGINPO'),
                        'desc'     => $this->l('please obtain this value from merchants account, after setting a secret question/answer combination.'),
                        'name'     => 'PLATIONLINE_RO_RSA_AUTH_LOGINPO',
                        'required' => true,
                    ),
                    $this->getSelectForm($this->l('Operating mode Login with Plati.Online'), 'PLATIONLINE_RO_LOGINPO_DEMO', self::$account_types),*/
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $fields_form_additional = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Additional Plati.Online payment method'),
                    'icon' => 'icon-envelope',
                    'desc' => $this->l('use only if instructed so by PlatiOnline'),
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('F_LOGIN_RON_ADDITIONAL'),
                        'desc' => $this->l('please fill in this field if you process RON payments.'),
                        'name' => 'PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('F_LOGIN_EUR_ADDITIONAL'),
                        'desc' => $this->l('please fill in this field if you process EURO payments.'),
                        'name' => 'PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('F_LOGIN_USD_ADDITIONAL'),
                        'desc' => $this->l('please fill in this field if you process USD payments.'),
                        'name' => 'PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL',
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('PAYMENT_METHOD_NAME_ADDITIONAL'),
                        'desc' => $this->l('payment method name'),
                        'name' => 'PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL',
                    ),
                ),
                'submit' => array(
                    'title' => $this->trans('Save', array(), 'Admin.Actions'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
        );

        $this->fields_form = array();

        return $helper->generateForm(array($fields_form, $fields_form_additional));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PLATIONLINE_RO_LOGIN_ID_RON' => trim(Tools::getValue('PLATIONLINE_RO_LOGIN_ID_RON', Configuration::get('PLATIONLINE_RO_LOGIN_ID_RON'))),
            'PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL' => trim(Tools::getValue('PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL', Configuration::get('PLATIONLINE_RO_LOGIN_ID_RON_ADDITIONAL'))),
            'PLATIONLINE_RO_LOGIN_ID_EUR' => trim(Tools::getValue('PLATIONLINE_RO_LOGIN_ID_EUR', Configuration::get('PLATIONLINE_RO_LOGIN_ID_EUR'))),
            'PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL' => trim(Tools::getValue('PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL', Configuration::get('PLATIONLINE_RO_LOGIN_ID_EUR_ADDITIONAL'))),
            'PLATIONLINE_RO_LOGIN_ID_USD' => trim(Tools::getValue('PLATIONLINE_RO_LOGIN_ID_USD', Configuration::get('PLATIONLINE_RO_LOGIN_ID_USD'))),
            'PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL' => trim(Tools::getValue('PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL', Configuration::get('PLATIONLINE_RO_LOGIN_ID_USD_ADDITIONAL'))),
            'PLATIONLINE_RO_RSA_AUTH' => Tools::getValue('PLATIONLINE_RO_RSA_AUTH', Configuration::get('PLATIONLINE_RO_RSA_AUTH')),
            'PLATIONLINE_RO_IV_AUTH' => Tools::getValue('PLATIONLINE_RO_IV_AUTH', Configuration::get('PLATIONLINE_RO_IV_AUTH')),
            'PLATIONLINE_RO_RSA_ITSN' => Tools::getValue('PLATIONLINE_RO_RSA_ITSN', Configuration::get('PLATIONLINE_RO_RSA_ITSN')),
            'PLATIONLINE_RO_IV_ITSN' => Tools::getValue('PLATIONLINE_RO_IV_ITSN', Configuration::get('PLATIONLINE_RO_IV_ITSN')),
            'PLATIONLINE_RO_RELAY_METHOD' => Tools::getValue('PLATIONLINE_RO_RELAY_METHOD', Configuration::get('PLATIONLINE_RO_RELAY_METHOD')),
            'PLATIONLINE_RO_DEMO' => Tools::getValue('PLATIONLINE_RO_DEMO', Configuration::get('PLATIONLINE_RO_DEMO')),
            'PLATIONLINE_RO_CC' => Tools::getValue('PLATIONLINE_RO_CC', Configuration::get('PLATIONLINE_RO_CC')),
            'PLATIONLINE_RO_LOGINPO_ACTIVATE' => Tools::getValue('PLATIONLINE_RO_LOGINPO_ACTIVATE', Configuration::get('PLATIONLINE_RO_LOGINPO_ACTIVATE')),
            'PLATIONLINE_RO_RSA_AUTH_LOGINPO' => Tools::getValue('PLATIONLINE_RO_RSA_AUTH_LOGINPO', Configuration::get('PLATIONLINE_RO_RSA_AUTH_LOGINPO')),
            'PLATIONLINE_RO_LOGINPO_DEMO' => Tools::getValue('PLATIONLINE_RO_LOGINPO_DEMO', Configuration::get('PLATIONLINE_RO_LOGINPO_DEMO')),
            'PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL' => Tools::getValue('PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL', Configuration::get('PLATIONLINE_RO_PAYMENT_METHOD_NAME_ADDITIONAL')),
        );
    }


    private function getSelectForm($label, $name, $selectList, $desc = '', $required = true)
    {
        $selectForm = array(
            'type' => 'select',
            'label' => $label,
            'name' => $name,
            'desc' => $desc,
            'options' => array(
                'query' => $selectList,
                'id' => 'id',
                'name' => 'name',
            ),
            'required' => $required,
        );
        return $selectForm;
    }

    private function getRadioForm($label, $name, $selectList, $desc = '', $required = false)
    {
        $selectForm = array(
            'type' => 'radio',
            'label' => $label,
            'name' => $name,
            'desc' => $desc,
            'is_bool' => true,
            'values' => $selectList,
            'required' => $required,
        );
        return $selectForm;
    }

    public function getTemplateVars()
    {
        $cc = Configuration::get('PLATIONLINE_RO_CC');

        $logos = Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/views/img/PO_VS_MC_wide.png');

        if ($cc != "RZB") {
            return array(
                'logos' => $logos,
                'redirect_message' => $this->l('You will be redirected to PlatiOnline page'),
            );
        } else {
            return array(
                'logos' => false,
                'redirect_message' => $this->l('You will be redirected to Raiffeisen PlatiOnline page'),
            );
        }
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->context->controller->addJs('modules/' . $this->name . '/views/js/riot+compiler.min.js');
        $this->context->controller->addCss('modules/' . $this->name . '/views/css/plationline-admin.css');
        if (strcmp(Tools::getValue('configure'), $this->name) === 0) {
            $this->context->controller->addJs('modules/' . $this->name . '/views/js/plationline-admin.js');
        }
    }

    public function hookDisplayAdminOrder($params)
    {
        $order_id = (int)$params['id_order'];
        $order = new Order($order_id);
        // verific daca este platita prin PlatiOnline
        if ($order->module == $this->name) {
            $currency = new Currency($order->id_currency);
            $order_payments = OrderPayment::getByOrderReference($order->reference); //preiau toate platile

            // verific care este cea mai recenta plata si ii preiau transid
            $trans_id = null;
            foreach (array_reverse($order_payments) as $op) {
                if (!empty($op->transaction_id)) {
                    $trans_id = (int)$op->transaction_id;
                    break;
                }
            }

            if (!empty($trans_id)) {
                $this->smarty->assign('tags', array('table', 'panel'));
                $this->smarty->assign(
                    array(
                        'order_id' => $order_id,
                        'trans_id' => $trans_id,
                        'amount' => $order->total_paid,
                        'currency' => $currency->iso_code,
                        'secure_key' => $this->secure_key,
                        'absoluteUrl' => $this->absoluteUrl,
                    )
                );
                $html = $this->display(__FILE__, 'views/templates/hook/transaction.tpl');
                return $html . $this->display(__FILE__, 'views/templates/admin/prestui/ps-tags.tpl');
            }
        }
    }

    public function hookHeader($params)
    {
        $this->context->controller->registerStylesheet('plationline-login', 'modules/' . $this->name . '/views/css/plationline-login.css', array('media' => 'all', 'priority' => 50));
    }

    /* LOGIN WITH PLATI.ONLINE */
    public function hookDisplayCustomerLoginFormAfter()
    {
        // to be implemented later
        return false;
        $lang = new Language($this->context->language->id);
        $this->context = Context::getContext();

        // Do not display for users that are logged in, or for users that are using our controller
        if (!$this->context->customer->isLogged() and $this->context->controller->php_self != 'plationline') {

            $currency = new Currency(Configuration::get('PS_CURRENCY_DEFAULT'));
            $default_currency = $currency->iso_code;
            $data = array(
                'response_type' => 'code',
                'client_id' => Configuration::get('PLATIONLINE_RO_LOGIN_ID_' . Tools::strtoupper($default_currency)),
                'lang' => $lang->iso_code,
                'state' => (Configuration::get('PLATIONLINE_RO_LOGINPO_DEMO' === 'DEMO' ? 'test_account' : 'live_account')),
                'redirect_uri' => $this->context->link->getModuleLink('plationline', 'loginReturn', array('secure_key' => $this->secure_key), $this->ssl_enabled),
                'scope' => urlencode('account_info billingAddress shippingAddress'),
                'singleAddress' => 'true', //customer can select only 1 billing address and 1 shipping address
            );
            $url = PO5::$url_login_plationline . '?' . http_build_query($data);
            $this->smarty->assign(
                array(
                    'url' => $url,
                    'absoluteUrl' => $this->absoluteUrl,
                )
            );

            return $this->display(__FILE__, 'views/templates/hook/login.tpl');
        }
    }

    /* RETRY PAYMENT */
    public function hookDisplayOrderDetail($params)
    {
        $this->context = Context::getContext();

        $order = $params['order'];
        $messages = Message::getMessagesByOrderId($order->id, true);

        // verific daca este platita prin PlatiOnline
        if ($this->context->customer->isLogged() && $order->module == $this->name) {
            $was_authorized = !empty($order->getHistory($this->context->language->id, Configuration::get('PO_AUTHORIZED')));
            if (!$was_authorized && !empty($messages[0]) && !empty($messages[0]['message'])) {
                $url = $messages[0]['message'];

                $this->smarty->assign(
                    array(
                        'url' => $url,
                        'absoluteUrl' => $this->absoluteUrl,
                    )
                );

                return $this->display(__FILE__, 'views/templates/hook/retry_payment.tpl');
            }
        }
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        $order = $params['order'];
        $order_id = $order->getOrderByCartId($params['order']->id_cart);

        $url = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'index.php?controller=order-detail&id_order=';

        $customer = new Customer((int)$order->id_customer);
        $url .= $order_id . '&key=' . $customer->secure_key;

        $text = sprintf($this->l('Congratulations, the transaction for order #%s was successfully authorized!', 'paymentreturn'), $order_id);
        $text_color = 'text-success';

        $this->smarty->assign(array(
            'shop_name' => $this->context->shop->name,
            'text' => $text,
            'text_color' => $text_color,
            'url_redirect' => $url,
            'see_order' => $this->l('See order', 'paymentreturn'),
        ));

        return $this->fetch('module:plationline/views/templates/hook/payment_return_success.tpl');

    }
}
