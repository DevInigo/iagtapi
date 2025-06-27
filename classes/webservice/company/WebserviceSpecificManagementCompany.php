<?php
/**
 * 2007-2018 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
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
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
class WebserviceSpecificManagementCompany implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;
    protected $output;

    /** @var WebserviceRequest */
    protected $wsObject;

    /* ------------------------------------------------
     * GETTERS & SETTERS
     * ------------------------------------------------ */

    /**
     * @param WebserviceOutputBuilderCore $obj
     *
     * @return WebserviceSpecificManagementInterface
     */
    public function setObjectOutput(WebserviceOutputBuilderCore $obj)
    {
        $this->objOutput = $obj;

        return $this;
    }

    public function setWsObject(WebserviceRequestCore $obj)
    {
        $this->wsObject = $obj;

        return $this;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function setUrlSegment($segments)
    {
        $this->urlSegment = $segments;

        return $this;
    }

    public function getUrlSegment()
    {
        return $this->urlSegment;
    }

    /**
     * Management of search.
     */
public function manage()
    {
        // Consulta de configuración
        $sqlConfig = "
            SELECT
                name, value
            FROM
                " . _DB_PREFIX_ . "configuration
            WHERE name IN (
                'iqitcontactp_company',
                'iqitcontactp_address',
                'iqitcontactp_latitude',
                'iqitcontactp_longitude',
                'iqitcontactp_show_map',
                'iqitcontactp_phone',
                'iqitcontactp_mail',
                'iqitcontactp_content'
            )
        ";

        // Ejecutar la consulta de configuración
        $resultsConfig = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sqlConfig);

        // Mapeo de claves originales a nuevas claves más legibles
        $keyMapping = array(
            'iqitcontactp_company' => 'company',
            'iqitcontactp_address' => 'address',
            'iqitcontactp_latitude' => 'latitude',
            'iqitcontactp_longitude' => 'longitude',
            'iqitcontactp_show_map' => 'show_map',
            'iqitcontactp_phone' => 'phone',
            'iqitcontactp_mail' => 'mail',
            'iqitcontactp_content' => 'content'
        );

        // Convertir los resultados de configuración a un array asociativo con las nuevas claves
        $configurations = array();
        foreach ($resultsConfig as $result) {
            if (isset($keyMapping[$result['name']])) {
                $configurations[$keyMapping[$result['name']]] = $result['value'];
            }
        }

        // Consulta para obtener los meta_title y id_cms de ps_cms_lang
        $sqlCms = "
            SELECT id_cms, meta_title
            FROM " . _DB_PREFIX_ . "cms_lang
            WHERE id_lang = 1 AND id_shop = 1 AND id_cms IN (1, 2, 7, 9, 14)
        ";

        // Ejecutar la consulta de CMS
        $resultsCms = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sqlCms);

        // Agregar los resultados de CMS al array de configuración con URLs
        $configurations['cms'] = array();
        $shopUrl = Tools::getShopDomainSsl() . __PS_BASE_URI__; // Dominio y base URI

        foreach ($resultsCms as $result) {
            $cmsUrl = $shopUrl . 'content/' . $result['id_cms'] . '-' . Tools::str2url($result['meta_title']) . '.html';
            $configurations['cms'][] = array(
                'meta_title' => $result['meta_title'],
                'url' => $cmsUrl
            );
        }

        // Añadir la URL de recuperación de contraseña
        $configurations['reset_password_url'] = $shopUrl . 'en/recuperaci%C3%B3%20de%20contrasenya';

        $this->output = json_encode($configurations);
    }

    /**
     * This must be return a string with specific values as WebserviceRequest expects.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->output;
    }

}
