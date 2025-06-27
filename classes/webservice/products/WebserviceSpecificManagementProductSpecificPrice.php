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
class WebserviceSpecificManagementProductSpecificPrice implements WebserviceSpecificManagementInterface
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
     $productId = (int)$this->wsObject->urlFragments['filter']['id_product'];

        // Obtener los specific prices del producto
        $specificPrices = SpecificPrice::getByProductId($productId);
        $response = ["specific_prices" => []];

        foreach ($specificPrices as $specificPrice) {
            $priceWithTax = $specificPrice['price'];
            
            // Calcular el precio con IVA
            if ($priceWithTax != -1) {
                $taxRate = 21; // Reemplazar con la tasa de impuesto adecuada para este producto
                $priceWithTax = $priceWithTax * (1 + ($taxRate / 100));
            }

            // Construir el formato esperado
            $response["specific_prices"][] = [
                'id_country' => $specificPrice['id_country'],
                'price' => round($priceWithTax, 6),
                'from_quantity' => $specificPrice['from_quantity'],
                'reduction' => $specificPrice['reduction']
            ];
        }

        $this->output = json_encode($response);
    
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
