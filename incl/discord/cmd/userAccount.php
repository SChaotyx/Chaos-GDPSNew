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
require_once "../emojis.php";
include __DIR__ . "/../../../config/discord.php";
if(empty($_POST["userName"])){
	exit ("The server did not receive data");
}
$userSearch = $_POST['userName'];
$channelID = $_POST['channel'];
//SELECT A ROW FROM DB
$query = $db->prepare("SELECT * FROM accounts WHERE userName = :userName OR accountID = :userName LIMIT 1");
$query->execute([':userName' => $userSearch]);
//IF EXIST?
if($query->rowCount() == 0){
	$nothing = "<@".$_POST['tagID'].">, Nothing Found";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($channelID, $data_string);
	exit ("profile Command: nothing found");
}
//GETTING DATA
$result = $query->fetchAll();
foreach($result as &$account){
	$accountID = $account["accountID"];
	$userName = $account["userName"];
	$email = $account["email"];
	$registerDate = $account["registerDate"];
	$DiscordID = $account["discordID"];
	$friends = $account["friendsCount"];
}

$query = $db->prepare("SELECT * FROM users WHERE extID = :extID LIMIT 1");
$query->execute([':extID' => $accountID]);
if($query->rowCount() == 0){
	$userID = "????";
	$PuserName = "Unknow";
	$Pstats = "This Account don't have a user profile.";
}else{
	$result = $query->fetchAll();
	foreach($result as &$profile){
		$PuserName = $profile["userName"];
		$userID = $profile["userID"];
		$userstars = $profile["stars"];
		$userdiamonds = $profile["diamonds"];
		$userscoins = $profile["coins"];
		$userucoins = $profile["userCoins"];
		$userdemons = $profile["demons"];
		$usercp = $profile["creatorPoints"];
		$userorbs = $profile["orbs"];
		$lastplayed = $profile["lastPlayed"];
		/*
		$chest1count = $profile["chest1Count"];
		$chest12count = $profile["chest2Count"];
		*/
		$completedLvls = $profile["completedLvls"];
		$isbanned = $profile["isBanned"];
		$isCbanned = $profile["isCreatorBanned"];
	}
	$banStatus = "No.";
	$cbanStatus = "No.";
	if($isbanned == 1){$banStatus = "Banned.";}
	if($isCbanned == 1){$cbanStatus = "Banned.";}

	$Pstats = "$icon_star `$userstars` | $icon_diamond `$userdiamonds` | $icon_secretcoin `$userscoins` | $icon_verifycoins `$userucoins` \n $icon_demon `$userdemons` | $icon_cp `$usercp` | $icon_orbs `$userorbs`\n───────────────────\n".
	"$icon_friends **Friends Count:** `".$friends."`\n".
	"$icon_length **Last Time Online:** `".$gs->timeElapsed($lastplayed)." ago`\n".
	"$icon_play **Completed Levels:** `$completedLvls`\n".
	"$icon_globalrank **Is Banned:** `$banStatus`\n".
	"$icon_creatorrank **Is Creator Banned:** `$cbanStatus`\n";
}

if($DiscordID == 0){
	$distag = "``Not Linked``";
}else{
	$distag = "<@".$DiscordID.">";
}
//SET DATA
$content = "<@".$_POST["tagID"].">, here is the full account info for **$userName**:";
$title = "$icon_friends Account info";
$name1 = "$icon_profile ".$userName."'s account info:";
$value1 = "**UserName:** `".$userName."`\n**Password:** ||( ͡° ͜ʖ ͡°)||";
$name2 = "───────────────────";
$value2 = ":calendar: **Register Date:** `".date("D d/m/Y", $registerDate)." (".$gs->timeElapsed($registerDate)." ago)` \n $icon_message **Email:** `".$email."` \n $icon_discord **Discord:** ".$distag."\n───────────────────";
$name3 = "$icon_profile Profile: ".$PuserName;
$value3 = $Pstats;
$thumbnail = "buttons/user_button.png";
$footicon = "misc/auto.png";
$userinfo = "Chaos Bot | UserID: $userID | AccID: $accountID";
//BUILD JSON
$data = array(
	"content"=> $content,
	'embed'=> [
		"title"=> $title,
		"fields"=> [
			["name"=> $name1, "value"=> $value1],
			["name"=> $name2, "value"=> $value2],
			["name"=> $name3, "value"=> $value3]
		],					
		"color"=> $dis->embedColor(7),
		"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($userinfo)],
		"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
	]
);
$data_string = json_encode($data);
$dis->discordNotify($channelID, $data_string);
echo "Account Command: $userSearch's Info found!";
?>