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

namespace OpenEMR\Form\Sbirt;


class SbirtController
{

    public function indexAction()
    {
        /**
         * @throws Twig\Error\LoaderError
         * @throws Twig\Error\RuntimeError
         * @throws \Twig\Error\SyntaxError
         */
        return $GLOBALS['twig']->render('form/sbirt/sbirt_template.twig',
        [
            'tabtitle' => "SBIRT"
        ]
        );
    }

}
