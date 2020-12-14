<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Ps_Contactinfo extends Module implements WidgetInterface
{
    private $templates = array (
        'light' => 'nav.tpl',
        'rich' => 'ps_contactinfo-rich.tpl',
        'default' => 'ps_contactinfo.tpl',
    );

    public function __construct()
    {
        $this->name = 'ps_contactinfo';
        $this->author = 'PrestaShop';
        $this->version = '3.3.0';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->getTranslator()->trans('Contact information', array(), 'Modules.Contactinfo.Admin');
        $this->description = $this->getTranslator()->trans('Allows you to display additional information about your store\'s customer service.', array(), 'Modules.Contactinfo.Admin');
        $this->ps_versions_compliancy = array('min' => '1.7.2.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        return parent::install()
            && Configuration::updateValue('PS_CONTACT_INFO_DISPLAY_EMAIL', 1)
            && $this->registerHook([
                'displayNav', // Standard hook
                'displayNav1', // For Classic-inspired themes
                'displayFooter',
                'actionAdminStoresControllerUpdate_optionsAfter',
            ]);
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        if ($hookName == null && isset($configuration['hook'])) {
            $hookName = $configuration['hook'];
        }

        if (preg_match('/^displayNav\d*$/', $hookName)) {
            $template_file = $this->templates['light'];
        } elseif ($hookName == 'displayLeftColumn' || $hookName == 'displayRightColumn') {
            $template_file = $this->templates['rich'];
        } else {
            $template_file = $this->templates['default'];
        }

        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch('module:'.$this->name.'/'.$template_file);
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $address = $this->context->shop->getAddress();

        $is_state_multilang = !empty(State::$definition['multilang']);
        $state_name = (new State($address->id_state))->name;

        $contact_infos = [
            'company' => Configuration::get('PS_SHOP_NAME'),
            'address' => [
                'formatted' => AddressFormat::generateAddress($address, array(), '<br />'),
                'address1' => $address->address1,
                'address2' => $address->address2,
                'postcode' => $address->postcode,
                'city' => $address->city,
                'state' => $is_state_multilang ? $state_name[$this->context->language->id] : $state_name,
                'country' => (new Country($address->id_country))->name[$this->context->language->id],
            ],
            'phone' => Configuration::get('PS_SHOP_PHONE'),
            'fax' => Configuration::get('PS_SHOP_FAX'),
            'email' => Configuration::get('PS_SHOP_EMAIL'),
        ];

        return [
            'contact_infos' => $contact_infos,
            'display_email' => Configuration::get('PS_CONTACT_INFO_DISPLAY_EMAIL'),
        ];
    }

    public function hookActionAdminStoresControllerUpdate_optionsAfter()
    {
        foreach ($this->templates as $template) {
            $this->_clearCache($template);
        }

        return true;
    }

    public function getContent()
    {
        $output = [];

        if (Tools::isSubmit('submitContactInfo')) {
            Configuration::updateValue('PS_CONTACT_INFO_DISPLAY_EMAIL', (int)Tools::getValue('PS_CONTACT_INFO_DISPLAY_EMAIL'));

            foreach ($this->templates as $template) {
                $this->_clearCache($template);
            }

            $output[] = $this->displayConfirmation($this->trans('Settings updated.', array(), 'Admin.Notifications.Success'));

            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&conf=6&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name);
        }

        $helper = new HelperForm();
        $helper->submit_action = 'submitContactInfo';

        $field = array(
            'type' => 'switch',
            'label' => $this->trans('Display email address', array(), 'Admin.Actions'),
            'name' => 'PS_CONTACT_INFO_DISPLAY_EMAIL',
            'desc' => $this->trans('Your theme needs to be compatible with this feature', array(), 'Modules.Contactinfo.Admin'),
            'values' => array(
                array(
                    'id' => 'active_on',
                    'value' => 1,
                    'label' => $this->trans('Yes', array(), 'Admin.Global')
                ),
                array(
                    'id' => 'active_off',
                    'value' => 0,
                    'label' => $this->trans('No', array(), 'Admin.Global')
                )
            )
        );

        $helper->fields_value['PS_CONTACT_INFO_DISPLAY_EMAIL'] = Configuration::get('PS_CONTACT_INFO_DISPLAY_EMAIL');

        $output[] = $helper->generateForm(array(
            array(
                'form' => array(
                    'legend' => array(
                        'title' => $this->displayName,
                        'icon' => 'icon-share'
                    ),
                    'input' => [$field],
                    'submit' => array(
                        'title' => $this->trans('Save', array(), 'Admin.Actions')
                    )
                )
            )
        ));

        return implode($output);
    }
}
