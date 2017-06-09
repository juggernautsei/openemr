<?php
/**
 * weno admin.
 *
 * @package OpenEMR
 * @link    http://www.open-emr.org
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2016-2017 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

$sanitize_all_escapes = true;		// SANITIZE ALL ESCAPES

$fake_register_globals = false;		// STOP FAKE REGISTER GLOBALS

require_once('../globals.php');
require_once('transmitDataClass.php');
require_once("adminClass.php");
require_once("$srcdir/options.inc.php");


$tables   = new adminProperties();
$exist  = $tables->dataBaseTableExist();

//var_dump($exist);
$finished = filter_input(INPUT_GET, 'status');
?>
<html>
<head>
     <title><?php print xlt("Weno Admin"); ?></title>
    <?php html_header_show(); ?>
    <link rel="stylesheet" href="<?php echo $css_header; ?>" type="text/css">

</head>


<body class="body_top">
<?php 

if($GLOBALS['weno_rx_enable'] != 1){ print xlt("You must activate Weno first!"); exit; } else {print xlt("Weno Service is Enabled")."<br>";} 



if(empty($exist[0]) && empty($exist[1]) && empty($exist[2]) && empty($exist[3])){

	print xlt("All table are being installed. Please wait")."<br>";

	$go = $tables->createTables();

	print $go;

    } else { 
    	print xlt("All tables are installed")."<br><br>"; 
    }
  
   $drugData = $tables->drugTableInfo();
if(!$drugData['ndc']){
   echo "<button><a href='drugPaidInsert.php'>".xlt("Install Drugs Info")."</a></button> <br><br><br>".xlt("Be patient this may take a while");
} else {
	print xlt("Drugs inserted into table")."<br>";


}

?>

<h3>Select State to Import</h3>
<form method="post" action="import_pharmacies.php" >
<?php
echo generate_form_field(array('data_type'=>$GLOBALS['state_data_type'],'list_id'=>$GLOBALS['state_list'], 'field_id'=>'state'));

?><br><br>
<input type="submit" value="Import Pharmacies"><br>
<p><?php echo xlt("Be patient, this can take a while."); ?><br></p>
</form>
<br><br>

<?php  if(!empty($finish)){echo $finish . "with import";} ?>



</body>
</html>


