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
class WebserviceSpecificManagementBrandCarousel implements WebserviceSpecificManagementInterface
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
        $id_lang = (int) $this->wsObject->urlFragments['filter']['id_lang'];
    $db = \Db::getInstance();

    // Consulta para obtener los datos de la tabla personalizada
    $query = 'SELECT id, id_lang, id_brand_1, id_brand_2, id_brand_3, id_brand_4, id_brand_5, id_brand_6, id_brand_7, id_brand_8, id_brand_9, id_brand_10, title, button_text 
              FROM '. _DB_PREFIX_ .'iagthome_eleventhblock 
              WHERE id_lang = '. (int)$id_lang;

    $resultDb = $db->executeS($query);

    if (!empty($resultDb)) {
        $result = [];

        // Asignamos campos básicos
        $result['id'] = $resultDb[0]['id'];
        $result['id_lang'] = $resultDb[0]['id_lang'];
        $result['title'] = $resultDb[0]['title'];
        $result['button_text'] = $resultDb[0]['button_text'];

        // Creamos un array para almacenar las marcas y sus datos (nombre y URL del logo)
        $result['brands'] = [];

        // Recorremos las marcas (id_brand_1 hasta id_brand_10)
        for ($i = 1; $i <= 10; $i++) {
            $brandId = $resultDb[0]['id_brand_' . $i];

            if (!empty($brandId)) {
                // Consulta para obtener el nombre de la marca desde ps_manufacturer
               $query_brand = 'SELECT name FROM ' . _DB_PREFIX_ . 'manufacturer WHERE id_manufacturer = ' . (int)$brandId;
                $brandResult = $db->getRow($query_brand);
				$link = new Link();
				$slugName = str_replace(' ', '-', strtolower($brandResult['name']));
				$urlBrand = $link->getManufacturerLink($brandId, $slugName);
                if (!empty($brandResult)) {
                    // Construimos la URL de la imagen de la marca
					$baseUrl = 'https://' . Tools::getShopDomainSsl() . __PS_BASE_URI__;
                    $logoUrl = $baseUrl . '/img/m/' . (int)$brandId . '.jpg';
				
					

                    // Almacenamos el ID, nombre de la marca y su URL en el array 'brands'
                    $result['brands'][] = [
                        'id_brand' => $brandId,
                        'name' => $brandResult['name'],
                        'logo_url' => $logoUrl,
						'brand_url' => $urlBrand,
                    ];
                }
            }
        }
    }

    $this->output = json_encode($result);
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
