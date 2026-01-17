<?php
class Commands {

    public static function ownCommand($comment, $command, $accountID, $targetExtID){
		require_once "../lib/mainLib.php";
		$gs = new mainLib();
		$commandInComment = strtolower("!".$command);
		$commandInPerms = ucfirst(strtolower($command));
		$commandlength = strlen($commandInComment);
		if(substr($comment,0,$commandlength) == $commandInComment AND (($gs->checkPermission($accountID, "command".$commandInPerms."All") OR ($targetExtID == $accountID AND $gs->checkPermission($accountID, "command".$commandInPerms."Own"))))){
			return true;
		}
		return false;
	}

    public static function doCommands($accountID, $comment, $levelID) {
		include dirname(__FILE__)."/../lib/connection.php";
		require_once dirname(__FILE__)."/../lib/exploitPatch.php";
		require_once dirname(__FILE__)."/../lib/mainLib.php";
		require_once dirname(__FILE__)."/../discord/discordLib.php";
		$ep = new exploitPatch();
		$gs = new mainLib();
		$dis = new discordLib();
		$commentarray = explode(' ', $comment);
		$uploadDate = time();

		//LEVELINFO
		$query = $db->prepare("SELECT userID, extID, starStars, rateDate, levelLength, original FROM levels WHERE levelID = :levelID");
		$query->execute([':levelID' => $levelID]);
		$result = $query->fetchAll();
		if ($query->rowCount() == 0) { return false; }
		foreach($result as $lvl){
			$lvlUserID = $lvl["userID"];
			$lvlExtID = $lvl["extID"];
			$lvlstars = $lvl["starStars"];
			$lvlLength = $lvl["levelLength"];
			$lvlRateDate = $lvl["rateDate"];
			$lvlOriginal = $lvl["original"];
			$timerated = time() - $lvlRateDate;
			if($lvlRateDate == 0){ $timerated = 0;}
		}

		//----------------
		//----------------
		//ADMIN COMMANDS
		//----------------
		//----------------
		if($gs->checkPermission($accountID, "adminTools")){

			//delete level
			if(substr($comment,0,7) == '!delete'){
				if(!is_numeric($levelID)){
					return false;
				}
				$dis->discordNotifyNew(1, $levelID, 1, 2, 13, 3, $accountID, 2, 11, 0);
				$query = $db->prepare("DELETE from levels WHERE levelID=:levelID LIMIT 1");
				$query->execute([':levelID' => $levelID]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('6', :value, :levelID, :timestamp, :id)");
				$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
				if(file_exists(dirname(__FILE__)."../../data/levels/$levelID")){
					rename(dirname(__FILE__)."../../data/levels/$levelID",dirname(__FILE__)."../../data/levels/deleted/$levelID");
				}
				return true;
			}

			//SET LEVEL ACCOUNT
			if(substr($comment,0,7) == '!setacc'){
				$query = $db->prepare("SELECT accountID FROM accounts WHERE userName = :userName OR accountID = :userName LIMIT 1");
				$query->execute([':userName' => $commentarray[1]]);
				if($query->rowCount() == 0){
					return false;
				}
				$targetAcc = $query->fetchColumn();
				$query = $db->prepare("SELECT userID FROM users WHERE extID = :extID LIMIT 1");
				$query->execute([':extID' => $targetAcc]);
				$userID = $query->fetchColumn();
				$dis->discordNotifyNew(1, $levelID, 1, 5, 14, 6, $accountID, 2, 12, $commentarray[1]);
				$query = $db->prepare("UPDATE levels SET extID=:extID, userID=:userID, userName=:userName WHERE levelID=:levelID");
				$query->execute([':extID' => $targetAcc, ':userID' => $userID, ':userName' => $commentarray[1], ':levelID' => $levelID]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('7', :value, :levelID, :timestamp, :id)");
				$query->execute([':value' => $commentarray[1], ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
				if($lvlstars > 0){ $gs->updatecp(0, $lvlUserID); $gs->updatecp(0, $userID); }
				return true;
			}


			//SET LEVEL LENGTH
			if(substr($comment,0,7) == '!length'){
				if(empty($commentarray[1])){ return false; }
				switch($commentarray[1]){
				case "tiny": $setlength = 0;
				break;
				case "short": $setlength = 1;
				break;
				case "medium": $setlength = 2;
				break;
				case "long": $setlength = 3;
				break;
				case "xl": $setlength = 4;
				break;
				default: return false;
				break;
				}
				$query = $db->prepare("UPDATE levels SET levelLength=:setlength WHERE levelID=:levelID");
				$query->execute([':setlength' => $setlength, ':levelID' => $levelID]);
				return true;
			}

			//SET LEVEL CP COUNT - Disabled: CP is now calculated dynamically from starFeatured and starEpic
			//if(substr($comment,0,3) == '!cp'){
			//	$cpCount = $commentarray[1];
			//	if(!is_numeric($cpCount)){ return false; }
			//	$query = $db->prepare("UPDATE levels SET cpCount = :cpValue WHERE levelID=:levelID");
			//	$query->execute([':cpValue' => $cpCount, ':levelID' => $levelID]);
			//	$gs->updatecp(0, $lvlUserID);
			//	return true;
			//}
		}

		//----------------
		//----------------
		//>ELDER MOD COMMANDS
		//----------------
		//----------------
		if($gs->checkPermission($accountID, "elderTools")){
			//daily
			if(substr($comment,0,6) == '!daily'){
				/*
				$query = $db->prepare("SELECT count(*) FROM dailyfeatures WHERE levelID = :level AND type = 0");
					$query->execute([':level' => $levelID]);
				if($query->fetchColumn() != 0){
					return false;
				}
				*/
				$query = $db->prepare("SELECT timestamp FROM dailyfeatures WHERE timestamp >= :tomorrow AND type = 0 ORDER BY timestamp DESC LIMIT 1");
				$query->execute([':tomorrow' => strtotime("tomorrow 00:00:00")]);
				if($query->rowCount() == 0){
					$timestamp = strtotime("tomorrow 00:00:00");
				}else{
					$timestamp = $query->fetchColumn() + 86400;
				}
				$query = $db->prepare("INSERT INTO dailyfeatures (levelID, timestamp, type) VALUES (:levelID, :uploadDate, 0)");
					$query->execute([':levelID' => $levelID, ':uploadDate' => $timestamp]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account, value2, value4) VALUES ('5', :value, :levelID, :timestamp, :id, :dailytime, 0)");
				$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID, ':dailytime' => $timestamp]);
				$date = date("d/m/Y", $timestamp - 1);
				$dis->discordNotifyNew(1, $levelID, 1, 4, 11, 6, $accountID, 1, 0, $date);
				return true;
			}
			//weekly
			if(substr($comment,0,7) == '!weekly'){
				//$query = $db->prepare("SELECT count(*) FROM dailyfeatures WHERE levelID = :level AND type = 1");
				//$query->execute([':level' => $levelID]);
				//if($query->fetchColumn() != 0){
				//	return false;
				//}
				$query = $db->prepare("SELECT timestamp FROM dailyfeatures WHERE timestamp >= :tomorrow AND type = 1 ORDER BY timestamp DESC LIMIT 1");
					$query->execute([':tomorrow' => strtotime("next monday")]);
				if($query->rowCount() == 0){
					$timestamp = strtotime("next monday");
				}else{
					$timestamp = $query->fetchColumn() + 604800;
				}
				$query = $db->prepare("INSERT INTO dailyfeatures (levelID, timestamp, type) VALUES (:levelID, :uploadDate, 1)");
				$query->execute([':levelID' => $levelID, ':uploadDate' => $timestamp]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account, value2, value4) VALUES ('5', :value, :levelID, :timestamp, :id, :dailytime, 1)");
				$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID, ':dailytime' => $timestamp]);
				$date = date("d/m/Y", $timestamp - 1);
				$dis->discordNotifyNew(1, $levelID, 1, 4, 12, 6, $accountID, 1, 0, $date);
				return true;
			}			
		}
		//----------------
		//----------------
		//HEAD MOD COMMANDS
		//----------------
		//----------------
		if($gs->checkPermission($accountID, "headTools")){
				//unrate level
				if(substr($comment,0,7) == '!unrate'){
					$query = $db->prepare("UPDATE levels SET starFeatured='0', starEpic='0', starStars='0', starCoins='0', starDemon='0', starDemonDiff='0' WHERE levelID=:levelID");
					$query->execute([':levelID' => $levelID]);
					$gs->updatecp(0, $lvlUserID);
					$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('16', :value, :levelID, :timestamp, :id)");
					$query->execute([':value' => "0", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
					$dis->discordNotifyNew(1, $levelID, 1, 2, 3, 3, $accountID, 1, 0, 0);
					return true;
				}
				//old rates
				if($lvlstars > 0 AND $lvlLength > 1 AND $timerated < 86400 OR $gs->checkPermission($accountID, "adminTools")){
					//feature command
					if(substr($comment,0,8) == '!feature' OR substr($comment,0,5) == '!feat'){
						$query = $db->prepare("UPDATE levels SET starFeatured='1' WHERE levelID=:levelID");
						$query->execute([':levelID' => $levelID]);
						$gs->updatecp(0, $lvlUserID);
						$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('2', :value, :levelID, :timestamp, :id)");
						$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
						$dis->discordNotifyNew(1, $levelID, 1, 2, 5, 1, $accountID, 1, 0, 0);
						return true;
					}
					if(substr($comment,0,8) == '!unfeat'){
						$query = $db->prepare("UPDATE levels SET starFeatured='0', starEpic='0' WHERE levelID=:levelID");
						$query->execute([':levelID' => $levelID]);
						$gs->updatecp(0, $lvlUserID);
						$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('2', :value, :levelID, :timestamp, :id)");
						$query->execute([':value' => "0", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
						$dis->discordNotifyNew(1, $levelID, 1, 2, 6, 4, $accountID, 1, 0, 0);
						return true;
					}
					//epic command
					if(substr($comment,0,5) == '!epic'){
						$query = $db->prepare("UPDATE levels SET starEpic='1', starFeatured='1' WHERE levelID=:levelID");
						$query->execute([':levelID' => $levelID]);
						$gs->updatecp(0, $lvlUserID);
						$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('4', :value, :levelID, :timestamp, :id)");
						$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
						$dis->discordNotifyNew(1, $levelID, 1, 2, 7, 1, $accountID, 1, 0, 0);
						return true;
					}
					if(substr($comment,0,7) == '!unepic'){
						$query = $db->prepare("UPDATE levels SET starEpic='0' WHERE levelID=:levelID");
						$query->execute([':levelID' => $levelID]);
						$gs->updatecp(0, $lvlUserID);
						$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('4', :value, :levelID, :timestamp, :id)");
						$query->execute([':value' => "0", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
						$dis->discordNotifyNew(1, $levelID, 1, 2, 8, 4, $accountID, 1, 0, 0);
						return true;
					}
					//legendary command
					if(substr($comment,0,10) == '!legendary'){
						$query = $db->prepare("UPDATE levels SET starEpic='2', starFeatured='1' WHERE levelID=:levelID");
						$query->execute([':levelID' => $levelID]);
						$gs->updatecp(0, $lvlUserID);
						$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('4', :value, :levelID, :timestamp, :id)");
						$query->execute([':value' => "3", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
						$dis->discordNotifyNew(1, $levelID, 1, 2, 28, 1, $accountID, 1, 0, 0);
						return true;
					}
					//mythic command
					if(substr($comment,0,7) == '!mythic'){
						$query = $db->prepare("UPDATE levels SET starEpic='3', starFeatured='1' WHERE levelID=:levelID");
						$query->execute([':levelID' => $levelID]);
						$gs->updatecp(0, $lvlUserID);
						$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('4', :value, :levelID, :timestamp, :id)");
						$query->execute([':value' => "4", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
						$dis->discordNotifyNew(1, $levelID, 1, 2, 29, 1, $accountID, 1, 0, 0);
						return true;
					}
					//verify coins command
					if(substr($comment,0,7) == '!verify' OR substr($comment,0,9) == '!unverify'){
						if(substr($comment,0,7) == '!verify'){
							$starCoins = 1; $v1 = 9; $v2 = 2;
						}else{
							$starCoins = 0; $v1 = 10; $v2 = 4;
						}
						$query = $db->prepare("UPDATE levels SET starCoins=:starCoins WHERE levelID = :levelID");
						$query->execute([':levelID' => $levelID, ':starCoins' => $starCoins]);
						$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('3', :value, :levelID, :timestamp, :id)");
						$query->execute([':value' => "1", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
						$dis->discordNotifyNew(1, $levelID, 1, 2, $v1, $v2, $accountID, 1, 0, 0);
						return true;
					}
				}
		}
		//----------------
		//----------------
		//>PUBLIC COMMANDS
		//----------------
		//----------------
		if($gs->checkPermission($accountID, "publicTools") AND $lvlExtID == $accountID OR $gs->checkPermission($accountID, "adminTools")){
			//prevent rated level modify
			if($lvlstars == 0 OR  $gs->checkPermission($accountID, "adminTools")){
				//rename level
				if(substr($comment,0,7) == '!rename'){
					$name = $ep->remove(str_replace("!rename ", "", $comment));
					$query = $db->prepare("UPDATE levels SET levelName=:levelName WHERE levelID=:levelID");
					$query->execute([':levelID' => $levelID, ':levelName' => $name]);
					$query = $db->prepare("INSERT INTO modactions (type, value, timestamp, account, value3) VALUES ('8', :value, :timestamp, :id, :levelID)");
					$query->execute([':value' => $name, ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
					return true;
				}
				//unlisted command
				if(substr($comment,0,7) == '!unlist' OR substr($comment,0,7) == '!public'){
					if(substr($comment,0,7) == '!unlist') { $unlisted = 1; } else { $unlisted = 0; }
					$query = $db->prepare("UPDATE levels SET unlisted=:unlisted WHERE levelID=:levelID");
					$query->execute([':levelID' => $levelID, ':unlisted' => $unlisted]);
					$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('12', :value, :levelID, :timestamp, :id)");
					$query->execute([':value' => "0", ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
					return true;
				}
			}
			//password command
			if(substr($comment,0,5) == '!pass'){
				$pass = $ep->remove(str_replace("!pass ", "", $comment));
				if(is_numeric($pass)){
					$pass = sprintf("%06d", $pass);
					if($pass == "000000"){
						$pass = "";
					}
					$pass = "1".$pass;
					$query = $db->prepare("UPDATE levels SET password=:password WHERE levelID=:levelID");
					$query->execute([':levelID' => $levelID, ':password' => $pass]);
					$query = $db->prepare("INSERT INTO modactions (type, value, timestamp, account, value3) VALUES ('9', :value, :timestamp, :id, :levelID)");
					$query->execute([':value' => $pass, ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
					return true;
				}
			}
			//song command
			if(substr($comment,0,5) == '!song'){
				$song = $ep->remove(str_replace("!song ", "", $comment));
				if(is_numeric($song)){
					$query = $db->prepare("UPDATE levels SET songID=:song WHERE levelID=:levelID");
					$query->execute([':levelID' => $levelID, ':song' => $song]);
					$query = $db->prepare("INSERT INTO modactions (type, value, timestamp, account, value3) VALUES ('16', :value, :timestamp, :id, :levelID)");
					$query->execute([':value' => $song, ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
					return true;
				}
			}
			//description command
			if(substr($comment,0,5) == '!desc'){
				$desc = base64_encode($ep->remove(str_replace("!desc ", "", $comment)));
				$query = $db->prepare("UPDATE levels SET levelDesc=:desc WHERE levelID=:levelID");
				$query->execute([':levelID' => $levelID, ':desc' => $desc]);
				$query = $db->prepare("INSERT INTO modactions (type, value, timestamp, account, value3) VALUES ('13', :value, :timestamp, :id, :levelID)");
				$query->execute([':value' => $desc, ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
				return true;
			}
			//ldm command
			if(substr($comment,0,4) == '!ldm' OR substr($comment,0,6) == '!unldm'){
				if(substr($comment,0,4) == '!ldm'){ $ldm = 1; } else { $ldm = 0; }
				$query = $db->prepare("UPDATE levels SET isLDM=:ldm WHERE levelID=:levelID");
				$query->execute([':levelID' => $levelID, ':ldm' => $ldm]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('14', :value, :levelID, :timestamp, :id)");
				$query->execute([':value' => $ldm, ':timestamp' => $uploadDate, ':id' => $accountID, ':levelID' => $levelID]);
				return true;
			}
		}
		return false;
	}

	public static function doProfileCommands($accountID, $userID, $command){
		include dirname(__FILE__)."/../lib/connection.php";
		require_once "../lib/exploitPatch.php";
		require_once "../lib/mainLib.php";
		require_once "../discord/discordLib.php";
		require_once "../lib/XORCipher.php";
		$xc = new XORCipher();
		$dis = new discordLib();
		$ep = new exploitPatch();
		$gs = new mainLib();
		$commentarray = explode(' ', $command);
		//----------------
		//----------------
		//ADMIN COMMANDS
		//----------------
		//----------------
		if($gs->checkPermission($accountID, "adminTools")){
			
			if(substr($command, 0, 8) == '!setrank'){
				$targetUser = $commentarray[1];
				$targetRank = $commentarray[2];
				$query = $db->prepare("SELECT accountID FROM accounts WHERE userName = :commentarray OR accountID = :commentarray LIMIT 1");
				$query->execute([':commentarray' => $targetUser]);
				if($query->rowCount() == 0){
					return false;
				}
				$targetAccID = $query->fetchColumn();
				switch($targetRank){
					case "demote": $roleID = 1; break;
					case "mod": $roleID = 2; break;
					case "head": $roleID = 3; break;
					case "elder": $roleID = 4; break;
					case "admin": $roleID = 5; break;
				}
				if($roleID==0){
					return false;
				}
				$query = $db->prepare("SELECT accountID FROM roleassign WHERE accountID=:accountID");
				$query->execute([':accountID' => $targetAccID]);
				if($query->rowCount() == 0){
					$titleID = 16; //discord
					$query = $db->prepare("INSERT INTO roleassign (roleID, accountID) VALUES (:roleID, :accountID)");
					$query->execute([':roleID' => $roleID, ':accountID' => $targetAccID]);
				}else{
					$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID=:accountID");
					$query->execute([':accountID' => $targetAccID]);
					$readyrank = $query->fetchColumn();
					if($roleID==$readyrank){
						return false;
					}
					if($readyrank < $roleID){
						$titleID = 16;
					}else{
						$titleID = 26;
					}
					$query = $db->prepare("UPDATE roleassign SET roleID=:roleID WHERE accountID=:accountID");
					$query->execute([':roleID' => $roleID, ':accountID' => $targetAccID]);
				}
				if($roleID==1){
					$dis->discordNotifyNew(1, $targetAccID, 2, 1, 17, 7, $accountID, 0, 0, 0);
				}else{
					$dis->discordNotifyNew(1, $targetAccID, 2, 1, $titleID, 7, $accountID, 0, 0, 0);
				}
				return true;
			}
			
			//updatecpall command - Admin only: updates CP for all users
			if(substr($command, 0, 12) == '!updatecpall'){
				//Execute update in background to avoid timeout
				if(function_exists("set_time_limit")) set_time_limit(0);
				
				$result = $gs->updateAllCPs();
				
				//Send Discord notification
				$dis->notifyUpdateCPAll($result);
				
				return true;
			}
		}
		
		//----------------
		//----------------
		//USER COMMANDS (Own profile)
		//----------------
		//----------------
		//updatecp command - Users can update their own CP
		if(substr($command, 0, 9) == '!updatecp'){
			//Get userID from accountID
			$query = $db->prepare("SELECT userID FROM users WHERE extID = :accountID LIMIT 1");
			$query->execute([':accountID' => $accountID]);
			if($query->rowCount() == 0){
				return false;
			}
			$targetUserID = $query->fetchColumn();
			
			//Update CP for the user
			$gs->updatecp(0, $targetUserID);
			return true;
		}
	}

	public static function doListCommands($accountID, $command, $listID) {
		if(substr($command,0,1) != '!') return false;
		$listID = $listID * -1;
		include dirname(__FILE__)."/../lib/connection.php";
		require_once "../lib/exploitPatch.php";
		require_once "../lib/mainLib.php";
		$gs = new mainLib();
		$carray = explode(' ', $command);
		switch($carray[0]) {
			case '!r':
			case '!rate':
				$getList = $db->prepare('SELECT * FROM lists WHERE listID = :listID');
				$getList->execute([':listID' => $listID]);
				$getList = $getList->fetch();
				$reward = ExploitPatch::number($carray[1]);
				$diff = ExploitPatch::charclean($carray[2]);
				$featured = is_numeric($carray[3]) ? ExploitPatch::number($carray[3]) : ExploitPatch::number($carray[4]);
				$count = is_numeric($carray[3]) ? ExploitPatch::number($carray[4]) : ExploitPatch::number($carray[5]);
				if(empty($count)) {
					$levelsCount = $getList['listlevels'];
					$count = count(explode(',', $levelsCount));
				}
				if(!is_numeric($diff)) {
					$diff = strtolower($diff);
					if(isset($carray[3]) AND strtolower($carray[3]) == "demon") {
						$diffList = ['easy' => 1, 'medium' => 2, 'hard' => 3, 'insane' => 4, 'extreme' => 5];
						$diff = 5+$diffList[$diff];
					} else {
						$diffList = ['na' => -1, 'auto' => 0, 'easy' => 1, 'normal' => 2, 'hard' => 3, 'harder' => 4, 'demon' => 5];
						$diff = $diffList[$diff];
					}
				}
				if(!isset($diff)) $diff = $getList['starDifficulty'];
				if($gs->checkPermission($accountID, "commandRate")) {
					$query = $db->prepare("UPDATE lists SET starStars = :reward, starDifficulty = :diff, starFeatured = :feat, countForReward = :count WHERE listID = :listID");
					$query->execute([':listID' => $listID, ':reward' => $reward, ':diff' => $diff, ':feat' => $featured, ':count' => $count]);
					$query = $db->prepare("INSERT INTO modactions (type, value, value2, value3, timestamp, account) VALUES ('30', :value, :value2, :listID, :timestamp, :id)");
					$query->execute([':value' => $reward, ':value2' => $diff, ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
				} elseif($gs->checkPermission($accountID, "actionSuggestRating")) {
					$query = $db->prepare("INSERT INTO suggest (suggestBy, suggestLevelId, suggestDifficulty, suggestStars, suggestFeatured, timestamp) VALUES (:accID, :listID, :diff, :reward, :feat, :time)");
					$query->execute([':listID' => $listID*-1, ':reward' => $reward, ':diff' => $diff, ':accID' => $accountID, ':feat' => $featured, ':time' => time()]);
					$query = $db->prepare("INSERT INTO modactions (type, value, value2, value3, timestamp, account) VALUES ('31', :value, :value2, :listID, :timestamp, :id)");
					$query->execute([':value' => $reward, ':value2' => $diff, ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
				} else return false;
				break;
			case '!f':
			case '!feature':
				if(!$gs->checkPermission($accountID, "commandFeature")) return false;
				if(!isset($carray[1])) $carray[1] = 1;
				$query = $db->prepare("UPDATE lists SET starFeatured = :feat WHERE listID=:listID");
				$query->execute([':listID' => $listID, ':feat' => $carray[1]]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('32', :value, :listID, :timestamp, :id)");
				$query->execute([':value' => $carray[1], ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
				break;
			case '!un':
			case '!unlist':
				$accCheck = $gs->getListOwner($listID);
				if(!$gs->checkPermission($accountID, "commandUnlistAll") AND $accountID != $accCheck) return false;
				if(!isset($carray[1])) $carray[1] = 1;
				$query = $db->prepare("UPDATE lists SET unlisted = :unlisted WHERE listID=:listID");
				$query->execute([':listID' => $listID, ':unlisted' => $carray[1]]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('33', :value, :listID, :timestamp, :id)");
				$query->execute([':value' => $carray[1], ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
				break;
			case '!d':
			case '!delete':
				if(!$gs->checkPermission($accountID, "commandDelete")) return false;
				$query = $db->prepare("DELETE FROM lists WHERE listID = :listID");
				$query->execute([':listID' => $listID]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('34', 0, :listID, :timestamp, :id)");
				$query->execute([':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
				break;
			case '!acc':
			case '!setacc':
				if(!$gs->checkPermission($accountID, "commandSetacc")) return false;
				if(is_numeric($carray[1])) $acc = ExploitPatch::number($carray[1]);
				else $acc = $gs->getAccountIDFromName(ExploitPatch::charclean($carray[1]));
				if(empty($acc)) return false;
				$query = $db->prepare("UPDATE lists SET accountID = :accID WHERE listID=:listID");
				$query->execute([':listID' => $listID, ':accID' => $acc]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('35', :value, :listID, :timestamp, :id)");
				$query->execute([':value' => $acc, ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
				break;
			case '!re':
			case '!rename':
				$accCheck = $gs->getListOwner($listID);
				if(!$gs->checkPermission($accountID, "commandRenameAll") AND $accountID != $accCheck) return false;
				$carray[0] = '';
				$oldName = $gs->getListName($listID);
				$name = trim(ExploitPatch::charclean(implode(' ', $carray)));
				$query = $db->prepare("UPDATE lists SET listName = :name WHERE listID = :listID");
				$query->execute([':listID' => $listID, ':name' => $name]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value2, value3, timestamp, account) VALUES ('36', :value, :value2, :listID, :timestamp, :id)");
				$query->execute([':value' => $name, ':value2' => $oldName, ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
				break;
			case '!desc':
			case '!description':
				$accCheck = $gs->getListOwner($listID);
				if(!$gs->checkPermission($accountID, "commandDescriptionAll") AND $accountID != $accCheck) return false;
				$carray[0] = '';
				$name = base64_encode(trim(ExploitPatch::charclean(implode(' ', $carray))));
				$query = $db->prepare("UPDATE lists SET listDesc = :name WHERE listID = :listID");
				$query->execute([':listID' => $listID, ':name' => $name]);
				$query = $db->prepare("INSERT INTO modactions (type, value, value3, timestamp, account) VALUES ('37', :value, :listID, :timestamp, :id)");
				$query->execute([':value' => $name, ':timestamp' => time(), ':id' => $accountID, ':listID' => $listID]);
				break;
		}
		return true;
	}
}
?>
