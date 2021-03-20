<?php

/*
 * @package OpenEMR
 * @link    http://www.open-emr.org
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2021 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Events\JitsiAlertEvent;

use OpenEMR\Common\Crypto\CryptoGen;
use PHPMailer\PHPMailer\PHPMailer;

class EmailPatientMeetingLink extends PHPMailer
{
    public $email;
    public $pid;

    public function __construct()
    {
    }

    public function sendEmail()
    {
        $mail = new PHPMailer(TRUE);
        try {
            $mail->SMTPDebug = 1;
            $mail->isSMTP();
            $mail->IsHTML(true);
            $mail->Host = $GLOBALS['SMTP_HOST'];
            $mail->SMTPAuth = true;
            $mail->Username = $GLOBALS['SMTP_USER'];
            $cryptoGen = new CryptoGen();
            $mail->Password = $cryptoGen->decryptStandard($GLOBALS['SMTP_PASS']);
            $mail->SMTPSecure = $GLOBALS['SMTP_SECURE'];
            $mail->Port = $GLOBALS['SMTP_PORT'];

            $mail->setFrom('info@scshawaii.net', 'Scheduling');
            $mail->addReplyTo('callcenter@medbossconsulting.com', 'Scheduling');
            $mail->addAddress($this->email, 'Client');
            $mail->Subject = 'Scheduled Appointment ';
            $mail->Body = self::buildMessageBody();

            $mail->send();
            return '<br><br>Message Sent. Please check email for results';
        }
        catch (Exception $e)
        {
            $errorout = "Message could not be sent";
            $errorout .= "<pre>";
            $errorout .= "Mailer error: " . $mail->ErrorInfo;
            return $errorout;
        }
    }
    public function buildMessageBody(): string
    {
        $body = "<table cellpadding='0' cellspacing='2'>" .
                "<tr>" .
                "<td align='center'><strong>Telehealth Meeting</strong></td>" .
                "</tr><tr>" .
                "<td bgcolor='orange'><a href='". self::buildPatientLink() ."' ><button style='background-color: orange'>JOIN Meeting</button></a></td>" .
                "</tr><td style='font: red'>Please install browser plugin to access session</td></te><tr>" .
                "</tr></table>";
        return $body;
    }
    private function buildPatientLink(): string
    {
        $link = $_SERVER['SERVER_NAME']."/interface/jitsi/jitsi.php?room=".self::createMeetingId()."&pid=".$this->pid;
        return $link;
    }
    private function createMeetingId(): string
    {
        $newmeetingid = sqlQuery("select DOB from patient_data where pid = ?", [$this->pid]);
        $room = md5($newmeetingid['DOB'] . $this->pid);
        return $room;
    }
}
