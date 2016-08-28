<?php

/** Copyright (C) 2016 Sherwin Gaddis <sherwingaddis@gmail.com>
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://opensource.org/licenses/gpl-license.php>;.
 *
 * @package OpenEMR
 * Sherwin Gaddis <sherwingaddis@gmail.com>
 * @link    http://www.open-emr.org
 */
 
class SoapBodyNCPDP {
  public $TransactionDetails; // StandardsType
  public $EnvelopeReferenceID; // string
  public $Payload; // string
}

class StandardsType {
  public $TransactionStandard; // string
  public $Format; // string
  public $Version; // string
  public $Release; // string
  public $WenoMailNewRx; // WenoMail
}

class WenoMail {
  public $WenoMailUUID; // string
  public $PharmacyFAXNumber; // string
}

class Security {
  public $UserName; // string
  public $Password; // string
}

class SoapBody {
  public $Payload; // string
}

class SoapBodyX12 {
  public $TransactionDetails; // StandardsType
  public $EnvelopeReferenceID; // string
  public $Payload; // string
}

class SoapBodyBSureReport {
  public $ZipFileName; // string
  public $ZipBytes; // base64Binary
  public $Payload; // string
}


/**
 * Service class
 * 
 *  
 * 
 * @author    {author}
 * @copyright {copyright}
 * @package   {package}
 */
class Service extends SoapClient {

  private static $classmap = array(
                                    'SoapBodyNCPDP' => 'SoapBodyNCPDP',
                                    'StandardsType' => 'StandardsType',
                                    'WenoMail' => 'WenoMail',
                                    'Security' => 'Security',
                                    'SoapBody' => 'SoapBody',
                                    'SoapBodyX12' => 'SoapBodyX12',
                                    'SoapBodyBSureReport' => 'SoapBodyBSureReport',
                                   );

  public function Service($username,$password) {
    foreach(self::$classmap as $key => $value) {
      if(!isset($options['classmap'][$key])) {
        $options['classmap'][$key] = $value;
      }
    }
    parent::__construct("https://wexlb.wenoexchange.com/wenox/service.asmx?WSDL", array('trace' => 1));
	
	$headers = array();
	
	$security = new Security();
	$security->UserName = $username;
	$security->Password = $password;
	
	$headers[] = new SoapHeader('http://schemas.xmlsoap.org/soap/envelope/', 
                            'Security',
                            $security);


	$this->__setSoapHeaders($headers);

  }

  /**
   *  
   *
   * @param SoapBodyNCPDP $NCPDPRequest
   * @return SoapBodyNCPDP
   */
  public function RealTimeTransactionNCPDP(SoapBodyNCPDP $NCPDPRequest) {
    $resp = $this->__soapCall('RealTimeTransactionNCPDP', array($NCPDPRequest),       array(
            'uri' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'soapaction' => ''
           )
      );
	  
	//echo "REQUEST:\n" . $this->__getLastRequest() . "\n";
	//echo "Response:\n" . $this->__getLastResponse() . "\n";
	
	return $resp;
  }

  /**
   *  
   *
   * @param SoapBody $WEXManageRequest
   * @return SoapBody
   */
  public function RealTimeTransactionWEXManage(SoapBody $WEXManageRequest) {
    return $this->__soapCall('RealTimeTransactionWEXManage', array($WEXManageRequest),       array(
            'uri' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'soapaction' => ''
           )
      );
  }

  /**
   *  
   *
   * @param SoapBodyX12 $X12Request
   * @return SoapBodyX12
   */
  public function RealTimeTransactionX12(SoapBodyX12 $X12Request) {
    return $this->__soapCall('RealTimeTransactionX12', array($X12Request),       array(
            'uri' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'soapaction' => ''
           )
      );
  }

  /**
   *  
   *
   * @param SoapBody $WEXFormularyRequest
   * @return SoapBody
   */
  public function RealTimeTransactionFormularyUpload(SoapBody $WEXFormularyRequest) {
    return $this->__soapCall('RealTimeTransactionFormularyUpload', array($WEXFormularyRequest),       array(
            'uri' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'soapaction' => ''
           )
      );
  }

  /**
   *  
   *
   * @param SoapBodyBSureReport $WEXBSureReport
   * @return SoapBody
   */
  public function BSureReportUpload(SoapBodyBSureReport $WEXBSureReport) {
    return $this->__soapCall('BSureReportUpload', array($WEXBSureReport),       array(
            'uri' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'soapaction' => ''
           )
      );
  }

}

?>
