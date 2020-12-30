<?php
/**
 * Copyright 2020 Mafisz
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0).
 * It is available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Mafisz <mafisz@gmail.com>
 * @copyright Mafisz
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License (AFL 3.0)
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Mafisz_Import extends Module implements WidgetInterface
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'mafisz_import';
        $this->tab = 'back_office_features';
        $this->version = '1.0.0';
        $this->author = 'Mafisz';
        $this->need_instance = 1;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Mafisz importer');
        $this->description = $this->l('Import towarów i kontenerów');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        $this->templateFile = 'module:mafisz_import/kontenery.tpl';
    }

    public function install()
    {
        Configuration::updateValue('MAFISZ_IMPORT_PRODUCT_PATH', null);
        Configuration::updateValue('MAFISZ_IMPORT_QUANTITY_PATH', null);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader');
    }

    public function uninstall()
    {
        Configuration::deleteByName('MAFISZ_IMPORT_PRODUCT_PATH');
        Configuration::deleteByName('MAFISZ_IMPORT_QUANTITY_PATH');

        include(dirname(__FILE__).'/sql/uninstall.php');

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
        if (((bool)Tools::isSubmit('submitMafisz_importModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
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
        $helper->submit_action = 'submitMafisz_importModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Towary'),
                        'class' => 'input fixed-width-sm',
                        'name' => 'MAFISZ_IMPORT_PRODUCT_PATH',
                        'desc' => $this->l('Ścieżka do pliku z produktami'),
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Stany'),
                        'class' => 'input fixed-width-sm',
                        'name' => 'MAFISZ_IMPORT_QUANTITY_PATH',
                        'desc' => $this->l('Ścieżka do pliku z ilościami'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        return $helper->generateForm(array($fields_form));
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $values = [];

        $default_config = array(
            'MAFISZ_IMPORT_PRODUCT_PATH' => Configuration::get('MAFISZ_IMPORT_PRODUCT_PATH', null),
            'MAFISZ_IMPORT_QUANTITY_PATH' => Configuration::get('MAFISZ_CAT_FOOTER_ID', null)
        );

        $values = array_merge($values, $default_config);

        return $values;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        Configuration::updateValue('MAFISZ_IMPORT_PRODUCT_PATH', Tools::getValue('MAFISZ_IMPORT_PRODUCT_PATH'));
        Configuration::updateValue('MAFISZ_IMPORT_QUANTITY_PATH', Tools::getValue('MAFISZ_IMPORT_QUANTITY_PATH'));
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            // $this->context->controller->addJS($this->_path.'views/js/back.js');
            // $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        // $this->context->controller->addJS($this->_path.'/views/js/front.js');
        // $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
        if (!$this->isCached($this->templateFile, $this->getCacheId('mafisz_import'))) {
            $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));
        }

        return $this->fetch($this->templateFile, $this->getCacheId('mafisz_import'));
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $lang = $this->context->language->id;
        $shop = $this->context->shop->id;

        $towary = Configuration::get('MAFISZ_IMPORT_PRODUCT_PATH', null);
        $stany = Configuration::get('MAFISZ_IMPORT_QUANTITY_PATH', null);
        // $cats = Category::getCategories($lang, true, true);

        // $i = 0;

        // foreach ($cats[$parent] as $key => $value) {
        //     if ($i++ < $count) {
        //         if ($value['infos']['active'] == 1) {
        //             $id = $value['infos']['id_category'];
        //             $category = new Category($id, $lang, $shop);
        //             $cat['name'] = $category->name;
        //             $cat['url'] = $this->context->link->getCategoryLink($category);
        //             $categories[] = $cat;
        //         }
        //     }
        // }

        return [
            // 'categories' => $categories,
        ];
    }
}
