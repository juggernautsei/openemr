<?php

/**
 * PatientRetrieveOffsiteDocument
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2024 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Events\PatientDocuments;

use Symfony\Contracts\EventDispatcher\Event;

class PatientRetrieveOffsiteDocument extends Event
{
    const REMOTE_DOCUMENT_LOCATION = 'remote.document.retrieve.location';
    private string $url;
    private $presignedurl;
    public function __construct($url)
    {
        $this->url = $url;
    }

    public function setPresignedUrl(string $presignedUrl): void
    {
        $this->presignedurl = $presignedUrl;
    }

    public function getPresignedUrl()
    {
        return $this->presignedurl;
    }
}