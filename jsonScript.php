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
 
 /*
 * The purpose of this is to on the fly output the json code needed to transmit the prescriptions individually
 */



$date = date("Y-m-d");
$pid = $GLOBALS['pid'];
$uid = $_SESSION['authUserID'];

$i = rand();
$fillData = filter_input(INPUT_GET, "getJson");


$fill = explode(",", $fillData);

$id = $fill[0]; //setting the pharmacy ID for later

array_shift($fill);  //removing the pharmacy from the array

//created a loop in case
foreach($fill as $data){
$pInfo = getPatientData($pid);


$fname      = $pInfo['fname'];
$lname      = $pInfo['lname'];
$street     = $pInfo['street'];
$postalCode = $pInfo['postal_code'];
$city       = $pInfo['city'];
$DOB        = $pInfo['DOB'];
$sex        = $pInfo['sex'];

function fillJsonPatient($fname,$lname,$street,$postalCode,$city,$DOB,$sex){

    if($sex == "Male"){ $sex = "M";}
	if($sex == "Female"){$sex = "F";} //has to be a single letter
	
$patient ='[{"patient": {"lname" : "'.$lname.'","fname" : "'.$fname.'","street" : "'.$street.'","city" : "'.$city.'","postal" : "'.$postalCode.'","DOB" : "'.$DOB.'","Sex" : "'.$sex.'"}},';
	return $patient;
}

$prInfo = new transmitData();
$proData = $prInfo->getProviderFacility($uid);

    $fname      = $proData[1]['fname'];
    $lname      = $proData[1]['lname'];
    $npi        = $proData[1]['npi'];
    $wenoProvId = $proData[1]['weno_prov_id'];
    $name       = $proData[1]['name'];
    $phone      = $proData[1]['phone'];
    $fax        = $proData[1]['fax'];
    $street     = $proData[1]['street'];
    $city       = $proData[1]['city'];
    $state      = $proData[1]['state'];
    $postalCode = $proData[1]['postal_code'];
    $wenoAccId  = $proData[0][0]['gl_value'];
    $wenoPass   = $proData[0][1]['gl_value'];

function fillJsonProvider($fname,$lname,$npi,$wenoProvId,$name,$phone,$fax,$street,$city,$state,$postalCode,$wenoAccId,$wenoPass){
	
 $provider ='{"provider": {"provlname" : "'.$fname.'","provfname" : "'.$lname.'","provnpi" : '.$npi.',"facilityfax" : '.$fax.',"facilityphone" : '.$phone.',"facilityname" : "'.$name.'","facilitystreet" : "'.$street.'","facilitycity" : "'.$city.'","facilitystate" : "'.$state.'","facilityzip" : '.$postalCode.',"qualifier" : "'.$wenoProvId.'","wenoAccountId" : "'.$wenoAccId.'","wenoAccountPass" : "'.$wenoPass.'","wenoClinicId" : "'.$wenoProvId.'"}},';
	return $provider; 
}

$pharmData = $prInfo->findPharmacy($id);

$storename     = $pharmData['store_name'];
$storenpi      = $pharmData['NPI'];
$pharmacy      = $pharmData['NCPDP'];
$pharmacyPhone = $pharmData['Pharmacy_Phone'];
$pharmacyFax   = $pharmData['Pharmacy_Fax'];

function fillJsonPharmacy($storename,$storenpi,$pharmacy,$pharmacyPhone,$pharmacyFax){
	
$pharmacy ='{"pharmacy": {"storename" : "'.$storename.'","storenpi" : '.$storenpi.',"pharmacy" : '.$pharmacy.',"pharmacyPhone" : '.$pharmacyPhone.',"pharmacyFax" : '.$pharmacyFax.'}},';
	return $pharmacy;
}	

$drugData = $prInfo->oneDrug($data);

    $drugName     = $drugData['drug'];
    $drugNDC      = $drugData['drug_id'];
    $dateAdded    = $drugData['date_Added'];
    $quantity     = $drugData['quantity'];
    $refills      = $drugData['refills'];
    $dateModified = $drugData['date_Modified'];
    $note         = $drugData['note'];
    $take         = $drugData['dosage'];

function fillJsonScript($drugName,$drugNDC,$dateAdded,$quantity,$refills,$dateModified,$note,$take){
$script ='{"script": {"drugName" : "'.$drugName.'","drug_NDC" : "'.$drugNDC.'","dateAdded" : "'.$dateAdded.'","quantity" : "'.$quantity.'","refills" : "'.$refills.'","dateModified" : "'.$dateModified.'","note" : "'.$note.'","take" : "'.$take.'"}}]';
		
		return $script;
}

$completeJson =  fillJsonPatient($fname,$lname,$street,$postalCode,$city,$DOB,$sex) . 
                 fillJsonProvider($fname,$lname,$npi,$wenoProvId,$name,$phone,$fax,$street,$city,$state,$postalCode,$wenoAccId,$wenoPass) . 
				 fillJsonPharmacy($storename,$storenpi,$pharmacy,$pharmacyPhone,$pharmacyFax) . 
				 fillJsonScript($drugName,$drugNDC,$dateAdded,$quantity,$refills,$dateModified,$note,$take);


//file_put_contents($i."jsonScript.txt", $completeJson); //leave this for troubleshooting
echo $completeJson;
} //end of foreach loop!!!