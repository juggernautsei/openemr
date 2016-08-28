<html>
<header>
<link rel="stylesheet" href="/interface/themes/style_metal.css" type="text/css">
<styles>
</styles>
</header>
<body class="body_top">
</body>
</html>

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
	
include_once('../globals.php');
include_once('Service.php');
include_once('$srcdir/patient.inc');

include_once('transmit.inc.php');


set_time_limit(0);

$date = date("Y-m-d");
$pid = $GLOBALS['pid'];
$uid = $_SESSION['authUserID'];          //username of the person for this session


$send = getDrugList($pid, $date);

//Fetch provider and practice info
$providerInfo = getProviderFacility($uid);
	
   //This loop is to send all drugs marked for transmit
  
while($list = sqlFetchArray($send)){

    //for troubleshooting without the loop.
    //$list = sqlFetchArray($send);


	$patientInfo = getPatientData($pid);
	
	$pharmacyId = $list['pharmacy_id'];
	
/*********** Things to do. Add this to prescription class ****************************/
	$drugname = explode(" ", $list['drug']);
    $dea_sql = "SELECT csa_sch FROM `narcotics` WHERE `drug` LIKE '%".$drugname[0]."%' OR `other_names` LIKE '%".$drugname[0]."%'";
    $narc = sqlQuery($dea_sql);
    $sch = $narc['csa_sch'];
    
	if(empty($narc['csa_sch'])){
		$sch = "NONE";
	}
   	
    if(!empty($narc)){
		print $list['drug']. "<font color='red'> is a controlled substance and cannot be transmitted</font>";
		continue;
	}
 	
	$a_sql = "SELECT area_code, prefix, number FROM phone_numbers WHERE foreign_id = ".$pharmacyId;
	$a_sql = sqlQuery($a_sql);
	
	$p_sql = "SELECT name, ncpdp, npi FROM pharmacies WHERE id = ".$pharmacyId ;
	$p_res = sqlQuery($p_sql);
	
	$c_sql = "SELECT * FROM prescription_rx_log WHERE prescription_id = ".$list['id'];
    $check = sqlQuery($c_sql);
	
	if(!empty($check)){
		echo "Drug ".$list['drug']." for transmit have been transmitted today!<br>";
		continue;
	}
/*********************************Class needed above********************************************/

$ext1 = rand();
$ext3 = rand();
$ext2 = rand(1111,9999);

$messageId = $ext1.$ext2.$ext3."1";
//$messageId = "AugTest50";

$clinic = $GLOBALS['weno_provider_id'];
$prescriber_npi = $p_res['npi']; 
$qualifier = $GLOBALS['weno_facility_id'];
$delivery_id = 'All the way EMR Version 4.3.1';

//Patient Info
$lname = $patientInfo['lname'];
$fname = $patientInfo['fname'];
$street = $patientInfo['street'];
$city = $patientInfo['city'];
$postal = $patientInfo['postal_code'];
$DOB = $patientInfo['DOB'];
if($patientInfo['sex'] === "Male"){
	$sex = "M";
}
if($patientInfo['sex'] === "Female"){
	$sex = "F";
}

//Provider Practice Info
$provlname = $providerInfo['lname'];
$provfname = $providerInfo['fname'];
$facilityphone = $providerInfo['phone'];
$facilityfax = $providerInfo['fax'];
$facilityname = $providerInfo['name'];
$facilitystreet = $providerInfo['street'];
$facilitycity = $providerInfo['city'];
$facilitystate = $providerInfo['state'];
$facilityzip = $providerInfo['postal_code'];

//Pharmacy Info
$storename = $p_res['name'];
$storenpi = $p_res['npi'];
$pharmacy = $p_res['ncpdp'];
$pharmacyFax = $a_sql['area_code'].$a_sql['prefix'].$a_sql['number'];
 
 //Drug Info
$drug = $list['drug'];
$drug_NDC = $list['drug_id'];
$dateAdded = $list['date_added'];
$quantity = $list['quanity'];
$refills = $list['refills'];
$dateModified = $list['date_modified'];
$note = $list['note'];

if(empty($note)){
	$note = "N|A";
}
	
$username = $GLOBALS['weno_account_id'];
$partner_password = $GLOBALS['weno_account_pass'];


$ncpdp = new SoapBodyNCPDP();


$WenoMailNewRx = new WenoMail();

$WenoMailNewRx->WenoMailUUID = "";
$WenoMailNewRx->PharmacyFAXNumber = $pharmacyFax;


$transdet = new StandardsType();
$transdet->TransactionStandard = "15";
$transdet->Format = "1.2";
$transdet->Version = "010";
$transdet->Release = "006";
$transdet->WenoMailNewRx = $WenoMailNewRx;

$ncpdp->TransactionDetails = $transdet;

$ncpdp->EnvelopeReferenceID = "";

$ncpdp->Payload = base64_encode('<?xml version="1.0" encoding="utf-8"?>
<Message version="010" release="006" xmlns="https://wexlb.wenoexchange.com/schema/SCRIPT" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
<Header>
<To Qualifier="P">'.$pharmacy.'</To>
<From Qualifier="D">'.$qualifier.'</From>
<MessageID>'.$messageId.'</MessageID>
<SentTime>'.date("Y-m-d")."T".date("H:m:i").'</SentTime>
<Mailbox>
<DeliveredID>'.$delivery_id.'</DeliveredID>
</Mailbox>
</Header>
<Body>
<NewRx>
<Pharmacy>
<Identification>
<NCPDPID>'.$pharmacy.'</NCPDPID>
</Identification>
<StoreName>'.$storename.'</StoreName>
<CommunicationNumbers>
<Communication>
<Number>'.$pharmacyFax.'</Number>
<Qualifier>TE</Qualifier>
</Communication>
</CommunicationNumbers>
</Pharmacy>
<Prescriber>
<Identification>
<NPI>'.$storenpi.'</NPI>
</Identification>
<Name>
<LastName>'.$provlname.'</LastName>
<FirstName>'.$provfname.'</FirstName>
</Name>
<Address>
<AddressLine1>'.$facilitystreet.'</AddressLine1>
<City>'.$facilitycity.'</City>
<State>'.$facilitystate.'</State>
<ZipCode>'.$facilityzip.'</ZipCode>
</Address>
<CommunicationNumbers>
<Communication>
<Number>'.$facilityphone.'</Number>
<Qualifier>TE</Qualifier>
</Communication>
<Communication>
<Number>'.$facilityfax.'</Number>
<Qualifier>FX</Qualifier>
</Communication>
</CommunicationNumbers>
</Prescriber>
<Patient>
<Name>
<LastName>'.$lname.'</LastName>
<FirstName>'.$fname.'</FirstName>
</Name>
<Gender>'.$sex.'</Gender>
<DateOfBirth>
<Date>'.$DOB.'</Date>
</DateOfBirth>
<Address>
<AddressLine1>'.$street.'</AddressLine1>
<City>'.$city.'</City>
<State>TX</State>
</Address>
</Patient>
<MedicationPrescribed>
<DrugDescription>'.$drug.'</DrugDescription>
<DrugCoded>
<ProductCode>2989</ProductCode>
<ProductCodeQualifier>ND</ProductCodeQualifier>
<DrugDBCode>989892</DrugDBCode>
<DrugDBCodeQualifier>MD</DrugDBCodeQualifier>
<DEASchedule>'.$sch.'</DEASchedule>
</DrugCoded>
<Quantity>
<Value>'.$quantity.'</Value>
<CodeListQualifier>38</CodeListQualifier>
<UnitSourceCode>AC</UnitSourceCode>
<PotencyUnitCode>C38016</PotencyUnitCode>
</Quantity>
<Directions>'.$note.'</Directions>
<Note>PHARMACY MESSAGE:CASH CARD: BIN:011867 PCN:HT GROUP: BSURE13 ID:WENO</Note>
<Refills>
<Qualifier>R</Qualifier>
<Value>'.$refills.'</Value>
</Refills>
<WrittenDate>
<Date>'.$dateAdded.'</Date>
</WrittenDate>
<EffectiveDate>
<Date>'.$date.'</Date>
</EffectiveDate>
<DrugCoverageStatusCode>UN</DrugCoverageStatusCode>
</MedicationPrescribed>
</NewRx>
</Body>
</Message>');



$service = new Service($username,$partner_password);

$response = $service->RealTimeTransactionNCPDP($ncpdp);

$payload = $response->Payload;


echo "<br/>========================================<br/>";


echo "<pre>".base64_decode($payload)."</pre>";
$save = base64_decode($payload);

$xml = simplexml_load_string($save);
//var_dump($xml); 
$timeStamp = (string)$xml->Header->SentTime;
$weno = (string)$xml->Header->Mailbox->DeliveredID;
$code = (string)$xml->Body->Status->Code;
$status = (string)$xml->Body->Status->Description;
$time = explode("T", $timeStamp);
echo $list['drug'] . "<br> " . $weno . "<br> " . $time[0] . "<br> " . $time[1] . "<br> " . $code . "<br> " . $status;

	if(isset($status)){
		$sql = "INSERT INTO `prescription_rx_log` (`id`, `prescription_id`, `date`,`time`, `code`, `status`,`message_id` ) VALUES (NULL, ?, ?, ?, ?, ?, ?);";
		sqlStatement($sql, array($list['id'], $time[0], $time[1], $code, $status, $messageId));
    }
	
}


?>

