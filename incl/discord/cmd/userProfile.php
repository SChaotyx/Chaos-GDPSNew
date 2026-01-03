<?php
chdir(dirname(__FILE__));
//error_reporting(0);
include "../../lib/connection.php";
include "../../../config/discord.php";
require_once "../../lib/GJPCheck.php";
require_once "../../lib/exploitPatch.php";
$ep = new exploitPatch();
require_once "../../lib/mainLib.php";
$gs = new mainLib();
require_once "../discordLib.php";
$dis = new discordLib();
$userName = $_POST['userName'];
$channelID = $_POST['channel'];
if(empty($_POST["userName"])){
	$query = $db->prepare("SELECT userName, discordLinkReq, accountID FROM accounts WHERE discordID = :discordID");
	$query->execute([':discordID' => $_POST['tagID']]);
	$userInfo = $query->fetchAll()[0];
	$linkStatus = $userInfo["discordLinkReq"];
	$targetAccID = $userInfo["accountID"];
	$userName = $userInfo["userName"];
	if($linkStatus == 1){
		$dis->discordNotifyNew($channelID, $targetAccID, 2, 2, 22, 7, 1, 0, 0, $_POST["tagID"]);
		exit ("profile Command: $userName's stats found!");
	}else{
		$nothing = "<@".$_POST['tagID'].">, use `".$prefix."profile <usarName or userID>`";
		$data = array("content"=> $nothing);                                               
		$data_string = json_encode($data);
		$dis->discordNotify($channelID, $data_string);
		exit ("profile Command: nothing found");
	}
}
$query = $db->prepare("SELECT extID FROM users WHERE userName = :userName OR userID = :userName LIMIT 1");
$query->execute([':userName' => $userName]);
if($query->rowCount() == 0){
	$nothing = "<@".$_POST['tagID'].">, Nothing Found";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($channelID, $data_string);
	exit ("profile Command: nothing found");
}
$targetAccID = $query->fetchColumn();
$query = $db->prepare("SELECT accountID FROM accounts WHERE accountID = :accountID");
$query->execute([':accountID' => $targetAccID]);
if($query->rowCount() == 0){
	$nothing = "<@".$_POST['tagID'].">, Nothing Found";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($channelID, $data_string);
	exit ("profile Command: nothing found");
}
$dis->discordNotifyNew($channelID, $targetAccID, 2, 2, 22, 7, 1, 0, 0, $_POST["tagID"]);
//$dis->discordNotify($channelID, $dis->accEmbedContent(2, $dis->title(22), $dis->iconProfile($targetAccID), $dis->embedColor(7), "misc/auto.png", "Chaos-Bot", $targetAccID, $_POST["tagID"]));
echo "profile Command: $userName's stats found!";
?>