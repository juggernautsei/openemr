<?php

/*
 * @package      OpenEMR
 * @link               https://www.open-emr.org
 *
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2021 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 *
 */

require_once dirname(__FILE__, 2) . "/globals.php";

use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\InsuranceCompanyService;

$insurance_data = new InsuranceService();
//make sure there is a pid sent in the call
$pid = $_GET['pid'] ?? null;
//properly initialize variables
$primary = '';
$secondary = '';
$tertiary = '';
$primary_id = '';
$secondary_id = '';
$tertiary_id = '';

//if no pid fail gracefully with usable error message.
if ($pid) {
    $insurance_display = $insurance_data->getAll($pid);
} else {
    die("Error: patient PID is empty");
}
//initialize count of insurance companies which at this time can be only 3
$i = 1;

//loop through the returned data. setting the variables
foreach ($insurance_display as $display) {
    $show_provider = '';
    $provider_name = new InsuranceCompanyService();
    $show_provider = $provider_name->getOne($display['provider']);
    if ($i == 1) {
         $primary = $show_provider['name'];
         $primary_id = $show_provider['id'];
    } elseif ($i == 2) {
         $secondary = $show_provider['name'];
         $secondary_id = $show_provider['id'];
    } else {
        $tertiary = $show_provider['name'];
        $tertiary_id = $show_provider['id'];
    }
    ++$i;
}

//This is temporary until decide how this will be handled on this page to change the status
echo xlt("Primary ") . ": " . $primary . "<br>";
echo xlt("Secondary ") . ": " . $secondary;

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Change Insurance Company Order</title>
</head>
<body>
<div class="example-parent">
    <div class="example-origin">
        <div
            id="draggable-1"
            class="example-draggable"
        >
            draggable
        </div>
    </div>

    <div
        class="example-dropzone"
    >
        dropzone
    </div>
</div>
</body>
</html>
