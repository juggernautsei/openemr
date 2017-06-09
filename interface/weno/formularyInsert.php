<?php
/**
 * weno rx formulary update.
 *
 * @package OpenEMR
 * @link    http://www.open-emr.org
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2016-2017 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

 require_once("../globals.php");
 
 echo xlt("Trying")."<BR>";
/* 
$username = $GLOBALS['weno_account_id'];
$partner_password = $GLOBALS['weno_account_pass'];
$url = "https://live.wenoexchange.com/wenox/GetListResponse.aspx?fr=1&fd=1";

$ch = curl_init();
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 3);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_POST, FALSE);

//set the URL to the protected file
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//set Headers
$headers = array();
$headers[] = 'Authorization: Basic ' . base64_encode($username.":".$partner_password);
$headers[] = 'Content-type: application/x-www-form-urlencoded ';

curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);


//execute the request (the login)
$answer = curl_exec($ch);

if(curl_error($ch)){
	echo curl_error($ch);
}

curl_close($ch);
file_put_contents('FormularyFiles.zip', $answer);

*/
$zip = new ZipArchive;
$zip->open('FormularyFiles.zip');
$zip->extractTo('./');
$zip->close();



 $file = "./Weno Exchange LLC/BSureFormulary.txt";
 $lines = count(file($file));
 echo text($lines). "<br>";
 
 
 $i = 2;
 do{
 $fileName = new SplFileObject($file);
 $fileName->seek($i);
 //echo $fileName."<br>";   //return the first line from file
 
 $in = explode("|", $fileName);
 /*
 echo $in[2]."<br>";
 echo $in[5]."<br>";
 echo $in[8]."<br>";
 */
 $ndc = $in[2];
 $price = $in[5];
 $drugName = $in[8];
 
 //$find = sqlQuery("SELECT ndc FROM erx_drug_paid WHERE ndc = ?", array($ndc));
 
 //if(!empty($find['ndc'])){
	 sqlStatement("INSERT INTO `erx_drug_paid` SET `drug_label_name` = ?, `NDC` = ?, `price_per_unit` = ? ", array($drugName,$ndc,$price));
	 echo text("Inserted")."". text($drugName) ."<br> "; 	
	 //sqlStatement("UPDATE erx_drug_paid SET price_per_unit = ? WHERE ndc = ?", array($price, $ndc));
	 //echo "Updated!<br>";
// }else{
	 //sqlStatement("INSERT INTO `erx_drug_paid` SET `drug_label_name` = ?, `NDC` = ?, `price_per_unit` = ? ", array($drugName,$ndc,$price));
	 //echo "Inserted<br> ";

 //}
 $i++;
 } while ($i < $lines);
 
 