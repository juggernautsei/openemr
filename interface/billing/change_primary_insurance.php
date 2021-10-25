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
if (!empty($_POST)) {
    var_dump($_POST);
    die;
}
$pid = $_GET['pid'] ?? null;
$primary = '';
$secondary = '';
$tertiary = '';
$primary_id = '';
$secondary_id = '';
$tertiary_id = '';

if ($pid) {
    $insurance_display = $insurance_data->getAll($pid);
} else {
    die("Error patient PID is empty");
}
$i = 1;
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
    <style>
        .insurance-parent {
            border: 2px solid #DFA612;
            color: black;
            display: flex;
            font-family: sans-serif;
            font-weight: bold;
        }

        .insurance-origin {
            flex-basis: 100%;
            flex-grow: 1;
            padding: 10px;
        }

        .insurance-draggable {
            background-color: #4AAE9B;
            font-weight: normal;
            margin-bottom: 10px;
            margin-top: 10px;
            padding: 10px;
        }

        .insurance-dropzone {
            background-color: #6DB65B;
            flex-basis: 100%;
            flex-grow: 1;
            padding: 10px;
        }
    </style>
</head>
<body>
<div class="insurance-parent">
    <div class="insurance-origin">
        <form id='draggable' method='post' action='change_primary_insurance.php'>
            <div
                id="insurance-1"
                class="insurance-draggable"
                draggable="true"
                ondragstart="onDragStart(Event);">
                <input type='hidden' value='5' id='insurance1'>
                Insurance One
            </div>
            <div
                id="insurance-2"
                class="insurance-draggable"
                draggable="true"
                ondragstart="onDragStart(Event);">
                <input type='hidden' value='15'  id='insurance2'>
                Insurance Two
            </div>
            <div
                id="insurance-3"
                class="insurance-draggable"
                draggable="true"
                ondragstart="onDragStart(Event);">
                <input type='hidden' value='115'  id='insurance3'>
                Insurance Three
            </div>
    </div>
    <div
        class="insurance-dropzone"
        ondragover="onDragOver(Event);"
        ondrop="onDrop(Event);"
    >
        dropzone
    </div>
    <input type='submit' value='Update'>
</div>
<div class='button'>

    <script>

        function onDragStart(event) {
            event
                .dataTransfer
                .setData('text/plain', event.target.id);

            event
                .currentTarget
                .style
                .backgroundColor = 'yellow';
        }

        function onDragOver(event) {
            event.preventDefault();

        }

        function onDrop(event) {
            const id = event
                .dataTransfer
                .getData('text');
            const draggableElement = document.getElementById(id);
            const dropzone = event.target;
            dropzone.appendChild(draggableElement);
            event
                .dataTransfer
                .clearData();

        }

        function getInputs() {
            const ele = document.getElementsByTagName('input');

            for (i = 0; i < ele.length; i++) {
                if (ele[i].type == 'hidden') {
                    console.log('Value: ' + ele[i].value);
                    for (j = 0; j < ele[i].attributes.length; j++) {
                        console.log(ele[i].attributes[j]);
                    }
                }
            }
        }

    </script>

</body>
</html>
