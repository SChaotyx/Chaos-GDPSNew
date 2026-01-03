<?php
include "../../lib/connection.php";
require_once "../discordLib.php";
require_once "../emojis.php";
include __DIR__ . "/../../../config/discord.php";
$dis = new discordLib();
$stars = 190; //190 total stars from RobTop Levels | +20 max tolerable ban
$usercoins = 0;  //+3 user coins tolerable ban
$secretcoins = 66; //66 total secret coins on levels and the vaults //removed map packs secret coins
$demons = 3; //3 total demons by RobTop | +2 max tolerable ban
//calculate stars from rated levels
$query = $db->prepare("SELECT starStars, coins, starCoins, starDemon FROM levels");
$query->execute();
$levelstuff = $query->fetchAll();
foreach($levelstuff as $level){
	$stars = $stars + $level["starStars"];
	if($level["starCoins"] != 0){
		$usercoins += $level["coins"];
	}
	if($level["starDemon"] != 0){
		$demons++;
	}
}
//calculate stars from daily/weekly levels
$query = $db->prepare("SELECT levelID, type FROM dailyfeatures");
$query->execute();
$leveldaily = $query->fetchAll();
foreach($leveldaily as $daily){
	$levelIDdaily = $daily["levelID"];
	$query = $db->prepare("SELECT starStars, coins, starCoins, starDemon FROM levels WHERE levelID = :levelIDdaily");
	$query->execute([':levelIDdaily' => $levelIDdaily]);
	$dailycalculate = $query->fetchAll();
	foreach($dailycalculate as $dailystars){
		$stars += $dailystars["starStars"];
		if($dailystars["starCoins"] != 0){
			$usercoins += $dailystars["coins"];
		}
		if($dailystars["starDemon"] != 0){
			$demons++;
		}
	}
}
//calculate stars from gauntlets
$query = $db->prepare("SELECT level1, level2, level3, level4, level5 FROM gauntlets");
$query->execute();
$levelgauntlet = $query->fetchAll();
foreach($levelgauntlet as $gauntlet){
	for($x = 1; $x < 6; $x++){
		$query = $db->prepare("SELECT starStars, coins, starCoins, starDemon FROM levels WHERE levelID = :levelIDgauntlet");
		$query->execute([':levelIDgauntlet' => $gauntlet["level".$x]]);
		$gauntletcalculate = $query->fetchAll();
		foreach($gauntletcalculate as $gauntletstars){
			$stars += $gauntletstars["starStars"];
			if($gauntletstars["starCoins"] != 0){
				$usercoins += $gauntletstars["coins"];
			}
			if($gauntletstars["starDemon"] != 0){
				$demons++;
			}
		}
	}	
}
//calculate stars from mappacks
$query = $db->prepare("SELECT stars, coins FROM mappacks");
$query->execute();
$result = $query->fetchAll();
foreach($result as $pack){
	$stars += $pack["stars"];
	$secretcoins += $pack["coins"];
}
//DISCORD NOTIFY
$starsMax = $dis->charCount($stars);
$usercMax = $dis->charCount($usercoins);
$demonsMax = $dis->charCount($demons);
$secretcoins = $dis->charCount($secretcoins);
//accounts
$query = $db->prepare("SELECT count(*) FROM accounts");
$query->execute();
$totalaccounts = $query->fetchColumn();
$timeago = time() - 86400;
$query = $db->prepare("SELECT count(*) FROM users WHERE lastPlayed > :lastPlayed");
$query->execute([':lastPlayed' => $timeago]);
$activeusers = $query->fetchColumn();
$query = $db->prepare("SELECT count(*) FROM levels");
$query->execute();
$levelcount = $query->fetchColumn();
$query = $db->prepare("SELECT count(*) FROM levels WHERE starStars != 0");
$query->execute();
$ratedlevelcount = $query->fetchColumn();
//content message
$tag = "<@".$_POST['tagID'].">, Here Geometry Dash Chaos Stats:";
$info = "These are the maximum leaderboard stats to date";
$gdpsstats = "$icon_star `$starsMax`\n$icon_diamond `      ???`\n$icon_secretcoin `$secretcoins`\n$icon_verifycoins `$usercMax`\n$icon_demon `$demonsMax`";
$bar = "───────────────────";
$gdpsinfo = "
$icon_play __Levels__
**Total levels:** $levelcount
**Rated levels:** $ratedlevelcount
$icon_friends __Accounts__
**Registered:** $totalaccounts
**Active users:** $activeusers";
$boticon = "misc/gdpsbot.png";
$botinfo = "Chaos-Bot";
$thumbnail = "misc/gdpsthumb.png";
$image = "misc/gdpslogo.png";
//BUILD JSON
$data = array(
				"content"=> $tag,
				'embed'=> [
					"title"=> $dis->title(25),
					"description"=> $info,
				    "fields"=> [
						["name"=> "────────────", "value"=> $gdpsstats, "inline"=> true],
						["name"=> "────────────", "value"=> $gdpsinfo, "inline"=> true]],					
					"color"=> $dis->embedColor(7),
					"footer"=> ["icon_url"=> ($iconhost.$boticon), "text"=> $botinfo],
					"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
					"image"=> ["url"=> ($iconhost.$image)]
				]);
$data_string = json_encode($data);
$dis->discordNotify($_POST['channel'], $data_string);
echo "Server Stats CMD"
?>