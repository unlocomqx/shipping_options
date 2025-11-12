<?php
/**
 * 2007-2025 PrestaShop
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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2025 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use DynamicProduct\classes\DynamicTools;
use DynamicProduct\classes\helpers\DynamicInputFieldsHelper;
use DynamicProduct\classes\models\DynamicConfig;
use DynamicProduct\classes\models\DynamicField;
use DynamicProduct\classes\models\DynamicMainConfig;
use DynamicProduct\lib\media\DynamicEntriesHelper;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Shippingoptions extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'shippingoptions';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'TuniSoft';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Shipping Options');
        $this->description = $this->l('Display extra shipping option for certain carriers');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => '9.0');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('displayBackOfficeHeader') &&
            $this->registerHook('actionCarrierProcess') &&
            $this->registerHook('actionCarrierUpdate') &&
            $this->registerHook('displayAfterCarrier') &&
            $this->registerHook('displayCarrierExtraContent') &&
            $this->registerHook('displayCarrierList');
    }

    public function uninstall()
    {
        Configuration::deleteByName('SHIPPINGOPTIONS_ID_PRODUCT');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitShippingoptionsModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

//        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        $output = '';
        return $output . $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitShippingoptionsModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-cog"></i>',
                        'desc' => $this->l('The product containing the shipping options'),
                        'name' => 'SHIPPINGOPTIONS_ID_PRODUCT',
                        'label' => $this->l('Product ID'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'SHIPPINGOPTIONS_ID_PRODUCT' => Configuration::get('SHIPPINGOPTIONS_ID_PRODUCT', true),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS($this->_path . 'views/js/back.js');
            $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $scripts = [];
        $output = '';
        $id_product = (int)Configuration::get('SHIPPINGOPTIONS_ID_PRODUCT');
        $product_config = DynamicConfig::getByProduct($id_product);

        if ($product_config->active) {
            /** @var DynamicProduct $dynamicproduct */
            $dynamicproduct = Module::getInstanceByName('dynamicproduct');
            $entries_helper = new DynamicEntriesHelper($this, $this->context);
            $is_hot_mode = DynamicTools::isHotMode(2001);

            $this->context->controller->addJqueryUI('ui.tooltip');
            $this->context->controller->addJqueryUI('ui.spinner');
            $this->context->controller->addJqueryUI('ui.slider');
            $this->context->controller->addJqueryUI('ui.datepicker');
            $this->context->controller->addJqueryUI('ui.progressbar');

            $main_config = DynamicMainConfig::getConfig();
            if ($main_config->defer_load) {
                Media::addJsDef([
                    'dp_hot_mode' => $is_hot_mode,
                    'ps_module_dev' => DynamicTools::isModuleDevMode(),
                    'dp' => [
                        'id_product' => $id_product,
                        'id_source_product' => 0,
                        'id_attribute' => 0,
                        'is_admin_edit' => false,
                        'is_create_customization' => 0,
                        'dp_customer' => 0,
                        'main_config' => $main_config,
                        'controllers' => [
                            'loader' => $this->context->link->getModuleLink($dynamicproduct->name, 'loader'),
                        ],
                    ],
                ]);
            } else {
                $input_fields_helper = new DynamicInputFieldsHelper($dynamicproduct, $this->context);
                $variables = $input_fields_helper->loadVariables(
                    [
                        'is_hot_mode' => $is_hot_mode,
                        'id_product' => $id_product,
                        'id_source_product' => 0,
                        'id_attribute' => 0,
                        'is_admin_edit' => false,
                        'url_values' => [],
                    ]
                );
                Media::addJsDef($variables);
            }

            if (!$is_hot_mode) {
                $scripts = array_merge($scripts, [
                    $entries_helper->getEntry('../../vite/legacy-polyfills-legacy'),
                    $entries_helper->getEntry('front/product-buttons-legacy.ts'),
                ]);
            } else {
                $this->smarty->assign('script', DynamicTools::addScriptBase('front/product-buttons.ts'));
                $output .= $this->fetch($dynamicproduct->getFilePath('views/templates/hook/vite-script.tpl'));
            }

            Media::addJsDef([
                'dp_version' => $this->version,
                'dp_id_module' => $this->id,
                'dp_public_path' => $dynamicproduct->getFolderUrl('lib/media/dist/'),
                'dp_id_input' => 0,
            ]);

            Media::addJsDef([
                'dp_scripts' => array_map(function ($script) use ($dynamicproduct) {
                    return $dynamicproduct->getPathUri() . $script;
                }, array_unique($scripts)),
            ]);

            if (count($scripts)) {
                $output .= $this->fetch($dynamicproduct->getFilePath('views/templates/api/scripts.tpl'));
            }

            return $output;
        }

        return "";
    }

    public function hookActionCarrierProcess()
    {
        $a = 1;
    }

    public function hookActionCarrierUpdate()
    {
        $a = 1;
    }

    public function hookDisplayAfterCarrier()
    {
        $id_product = (int)Configuration::get('SHIPPINGOPTIONS_ID_PRODUCT');
        $product_config = DynamicConfig::getByProduct($id_product);
        if ($product_config->active) {
            $shipping_options = DynamicField::getFieldByName($id_product, 'shipping_options');
            $shipping_option = (int)$shipping_options->init;
            $this->context->smarty->assign([
                'shipping_option' => $shipping_option,
            ]);
            return $this->display(__FILE__, 'views/templates/hook/display-after-carrier.tpl');
        }

        return "";
    }

    public function hookDisplayCarrierExtraContent()
    {
        $a = 1;
    }

    public function hookDisplayCarrierList()
    {
        $a = 1;
    }
}
