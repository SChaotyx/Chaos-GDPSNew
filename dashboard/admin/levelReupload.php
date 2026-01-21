<?php
session_start();
require_once "../../incl/lib/connection.php";
require "../../incl/lib/generatePass.php";
require_once "../../incl/lib/exploitPatch.php";
$ep = new exploitPatch();
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
require_once "../../incl/lib/mainLib.php";
$gs = new mainLib();
require "../../incl/lib/XORCipher.php";
$xc = new XORCipher();
if(!isset($_SESSION["accountID"]) OR $_SESSION["accountID"] == 0){
	header("Location: ../login/login.php");
	exit();
}
if($gs->checkPermission($_SESSION["accountID"], "adminTools") == false){
	exit($dl->printBox("<h1>NO NO NO</h1><p>This account do not have the permissions to access this tool.</p>"));
}
function chkarray($source){
	if($source == ""){
		$target = "0";
	}else{
		$target = $source;
	}
	return $target;
}
if(isset($_POST["userName"]) && isset($_POST["password"]) && isset($_POST["levelid"]) &&
   !empty($_POST["userName"]) && !empty($_POST["password"]) && !empty($_POST["levelid"])){
	$userName = $ep->remove($_POST["userName"]);
	$password = $ep->remove($_POST["password"]);
	$generatePass = new generatePass();
	$pass = $generatePass->isValidUsrname($userName, $password);
	if($pass == 1){
		$query = $db->prepare("SELECT accountID FROM accounts WHERE userName=:userName");	
		$query->execute([':userName' => $userName]);
		$accountID2 = $query->fetchColumn();
		//checking account permissions
		if($gs->checkPermission($accountID2, "adminTools") == false){
			exit($dl->printBox("<h1>Level Reupload</h1><p>This account do not have the permissions to access this tool. <a href='admin/levelReupload.php'>Try again</a></p>", false, "admin"));
		}
		$levelID = $_POST["levelid"];
		$levelID = preg_replace("/[^0-9]/", '', $levelID);
		$url = isset($_POST["server"]) && !empty($_POST["server"]) ? $_POST["server"] : "http://www.boomlings.com/database/downloadGJLevel22.php";
		$post = ['gameVersion' => '22', 'binaryVersion' => '37', 'gdw' => '0', 'levelID' => $levelID, 'secret' => 'Wmfd2893gb7', 'inc' => '0', 'extras' => '0'];
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		$result = curl_exec($ch);
		curl_close($ch);
		if($result == "" OR $result == "-1" OR $result == "No no no"){
			if($result==""){
				exit($dl->printBox("<h1>Level Reupload</h1><p>An error has occured while connecting to the server.<br>Error code: ".htmlspecialchars($result, ENT_QUOTES)." <a href='admin/levelReupload.php'>Try again</a></p>", false, "admin"));
			}else if($result=="-1"){
				exit($dl->printBox("<h1>Level Reupload</h1><p>This level doesn't exist.<br>Error code: ".htmlspecialchars($result, ENT_QUOTES)." <a href='admin/levelReupload.php'>Try again</a></p>", false, "admin"));
			}else{
				exit($dl->printBox("<h1>Level Reupload</h1><p>RobTop doesn't like you or something...<br>Error code: ".htmlspecialchars($result, ENT_QUOTES)." <a href='admin/levelReupload.php'>Try again</a></p>", false, "admin"));
			}
		}else{
			$level = explode('#', $result)[0];
			$resultarray = explode(':', $level);
			$levelarray = array();
			$x = 1;
			foreach($resultarray as &$value){
				if ($x % 2 == 0) {
					$levelarray["a$arname"] = $value;
				}else{
					$arname = $value;
				}
				$x++;
			}
			$echo = "";
			$debug = isset($_POST["debug"]) ? $_POST["debug"] : 0;
			if($debug == 1){
				$echo = "<br>".htmlspecialchars($result, ENT_QUOTES) . "<br>";
				$echo .= "<pre>".htmlspecialchars(print_r($levelarray, true), ENT_QUOTES)."</pre>";
			}
			if($levelarray["a4"] == ""){
				exit($dl->printBox("<h1>Level Reupload</h1><p>$echo An error has occured.<br>Error code: ".htmlspecialchars($result,ENT_QUOTES)." <a href='admin/levelReupload.php'>Try again</a></p>", false, "admin"));
			}
			$uploadDate = time();
			//old levelString
			$levelString = chkarray($levelarray["a4"]);
			$gameVersion = chkarray($levelarray["a13"]);
			if(substr($levelString,0,2) == 'eJ'){
				$levelString = str_replace("_","/",$levelString);
				$levelString = str_replace("-","+",$levelString);
				$levelString = gzuncompress(base64_decode($levelString));
				if($gameVersion > 18){
					$gameVersion = 18;
				}
			}
			//check if exists
			$query = $db->prepare("SELECT count(*) FROM levels WHERE originalReup = :lvl OR original = :lvl");
			$query->execute([':lvl' => $levelarray["a1"]]);
			if($query->fetchColumn() == 0){
				$parsedurl = parse_url($url);
				if($parsedurl["host"] == $_SERVER['SERVER_NAME']){
					exit($dl->printBox("<h1>Level Reupload</h1><p>$echo You're attempting to reupload from the target server. <a href='admin/levelReupload.php'>Try again</a></p>", false, "admin"));
				}
				if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
					$hostname = $_SERVER['HTTP_CLIENT_IP'];
				} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
					$hostname = $_SERVER['HTTP_X_FORWARDED_FOR'];
				}else{
					$hostname = $_SERVER['REMOTE_ADDR'];
				}
				//values
				$twoPlayer = chkarray($levelarray["a31"]);
				$songID = chkarray($levelarray["a35"]);
				$coins = chkarray($levelarray["a37"]);
				$reqstar = chkarray($levelarray["a39"]);
				$extraString = chkarray($levelarray["a36"]);
				$starStars = chkarray($levelarray["a18"]);
				$isLDM = chkarray($levelarray["a40"]);
				$password = chkarray($xc->cipher(base64_decode($levelarray["a27"]),26364));
				$starCoins = 0;
				$starDiff = 0;
				$starDemon = 0;
				$starAuto = 0;
				if($parsedurl["host"] == "www.boomlings.com"){
					if($starStars != 0){
						$starCoins = chkarray($levelarray["a38"]);
						$starDiff = chkarray($levelarray["a9"]);
						$starDemon = chkarray($levelarray["a17"]);
						$starAuto = chkarray($levelarray["a25"]);
					}
				}else{
					$starStars = 0;
				}
				$targetuser = isset($_POST["targetuser"]) ? $_POST["targetuser"] : "";
				if(empty($targetuser)){
					$userID = 0;
					$extID = 0;
					$userNameTarget = "reupload";
				}else{
					$query = $db->prepare("SELECT accountID, userName FROM accounts WHERE userName=:targetuser OR accountID=:targetuser");
					$query->execute([':targetuser' => $targetuser]);
					if($query->rowCount() == 0){
						$extID = 0;
						$userNameTarget = $ep->remove($targetuser);
						$query2 = $db->prepare("SELECT userID FROM users WHERE userName=:targetuser");
						$query2->execute([':targetuser' => $userNameTarget]);
						if($query2->rowCount() == 0){
							$query2 = $db->prepare("INSERT INTO `users` (`isRegistered`, `userID`, `extID`, `userName`, `stars`, `demons`, `icon`, `color1`, `color2`, `iconType`, `coins`, `userCoins`, `special`, `gameVersion`, `secret`, `accIcon`, `accShip`, `accBall`, `accBird`, `accDart`, `accRobot`, `accGlow`, `creatorPoints`, `IP`, `lastPlayed`, `diamonds`, `orbs`, `completedLvls`, `accSpider`, `accExplosion`, `chest1time`, `chest2time`, `chest1count`, `chest2count`, `isBanned`, `isCreatorBanned`) 
															VALUES ('0', NULL, '0', :targetuser, '0', '0', '0', '0', '0', '0', '0', '0', '0', '21', 'Wmfd2893gb7', '0', '0', '0', '0', '0', '0', '0', '0', '186.12.112.160', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0')");
							$query2->execute([':targetuser' => $userNameTarget]);
							$userID = $db->lastInsertId();
						}else{
							$userID = $query2->fetchColumn();
						}	
					}else{
						$userInfo = $query->fetchAll()[0];
						$extID = $userInfo["accountID"];
						$userNameTarget = $userInfo["userName"];
						$query = $db->prepare("SELECT userID FROM users WHERE extID=:extID");
						$query->execute([':extID' => $extID]);
						if($query->rowCount() == 0){
							$userID = 0;
							$extID = 0;
						}else{
							$userID = $query->fetchColumn();
						}
					}
				}
				//query
				$levelarray["a2"] = $ep->remove($levelarray["a2"]);
				$query = $db->prepare("INSERT INTO levels (levelName, gameVersion, binaryVersion, userName, levelDesc, levelVersion, levelLength, audioTrack, auto, password, original, twoPlayer, songID, objects, coins, requestedStars, extraString, levelString, levelInfo, secret, uploadDate, updateDate, originalReup, userID, extID, unlisted, hostname, starStars, starCoins, starDifficulty, starDemon, starAuto, isLDM, songIDs, sfxIDs, ts)
												VALUES (:name ,:gameVersion, '27', :usertarget, :desc, :version, :length, :audiotrack, '0', :password, '1', :twoPlayer, :songID, '0', :coins, :reqstar, :extraString, :levelString, '0', '0', '$uploadDate', '$uploadDate', :originalReup, :userID, :extID, '0', :hostname, :starStars, :starCoins, :starDifficulty, :starDemon, :starAuto, :isLDM, :songIDs, :sfxIDs, :ts)");
				$query->execute([':password' => $password, ':starDemon' => $starDemon, ':starAuto' => $starAuto, ':gameVersion' => $gameVersion, ':name' => $levelarray["a2"], ':desc' => $levelarray["a3"], ':version' => $levelarray["a5"], ':length' => $levelarray["a15"], ':audiotrack' => $levelarray["a12"], ':twoPlayer' => $twoPlayer, ':songID' => $songID, ':coins' => $coins, ':reqstar' => $reqstar, ':extraString' => $extraString, ':levelString' => "", ':originalReup' => $levelarray["a1"], ':hostname' => $hostname, ':starStars' => $starStars, ':starCoins' => $starCoins, ':starDifficulty' => $starDiff, ':userID' => $userID, ':extID' => $extID, ':isLDM' => $isLDM, ':usertarget' => $userNameTarget, ':songIDs' => isset($levelarray["a52"]) ? $levelarray["a52"] : "", ':sfxIDs' => isset($levelarray["a53"]) ? $levelarray["a53"] : "", ':ts' => isset($levelarray["a57"]) ? $levelarray["a57"] : ""]);
				$levelID = $db->lastInsertId();
				file_put_contents("../../data/levels/$levelID",$levelString);
				exit($dl->printBox("<h1>Level Reupload</h1><p>$echo Level reuploaded, ID: ".htmlspecialchars($levelID, ENT_QUOTES)."</p>", false, "admin"));
			}else{
				exit($dl->printBox("<h1>Level Reupload</h1><p>$echo This level has been already reuploaded</p>", false, "admin"));
			}
		}
	}else{
		//if invalid username or password
		exit($dl->printBox("<h1>Level Reupload</h1><p>Invalid password or nonexistant account. <a href='admin/levelReupload.php'>Try again</a></p>", false, "admin"));
	}
}else{
	$formContent = '<form action="" method="post">
					<div class="form-group">
						<label for="usernameField">Admin Data</label>
						<input type="text" class="form-control" id="usernameField" name="userName" placeholder="Enter username">
						<input type="password" class="form-control" id="passwordField" name="password" placeholder="Enter password">
					</div>
					<div class="form-group">
						<label for="levelIDField">Level ID</label>
						<input type="text" class="form-control" id="levelIDField" name="levelid" placeholder="Enter levelID">
					</div>
					<div class="form-group">
						<label for="targetuserField">Target User (optional)</label>
						<input type="text" class="form-control" id="targetuserField" name="targetuser" placeholder="Enter Target userName or userID">
					</div>
					<div class="form-group">
						<label for="serverField">URL (don\'t change if you don\'t know what you\'re doing)</label>
						<input type="text" class="form-control" id="URLField" name="server" value="http://www.boomlings.com/database/downloadGJLevel22.php" placeholder="URL">
					</div>
					<div class="form-group">
						<label for="debugField">Debug (0=off, 1=on)</label>
						<input type="text" class="form-control" id="debugField" name="debug" value="0" placeholder="debug">
					</div>
					<button type="submit" class="btn btn-primary btn-block">Reupload</button>
				</form>';
	$dl->printBox('<h1>'.$dl->getLocalizedString("levelReupload").'</h1>'.$formContent, false, "admin");
}
?>
