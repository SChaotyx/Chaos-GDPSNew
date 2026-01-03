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
$userData = $_POST['userData'];
$channelID = $_POST['channel'];
$tagID = $_POST['tagID'];
$userTag = $_POST['userTag'];
$query = $db->prepare("SELECT accountID FROM accounts WHERE userName = :userData OR accountID = :userData LIMIT 1");
$query->execute([':userData' => $userData]);
if($query->rowCount() == 0){
	$nothing = "<@".$_POST['tagID'].">, Usuario no encontrado";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($channelID, $data_string);
	exit ($userData);
}
$accountID = $query->fetchColumn();
//is already linked?
$query = $db->prepare("SELECT discordLinkReq, discordID FROM accounts WHERE accountID = :accountID");
$query->execute([':accountID' => $accountID]);
$userInfo = $query->fetchAll()[0];
$linkStatus = $userInfo["discordLinkReq"];
$usertag2 = $userInfo["discordID"];
if($linkStatus == 1){
    $nothing = "<@".$_POST['tagID'].">, esta cuenta ya esta enlazada a <@".$usertag2.">";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($channelID, $data_string);
	exit (-1);
}
//generate confirm code
$length = 6;
$code = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, $length);
//update db
$query = $db->prepare("UPDATE accounts SET discordID=:discordID, discordLinkReq=:code WHERE accountID=:accountID"); 
$query->execute([':discordID' => $tagID, ':code' => $code, ':accountID' => $accountID]);
//send msg
$nothing = "Bien <@".$_POST['tagID'].">, ahora ve a tu perfil en el gdps y coloca `!confirm ".$code."` (respeta mayúsculas) para confirmar que eres tu.";
$data = array("content"=> $nothing);                                               
$data_string = json_encode($data);
$dis->discordNotify($channelID, $data_string);
?>