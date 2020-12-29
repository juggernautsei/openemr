<?php

/*
 *   @package   OpenEMR
 *   @link      http://www.open-emr.org
 *   @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 *   @copyright Copyright (c )2020. Sherwin Gaddis <sherwingaddis@gmail.com>
 *   @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 *   https://github.com/rniemeyer/knockout-sortable
 */

require_once "../../interface/globals.php";

use OpenEMR\Core\Header;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\EncounterTabsManager\TabsManager;

$formscategories = new TabsManager();
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(['common']); ?>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <title><?php echo xl("Encounter Tabs Manager"); ?></title>

    <script type='text/javascript' src='ext/jquery-1.9.1.js'></script>
    <script type="text/javascript" src="ext/jquery-ui.js"></script>

    <script type='text/javascript' src="ext/knockout-3.5.1.js"></script>
    <script type='text/javascript' src="build/knockout-sortable.min.js"></script>

    <style type='text/css'>
        body {
            font-family: arial;
        }

        h3 {
            font-weight: bold;
            /*text-align: center;*/
        }

        div {
            padding: 5px;
            margin: 5px;
            /*border: black 1px solid;*/
        }

        p, a {
            font-size: 1em;
        }

        ul {
            padding-bottom: 10px;
        }

        .ko_container {
            /*width: 125px;*/
            min-height: 50px;
            background-color: #AAA;
        }
        .ko_container .item {
            float: left;
            width: auto;
        }

        .high {
            background-color: aquamarine;
            overflow: auto;
        }

        .trash {
            background-color: #000;
            visibility: hidden;
        }

        .item {
            border: black 1px solid;
            background-color: #DDD;
            cursor: move;
            text-align: center;
        }

        .item input {
            width: auto;
        }

        #main {
            /*float: left;*/
            width: auto;
            margin-top: 0px;
        }

        #results {
            /*margin-left: 175px;*/
            width: 150px;
            visibility: hidden;
        }
    </style>
</head>
<body>
<div class="container">
    <div id="main">
        <h1><?php echo xl("Encounter Tabs Manager"); ?></h1><br><br>


        <div class="categoryselection">
            <label for="categories"><?php echo xl("Calendar Categories"); ?></label>
            <select id="categories" name="category" onchange="getStoredList(event)" title="default is for everyone no matter the category, there should be only one default">
                <option value=""><?php echo xl("Select Category") ?></option>
                <?php
                $category_list = $formscategories->calendarCategories();
                foreach ($category_list as $list_item) {
                    echo "<option value='".$list_item['pc_catid']."'>";
                    echo xl($list_item['pc_catname']) . " </option>";
                }
                ?>
            </select>
        </div>
        <h3>System Forms</h3>

        <div class="high"
             data-bind="sortable: { template: 'taskTmpl', data: systemforms, afterMove: myDropCallback }">
        </div>

        <h3>Tabs Order</h3>
        <div class="high"
             data-bind="sortable: { template: 'taskTmpl', data: tabsorder, afterMove: myDropCallback }">
        </div>

        <h3>Category Current Order</h3>
        <div class="high" id="active">
        </div>

        <div >
            <input type="checkbox" id="agegroup" name="agegroup" title="Applies to under 18" > Applies to under 18

            <br><br>
            <button id="submit" class="btn primary"><?php print xl("Submit"); ?></button>
            <div id="saved"></div>
        </div>
        <div class="trash" data-bind="sortable: trash"><p></p></div>
        <script id="taskTmpl" type="text/html">
            <div class="item">
                     <span data-bind="visible: !$root.isTaskSelected($data)">
                        <p data-bind="text: name"></p>
                    </span>
                <span data-bind="visibleAndSelect: $root.isTaskSelected($data)">
                        <input data-bind="value: name, event: { blur: $root.clearTask }" />
                    </span>
            </div>
        </script>
    </div>
</div>

<div id="results">
    <h3>High Priority</h3>
    <ul data-bind="foreach: systemforms">
        <li data-bind="text: name"></li>
    </ul>
    <h3>Normal Priority</h3>
    <ul id="tablist" data-bind="foreach: tabsorder">
        <li data-bind="text: name"></li>
    </ul>
</div>

<script type='text/javascript'>
    //control visibility, give element focus, and select the contents (in order)
    ko.bindingHandlers.visibleAndSelect = {
        update:function (element, valueAccessor) {
            ko.bindingHandlers.visible.update(element, valueAccessor);
            if (valueAccessor()) {
                setTimeout(function () {
                    $(element).find("input").focus().select();
                }, 0); //new tasks are not in DOM yet
            }
        }
    };

    var Task = function (name) {
        this.name = ko.observable(name);
    }

    var ViewModel = function () {
        var self = this;

        self.systemforms = ko.observableArray([
            <?php
                $form_list = $formscategories->getHardCodedForms();
                foreach ($form_list as $form_item) {
                    echo "new Task('". xl($form_item['name']) . "'),";
                }
                $form_list_lbf = $formscategories->getLayoutBaseForms();
                foreach ($form_list_lbf as $form_item) {
                    if (!empty($form_item['grp_title'])) {
                        echo "new Task('". xl($form_item['grp_title']) . "'),";
                    }
                }
            ?>
        ]);
        self.systemforms.id = "high";

        self.tabsorder = ko.observableArray([

        ]);
        self.tabsorder.id = "normal";

        self.selectedTask = ko.observable();
        self.clearTask = function(data, event) {
            if (data === self.selectedTask()) {
                self.selectedTask(null);
            }

            if (data.name() === "") {
                self.systemforms.remove(data);
                self.tabsorder.remove(data);
            }
        };

        self.isTaskSelected = function(task) {
            return task === self.selectedTask();
        };

        self.addTask = function () {
            var task = new Task("new");
            self.selectedTask(task);
            self.tabsorder.push(task);
        };
        self.trash = ko.observableArray([]);
        self.trash.id = "trash";

        self.myDropCallback = function (arg) {
            if (console) {
                console.log("Moved '" + arg.item.name() + "' from " + arg.sourceParent.id + " (index: " + arg.sourceIndex + ") to " + arg.targetParent.id + " (index " + arg.targetIndex + ")");
            }
        };
    };

    ko.applyBindings(new ViewModel());

    // get the list of tabs
    const newtablist = document.getElementById("tablist").getElementsByTagName("li");
    const s = document.getElementById("submit").addEventListener("click", updateTabData);

    function updateTabData() {
        let xhttp = new XMLHttpRequest();
        let listjson = '';
        let lg = newtablist.length;
        let category = document.getElementById("categories").value;
        let ages = document.getElementById("agegroup").checked;
        let csrf_token_form = <?php echo js_escape(CsrfUtils::collectCsrfToken()); ?>;
        for (i = 0; i < lg; i++) {
               listjson += newtablist.item(i).innerHTML+", ";
            }
        if (!category) {
            alert("<?php print 'You forgot to select a category'?>!");
            return;
        }

        let tabs = category +"|"+ listjson +"|"+ ages +"|"+ csrf_token_form;

        xhttp.onreadystatechange = function () {
            if (xhttp.readyState === 4 && xhttp.status === 200) {
                console.log(xhttp.responseText);
                if (xhttp.responseText == 'success') {
                    document.getElementById('saved').innerHTML = "<p style='color: red'><strong>Menu Saved</strong></p>";
                    setTimeout(function(){ window.location.reload(); }, 2000);
                }
            }
        };
        xhttp.onerror = function() {
            console.log(xhttp.responseText);
        }
        xhttp.open("POST", "updatetabdata.php", true);
        xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhttp.send(tabs);

    }

    function getStoredList(e) {
        let selected = e.target.value;
        xhr = new XMLHttpRequest();
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                if (xhr.responseText !== '') {
                    document.getElementById('active').innerHTML = "<div style='float: left;'></div>"
                    let s_list = JSON.parse(xhr.responseText);
                    Object.values(s_list).forEach(function (k, v) {
                        document.getElementById('active').innerHTML +=
                            "<div style='float: left; background-color: #DDD; border: black 1px solid;'>" + k + "</div>";
                    });
                } else {
                    document.getElementById('active').innerHTML = "<div style='float: left;'></div>"
                }
            }
        }
        xhr.onerror = function () {
            console.log(xhr.responseText);
        }
        xhr.open('POST', "getcategorymenu.php", true);
        xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xhr.setRequestHeader("Content-type", "application/json");
        xhr.send(selected);
    }


</script>
</body>
</html>