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

include_once("../globals.php");
$date = date("Y-m-d");
$script = filter_input(INPUT_GET, "rx");

$e_script = explode("-", $script);

if($e_script[0] === "NewRx"){
	//See if the value is set 
	$check = "SELECT ntx FROM prescriptions WHERE id = "  . $e_script[1];
	$getVal = sqlStatement($check);
	$val = sqlFetchArray($getVal);
	
	//If the value is not set to 1 then set it for new rx to transmit 
	// ToDo add transmit date
	if(empty($val['ntx'])){	
		$sql = "UPDATE prescriptions SET ntx = '1', txDate = '". $date ."' WHERE id = " . $e_script[1];
		sqlStatement($sql);
	}

}

if($e_script[0] === "RefillRx"){
	$sql = "UPDATE prescriptions SET rTx = '1', txDate = '0000-00-00' WHERE id = ".$e_script[1];
	sqlStatement($sql);
	
}
