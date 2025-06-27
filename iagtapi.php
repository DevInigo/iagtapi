<?php
/**
* 2007-2020 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/orders/WebserviceSpecificManagementDetailedOrders.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/orders/WebserviceSpecificManagementOrders.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/orders/WebserviceSpecificManagementCheckout.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/products/WebserviceSpecificManagementProductDetails.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/products/WebserviceSpecificManagementBestSellers.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/products/WebserviceSpecificManagementProductCarrusel.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/products/WebserviceSpecificManagementProductCritic.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/products/WebserviceSpecificManagementProductList.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/products/WebserviceSpecificManagementProductSpecificPrice.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/products/WebserviceSpecificManagementSensorial.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/products/WebserviceSpecificManagementRelatedProducts.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/brands/WebserviceSpecificManagementBrandDetails.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/home/WebserviceSpecificManagementHomeSlider.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/home/WebserviceSpecificManagementBrandCarousel.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/auth/WebserviceSpecificManagementAuth.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/auth/WebserviceSpecificManagementLogin.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/customer/WebserviceSpecificManagementCustomerAddresses.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/customer/WebserviceSpecificManagementWishlist.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/customer/WebserviceSpecificManagementCart.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/company/WebserviceSpecificManagementCompany.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/company/WebserviceSpecificManagementCompanyCountries.php');
include_once(_PS_MODULE_DIR_.'iagtapi/classes/webservice/shipping/WebserviceSpecificManagementFreeShipping.php');

use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class Iagtapi extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'iagtapi';
        $this->tab = 'administration';
        $this->version = '1.7.1';
        $this->author = 'Iagt';
        $this->need_instance = 0;
        $this->isPS16 = stripos(_PS_VERSION_,'1.6')!==false;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Iagt Custom API module');
        $this->description = $this->l('Enhance the prestashop REST API to add more endpoints');

        $this->confirmUninstall = $this->l('');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        if(!$this->isPS16 && !$this->isRegisteredInHook('addWebserviceResources')) $this->registerHook('addWebserviceResources');
    }

    public function install()
    {
        if (!parent::install() || 
            !Configuration::updateValue('IAGTAPI_LIVE_MODE', false) ||
            !$this->registerHook('header') ||
            !$this->registerHook('backOfficeHeader') ||
            !$this->registerHook('actionOrderGridDefinitionModifier') ||
            !$this->registerHook('actionOrderGridQueryBuilderModifier')
        ) {
            return false;
        }

        // Ejecutar la consulta SQL para agregar el campo 'source' si no existe
        $sql = "ALTER TABLE " . _DB_PREFIX_ . "orders ADD COLUMN IF NOT EXISTS `source` ENUM('', 'App') DEFAULT ''";
        Db::getInstance()->execute($sql);

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('IAGTAPI_LIVE_MODE');
        
        // Eliminar la columna 'source' de la tabla 'orders'
        $sql = "ALTER TABLE " . _DB_PREFIX_ . "orders DROP COLUMN IF EXISTS `source`";
        Db::getInstance()->execute($sql);
        
        return parent::uninstall();
    }

    /**
     * Ejecutar un archivo PHP para instalar tablas u otros datos
     */
    private function executePhpInstall($phpFile)
	{
		$filePath = dirname(__FILE__) . '/sql/' . $phpFile;

		if (file_exists($filePath)) {
			return require_once($filePath);
		}

		return false;
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
        $helper->submit_action = 'submitIagtapiModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
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
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'IAGTAPI_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'IAGTAPI_ACCOUNT_EMAIL',
                        'label' => $this->l('Email'),
                    ),
                    array(
                        'type' => 'password',
                        'name' => 'IAGTAPI_ACCOUNT_PASSWORD',
                        'label' => $this->l('Password'),
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
            'IAGTAPI_LIVE_MODE' => Configuration::get('IAGTAPI_LIVE_MODE', true),
            'IAGTAPI_ACCOUNT_EMAIL' => Configuration::get('IAGTAPI_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'IAGTAPI_ACCOUNT_PASSWORD' => Configuration::get('IAGTAPI_ACCOUNT_PASSWORD', null),
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
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * Add source field in Summary of hookDisplayAdminOrder
     * @param mixed $params
     * @return string
     */
    public function hookActionOrderGridDefinitionModifier(array $params)
	{
		$gridDefinition = $params['definition'];

		// Añadir la columna 'source' después de 'customer'
		$gridDefinition->getColumns()->addAfter(
			'customer',
			(new DataColumn('source'))
				->setName($this->trans('Origen', [], 'Admin.Orderscustomers.Feature'))
				->setOptions([
					'field' => 'source',
				])
		);

		// Agregar el filtro para la columna `source` como un desplegable
    $gridDefinition->getFilters()->add(
        (new PrestaShop\PrestaShop\Core\Grid\Filter\Filter('source', ChoiceType::class))
            ->setAssociatedColumn('source')
            ->setTypeOptions([
                'choices' => [
                    $this->trans('All', [], 'Admin.Global') => '',
                    $this->trans('App', [], 'Admin.Global') => 'App',
                    $this->trans('', [], 'Admin.Global') => '', // Puedes agregar más opciones si es necesario
                ],
                'required' => false,
                'expanded' => false, // Para mostrar como un desplegable
                'multiple' => false,
            ])
    );
	}

    public function hookActionOrderGridQueryBuilderModifier(array $params)
	{
		/** @var Doctrine\DBAL\Query\QueryBuilder $searchQueryBuilder */
		$searchQueryBuilder = $params['search_query_builder'];

		// Añadir `source` a la consulta para que esté disponible en la cuadrícula
		$searchQueryBuilder->addSelect('o.source');
	}

    public function hookAddWebserviceResources() {
        return array(
            'detailedorders' => array(
                'description' => 'Detailed Orders API',
                'specific_management' => true,
            ),
            'productdetails' => array(
                'description' => 'Product Details API',
                'specific_management' => true,
            ),
            'auth' => array (
                'description' => 'Customer Auth API',
                'specific_management' => true,
            ),
            'login' => array (
                'description' => 'Customer Login API',
                'specific_management' => true,
            ),
            'productlist' => array (
                'description' => 'Product List API',
                'specific_management' => true,
            ),
            'productcarrusel' => array (
                'description' => 'Product Carrusel Images API',
                'specific_management' => true,
            ),
            'productcritic' => array (
                'description' => 'Product Critic opinion API',
                'specific_management' => true,
            ),
            'branddetails' => array (
                'description' => 'Brand Details API',
                'specific_management' => true,
            ),
            'sensorial' => array (
                'description' => 'Sensorial Experience API',
                'specific_management' => true,
            ),
            'relatedproducts' => array (
                'description' => 'Related Products API',
                'specific_management' => true,
            ),
            'homeslider' => array (
                'description' => 'Home Slider API',
                'specific_management' => true,
            ),
            'brandcarousel' => array (
                'description' => 'Home Carousel Brands API',
                'specific_management' => true,
            ),
            'wishlist' => array (
                'description' => 'Customer Wishlist API',
                'specific_management' => true,
            ),
            'cart' => array (
                'description' => 'Add Product Cart API',
                'specific_management' => true,
            ),
            'company' => array (
                'description' => 'Company Info API',
                'specific_management' => true,
            ),
			'customeraddresses' => array (
                'description' => 'Addresses Customer Info API',
                'specific_management' => true,
            ),
			'orders' => array (
                'description' => 'Orders Customer Info API',
                'specific_management' => true,
            ),
            'checkout' => array (
                'description' => 'CheckOut API',
                'specific_management' => true,
            ),
            'freeshipping' => array (
                'description' => 'Free Shipping Info API',
                'specific_management' => true,
            ),
            'companycountries' => array (
                'description' => 'Countries Info API',
                'specific_management' => true,
            ),
            'bestsellers' => array (
                'description' => 'Best Sellers Info API',
                'specific_management' => true,
            ),
			'productspecificprice' => array (
                'description' => 'Product Specific Price Info API',
                'specific_management' => true,
            ),
        );
    }
}
