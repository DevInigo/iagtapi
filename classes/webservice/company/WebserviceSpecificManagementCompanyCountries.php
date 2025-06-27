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
class WebserviceSpecificManagementCompanyCountries implements WebserviceSpecificManagementInterface
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
        // Obtener el idioma actual o el predeterminado de la tienda
        $languageId = $this->wsObject->urlFragments['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');
    
        // Consulta para obtener la información de los países activos y su nombre en el idioma actual
        $sqlCountry = "
            SELECT
                c.id_country, c.id_zone, c.iso_code, c.active, cl.name
            FROM
                " . _DB_PREFIX_ . "country c
            INNER JOIN
                " . _DB_PREFIX_ . "country_lang cl ON c.id_country = cl.id_country
            WHERE
                c.active = 1
            AND
                cl.id_lang = " . (int)$languageId;
    
        // Ejecutar la consulta
        $resultsCountry = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sqlCountry);
    
        // Inicializar un array para almacenar los países
        $countries = [];
    
        // Convertir los resultados a un array asociativo con las claves necesarias
        foreach ($resultsCountry as $result) {
            $countries[] = [
                'id_country' => $result['id_country'],
                'id_zone' => $result['id_zone'],
                'iso_code' => $result['iso_code'],
                'name' => $result['name'], // Nombre del país en el idioma actual
                'active' => $result['active']
            ];
        }
    
        // Establecer la salida en formato JSON
        $this->output = json_encode($countries);
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
