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
$query = $db->prepare("SELECT * FROM roleassign ORDER BY roleID DESC");
$query->execute();
if($query->rowCount() == 0){
	$nothing = "<@".$_POST['tagID'].">, Nothing Found";
	$data = array("content"=> $nothing);                                               
	$data_string = json_encode($data);
	$dis->discordNotify($_POST['channel'], $data_string);
	exit ("Modlist is empty");
}
$moddata = $query->fetchAll();
$modelist = "";
$elderlist = "";
$headlist = "";
$adminlist = "";
$devlist = "";
$ownerlist = "";
foreach($moddata as $mod) {
    $query = $db->prepare("SELECT userName FROM accounts WHERE accountID=:accountID");
    $query->execute([":accountID" => $mod["accountID"]]);
    $userName = $query->fetchColumn();
    switch($mod["roleID"]){
        case 0: $rank = ""; break;
        case 1: $rank = "$icon_brokenmodstar"; break;
        case 2: $rank = "$icon_mod"; break;
        case 3: $rank = "$icon_elder"; break;
        case 4: $rank = "$icon_head"; break;
        case 5: $rank = "$icon_admin"; break;
        case 6: $rank = "$icon_dev"; break;
        case 7: $rank = "$icon_owner"; break;
    }
    $modlist = "`─` $icon_modstar  `".$userName."`\n";
    switch($mod["roleID"]){
        case 2:
            $modelist .= $modlist; 
        break;
        case 3: 
            $elderlist .= $modlist; 
        break;
        case 4:
            $headlist .= $modlist;  
        break;
        case 5:
            $adminlist .= $modlist;  
        break;
        case 6:
            $devlist .= $modlist;  
        break;
        case 7:
            $ownerlist .= $modlist;  
        break;
    }
}
if(empty($modelist)){ $mode = ""; }else{ $mode = "$icon_mod **Moderators:**\n".$modelist; }
if(empty($elderlist)){ $elder = ""; }else{ $elder = "$icon_elder **Elder Moderators:**\n".$elderlist."──────────\n"; }
if(empty($headlist)){ $head = ""; }else{ $head = "$icon_head **Head Moderators:**\n".$headlist."──────────\n"; }
if(empty($adminlist)){ $admin = ""; }else{ $admin = "$icon_admin **Admin:**\n".$adminlist."──────────\n"; }
if(empty($devlist)){ $dev = ""; }else{ $dev = "$icon_dev **Developer:**\n".$devlist."──────────\n"; }
if(empty($ownerlist)){ $owner = ""; }else{ $owner = "$icon_owner **Owner:**\n".$ownerlist."──────────\n"; }
$lel = "───────────────────\n".$owner.$dev.$admin.$head.$elder.$mode."───────────────────";
$data = array(
    "content"=> "<@".$_POST["tagID"].">, here the full list of moderators in the GDPS",
    'embed'=> [
        "title"=> "<a:Mod:536710033589665803> __Moderator List.__",
        "description"=> $lel,
        "footer"=> ["text"=> date('Y-m-d H:i:s')],
        "thumbnail"=> ["url"=> ($iconhost."misc/gdpsthumb.png")],
    ]);
$data_string = json_encode($data);
$dis->discordNotify($_POST['channel'], $data_string);
echo "Mod List command: Done!";
?>