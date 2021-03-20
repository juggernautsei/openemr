<?php

/*
 * @package OpenEMR
 * @link    http://www.open-emr.org
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2021 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$ignoreAuth = true;
// Set $sessionAllowWrite to true to prevent session concurrency issues during authorization related code
$sessionAllowWrite = true;

require_once "../../interface/globals.php";
require_once __DIR__ . "/gen_tool/jaas-jwt.php";
require_once __DIR__ . "/lib/jitsi_create_meeting.php";

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

if (!empty($_GET['room'])) {
    $room = $_GET['room'];
} else {
    $room = createMeetingId();
}

?>
<html>
<head>
    <script src="https://8x8.vc/libs/external_api.min.js"></script>
    <style>html, body, #meet { height: 100%; }</style>
</head>
<body>
<div id="meet" class="margin:auto; text-align:center;"></div>

<script>
    const room = "vpaas-magic-cookie-02bc0019d5a3438186239dc1711e0ee1/<?php echo $room; ?>";
<?php //die; ?>
    const domain = "8x8.vc";
    const options = {
        roomName: room, // replace this with $your_tenant_name/$room_name
        width: 1200,
        height: 800,
        parentNode: document.querySelector("#meet"),
        jwt:
            "<?php echo $token; ?>",
    };
    const api = new JitsiMeetExternalAPI(domain, options);
    // Example of a command to manipulate the jitsi video meeting, such as muting eceryone
    // api.executeCommand('muteEveryone');
</script>
</body>
</html>

