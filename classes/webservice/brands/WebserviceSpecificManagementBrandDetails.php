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
class WebserviceSpecificManagementBrandDetails implements WebserviceSpecificManagementInterface
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
    $manufacturerId = (int) $this->wsObject->urlFragments['filter']['manufacturer_id'];
    $langId = (int)$this->wsObject->urlFragments['filter']['id_lang'];

    $sql = "
            SELECT
                m.name,
                im.image_url,
                ml.short_description,
                ml.description
            FROM
                " . _DB_PREFIX_ . "manufacturer m
            INNER JOIN " . _DB_PREFIX_ . "iagt_images_manufacture im ON m.id_manufacturer = im.id_manufacturer
            INNER JOIN " . _DB_PREFIX_ . "manufacturer_lang ml ON m.id_manufacturer = ml.id_manufacturer
            WHERE
                m.id_manufacturer = " . (int)$manufacturerId . "
                AND ml.id_lang = " . (int)$langId . ";
        ";

        // Ejecutar la consulta
        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if ($results) {
            $manufacturerDetails = [];
            foreach ($results as $result) {
                $manufacturerDetails[] = [
                    'name' => $result['name'],
                    'image_url' => $result['image_url'],
                    'short_description' => $result['short_description'],
                    'description' => $result['description']
                ];
            }
            // Convertir el array de resultados a formato JSON
            $this->output = json_encode($manufacturerDetails);
        } else {
            // Si no se encuentran resultados, devolver un error
            $this->output = json_encode(['error' => 'Manufacturer not found']);
        }
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
