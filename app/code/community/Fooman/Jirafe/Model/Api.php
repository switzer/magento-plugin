<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @package     Fooman_Jirafe
 * @copyright   Copyright (c) 2010 Jirafe Inc (http://www.jirafe.com)
 * @copyright   Copyright (c) 2010 Fooman Limited (http://www.fooman.co.nz)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fooman_Jirafe_Model_Api
{
    const JIRAFE_HB_URL =  'https://api.jirafe.com/v1/heartbeat';

    const JIRAFE_API_URL = 'https://api.jirafe.com/';
    const JIRAFE_API_VERSION = 'v1';
    const JIRAFE_API_APPLICATIONS = '/applications';
    const JIRAFE_API_SITES = '/sites';
    const JIRAFE_API_USERS = '/users';

    public function sendData($data, $method = Zend_Http_Client::POST)
    {
        //set up connection
        $conn = new Zend_Http_Client(self::JIRAFE_HB_URL);
		$conn->setConfig(array('timeout'=>30, 'keepalive'=>true));
		
        try {
            if (is_array($data) && $method == Zend_Http_Client::POST) {
                foreach($data as $parameter=>$value) {
                    $conn->setParameterPost($parameter,$value);
                }
            }
            $result = $conn->request($method);
			
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    public function transmit ($entryPoint, $data, $method = Zend_Http_Client::POST)
    {

        //set up connection
        $conn = new Zend_Http_Client(self::JIRAFE_API_URL);
        $conn->setConfig(array(
            'timeout' => 30,
            'keepalive' => true
        ));
        $conn->setUri(self::JIRAFE_API_URL.self::JIRAFE_API_VERSION.$entryPoint);

        try {
            //connect and send data to Jirafe
            //$result = $conn->setRawData(json_encode($data), 'application/json')->request($method);
            //Mage::log($data);
            if (is_array($data) && ($method == Zend_Http_Client::POST || $method == Zend_Http_Client::PUT)) {
                foreach($data as $parameter=>$value) {
                    $conn->setParameterPost($parameter,$value);
                }
            }
            $result = $conn->request($method);
            //Mage::log($result);
            //Mage::log($conn->getLastRequest());
        } catch (Exception $e) {
            return $e->getMessage();
        }
        $this->_errorChecking($result);
        return $result->getBody();
    }

    private function _errorChecking($result) {

    }

}