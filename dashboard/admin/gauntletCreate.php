<?php
session_start();
require_once "../../incl/lib/connection.php";
require "../../incl/lib/generatePass.php";
require "../../incl/lib/exploitPatch.php";
require_once "../../incl/lib/mainLib.php";
$gs = new mainLib();
$ep = new exploitPatch();
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
if(!isset($_SESSION["accountID"]) OR $_SESSION["accountID"] == 0){
	header("Location: ../login/login.php");
	exit();
}
if($gs->checkPermission($_SESSION["accountID"], "adminTools") == false){
	exit($dl->printBox("<h1>NO NO NO</h1><p>This account do not have the permissions to access this tool.</p>"));
}
if(isset($_POST["userName"]) && isset($_POST["password"]) && isset($_POST["gName"]) && isset($_POST["level1"]) && isset($_POST["level2"]) && isset($_POST["level3"]) && isset($_POST["level4"]) && isset($_POST["level5"]) &&
   !empty($_POST["userName"]) && !empty($_POST["password"]) && !empty($_POST["gName"]) && !empty($_POST["level1"]) && !empty($_POST["level2"]) && !empty($_POST["level3"]) && !empty($_POST["level4"]) && !empty($_POST["level5"])){
    function levelExist($levelID){
        global $db;
        $dl = new dashboardLib();
        $query = $db->prepare("SELECT levelName FROM levels WHERE levelID=:levelID");	
        $query->execute([':levelID' => $levelID]);
        if($query->rowCount() == 0){
            exit($dl->printBox("<h1>Gauntlet Create</h1><p>Level #$levelID doesn't exist. <a href='admin/gauntletCreate.php'>Try again</a></p>", false, "admin"));
        }else{
            return $query->fetchColumn();
        }
    }
    $userName = $ep->remove($_POST["userName"]);
    $password = $ep->remove($_POST["password"]);
    $gName = $ep->remove($_POST["gName"]);
    $level1 = $ep->remove($_POST["level1"]);
    $level2 = $ep->remove($_POST["level2"]);
    $level3 = $ep->remove($_POST["level3"]);
    $level4 = $ep->remove($_POST["level4"]);
    $level5 = $ep->remove($_POST["level5"]);
    $generatePass = new generatePass();
	$pass = $generatePass->isValidUsrname($userName, $password);
	if ($pass == 1) {
        $query = $db->prepare("SELECT accountID FROM accounts WHERE userName=:userName");	
		$query->execute([':userName' => $userName]);
        $accountID = $query->fetchColumn();
		if(!$gs->checkPermission($accountID, "toolPackcreate")){
			exit($dl->printBox('<h1>Gauntlet Create</h1><p>This account do not have the permissions to access this tool. <a href="admin/gauntletCreate.php">Try again</a></p>', false, "admin"));
        }
        $level1Name = levelExist($level1);
        $level2Name = levelExist($level2);
        $level3Name = levelExist($level3);
        $level4Name = levelExist($level4);
        $level5Name = levelExist($level5);
        switch($gName){
            case 'fire': $gID = 1; break;
            case 'ice': $gID = 2; break;
            case 'poison': $gID = 3; break;
            case 'shadow': $gID = 4; break;
            case 'lava': $gID = 5; break;
            case 'bonus': $gID = 6; break;
            case 'chaos': $gID = 7; break;
            case 'demon': $gID = 8; break;
            case 'time': $gID = 9; break;
            case 'crystal': $gID = 10; break;
            case 'magic': $gID = 11; break;
            case 'spike': $gID = 12; break;
            case 'monster': $gID = 13; break;
            case 'doom': $gID = 14; break;
            case 'death': $gID = 15; break;
            default:
			exit($dl->printBox('<h1>Gauntlet Create</h1><p>'.htmlspecialchars($gName, ENT_QUOTES).' is invalid. <a href="admin/gauntletCreate.php">Try again</a></p>', false, "admin"));
            break;
        }
        $query = $db->prepare("SELECT ID FROM gauntlets WHERE ID=:gID");	
        $query->execute([':gID' => $gID]);
        if($query->rowCount() != 0){
			exit($dl->printBox('<h1>Gauntlet Create</h1><p>'.htmlspecialchars($gName, ENT_QUOTES).' gauntlet already exist. <a href="admin/gauntletCreate.php">Try again</a></p>', false, "admin"));
        }
        $query = $db->prepare("INSERT INTO gauntlets (ID, level1, level2, level3, level4, level5) VALUES (:gID, :level1, :level2, :level3, :level4, :level5)");
        $query->execute([':gID'=>$gID, ':level1'=>$level1, ':level2'=>$level2, ':level3'=>$level3, ':level4'=>$level4, ':level5'=>$level5]);
        $dl->printBox("<h1>".htmlspecialchars($gs->getGauntletName($gID), ENT_QUOTES)." Gauntlet</h1>
                        <p>Level1: ".htmlspecialchars($level1Name, ENT_QUOTES)." (".htmlspecialchars($level1, ENT_QUOTES).")</p>
                        <p>Level2: ".htmlspecialchars($level2Name, ENT_QUOTES)." (".htmlspecialchars($level2, ENT_QUOTES).")</p>
                        <p>Level3: ".htmlspecialchars($level3Name, ENT_QUOTES)." (".htmlspecialchars($level3, ENT_QUOTES).")</p>
                        <p>Level4: ".htmlspecialchars($level4Name, ENT_QUOTES)." (".htmlspecialchars($level4, ENT_QUOTES).")</p>
                        <p>Level5: ".htmlspecialchars($level5Name, ENT_QUOTES)." (".htmlspecialchars($level5, ENT_QUOTES).")</p>
                        <p><a href='stats/gauntletTable.php'>GAUNTLET LIST</a></p>", false, "admin");
    }else{
		$dl->printBox('<h1>Gauntlet Create</h1><p>Invalid password or nonexistant account. <a href="admin/gauntletCreate.php">Try again</a></p>', false, "admin");
    }
}else{
    $formContent = '<form action="" method="post">
					<div class="form-group">
						<label for="usernameField">Admin Data</label>
						<input type="text" class="form-control" id="usernameField" name="userName" placeholder="Enter username">
						<input type="password" class="form-control" id="passwordField" name="password" placeholder="Enter password">
                        <label for="gNameField">Gauntlet Name</label>
                        <input type="text" class="form-control" id="gNameField" name="gName" placeholder="Enter Gauntlet Name (fire, ice, poison, shadow, lava, bonus, chaos, demon, time, crystal, magic, spike, monster, doom, death)">
                        <label for="levelsField">Levels</label>
                        <input type="text" class="form-control" id="level1Field" name="level1" placeholder="Enter Level 1 ID">
                        <input type="text" class="form-control" id="level2Field" name="level2" placeholder="Enter Level 2 ID">
                        <input type="text" class="form-control" id="level3Field" name="level3" placeholder="Enter Level 3 ID">
                        <input type="text" class="form-control" id="level4Field" name="level4" placeholder="Enter Level 4 ID">
                        <input type="text" class="form-control" id="level5Field" name="level5" placeholder="Enter Level 5 ID">
					</div>
					<button type="submit" class="btn btn-primary btn-block">Create Gauntlet</button>
				</form>';
    $dl->printBox('<h1>Gauntlet Create</h1>'.$formContent, false, "admin");
}
?>
