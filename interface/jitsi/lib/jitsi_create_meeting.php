<?php

/*
 * @package OpenEMR
 * @link    http://www.open-emr.org
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2021 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


function createMeetingId(): string
{
        $newmeetingid = sqlQuery("select DOB from patient_data where pid = ?", [$_SESSION['pid']]);
        $room = md5($newmeetingid['DOB'] . $_SESSION['pid']);
        return $room;
}

