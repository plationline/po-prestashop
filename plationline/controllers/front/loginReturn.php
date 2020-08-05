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

class PlationlineLoginReturnModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        if (!Module::isEnabled('plationline') || !Tools::isSubmit('secure_key') || Tools::getValue('secure_key') != $this->module->secure_key) {
            die(1);
        }

        var_dump('loginReturnController');
        die();
    }
}
