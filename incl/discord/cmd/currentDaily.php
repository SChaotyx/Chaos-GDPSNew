<?php
chdir(dirname(__FILE__));
//error_reporting(0);
include "../../lib/connection.php";
require_once "../../lib/GJPCheck.php";
require_once "../../lib/exploitPatch.php";
$ep = new exploitPatch();
require_once "../../lib/mainLib.php";
$gs = new mainLib();
require_once "../discordLib.php";
$dis = new discordLib();
if(empty($_POST["channel"])){
	exit ("The server did not receive data");
}
switch($_POST['type']){
	case 0: $titleday = 23;
	break;
	case 1: $titleday = 24;
	break;
}
$channelID = $_POST['channel'];
$current = time();
$query=$db->prepare("SELECT levelID FROM dailyfeatures WHERE timestamp < :current AND type = :type ORDER BY timestamp DESC LIMIT 1");
$query->execute([':current' => $current, ':type' => $_POST['type']]);
if($query->rowCount() == 0){
	$nothing = "<@".$_POST['tagID'].">, Nothing Found";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($channelID, $data_string);
	exit ("Daily/Weekly Command: nothing found");
}
$levelID = $query->fetchColumn();
$tag = "<@".$_POST['tagID'].">, here is the current Daily/Weekly level.";
$dis->discordNotifyNew($channelID, $levelID, 1, 7, $titleday, 7, 1, 1, 0, $tag);
echo "Daily/Weekly Command: daily/weekly Level found!";
?>