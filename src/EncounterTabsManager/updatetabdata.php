<?php
/*
 *   @package   OpenEMR
 *   @link      http://www.open-emr.org
 *   @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 *   @copyright Copyright (c )2020. Sherwin Gaddis <sherwingaddis@gmail.com>
 *   @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../../interface/globals.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\EncounterTabsManager\TabsManager;

$processdata = new TabsManager();

//receive post data and remove csrf token
foreach ($_POST as $key => $val)
{
    $tabs .= $key;
}

$item = explode("|", $tabs);
//pass data to class for saving
$categoryid = $item[0];
$formlist = str_replace("_"," ", $item[1]);
if ($item[2] === false) {
    $age = 0;
} else {
    $age = 1;
}
$csrf = $item[3];

if (!CsrfUtils::verifyCsrfToken($csrf)) {
CsrfUtils::csrfNotVerified();
}

print $processdata->saveTabs($categoryid, $formlist, $age);
