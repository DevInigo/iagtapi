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
use GeoIp2\Database\Reader;
class WebserviceSpecificManagementFreeShipping implements WebserviceSpecificManagementInterface
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
        // Solo manejar GET ya que es un servicio genérico
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->handleGetRequest();
        } else {
            $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }

 private function handleGetRequest()
    {
        // Obtener el país geolocalizado
        $country = $this->getGeolocatedCountry();

        if (!$country) {
            $this->sendResponse(['error' => 'Unable to determine country by IP'], 404);
            return;
        }

        // Obtener el código ISO del país
        $isoCode = $country->iso_code;

        // Obtener el amountfree desde la tabla iagtgeolocation según el código ISO
        $sql = 'SELECT amountfree FROM ' . _DB_PREFIX_ . 'iagtgeolocation WHERE iso_code = \'' . pSQL($isoCode) . '\'';
        $amountFree = Db::getInstance()->getValue($sql);

        if ($amountFree === false) {
            $this->sendResponse(['error' => 'No free shipping amount found for country', 'iso_code' => $isoCode], 404);
            return;
        }

        // Respuesta exitosa
        $this->sendResponse([
            'iso_code' => $isoCode,
            'amount_free' => $amountFree,
        ]);
    }

    private function getGeolocatedCountry()
    {
        $defaultCountryId = (int)Configuration::get('PS_COUNTRY_DEFAULT');
        $defaultCountry = new Country($defaultCountryId);

        // Obtener la IP del cliente
        $ip = Tools::getRemoteAddr();

        // Ruta al archivo GeoLite2-City.mmdb
        $geoLiteDatabasePath = _PS_ROOT_DIR_ . '/app/Resources/geoip/GeoLite2-City.mmdb';

        // Verificar si la clase Reader de GeoIp2 está disponible
        if (class_exists('GeoIp2\Database\Reader') && file_exists($geoLiteDatabasePath)) {
            try {
                // Crear un lector para la base de datos GeoLite2
                $reader = new Reader($geoLiteDatabasePath);
                
                // Obtener los datos de geolocalización para la IP
                $record = $reader->city($ip);
                $countryIso = $record->country->isoCode;
                if ($countryIso) {
                    $countryId = (int)Country::getByIso($countryIso);
                    if ($countryId) {
                        return new Country($countryId);
                    }
                }
            } catch (Exception $e) {
                // Manejar errores en caso de que la geolocalización falle
                $this->sendResponse(['error' => 'GeoIP2 error: ' . $e->getMessage()], 500);
                return null;
            }
        } else {
            $this->sendResponse(['error' => 'GeoIP2 database not found or Reader class not available'], 500);
            return null;
        }

        // Si no se puede determinar el país, devolver el país predeterminado de la tienda
        return $defaultCountry;
    }

    private function sendResponse($response, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($response);
        exit();
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
