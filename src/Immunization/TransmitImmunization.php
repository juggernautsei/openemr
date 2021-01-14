<?php
/**
 *  @package   OpenEMR
 *  @link      http://www.open-emr.org
 *  @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 *  @copyright Copyright (c )2020. Sherwin Gaddis <sherwingaddis@gmail.com>
 *  @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 *
 *
 */

namespace OpenEMR\Immunization;


class TransmitImmunization
{
    /**
     *
     */
    public function sendContent($content, $filename)
    {
        $dataFile = (__DIR__).'/'.$filename; //needs absolute path to file
        $sftpServer = "100.100.100.1"; //ip or url here
        $sftpUsername = 'usernamehere';
        $sftpPassword = 'passwordhere';
        $sftpPort = 22;
        $sftpRemoteDir = '/aphmd-in';

        $ch = curl_init('sftp://' . $sftpServer . ':' . $sftpPort . $sftpRemoteDir . '/' . basename($dataFile));
        $df = file_exists($dataFile);
        if ($df) {
            $fh = fopen($dataFile, 'r');
        }
        if ($content && $df == 1) {
            curl_setopt($ch, CURLOPT_USERPWD, $sftpUsername . ':' . $sftpPassword);
            curl_setopt($ch, CURLOPT_UPLOAD, true);
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_SFTP);
            curl_setopt($ch, CURLOPT_INFILE, $fh);
            curl_setopt($ch, CURLOPT_INFILESIZE, filesize($dataFile));
            curl_setopt($ch, CURLOPT_VERBOSE, true);

            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);

            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response) {
                return "Success";
            } else {
                $failure = "Failure" . $error;
                rewind($verbose);
                $verboseLog = stream_get_contents($verbose);
                return $failure . " \n Verbose information:\n" . $verboseLog . "\n";
            }
        } else {
            return "Error file not created or content is empty" . (__DIR__);
        }
    }
}
