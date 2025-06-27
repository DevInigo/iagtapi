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
class WebserviceSpecificManagementBestSellers implements WebserviceSpecificManagementInterface
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
    $languageId = $this->wsObject->urlFragments['id_lang'] ?? Configuration::get('PS_LANG_DEFAULT');

    $db = \Db::getInstance();
    $query = "
                SELECT 
                    p.id_product, 
                    p.reference, 
                    pl.name, 
                    SUM(od.product_quantity) AS total_sales
                FROM 
                    " . _DB_PREFIX_ . "order_detail od
                JOIN 
                    " . _DB_PREFIX_ . "product p ON p.id_product = od.product_id
                JOIN 
                    " . _DB_PREFIX_ . "product_lang pl ON p.id_product = pl.id_product AND pl.id_lang = " . (int)$languageId . "
                JOIN 
                    " . _DB_PREFIX_ . "orders o ON o.id_order = od.id_order
                WHERE 
                    o.current_state IN (2, 3, 4) 
                GROUP BY 
                    p.id_product
                ORDER BY 
                    total_sales DESC ;
            ";
            $resultDb = $db->executeS($query);

            $this->output = json_encode($resultDb);
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
