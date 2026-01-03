<?php
chdir(dirname(__FILE__));
//error_reporting(0);
include "../../lib/connection.php";
require_once "../../lib/GJPCheck.php";
require_once "../../lib/exploitPatch.php";
$ep = new exploitPatch();
require_once "../../lib/mainLib.php";
$gs = new mainLib();
require_once "../../discord/discordLib.php";
$dis = new discordLib();
if(empty($_POST["levelID_Name"])){
	exit ("The server did not receive data");
}
$lvl = $_POST['levelID_Name'];
$channelID = $_POST['channel'];
$query = $db->prepare("SELECT levelID FROM levels WHERE levelName = :lvl OR levelID = :lvl LIMIT 1");
$query->execute([':lvl' => $lvl]);
if($query->rowCount() == 0){
	$nothing = "<@".$_POST['tagID'].">, Nothing Found";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($channelID, $data_string);
	exit ("level command: nothing found");
}
$levelID = $query->fetchColumn();
$tag = "<@".$_POST['tagID'].">, here is the result of your search.";
$dis->discordNotifyNew($channelID, $levelID, 1, 6, 21, 7, 1, 1, 0, $tag);
//$dis->discordNotify($channelID, $dis->embedContent(6, $dis->title(21), $dis->diffthumbnail($levelID), $dis->embedColor(7), "misc/auto.png", "Chaos-Bot", $levelID, $tag));
echo "level command: $lvl found!";
?>