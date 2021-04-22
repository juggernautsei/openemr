<?php

/*
 *   @package   OpenEMR
 *   @link      http://www.open-emr.org
 *   @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 *   @copyright Copyright (c )2020. Sherwin Gaddis <sherwingaddis@gmail.com>
 *   @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 *   This has become the database layer of the module
 */

namespace OpenEMR\EncounterTabsManager;

use Exception;

class TabsManager
{
    private $categoryid;

    public function __construct()
    {
        //do epic stuff here
    }

    public function calendarCategories()
    {
        //the first 11 are the default categories so those are skipped
        $sql = "select pc_catid, pc_catname from openemr_postcalendar_categories where pc_catid > 11 order by pc_catname ASC";
        $categories = sqlStatement($sql);
        return $categories;
    }

    /**
     * @return mixed
     * to exclude a form added it to the NOT IN group
     */
    public function getHardCodedForms()
    {
        $fsql = "select name, id from registry where state = 1  and id NOT IN ( 1, 27, 23, 22, 14 ) order by name asc";
        $form = sqlStatement($fsql);
        return $form;
    }

    /**
     * @return mixed
     */
    public function getLayoutBaseForms()
    {
        $lsql = "select grp_form_id, grp_title from layout_group_properties " .
                " where grp_form_id LIKE ? and grp_group_id = '' order by grp_title asc";
        $form = sqlStatement($lsql, ['%LBF%']);
        return $form;
    }

    //see if any forms have been filled out for this patient
    private function docsAdded()
    {
        $enc = $GLOBALS['encounter'];
        $doclist = [];
        $sql = "select formdir FROM forms WHERE formdir != ? and encounter = ?";
        $res = sqlStatement($sql, ['newpatient', $enc]);
        while ($row = sqlFetchArray($res)) {
            $doclist[] = $row['formdir'];
        }
        return $doclist;
    }

    public function fetchStoredTabs($categoryid)
    {
        $sql = "SELECT * FROM tabs_manager WHERE category = ?";
        $res = sqlQuery($sql, [$categoryid]);
        return $res;
    }


    public function saveTabs($categoryid, $formlist, $age)
    {
        $this->categoryid = $categoryid;
        $doesexist = self::doesEntryExist();
        //process forms list
        //process this data into something savable
        $titles = explode(",", $formlist);
        $jsonarraytosave = [];
        foreach ($titles as $title) {
            $ftitle = trim($title);
            if (strlen($ftitle) > 3) {
                $list_build_json = $this->retrieveFormIdLbf($ftitle);
                if (strlen($list_build_json) < 3) {
                    $list_build_json = $this->retrieveFormIdHcoded($ftitle);
                }
                $jsonarraytosave[$list_build_json] = $ftitle;
            }

        }
        $thething = json_encode($jsonarraytosave);
        if (empty($doesexist)) {
                try {
                    $sql = "INSERT INTO tabs_manager SET category = '" . $categoryid . "', form_list = '" . $thething . "', age = '" . $age . "'";
                    sqlStatement($sql);
                } catch (Exception $e) {
                    return 'unable to save data ' . $e;
                }
                return 'success';
            }  else {
                try {
                    $sql = "UPDATE tabs_manager SET category = '" . $categoryid . "', form_list = '" . $thething . "', age = '" . $age . "'
                    WHERE id = '". $doesexist ."'";
                    sqlStatement($sql);
                } catch (Exception $e) {
                    return 'unable to save data ' . $e;
                }
                return 'success';
            }
        }

    private function retrieveFormIdLbf($title)
    {
        $sql = "select grp_form_id from layout_group_properties where grp_title = ? ";
        $arraytitle = sqlQuery($sql, [$title]);
            return $arraytitle['grp_form_id'];
    }
    private function retrieveFormIdHcoded($title)
    {
        $sql = "select directory from registry where name LIKE ? ";
        $arraytitle = sqlQuery($sql, [$title.'%']);
        return $arraytitle['directory'];
    }

    private function doesEntryExist()
    {
        $sql = "select id from tabs_manager where category = ?";
        $res = sqlQuery($sql, [$this->categoryid]);
        return $res['id'];
    }
}