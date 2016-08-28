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
 
require_once("interface/globals.php");
 
$base_url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME'];

if (!empty($_GET['site']))
    $site_id = $_GET['site'];
else if (is_dir("..".DIRECTORY_SEPARATOR."sites".DIRECTORY_SEPARATOR."" . $_SERVER['HTTP_HOST']))
    $site_id = $_SERVER['HTTP_HOST'];
else
    $site_id = 'default';

if (empty($site_id) || preg_match('/[^A-Za-z0-9\\-.]/', $site_id))
    die("Site ID '".htmlspecialchars($site_id,ENT_NOQUOTES)."' contains invalid characters.");

require_once "..".DIRECTORY_SEPARATOR."sites".DIRECTORY_SEPARATOR."$site_id".DIRECTORY_SEPARATOR."sqlconf.php";


require_once 'init_autoloader.php';

class WenoMailNewRX {
    /**
     * @var string
     */
  public $WenoMailUUID; // string
    /**
     * @var string
     */
  public $PharmacyFAXNumber; // string
}

class TransactionDetails
{
    /**
     * @var string
     */
    public $TransactionStandard;
    /**
     * @var string
     */
    public $Format;
    /**
     * @var string
     */
    public $Version;
    /**
     * @var string
     */
    public $Release;
    /**
     * @var WenoMailNewRX
     */
    public $WenoMailNewRX;


}

class SoapBodyNCPDP {
	/**
	 * @var TransactionDetails
	 */
	public $TransactionDetails;
	/**
	 * @var string
	 */
	public $EnvelopeReferenceID;
	/**
	 * @var string
	 */
	public $Payload;
}

class Security {
	
  public $UserName; // string
  public $Password; // string
}

class Logging
{
	
	/**
	 * A service to log the error messages
	 * @param SoapBodyNCPDP  $ncpdp
	 * @return SoapBodyNCPDP
	 */

    function LogError($ncpdp){
		
		global $sqlconf,$soap;
		
		$TransactionDetails = $ncpdp->TransactionDetails;
		$EnvelopeReferenceID = $ncpdp->EnvelopeReferenceID;
		
		$Payload = $ncpdp->Payload;
		
		$xmlobj=simplexml_load_string(base64_decode($Payload)) or die("Error: Cannot create object");
		$message_id = (string)$xmlobj->Header->RelatesToMessageID;
		$prescription_id =  (string)$xmlobj->Header->MessageID;
		$senttime = (string)$xmlobj->Header->SentTime;
		$from = (string)$xmlobj->Header->From;
		$to = (string)$xmlobj->Header->To;
		$code = (string)$xmlobj->Body->Error->Code;
		$description = (string)$xmlobj->Body->Error->Description;
		
		$standard = (string)$TransactionDetails->TransactionStandard;
		$version = (string)$TransactionDetails->Version;
		$release = (string)$TransactionDetails->Release;
		$format  = (string)$TransactionDetails->Format;
		

		$pieces = explode("T",$senttime);
		
		$date = $pieces[0];
		$time = "";
		if(count($pieces)>0)
			$time = $pieces[1];

		$con=mysqli_connect($sqlconf["host"],$sqlconf["login"],$sqlconf["pass"],$sqlconf["dbase"]);
		// Check connection
		if (mysqli_connect_errno()){
		  return "Failed to connect to MySQL: " . mysqli_connect_error();
		}

		mysqli_query($con,"INSERT INTO prescription_rx_log (`prescription_id`,`date`,`time`,`code`,`status`,`message_id`) 
		VALUES ('$prescription_id','$date','$time','$code','$description','$message_id')");
        	
		mysqli_close($con);
		
        //enter pNote

        $sql = "SELECT prescription_rx_log.prescription_id, prescription_rx_log.code, prescription_rx_log.status, prescriptions.patient_id, patient_data.lname, patient_data.fname 
                FROM `prescription_rx_log`  
                LEFT JOIN
                prescriptions ON prescriptions.id = prescription_rx_log.prescription_id 
                LEFT JOIN
                patient_data ON prescriptions.patient_id = patient_data.pid
                WHERE 
                prescriptions.id = prescription_rx_log.prescription_id
                ORDER BY prescription_rx_log.prescription_id DESC LIMIT 1";
				
		$pNotes = sqlQuery($sql);

         sqlStatement("INSERT INTO pnotes SET ". 
		              "date = '$date', ".
					  "body = '$description', ".
					  "pid = '".$pNotes['patient_id']."', ".
					  "autorized = 1, ".
					  "assigned_to = 'Thompson', ".
					  "title = 'eRx Transmit Error', ".
					  "activity = 1, 
					  ");			
				
		$WenoMailNewRX = new WenoMailNewRX();

		$WenoMailNewRX->WenoMailUUID = (string)$TransactionDetails->WenoMailNewRX->WenoMailUUID;
		$WenoMailNewRX->PharmacyFAXNumber = (string)$TransactionDetails->WenoMailNewRX->PharmacyFAXNumber;


		$transdet = new TransactionDetails();
		$transdet->TransactionStandard = $standard;
		$transdet->Format = $format;
		$transdet->Version = $version;
		$transdet->Release = $release;
		$transdet->WenoMailNewRX = $WenoMailNewRX;
		
		$username = '106';
		$password = 'B98A9CA20E82C8595183363345DDCF4B2E9670B1';
		
		$response = new SoapBodyNCPDP();
		$response->TransactionDetails = $transdet;
		$response->EnvelopeReferenceID = $EnvelopeReferenceID;
		
		//$payload = "<Message version=\"$version\" release=\"$release\" xmlns=\"https://wexlb.wenoexchange.com/schema/SCRIPT\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"><Header><To Qualifier=\"M\">Unknown due to rejected transaction</To><From Qualifier=\"M\">WEX</From><MessageID>0</MessageID><RelatesToMessageID>0</RelatesToMessageID><SentTime>2016-08-13T09:36:14.45565Z</SentTime><Mailbox><DeliveredID>Weno Exchange LLC</DeliveredID></Mailbox></Header><Body><Error><Code>900</Code><Description>Transaction rejected - invalid message</Description></Error></Body></Message>";
		
		$new_messageid = "ATW".(1000*time());
		$payload = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<Message version=\"$version\" release=\"$release\" xmlns=\"https://wexlb.wenoexchange.com/schema/SCRIPT\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
    <Header>
        <To Qualifier=\"P\">$from</To>
        <From Qualifier=\"D\">$to</From>
        <MessageID>$new_messageid</MessageID>
		<RelatesToMessageID>$prescription_id</RelatesToMessageID>
        <SentTime>$senttime</SentTime>
        <Mailbox>
            <DeliveredID>Sample Software Product Version 3</DeliveredID>
        </Mailbox>
    </Header>
    <Body>
        <Verify>
			<Code>000</Code>
			<Description>Success</Description>
		</Verify>
    </Body>
</Message>
";
		$response->Payload = base64_encode($payload);
		
		$security = new Security();
		$security->UserName = $username;
		$security->Password = $password;

		$header= new SoapHeader('http://schemas.xmlsoap.org/soap/envelope/', 
								'Security',
								$security);
								
		$soap->addSoapHeader($header);

		
		return $response;
	}
}



if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (! isset($_GET['wsdl'])) {
        header('HTTP/1.1 400 Client Error');
        return;
    }

    $autodiscover = new Zend\Soap\AutoDiscover();
    $autodiscover->setClass('Logging')
        ->setUri($base_url);
    header('Content-Type: text/xml; charset=utf-8');
	
	echo str_replace('http://localhost/sherwingaddis1/openemr-master/hservice/server.php',$base_url,file_get_contents("servicewsdl.xml"));
	return;

}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('HTTP/1.1 400 Client Error');
    return;
}



// pointing to the current file here
$soap = new SoapServer("$base_url?wsdl");
$soap->setClass('Logging');
$soap->handle();





?>