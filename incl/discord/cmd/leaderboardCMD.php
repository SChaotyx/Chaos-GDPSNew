<?php
chdir(dirname(__FILE__));
//error_reporting(0);
include "../../lib/connection.php";
include __DIR__ . "/../../../config/discord.php";
require_once "../../lib/GJPCheck.php";
require_once "../../lib/exploitPatch.php";
$ep = new exploitPatch();
require_once "../../lib/mainLib.php";
$gs = new mainLib();
require_once "../discordLib.php";
$dis = new discordLib();
require_once "../emojis.php";

if(empty($_POST)){
    exit ("The server did not receive data"); 
}

if(empty($_POST["type"])){
    $type = "stars";
}else{
    $type = $_POST["type"];
}

$tag = $_POST["tagID"];

$querypart1 = "SELECT * FROM users WHERE ";
$querypart2 = "LIMIT 20";
switch ($type) {
    case 'stars':
        $querypart3 = "$type > 9 AND isRegistered = 1 AND isBanned = 0 ORDER BY $type DESC, demons DESC, userCoins DESC, coins DESC, diamonds DESC ";
    break;
    case 'demons':
        $querypart3 = "$type > 0 AND isRegistered = 1 AND isBanned = 0 ORDER BY $type DESC, stars DESC, userCoins DESC, coins DESC, diamonds DESC ";
    break;
    case 'coins':
        $querypart3 = "$type > 0 AND isRegistered = 1 AND isBanned = 0 ORDER BY $type DESC, stars DESC, demons DESC, userCoins DESC, diamonds DESC ";
    break;
    case 'userCoins':
        $querypart3 = "$type > 0 AND isRegistered = 1 AND isBanned = 0 ORDER BY $type DESC, stars DESC, demons DESC, coins DESC, diamonds DESC ";
    break;
    case 'diamonds':
        $querypart3 = "$type > 99 AND isRegistered = 1 AND isBanned = 0 ORDER BY $type DESC, stars DESC, demons DESC, userCoins DESC, coins DESC ";
    break;
    case 'creatorPoints':
        $querypart3 = "$type > 0 AND isCreatorBanned = 0 ORDER BY $type DESC, stars DESC, demons DESC, userCoins DESC, coins DESC, diamonds DESC ";
    break;
    default:
        $querypart3 = "stars AND isRegistered = 1 AND isBanned = 0 ORDER BY stars DESC, demons DESC, userCoins DESC, coins DESC, diamonds DESC ";
    break;
}
$query = $db->prepare($querypart1.$querypart3.$querypart2);
$query->execute();
if($query->rowCount() == 0){
	$nothing = "<@".$_POST['tagID'].">, Nothing Found";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($_POST['channel'], $data_string);
	exit ("profile Command: nothing found");
}
$array = $query->fetchAll();
$lol = "";
$pos = 0;
switch($type){
    case "creatorPoints": $Ltype = "Creator Points"; $Licon = $icon_cp;
    break;
    case "stars": $Ltype = "Stars"; $Licon = $icon_star;
    break;
    case "demons": $Ltype = "Demons"; $Licon = $icon_demon;
    break;
    case "userCoins": $Ltype = "User Coins"; $Licon = $icon_verifycoins;
    break;
    case "coins": $Ltype = "Secret Coins"; $Licon = $icon_secretcoin;
    break;
    case "diamonds": $Ltype = "Diamonds"; $Licon = $icon_diamond;
    break;
}
foreach($array as $row) {
    $pos ++;
    switch($pos){
        case 1: $icontop = $icon_top1; $posn = "1 #"; break;
        case 2: $icontop = $icon_top10; $posn = "2 #"; break;
        case 3: $icontop = $icon_top50; $posn = "3 #"; break;
        case 4: $icontop = $icon_top100; $posn = "4 #"; break;
        case 5: $icontop = $icon_top200; $posn = "5 #"; break;
        case 6: $icontop = $icon_top500; $posn = "6 #"; break;
        case 7: $icontop = $icon_top500; $posn = "7 #"; break;
        case 8: $icontop = $icon_top500; $posn = "8 #"; break;
        case 9: $icontop = $icon_top500; $posn = "9 #"; break;
        case 10: $icontop = $icon_top1000; $posn = "10#"; break;
        case 11: $icontop = $icon_top1000; $posn = "11#"; break;
        case 12: $icontop = $icon_top1000; $posn = "12#"; break;
        case 13: $icontop = $icon_top1000; $posn = "13#"; break;
        case 14: $icontop = $icon_top1000; $posn = "14#"; break;
        case 15: $icontop = $icon_globalrank; $posn = "15#"; break;
        case 16: $icontop = $icon_globalrank; $posn = "16#"; break;
        case 17: $icontop = $icon_globalrank; $posn = "17#"; break;
        case 18: $icontop = $icon_globalrank; $posn = "18#"; break;
        case 19: $icontop = $icon_globalrank; $posn = "19#"; break;
        case 20: $icontop = $icon_globalrank; $posn = "20#"; break;
    }
    $lol .= "$icontop `$posn` | $Licon `".$dis->charCount($row[$type])."` | __**".$row['userName']."**__\n";
}


$data = array(
    "content"=> "<@".$_POST["tagID"].">, Here TOP 20 Leaderboard based on $Ltype",
    'embed'=> [
        "title"=> "$Licon __Top 20 Leaderboards!!!__",
        "description"=> "───────────────────\n".$lol."───────────────────",
        "footer"=> ["text"=> "Leaderboard dated on: ".date('Y-m-d H:i:s')],
        "thumbnail"=> ["url"=> ($iconhost."misc/gdpsthumb.png")],
    ]);
$data_string = json_encode($data);
$dis->discordNotify($_POST['channel'], $data_string);
echo "$Ltype Leaderboard command: Done!";
?>