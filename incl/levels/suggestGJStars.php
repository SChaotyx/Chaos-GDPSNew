<?php
//error_reporting(0);
chdir(dirname(__FILE__));
include "../lib/connection.php";
require_once "../lib/GJPCheck.php";
require_once "../lib/exploitPatch.php";
require_once "../lib/mainLib.php";
require_once "../discord/discordLib.php";
$gs = new mainLib();
$dis = new discordLib();
$gjp2check = isset($_POST['gjp2']) ? $_POST['gjp2'] : $_POST['gjp'];
$gjp = ExploitPatch::remove($gjp2check);
$stars = ExploitPatch::remove($_POST["stars"]);
$feature = ExploitPatch::remove($_POST["feature"]);
$levelID = ExploitPatch::remove($_POST["levelID"]);
$accountID = GJPCheck::getAccountIDOrDie();
$difficulty = $gs->getDiffFromStars($stars);

if($gs->checkPermission($accountID, "headTools")){
	$timerated = time() - $gs->getLevelValue($levelID, "rateDate");
	if($gs->getLevelValue($levelID, "rateDate") == 0){
		$timerated = 0;
	}
	//if(86400 > $timerated OR $gs->checkPermission($accountID, "adminCommands")){
	$difficulty = $gs->getDiffFromStars($stars);
	$gs->rateLevel($accountID, $levelID, $stars, $difficulty["diff"], $difficulty["auto"], $difficulty["demon"], $feature);
	$gs->updatecp(1, $levelID);
	$dis->discordNotifyNew(1, $levelID, 1, 2, 1, 1, $accountID, 1, 0, 0);
	echo 1;
	//}
}else if($gs->checkPermission($accountID, "modTools")){
	$gs->suggestLevel($accountID, $levelID, $difficulty["diff"], $stars, $feature, $difficulty["auto"], $difficulty["demon"]);
	echo 1;
}else{
	echo -2;
}
?>
