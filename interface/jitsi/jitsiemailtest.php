<?php

/*
 * @package OpenEMR
 * @link    http://www.open-emr.org
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2021 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../globals.php";

use OpenEMR\Events\JitsiAlertEvent\EmailPatientMeetingLink;

$alert = new EmailPatientMeetingLink;

$alert->pid = 2;
$alert->email = "jana@medbossconsulting.com";

echo $alert->sendEmail();

