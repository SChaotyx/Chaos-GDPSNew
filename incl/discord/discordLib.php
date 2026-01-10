<?php
class discordLib {
	public function discordNotify($id, $data_string){
		include __DIR__ . "/../../config/discord.php";
		if($discordEnabled != 1){
			return false;
		}
		switch($id){
			case 1: $channelID = $channel1; //modactions
			break;
			case 2: $channelID = $channel2; //publicactions
			break;
			case 3: $channelID = $channel3; //botspam cmd
			break;
			default: $channelID = $id;
			break;
		}
		$url = "https://discordapp.com/api/v6/channels/$channelID/messages";
		//echo $url;
		$crl = curl_init($url);
		$headr = array();
		$headr['User-Agent'] = 'Chaos-Bot, 1.1)';
		curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "POST");                                                                 
		curl_setopt($crl, CURLOPT_POSTFIELDS, $data_string);
		$headr[] = 'Content-type: application/json';
		$headr[] = 'Authorization: Bot '.$bottoken;
		curl_setopt($crl, CURLOPT_HTTPHEADER,$headr);
		curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1); 
		$response = curl_exec($crl);
		curl_close($crl);
	return $response;
	}
	public function discordNotifyNew($id, $objectID, $objectType, $embedID, $title, $color, $autorID, $thumbType, $thumbID, $extra){
		//$dis->discordNotifyNew(channel, objid, objtype, embedid, title, color, autorid, thumbtype, thumbid, extra);
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php";
		if($discordEnabled != 1){
			return false;
		}
		switch($id){
			case 1: $channelID = $channel1; //mod actions
			break;
			case 2: $channelID = $channel2; //public actions
			break;
			case 3: $channelID = $channel3; //botspam cmd
			break;
			default: $channelID = $id;
			break;
		}
		if($objectType == 1){
			switch($thumbType){
				case 1: $data_string = $this->embedContent($embedID, $this->title($title), $this->diffthumbnail($objectID), $this->embedColor($color), $this->modBadge($autorID), $this->footerText($autorID), $objectID, $extra);
				break;
				case 2: $data_string = $this->embedContent($embedID, $this->title($title), $this->thumbnail($thumbID), $this->embedColor($color), $this->modBadge($autorID), $this->footerText($autorID), $objectID, $extra);
				break;
				case 3: $data_string = $this->embedContent($embedID, $this->title($title), $this->iconSent($extra, $thumbID), $this->embedColor($color), $this->modBadge($autorID), $this->footerText($autorID), $objectID, $extra);
				break;
			}
			$query = $db->prepare("SELECT extID FROM levels WHERE levelID = :id");
			$query->execute([':id' => $objectID]);
			$objectID = $query->fetchColumn();
		}
		if($objectType == 2){
			$data_string = $this->accEmbedContent($embedID, $this->title($title), $this->iconProfile($objectID), $this->embedColor($color), $this->modBadge($autorID), $this->footerText($autorID), $objectID, $extra);
		}
		//DM NOTIFY
		$query = $db->prepare("SELECT discordID, discordLinkReq FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $objectID]);
		$result = $query->fetchAll();
		foreach($result as &$discordData){
		$discordID = $discordData["discordID"];
		$discordReq = $discordData["discordLinkReq"];
		}
		if($discordReq == 1){
			switch($title){
				case 1:
				case 2:
				case 3:
				case 4:
				case 5:
				case 6:
				case 7:
				case 8:
				case 9:
				case 10:
				case 11:
				case 12:
				case 16:
				case 17:
				case 26:
				case 27:
				$this->discordDMNotify($discordID, $data_string); 
				break;
			}
		}
		$url = "https://discordapp.com/api/v6/channels/$channelID/messages";
		//echo $url;
		$crl = curl_init($url);
		$headr = array();
		$headr['User-Agent'] = 'Chaos-Bot, 1.1)';
		curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "POST");                                                                 
		curl_setopt($crl, CURLOPT_POSTFIELDS, $data_string);
		$headr[] = 'Content-type: application/json';
		$headr[] = 'Authorization: Bot '.$bottoken;
		curl_setopt($crl, CURLOPT_HTTPHEADER,$headr);
		curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1); 
		$response = curl_exec($crl);
		curl_close($crl);
		return $response;
	}
	public function discordDMNotify($discordID, $data_string){
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php";
		if($discordEnabled != 1){
			return false;
		}
		//FIND USER CHANNEL
		$data = array("recipient_id" => $discordID);                                                                    
		$data_string2 = json_encode($data);
		$url = "https://discordapp.com/api/v6/users/@me/channels";
		$crl = curl_init($url);
		$headr = array();
		$headr['User-Agent'] = 'CvoltonGDPS (http://pi.michaelbrabec.cz:9010, 1.0)';
		curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
		curl_setopt($crl, CURLOPT_POSTFIELDS, $data_string2);
		$headr[] = 'Content-type: application/json';
		$headr[] = 'Authorization: Bot '.$bottoken;
		curl_setopt($crl, CURLOPT_HTTPHEADER,$headr);
		curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1); 
		$response = curl_exec($crl);
		curl_close($crl);
		$responseDecode = json_decode($response, true);
		//SEND MSG		
		$discordUserID = $responseDecode["id"];
		$url = "https://discordapp.com/api/v6/channels/".$discordUserID."/messages";
		$crl = curl_init($url);
		$headr = array();
		$headr['User-Agent'] = 'CvoltonGDPS (http://pi.michaelbrabec.cz:9010, 1.0)';
		curl_setopt($crl, CURLOPT_CUSTOMREQUEST, "POST");                                                                     
		curl_setopt($crl, CURLOPT_POSTFIELDS, $data_string);
		$headr[] = 'Content-type: application/json';
		$headr[] = 'Authorization: Bot '.$bottoken;
		curl_setopt($crl, CURLOPT_HTTPHEADER,$headr);
		curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1); 
		$response = curl_exec($crl);
		curl_close($crl);
		return $response;	
	}
	public function publicAction($objectType, $objData, $action){
		//objectID 0 = level, 1 = profile
		include __DIR__ . "/../../config/discord.php";
		include __DIR__ . "/../discord/emojis.php";
		//
		//LEVELS
		//
		if($objectType == 0){
			$levelName = $objData["levelName"];
			$userName = $objData["userName"];
			$original = $objData["original"];
			$objects = $objData["objects"];
			if($original > 0){ $copy = $icon_copy; }else{ $copy = ""; }
			$desc = "$icon_play **__".$levelName."__** by $userName $copy";
			$levelInfo = "levelID: ".$objData["levelID"];
			switch($action){
				case 1: $action = "$icon_info New recent level uploaded!!!";
				break;
				case 2: $action = "$icon_info Level Updated!!!";
				break;
			}
			$data = array(
				'embed'=> [
					"title"=> $action,
					"description"=> $desc,
					"footer"=> ["icon_url"=> ($iconhost."misc/gdpsbot.png"), "text"=> $levelInfo],
				]);
			$data_string = json_encode($data);
			$this->discordNotify(2, $data_string);
		}
		//
		//PROFILES
		//
		if($objectType == 1){
			$user = ":chart_with_upwards_trend: __**".$objData["userName"]."'s**__ Stats";

			$stats = "$icon_star `".$this->charCount($objData["stars"])."` ─> `".$this->charCount2($this->ispositive($objData["starsDiff"]).$objData["starsDiff"])."`\n".
				     "$icon_diamond `".$this->charCount($objData["diamonds"])."` ─> `".$this->charCount2($this->ispositive($objData["diamondsDiff"]).$objData["diamondsDiff"])."`\n".
				  	 "$icon_secretcoin `".$this->charCount($objData["coins"])."` ─> `".$this->charCount2($this->ispositive($objData["coinsDiff"]).$objData["coinsDiff"])."`\n".
				  	 "$icon_verifycoins `".$this->charCount($objData["uc"])."` ─> `".$this->charCount2($this->ispositive($objData["ucDiff"]).$objData["ucDiff"])."`\n".
					 "$icon_demon `".$this->charCount($objData["demons"])."` ─> `".$this->charCount2($this->ispositive($objData["demonsDiff"]).$objData["demonsDiff"])."`";
					   
			$statsDiff = "$icon_star `".$this->charCount($objData["starsDiff"])."`\n$icon_diamond `".$this->charCount($objData["diamondsDiff"])."`\n$icon_secretcoin `".$this->charCount($objData["coinsDiff"])."`\n$icon_verifycoins `".$this->charCount($objData["ucDiff"])."`\n$icon_demon `".$this->charCount($objData["demonsDiff"])."`";
			$userInfo = "userID: ".$objData["userID"];

			//$mainIcon = $this->iconProfile($objData["extID"]);

			$data = array(
				'embed'=> [
					"title"=> "$icon_info User Stats Updated!!!",
					"description" => $user."\n"."\n".$stats,
					//"fields" => [
					//	["name" => "────────────", "value" => $stats],
					//],
					"footer"=> ["icon_url"=> ($iconhost."misc/gdpsbot.png"), "text"=> $userInfo],
					//"thumbnail"=> ["url"=> ($iconhost.$mainIcon)],
				]);
			$data_string = json_encode($data);
			$this->discordNotify(2, $data_string);
		}
	}
	public function title($id){
		include __DIR__ . "/../discord/emojis.php";
		switch($id){
		case 1: $title = "$icon_star New Rated Level!!!";
			break;
		case 2: $title = "$icon_approved New Approved Level!";
			break;
		case 3: $title = "$icon_failed Command - Unrate";
			break;
		case 4: $title = "$icon_like Command - Played";
			break;
		case 5: $title = "$icon_cp Command - Feature";
			break;
		case 6: $title = "$icon_failed Command - Unfeat";
			break;
		case 7: $title = "$icon_cp Command - Epic";
			break;
		case 8: $title = "$icon_failed Command - Unepic";
			break;
		case 9: $title = "$icon_info Command - Verifycoins";
			break;
		case 10: $title = "$icon_info Command - Unverifycoins";
			break;
		case 11: $title = "$icon_daily Command - Daily";
			break;
		case 12: $title = "$icon_weekly Command - Weekly";
			break;
		case 13: $title = "$icon_cross Command - Delete";
			break;
		case 14: $title = "$icon_info Command - Setacc";
			break;
		case 15: $title = "$icon_succes Rated Demon!!!";
		    break;
		case 16: $title = "$icon_modstar User Promoted!!!";
		    break;
		case 17: $title = "$icon_brokenmodstar User Demoted...";
		    break;
		case 18: $title = "$icon_info User Stats Updated!!!";
			break;
		case 19: $title = "$icon_info Level Updated!!!";
			break;
		case 20: $title = "$icon_info New recent level uploaded!!!";
			break;
		case 21: $title = "$icon_search Search result.";
			break;
		case 22: $title = "$icon_profile User profile";
			break;
		case 23: $title = "$icon_daily Current Daily Level";
			break;
		case 24: $title = "$icon_weekly Current Weekly Level";
			break;
		case 25: $title = "$icon_profile Server Stats";
			break;
		case 26: $title = "$icon_brokenmodstar Rank degraded...";
			break;
		case 27: $title = "$icon_succes Your account has been linked!!!";
		    break;
		}
	return $title;
	}
	public function embedContent($id, $title, $thumbnail, $color, $footicon, $foottext, $levelID, $stars){
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php";
		include __DIR__ . "/../discord/emojis.php";
		//GETTING LEVEL DATA
		$query = $db->prepare("SELECT * FROM levels WHERE levelID = :lvlid");
		$query->execute([':lvlid' => $levelID]);
		$result = $query->fetchAll();
		foreach($result as &$level){
			$levelName = $level["levelName"];
			$userName = $level["userName"];		
			$levelDesc = $level["levelDesc"];
			$desc = base64_decode($levelDesc);
			$coins = $level["coins"];
			$starCoins = $level["starCoins"];
			$downloads = $level["downloads"];
			$likes = $level["likes"];		
			$levelLength = $level["levelLength"];
			$levelVersion = $level["levelVersion"];
			$objects = $level["objects"];
			$requestedStars = $level["requestedStars"];
			$original = $level["original"];
			$originalReup = $level["originalReup"];
			$audioTrack = $level["audioTrack"];
			$songID = $level["songID"];
			$extID = $level["extID"];
			$cpCount = $level["cpCount"];
			if($songID == 0){
				$songinfo = "";
				switch($audioTrack){
					case 0: $oficialsong = "__**Stereo Madness**__ by **ForeverBound**";
					break;
					case 1: $oficialsong = "__**Back on Track**__ by **DJVI**";
					break;
					case 2: $oficialsong = "__**Polargeist**__ by **Step**";
					break;
					case 3: $oficialsong = "__**Dry Out**__ by **DJVI**";
					break;
					case 4: $oficialsong = "__**Base after Base**__ by **DJVI**";
					break;
					case 5: $oficialsong = "__**Can't Let Go**__ by **DJVI**";
					break;
					case 6: $oficialsong = "__**Jumper**__ by **Waterflame**";
					break;
					case 7: $oficialsong = "__**Time Machine**__ by **Waterflame**";
					break;
					case 8: $oficialsong = "__**Cycles**__ by **DJVI**";
					break;
					case 9: $oficialsong = "__**xStep**__ by **DJVI**";
					break;
					case 10: $oficialsong = "__**Clutterfunk**__ by **Waterflame**";
					break;
					case 11: $oficialsong = "__**Theory of Everything**__ by **DJ Nate**";
					break;
					case 12: $oficialsong = "__**Electroman Adventures**__ by **Waterflame**";
					break;
					case 13: $oficialsong = "__**Club Step**__ by **DJ Nate**";
					break;
					case 14: $oficialsong = "__**Electrodynamix**__ by **DJ Nate**";
					break;
					case 15: $oficialsong = "__**Hexagon Force**__ by **Waterflame**";
					break;
					case 16: $oficialsong = "__**Blast Processing**__ by **Waterflame**";
					break;
					case 17: $oficialsong = "__**Theory of Everything 2**__ by **DJ Nate**";
					break;
					case 18: $oficialsong = "__**Geometrical Dominator**__ by **Waterflame**";
					break;
					case 19: $oficialsong = "__**Deadlocked**__ by **F-777**";
					break;
					case 20: $oficialsong = "__**Fingerbang**__ by **MDK**";
					break;
				}
				$songdesc = "$oficialsong";
			}else{
				$query = $db->prepare("SELECT ID FROM songs WHERE ID=:ID");
				$query->execute([':ID' => $songID]);
				if($query->rowCount() == 0){
					$songdesc = "*unknown*";
				}else{
					$query = $db->prepare("SELECT * FROM songs WHERE ID = :id");
					$query->execute([':id' => $songID]);
					$result2 = $query->fetchAll();
					foreach($result2 as &$song){
						$songname = $song["name"];
						$songauthor = $song["authorName"];
						$songsize = $song["size"];	
						$songdesc =  "__".$songname."__ by $songauthor";
						if($songID < 5000000){
							$downloadmp3 = rawurldecode($song["download"]);
							$songinfo = 
								"SongID: $songID - Size: ".$songsize."MB\n".
								$icon_play.'[Play on Newgrounds](https://www.newgrounds.com/audio/listen/'.$songID.')';
						}else{
							$downloadmp3 = $song["download"];
							$songinfo = 
								"SongID: $songID - Size: ".$songsize."MB";
								//$icon_download1.'[Download MP3]('.$downloadmp3.')'
						}
					}
				}
			}
			//EMPTY DESCRIPTION
			if(empty($levelDesc)){
			$desc = " No description provided ";
			}
			//COINS
		    $coinscount = "None";
			if($starCoins == 1){
				switch($coins){
					case 1: $coinscount = "$icon_verifycoins";
					break;
					case 2: $coinscount = "$icon_verifycoins $icon_verifycoins";
					break;
					case 3: $coinscount = "$icon_verifycoins $icon_verifycoins $icon_verifycoins";
					break;
				}
			}
			if($starCoins == 0){
				switch($coins){
					case 1: $coinscount = "$icon_unverifycoins";
					break;
					case 2: $coinscount = "$icon_unverifycoins $icon_unverifycoins";
					break;
					case 3: $coinscount = "$icon_unverifycoins $icon_unverifycoins $icon_unverifycoins";
					break;
				}
			}
			//LIKE/DISLIKE ICON
			$likeicon = "$icon_like";
			if($likes < 0){
				$likeicon  = "$icon_dislike";
			}
			//LEVEL LENGTH
            switch($levelLength){
				case 0: $Length = "TINY";
				break;
				case 1: $Length = "SHORT";
				break;
				case 2: $Length = "MEDIUM";
				break;
				case 3: $Length = "LONG";
				break;
				case 4: $Length = "XL";
				break;		
			}
			//+40K OBJECTS ICON
            $overObjects = "";
			if($objects > 40000){
			$overObjects = "$icon_objecto";
			}
			//COPY LEVEL
            $copylevel = "";
            $copylevelc = "";
			if(!empty($original)){
			$copylevel = "$icon_copy**Original:** $original";
			$copylevelc = "$icon_copy";
			}
			if($original == 1){
				$copylevel = "$icon_copy**Original Reupload:** ".$originalReup;
			}
		}
		if($cpCount != 0){
			$cpCount = $this->charCount($cpCount);
			$cpCount = "$icon_cp `$cpCount`\n";
		}else{
			$cpCount = "";
		}
		$downloads = $this->charCount($downloads);
		$likes = $this->charCount($likes);
		$Length = $this->charCount($Length);
		//LEVEL DATA
		$levelby = "$icon_play __".$levelName."__ by $userName";
		$description = "**Description:** $desc";
		$usercoins = "Coins: $coinscount";
		$stats = 
		    "$icon_download2 `‌$downloads` \n $likeicon `$likes` \n $icon_length `$Length`\n".$cpCount.
		    "───────────────────\n";
		$songdata = ":musical_note: $songdesc";
		$extrainfo = 
		    $songinfo." \n".
			"───────────────────\n".
			"**Level ID:** $levelID \n".
			"**Level Version:** $levelVersion \n".
			"**Objects count:** $objects $overObjects \n".
			"**Stars requested:** $requestedStars \n".
			"$copylevel";
		$levelbyc = "$icon_play __".$levelName."__ by $userName $copylevelc $overObjects";
		$songdatac = 
		    ":musical_note: $songdesc \n".
		    "LevelID: $levelID";
		$sentstars = "Sent Stars: $stars $icon_star";
		$bar = "───────────────────";
		$statsc = "$icon_download2 `‌$downloads` \n $likeicon `$likes` \n $icon_length `$Length`\n".$cpCount;
		$dailyinqueque = "New Daily/weekly level queued!";
		$isout = "$icon_length __Is out:__ $stars";
		$oldacc = "Old Account: **$userName**";
		$levelbynew = "$icon_play __".$levelName."__ by $stars $copylevelc $overObjects";
		$levelInfo = " | Level ID: $levelID";
		//Build json
		switch($id){
			//FULL EMBED
			case 1: $data = array(
				'embed'=> [
					"title"=> $title,
				    "fields"=> [
						["name"=> $levelby, "value"=> $description],
						["name"=> $usercoins, "value"=> $stats],
						["name"=> $songdata, "value"=> $extrainfo]],					
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$levelInfo)],
					"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
				]);
			break;
			//COMPACT EMBED
			case 2: $data = array(
				'embed'=> [
					"title"=> $title,
				    "fields"=> [
						["name"=> $levelbyc, "value"=> $stats],
						["name"=> $usercoins, "value"=> $songdata]],
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$levelInfo)],
					"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
				]);
			break;
			//SENT LEVEL
			case 3: $data = array(
				'embed'=> [
					"title"=> $title,
				    "fields"=> [
						["name"=> $levelbyc, "value"=> $statsc],
						["name"=> $sentstars, "value"=> $bar],
						["name"=> $usercoins, "value"=> $songdata]],
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$levelInfo)],
					"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
				]);
			break;
			//SET NEW DAILY IN QUEQUE
			case 4: $data = array(
				'embed'=> [
					"title"=> $title,
					"description"=> $dailyinqueque,
				    "fields"=> [
						["name"=> $levelbyc, "value"=> $stats],
						["name"=> $usercoins, "value"=> $isout]],
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$levelInfo)],
					"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
				]);
			break;
			//COMMAND !SETACC
			case 5: $data = array(
				'embed'=> [
					"title"=> $title,
				    "fields"=> [
						["name"=> $levelbynew, "value"=> $stats],
						["name"=> $usercoins, "value"=> $oldacc]],
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$levelInfo)],
					"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
				]);
			break;
			//WITH USER TAG
			case 6: $data = array(
				"content"=> $stars,
				'embed'=> [
					"title"=> $title,
				    "fields"=> [
						["name"=> $levelby, "value"=> $description],
						["name"=> $usercoins, "value"=> $stats],
						["name"=> $songdata, "value"=> $extrainfo]],					
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$levelInfo)],
					"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
				]);
			break;
			//COMPACT WITH USER TAG
			case 7: $data = array(
				"content"=> $stars,
				'embed'=> [
					"title"=> $title,
				    "fields"=> [
						["name"=> $levelbyc, "value"=> $stats],
						["name"=> $usercoins, "value"=> $songdata]],
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$levelInfo)],
					"thumbnail"=> ["url"=> ($iconhost.$thumbnail)],
				]);
			break;
		}                                                    
		$data_string = json_encode($data);
		return $data_string;
	}
	public function accEmbedContent($id, $title, $thumbnail, $color, $footicon, $foottext, $targetAccID, $stars){
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php";
		include __DIR__ . "/../discord/emojis.php";
		// YOUTUBE URL, TWITTER, TWITCH AND DICORD TAG
		$query = $db->prepare("SELECT youtubeurl, twitter, twitch, discordID, discordLinkReq FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $targetAccID]);
		$result = $query->fetchAll();
		foreach($result as &$userlinks){
			$yturl = $userlinks["youtubeurl"];
			$twitter = $userlinks["twitter"];
			$twitch = $userlinks["twitch"];
			$discordID = $userlinks["discordID"];
			$discordLinkReq = $userlinks["discordLinkReq"];
		}
		if(empty($yturl)){ $yturl = ""; }else{ $yturl = "$icon_youtube [**YouTube**](https://www.youtube.com/channel/".$yturl.")\n"; }
		if(empty($twitter)){ $twitter = ""; }else{ $twitter = "$icon_twitter [**Twitter**](https://www.twitter.com/".$twitter.")\n"; }
		if(empty($twitch)){ $twitch = ""; }else{ $twitch = "$icon_twitch [**Twitch**](https://www.twitch.tv/".$twitch.")\n"; }
		if($discordLinkReq == 1){ $discord = "$icon_discord **<@$discordID>**\n"; }
		//READ TARGET USER STATS
		$query = $db->prepare("SELECT * FROM users WHERE extID = :extID");
		$query->execute([':extID' => $targetAccID]);
		//NOT USER PROFILE... (FOR "bot!account" COMMAND)
		if($query->rowCount() == 0){
			$nothing = "This account exists but does not have a profile.";
			$data = array("content"=> $nothing);                                               
			$data_string = json_encode($data);
			return $data_string;
		}
		$result = $query->fetchAll();
		foreach($result as &$userstats){
			$userstars = $userstats["stars"];
			$userdiamonds = $userstats["diamonds"];
			$userscoins = $userstats["coins"];
			$userucoins = $userstats["userCoins"];
			$userdemons = $userstats["demons"];
			$usercp = $userstats["creatorPoints"];
			$userID = $userstats["userID"];
			$targetUser = $userstats["userName"];
			//ICON DATA
			$icontype = $userstats["iconType"];
			$icon = $userstats["icon"];
			$color1 = $userstats["color1"];
			$color2 = $userstats["color2"];
			$glow = $userstats["accGlow"];
			$accIcon = $userstats["accIcon"];
			$accShip = $userstats["accShip"];
			$accBall = $userstats["accBall"];
			$accBird = $userstats["accBird"];
			$accDart = $userstats["accDart"];
			$accRobot = $userstats["accRobot"];
			$accSpider = $userstats["accSpider"];

		}
		//DETECT USER RANK
		$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :id LIMIT 1");
		$query->execute([':id' => $targetAccID]);
		if ($query->rowCount() > 0) {
			$roleID = $query->fetchColumn();
			}else{
				$roleID = 0;
			}
		switch($roleID){
			case 0: $rank = "";
			break;
			case 1: $rank = "$icon_brokenmodstar **DEMOTED :(**\n";
			break;
			case 2: $rank = "$icon_mod **MODERATOR**\n";
			break;
			case 3: $rank = "$icon_elder **ELDER MOD**\n";
			break;
			case 4: $rank = "$icon_head **HEAD MOD**\n";
			break;
			case 5: $rank = "$icon_admin **ADMIN**\n";
			break;
			case 6: $rank = "$icon_dev **DEVELOPER**\n";
			break;
			case 7: $rank = "$icon_owner **OWNER**\n";
			break;
		}
		//GET GLOBAL RANK PLAYERS
		if($userstars > 25){
		$e = "SET @rownum := 0;";
		$query = $db->prepare($e);
		$query->execute();
		$f = "SELECT rank FROM (SELECT @rownum := @rownum + 1 AS rank, extID FROM users WHERE isBanned = '0' AND gameVersion > 19 AND stars > 25 ORDER BY stars DESC) as result WHERE extID=:extid";
		$query = $db->prepare($f);
		$query->execute([':extid' => $targetAccID]);
		$global = $query->fetchColumn();;
		//TROPHY
		if($global > 1000){
			$globaltro = "$icon_globalrank";
		}
		if($global < 1001){
			$globaltro = "$icon_top1000";
		}
		if($global < 501){
			$globaltro = "$icon_top500";
		}
		if($global < 201){
			$globaltro = "$icon_top200";
		}
		if($global < 101){
			$globaltro = "$icon_top100";
		}
		if($global < 51){
			$globaltro = "$icon_top50";
		}
		if($global < 11){
			$globaltro = "$icon_top10";
		}
		if($global==1){
			$globaltro = "$icon_top1";
		}
		//PRINT
		$globalrank = "$globaltro **Global Rank:** $global \n";
		if(empty($global)){
			$globalrank = "";
		}
		}else{
			$globalrank = "";
		}
		//GET TOP CREATORS
		if($usercp > 0){
			$e = "SET @rownum := 0;";
			$query = $db->prepare($e);
			$query->execute();
			$f = "SELECT rank FROM (SELECT @rownum := @rownum + 1 AS rank, extID FROM users WHERE isCreatorBanned = '0' AND gameVersion > 19 AND creatorPoints > 0 ORDER BY creatorPoints DESC) as result WHERE extID=:extid";
			$query = $db->prepare($f);
			$query->execute([':extid' => $targetAccID]);
			$globalc = $query->fetchColumn();
			$globalcreators = "$icon_creatorrank **Creator Rank:** $globalc \n";
		if(empty($globalc)){
			$globalcreators = "";
		}
		}else{
			$globalcreators = "";
		}
		//userstats char count
		$userstars = $this->charCount($userstars);
		$userdiamonds = $this->charCount($userdiamonds);
		$userscoins = $this->charCount($userscoins);
		$userucoins = $this->charCount($userucoins);
		$userdemons = $this->charCount($userdemons);
		$usercp = $this->charCount($usercp);
		//GET STRINGS
		$usertitle = "**:chart_with_upwards_trend: $targetUser's stats**";
		$userstats = "$icon_star `$userstars` \n $icon_diamond `$userdiamonds` \n $icon_secretcoin `$userscoins` \n $icon_verifycoins `$userucoins` \n $icon_demon `$userdemons` \n $icon_cp `$usercp`";
		$bar = "───────────────────";
		$leaderboardinfo = $rank.$globalrank.$globalcreators.$yturl.$twitter.$twitch.$discord;
		$userinfo = " | UserID: $userID | AccID: $targetAccID";
		$tag = "<@$stars>, here is the profile of user **$targetUser**:";
		$msg = "Felicidades tu cuenta ya esta enlazada!!!!";
		$mainIcon = $this->iconGenerator($icontype, $icon, $color1, $color2, $glow, 0);
		$iconSet = $this->iconSetProfile($icontype, $icon, $color1, $color2, $glow, $accIcon, $accShip, $accBall, $accBird, $accDart, $accRobot, $accSpider);
		//BUILD JSON
		switch($id){
			//FULL EMBED
			case 1: $data = array(
				'embed'=> [
					"title"=> $title,
					"description"=> $usertitle,
				    "fields"=> [
						["name"=> "────────────", "value"=> $userstats, "inline"=> true],
						["name"=> "────────────", "value"=> $leaderboardinfo, "inline"=> true]],					
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$userinfo)],
					"thumbnail"=> ["url"=> ($iconhost.$mainIcon)],
					"image"=> ["url"=> ($iconhost.$iconSet)],
				]);
			break;
			//FROM BOT TAG
			case 2: $data = array(
				"content"=> $tag,
				'embed'=> [
					"title"=> $title,
					"description"=> $usertitle,
				    "fields"=> [
						["name"=> "────────────", "value"=> $userstats, "inline"=> true],
						["name"=> "────────────", "value"=> $leaderboardinfo, "inline"=> true]],					
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$userinfo)],
					"thumbnail"=> ["url"=> ($iconhost.$mainIcon)],
					"image"=> ["url"=> ($iconhost.$iconSet)],
				]);
			break;
			//MD
			case 3: $data = array(
				"content"=> $msg,
				'embed'=> [
					"title"=> $title,
					"description"=> $usertitle,
				    "fields"=> [
						["name"=> "────────────", "value"=> $userstats, "inline"=> true],
						["name"=> "────────────", "value"=> $leaderboardinfo, "inline"=> true]],					
					"color"=> $color,
					"footer"=> ["icon_url"=> ($iconhost.$footicon), "text"=> ($foottext.$userinfo)],
					"thumbnail"=> ["url"=> ($iconhost.$mainIcon)],
					"image"=> ["url"=> ($iconhost.$iconSet)],
				]);
			break;
		}                                                    
		$data_string = json_encode($data);
		return $data_string;
	}
	//DIFFTHUMBNAIL WITH IMG GENERATOR
	public function diffthumbnail($levelID){
		chdir(dirname(__FILE__));
		include __DIR__ . "/../lib/connection.php";
		$query = $db->prepare("SELECT * FROM levels WHERE levelID = :lvlid");
		$query->execute([':lvlid' => $levelID]);
		$result = $query->fetchAll();
		foreach($result as &$level){
			$stars = $level["starStars"];
			$feature = $level["starFeatured"];
			$epic = $level["starEpic"];
			$demondiff = $level["starDemonDiff"];
			$difficulty = $level["starDifficulty"];
			$diffauto = $level["starAuto"];
			$diffdemon = $level["starDemon"];
		}
		//RATE CHECK
		$rateimg = "ratena";
		if($feature == 1){
			$rateimg = "ratefeat";
		}
		if($epic == 1){
			$rateimg = "rateepic";
		}
		if($epic == 2){
			$rateimg = "ratelegendary";
		}
		if($epic == 3){
			$rateimg = "ratemythic";
		}
		//DIFF CHECK
		switch($difficulty){
			case 0: $diffimg = "diff0"; // NA
			break;
			case 10: $diffimg = "diff10"; // EASY
			break;
			case 20: $diffimg = "diff20"; // NORMAL
			break;
			case 30: $diffimg = "diff30"; // HARD
			break;
			case 40: $diffimg = "diff40"; // HARDER
			break;
			case 50: $diffimg = "diff50"; // INSANE
		}
		if($diffauto == 1){
			$diffimg = "auto"; //AUTO
		}
		if($diffdemon == 1){
			switch($demondiff){
				case 0: $diffimg = "demon0"; //HARD DEMON
				break;
				case 3: $diffimg = "demon3"; //EASY DEMON
				break;
				case 4: $diffimg = "demon4"; //MEDIUM DEMON
				break;
				case 5: $diffimg = "demon5"; //INSANE DEMON
				break;
				case 6: $diffimg = "demon6"; //EXTREME DEMON
				break;
			}
		}
		//STARS CHECK
		switch($stars){
			case 0: $str = "str0";
			break;
			case 1: $str = "str1";
			break;
			case 2: $str = "str2";
			break;
			case 3: $str = "str3";
			break;
			case 4: $str = "str4";
			break;
			case 5: $str = "str5";
			break;
			case 6: $str = "str6";
			break;
			case 7: $str = "str7";
			break;
			case 8: $str = "str8";
			break;
			case 9: $str = "str9";
			break;
			case 10: $str = "str10";
			break;
		}
		//GENERATE FILENAME
		$filename = "../../resources/difficulty/".$rateimg.$diffimg.$str.".png";
		$imgurl = "difficulty/".$rateimg.$diffimg.$str.".png";
		//CHECK ALREADY EXIST IMG
		if (file_exists($filename)) {
			return $imgurl;
		}else{
			//CREATE IMAGE
			$png = imagecreatefrompng("resource/$rateimg.png");
			$png2 = imagecreatefrompng("resource/$diffimg.png");
			$png3 = imagecreatefrompng("resource/$str.png");
			imagesavealpha($png, true);
			$sizex = imagesx($png);
			$sizey = imagesy($png);
			imagecopyresampled( $png, $png2, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
			imagecopyresampled( $png, $png3, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
			imagepng($png, "../../resources/difficulty/".$rateimg.$diffimg.$str.".png");
			return $imgurl;
		}
	}
	public function iconSent($stars, $feature){
		if($feature == 0){
            switch($stars){
				case 1: $icon_face = "diff/sent/rate/1.png";
				break;	
				case 2: $icon_face = "diff/sent/rate/2.png";
				break;	
				case 3: $icon_face = "diff/sent/rate/3.png";
				break;	
				case 4: $icon_face = "diff/sent/rate/4.png";
				break;	
				case 5: $icon_face = "diff/sent/rate/4.png";
				break;	
				case 6: $icon_face = "diff/sent/rate/5.png";
				break;	
				case 7: $icon_face = "diff/sent/rate/5.png";
				break;	
				case 8: $icon_face = "diff/sent/rate/6.png";
				break;	
				case 9: $icon_face = "diff/sent/rate/6.png";
				break;	
				case 10: $icon_face = "diff/sent/rate/7.png";
				break;	
			}			
		}
		if($feature == 1){
            switch($stars){
				case 1: $icon_face = "diff/sent/feat/1.png";
				break;	
				case 2: $icon_face = "diff/sent/feat/2.png";
				break;	
				case 3: $icon_face = "diff/sent/feat/3.png";
				break;	
				case 4: $icon_face = "diff/sent/feat/4.png";
				break;	
				case 5: $icon_face = "diff/sent/feat/4.png";
				break;	
				case 6: $icon_face = "diff/sent/feat/5.png";
				break;	
				case 7: $icon_face = "diff/sent/feat/5.png";
				break;	
				case 8: $icon_face = "diff/sent/feat/6.png";
				break;	
				case 9: $icon_face = "diff/sent/feat/6.png";
				break;	
				case 10: $icon_face = "diff/sent/feat/7.png";
				break;	
			}			
		}
	return $icon_face;
	}
	public function thumbnail($id){
		switch($id){
			case 1: $image = "diff/sent/rate/0.png"; //Unrate
			break;
			case 2: $image = "levels/like.png"; //Played
			break;
			case 3: $image = "diff/sent/feat/0.png"; //Feature
			break;
			case 4: $image = "diff/sent/rate/0.png"; //Unfeat
			break;
			case 5: $image = "diff/0.png"; //Epic
			break;
			case 6: $image = "diff/sent/feat/0.png"; //Unepic
			break;
			case 7: $image = "player/user_coin.png"; //Verify
			break;
			case 8: $image = "player/user_coin_unverified.png"; //Unverify
			break;
			case 9: $image = "misc/daily.png"; //Daily
			break;
			case 10: $image = "misc/weekly.png"; //Weekly
			break;
			case 11: $image = "buttons/delete.png"; //Delete
			break;
			case 12: $image = "buttons/copy_button.png"; //Setacc
			break;
			case 13: $image = "buttons/user_button.png"; //USER
			break;
		}
	return $image;
	}
	public function embedColor($id){
		switch($id){
			case 1: $color = "16776960"; //RATED
			break;
            case 2: $color = "65280"; //SENT
			break;
            case 3: $color = "16711680"; //UNRATE
			break;
            case 4: $color = "16748288"; //UNEPIC/UNFEAT
			break;
            case 5: $color = "65535"; //OTHERS
			break;
            case 6: $color = "65412"; //ADMIN COMAND
			break;       
			case 7: $color = "0"; //ROLE MANAGE
			break; 
		}
	return $color;
	}
	public function modBadge($accountID){
		include __DIR__ . "/../lib/connection.php";
		if($accountID == 1){
			return "misc/gdpsbot.png";
		}
		$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :id");
		$query->execute([':id' => $accountID]);
		$roleID = $query->fetchColumn();
		switch($roleID){
			case 0: $icon = "buttons/profile.png";
			break;
			case 1: $icon = "buttons/starmodbroken.png";
			break;
			case 2: $icon = "modbadge/mod.png";
			break;
			case 3: $icon = "modbadge/elder.png";
			break;
			case 4: $icon = "modbadge/head.png";
			break;
			case 5: $icon = "modbadge/admin.png";
			break;
			case 6: $icon = "modbadge/dev.png";
			break;
			case 7: $icon = "modbadge/owner.png";
			break;
		}
	return $icon;
	}
	public function footerText($accountID){
		include __DIR__ . "/../lib/connection.php";
		if($accountID == 1){
			return "Chaos-Bot";
		}
		//DETECT MOD
		$query = $db->prepare("SELECT userName FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $accountID]);
		if ($query->rowCount() > 0) {
			$mod = $query->fetchColumn();
		}else{
			$mod = false;
		}
		$footertext = "$mod ($accountID)";
	return $footertext;
	}
	//---------------------------------
	//---------------------------------

	// ICON GENERATOR
	
	//---------------------------------
	//---------------------------------
	//replace color function
	public function replaceColor($png, $c){
		imagesavealpha($png, true);
		$sizex = imagesx($png);
		$sizey = imagesy($png);
		for($y=0;$y<$sizey;$y++) {
			for($x=0;$x<$sizex;$x++) {
				$rgb = imagecolorsforindex($png, imagecolorat($png, $x, $y));
				$transparent = imagecolorallocatealpha($png, 0, 0, 0, 127);
				imagesetpixel($png, $x, $y, $transparent);
				$red_set=$c[0]/255*$rgb['red'];
				$green_set=$c[1]/255*$rgb['green'];
				$blue_set=$c[2]/255*$rgb['blue'];
				if($red_set>255)$red_set=255;
				if($green_set>255)$green_set=255;
				if($blue_set>255)$blue_set=255;
				$pixelColor = imagecolorallocatealpha($png, $red_set, $green_set, $blue_set, $rgb['alpha']);
				imagesetpixel ($png, $x, $y, $pixelColor);
			}
		}
		return $png;
	}
	//COLOR VALUE
	public function colorSwitch($color){
		switch($color){
			case 0: $r = 125; $g = 255; $b = 0; break;
			case 1: $r = 0; $g = 255; $b = 0; break;
			case 2: $r = 0; $g = 255; $b = 125; break;
			case 3: $r = 0; $g = 255; $b = 255; break;
			case 16: $r = 0; $g = 200; $b = 255; break;
			case 4: $r = 0; $g = 125; $b = 255; break;
			case 5: $r = 0; $g = 0; $b = 255; break;
			case 6: $r = 125; $g = 0; $b = 255; break;
			case 13: $r = 185; $g = 0; $b = 255; break;
			case 7: $r = 255; $g = 0; $b = 255; break;
			case 8: $r = 255; $g = 0; $b = 125; break;
			case 9: $r = 255; $g = 0; $b = 0; break;
			case 29: $r = 255; $g = 75; $b = 0; break;
			case 10: $r = 255; $g = 125; $b = 0; break;
			case 14: $r = 255; $g = 185; $b = 0; break;
			case 11: $r = 255; $g = 255; $b = 0; break;
			case 12: $r = 255; $g = 255; $b = 255; break;
			case 17: $r = 175; $g = 175; $b = 175; break;
			case 18: $r = 90; $g = 90; $b = 90; break;
			case 15: $r = 0; $g = 0; $b = 0; break; //BLACK
			case 27: $r = 125; $g = 125; $b = 0; break;
			case 32: $r = 100; $g = 150; $b = 0; break;
			case 28: $r = 75; $g = 175; $b = 0; break;				
			case 38: $r = 0; $g = 150; $b = 0; break;
			case 20: $r = 0; $g = 175; $b = 75; break;
			case 33: $r = 0; $g = 150; $b = 100; break;
			case 21: $r = 0; $g = 125; $b = 125; break;
			case 34: $r = 0; $g = 100; $b = 150; break;
			case 22: $r = 0; $g = 75; $b = 175; break;
			case 39: $r = 0; $g = 0; $b = 150; break;
			case 23: $r = 75; $g = 0; $b = 175; break;
			case 35: $r = 100; $g = 0; $b = 150; break;
			case 24: $r = 125; $g = 0; $b = 125; break;
			case 36: $r = 150; $g = 0; $b = 100; break;
			case 25: $r = 175; $g = 0; $b = 75; break;
			case 37: $r = 150; $g = 0; $b = 0; break;
			case 30: $r = 150; $g = 50; $b = 0; break;
			case 26: $r = 175; $g = 75; $b = 0; break;
			case 31: $r = 150; $g = 100; $b = 0; break;
			case 19: $r = 255; $g = 125; $b = 125; break;
			case 40: $r = 125; $g = 255; $b = 175; break;
			case 41: $r = 125; $g = 125; $b = 255; break;
		}
		$RGB = array ($r, $g, $b);
		return $RGB;
	}
	//ICON GENERATOR (without query)
	public function iconGenerator($icontype, $icon, $color1, $color2, $glow, $request){
		switch($icontype){
			case 0: $prefix1 = "player"; $prefix2 = "P"; $folder = "0"; break;
			case 1: $prefix1 = "ship"; $prefix2 = "S"; $folder = "1"; break;
			case 2: $prefix1 = "player_ball"; $prefix2 = "B"; $folder = "2"; break;
			case 3: $prefix1 = "bird"; $prefix2 = "U"; $folder = "3"; break;
			case 4: $prefix1 = "dart"; $prefix2 = "W"; $folder = "4"; break;
			case 5: $prefix1 = "robot"; $prefix2 = "R"; $folder = "5"; break;
			case 6: $prefix1 = "spider"; $prefix2 = "A"; $folder = "6"; break;
		}
		//set color values
		$c1 = $this->colorSwitch($color1);
		$c2 = $this->colorSwitch($color2);
		$cG = $this->colorSwitch($color2);
		//icon with glow?
		$iglow = $prefix1."_".$icon."_glow_001.png";
		if($glow == 0){
			$iglow = "0.png";
		}
		//if color2 black, set glow color like color 1
		if($color2 == 15){ $cG = $this->colorSwitch($color1); }
		//if color1 black, set glow active
		if($color1 == 15){ $iglow = $prefix1."_".$icon."_glow_001.png"; }
		//if all colors black, set glow color white
		if($color1 == 15 AND $color2 == 15){ 
			$cG = array(255, 255, 255); 
			$iglow = $prefix1."_".$icon."_glow_001.png";
		}
		//set filename and img url
		$filename = "../../resources/iconProfile/[$icontype.$icon][$color1][$color2][$glow].png";
		$imgurl = "iconProfile/[$icontype.$icon][$color1][$color2][$glow].png";
		if($request == 0){
			$filename = "../../resources/iconProfile/mainIcon/[$icontype.$icon][$color1][$color2][$glow].png";
			$imgurl = "iconProfile/mainIcon/[$icontype.$icon][$color1][$color2][$glow].png";
		}
		//file exists?
		if (file_exists($filename)) {
			if($request == 1){ return $filename; }
			return $imgurl;
		}
		//locate png files
		$basepng = imagecreatefrompng("resource/icon/iconbase.png");
		imagesavealpha($basepng, true);
		$png = imagecreatefrompng("resource/icon/".$folder."/".$prefix1."_".$icon."_001.png"); //icon base (color 1)
		$png2 = imagecreatefrompng("resource/icon/".$folder."/".$prefix1."_".$icon."_2_001.png"); //icon part 2 (color 2)
		$png3 = imagecreatefrompng("resource/icon/".$folder."/$iglow"); //icon glow
		//get file size x/y
		$sizex = imagesx($png);
		$sizey = imagesy($png);
		//replace colors
		$png = $this->replaceColor($png, $c1);
		$png2 = $this->replaceColor($png2, $c2);
		$png3 = $this->replaceColor($png3, $cG);
		//build png
		if($folder == 3){ //ufo capsule
			$png5 = imagecreatefrompng("resource/icon/".$folder."/".$prefix1."_".$icon."_3_001.png");
			imagesavealpha($png5, true);
			imagecopyresampled( $png5, $png3, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
			$png3 = $png5;
		}
		if($request == 0){ 
			imagecopyresampled( $basepng, $png3, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
			$png3 = $basepng;
		}
		imagecopyresampled( $png3, $png2, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
		imagecopyresampled( $png3, $png, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
		//icon detail detect
		if (file_exists("resource/icon/".$folder."/".$prefix1."_".$icon."_extra_001.png")) {
			$png4 = imagecreatefrompng("resource/icon/".$folder."/".$prefix1."_".$icon."_extra_001.png");
			imagesavealpha($png4, true);
			imagecopyresampled( $png3, $png4, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
		}
		//save png file
		imagepng($png3, $filename);
		if($request == 1){ return $filename; }
		return $imgurl;
	}
	//ICON PROFILE DEFAULT
	public function iconProfile($accountID){
		chdir(dirname(__FILE__));
		include __DIR__ . "/../lib/connection.php";
		$query = $db->prepare("SELECT * FROM users WHERE extID = :extID");
		$query->execute([':extID' => $accountID]);
		$result = $query->fetchAll();
		foreach($result as &$user){
			$icontype = $user["iconType"];
			$icon = $user["icon"];
			$color1 = $user["color1"];
			$color2 = $user["color2"];
			$glow = $user["accGlow"];
			$accIcon = $user["accIcon"];
		}
		return $this->iconGenerator($icontype, $icon, $color1, $color2, $glow, 0);
	}
	public function iconSetProfile($icontype, $icon, $color1, $color2, $glow, $accIcon, $accShip, $accBall, $accBird, $accDart, $accRobot, $accSpider){
		chdir(dirname(__FILE__));
		$icontype = 100;
		/*
		include __DIR__ . "/../lib/connection.php";
		//user data
		$query = $db->prepare("SELECT * FROM users WHERE extID = :extID");
		$query->execute([':extID' => $accountID]);
		$result = $query->fetchAll();
		foreach($result as &$user){
			$icontype = $user["iconType"];
			$icon = $user["icon"];
			$color1 = $user["color1"];
			$color2 = $user["color2"];
			$glow = $user["accGlow"];
			$accIcon = $user["accIcon"];
			$accShip = $user["accShip"];
			$accBall = $user["accBall"];
			$accBird = $user["accBird"];
			$accDart = $user["accDart"];
			$accRobot = $user["accRobot"];
			$accSpider = $user["accSpider"];
		}
		*/
		//set icon order
		switch ($icontype) {
			case 0:
				$it1 = 1; $it2 = 2; $it3 = 3; $it4 = 4; $it5 = 5; $it6 = 6;
				$iv1 = $accShip; $iv2 = $accBall; $iv3 = $accBird; $iv4 = $accDart; $iv5 = $accRobot; $iv6 = $accSpider;
				break;
			case 1:
				$it1 = 0; $it2 = 2; $it3 = 3; $it4 = 4; $it5 = 5; $it6 = 6;
				$iv1 = $accIcon; $iv2 = $accBall; $iv3 = $accBird; $iv4 = $accDart; $iv5 = $accRobot; $iv6 = $accSpider;
				break;
			case 2:
				$it1 = 0; $it2 = 1; $it3 = 3; $it4 = 4; $it5 = 5; $it6 = 6;
				$iv1 = $accIcon; $iv2 = $accShip; $iv3 = $accBird; $iv4 = $accDart; $iv5 = $accRobot; $iv6 = $accSpider;
				break;
			case 3:
				$it1 = 0; $it2 = 1; $it3 = 2; $it4 = 4; $it5 = 5; $it6 = 6;
				$iv1 = $accIcon; $iv2 = $accShip; $iv3 = $accBall; $iv4 = $accDart; $iv5 = $accRobot; $iv6 = $accSpider;
				break;
			case 4:
				$it1 = 0; $it2 = 1; $it3 = 2; $it4 = 3; $it5 = 5; $it6 = 6;
				$iv1 = $accIcon; $iv2 = $accShip; $iv3 = $accBall; $iv4 = $accBird; $iv5 = $accRobot; $iv6 = $accSpider;
				break;
			case 5:
				$it1 = 0; $it2 = 1; $it3 = 2; $it4 = 3; $it5 = 4; $it6 = 6;
				$iv1 = $accIcon; $iv2 = $accShip; $iv3 = $accBall; $iv4 = $accBird; $iv5 = $accDart; $iv6 = $accSpider;
				break;
			case 6:
				$it1 = 0; $it2 = 1; $it3 = 2; $it4 = 3; $it5 = 4; $it6 = 5;
				$iv1 = $accIcon; $iv2 = $accShip; $iv3 = $accBall; $iv4 = $accBird; $iv5 = $accDart; $iv6 = $accRobot;
				break;
			case 100:
				$it1 = 0; $it2 = 1; $it3 = 2; $it4 = 3; $it5 = 4; $it6 = 5; $it7 = 6;
				$iv1 = $accIcon; $iv2 = $accShip; $iv3 = $accBall; $iv4 = $accBird; $iv5 = $accDart; $iv6 = $accRobot; $iv7 = $accSpider;
			break;
		}
		//generate icons
		$icon1 = $this->iconGenerator($it1, $iv1, $color1, $color2, $glow, 1);
		$icon2 = $this->iconGenerator($it2, $iv2, $color1, $color2, $glow, 1); 
		$icon3 = $this->iconGenerator($it3, $iv3, $color1, $color2, $glow, 1); 
		$icon4 = $this->iconGenerator($it4, $iv4, $color1, $color2, $glow, 1); 
		$icon5 = $this->iconGenerator($it5, $iv5, $color1, $color2, $glow, 1); 
		$icon6 = $this->iconGenerator($it6, $iv6, $color1, $color2, $glow, 1);
		if($icontype == 100){
			$icon7 = $this->iconGenerator($it7, $iv7, $color1, $color2, $glow, 1);
		}
		//set filename & url
		$filename = "../../resources/iconProfile/iconSet/[$it1.$iv1][$it2.$iv2][$it3.$iv3][$it4.$iv4][$it5.$iv5][$it6.$iv6][$color1][$color2][$glow].png";
		$imgurl = "iconProfile/iconSet/[$it1.$iv1][$it2.$iv2][$it3.$iv3][$it4.$iv4][$it5.$iv5][$it6.$iv6][$color1][$color2][$glow].png";
		if($icontype == 100){
			$filename = "../../resources/iconProfile/iconSet/[$it1.$iv1][$it2.$iv2][$it3.$iv3][$it4.$iv4][$it5.$iv5][$it6.$iv6][$it7.$iv7][$color1][$color2][$glow].png";
			$imgurl = "iconProfile/iconSet/[$it1.$iv1][$it2.$iv2][$it3.$iv3][$it4.$iv4][$it5.$iv5][$it6.$iv6][$it7.$iv7][$color1][$color2][$glow].png";
		}		
		//file exists?
		if (file_exists($filename)) {
			return $imgurl;
		}
		//locate icons
		$base = imagecreatefrompng("resource/icon/base.png"); //base
		if($icontype == 100){
			$base = imagecreatefrompng("resource/icon/base2.png"); //base
		}		
		$icon1 = imagecreatefrompng($icon1); //IMG1
		$icon2 = imagecreatefrompng($icon2); //IMG2
		$icon3 = imagecreatefrompng($icon3); //IMG3
		$icon4 = imagecreatefrompng($icon4); //IMG4
		$icon5 = imagecreatefrompng($icon5); //IMG5
		$icon6 = imagecreatefrompng($icon6); //IMG6
		if($icontype == 100){
			$icon7 = imagecreatefrompng($icon7); //IMG7
		}
		imagesavealpha($base, true);
		//build icon set
		imagecopyresampled( $base, $icon1, 0, 0, 0, 0, 60*2, 45*2, 120, 90);
		imagecopyresampled( $base, $icon2, 60*2, 0, 0, 0, 60*2, 45*2, 120, 90);
		imagecopyresampled( $base, $icon3, 120*2, 0, 0, 0, 60*2, 45*2, 120, 90);
		imagecopyresampled( $base, $icon4, 180*2, 0, 0, 0, 60*2, 45*2, 120, 90);
		imagecopyresampled( $base, $icon5, 240*2, 0, 0, 0, 60*2, 45*2, 120, 90);
		imagecopyresampled( $base, $icon6, 300*2, 0, 0, 0, 60*2, 45*2, 120, 90);
		if($icontype == 100){
			imagecopyresampled( $base, $icon7, 360*2, 0, 0, 0, 60*2, 45*2, 120, 90);
		}
		//save icon set
		imagepng($base, $filename);
		return $imgurl;
	}
	public function roleAssign($accountID){
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php";
		if($roleAssign != 1){ return false; }
		if($discordEnabled != 1){ return false; }
		$query = $db->prepare("SELECT discordID,discordLinkReq FROM accounts WHERE accountID=:accountID");
		$query->execute([':accountID' => $accountID]);
		if($query->rowCount() == 0){ return false; }
		$discord = $query->fetch();
		$discordID = $discord["discordID"];
		$discordLinkReq = $discord["discordLinkReq"];
		if($discordLinkReq != 1){ return false; }
		$query = $db->prepare("SELECT stars, creatorPoints, completedMapPacks FROM users WHERE extID=:accountID");
		$query->execute([':accountID' => $accountID]);
		if($query->rowCount() == 0){ return false; }
		$userstats = $query->fetch();
		$stars = $userstats["stars"];
		$cp = $userstats["creatorPoints"];
		$mpc = $userstats["completedMapPacks"];
		//member role
		$cmd = $prefix."setrole ".$discordID." ".$role1;
		$data = array("content"=> $cmd);  
		$data_string = json_encode($data);
		$this->discordNotify(3, $data_string);
		// +500 stars role
		if($stars > 499){ 
			$cmd = $prefix."setrole ".$discordID." ".$role2;
			$data = array("content"=> $cmd);  
			$data_string = json_encode($data);
			$this->discordNotify(3, $data_string);
		}
		// +5 rated levels role & 750 stars
		if($cp > 4 AND $stars > 749 ){ 
			$cmd = $prefix."setrole ".$discordID." ".$role3;
			$data = array("content"=> $cmd);  
			$data_string = json_encode($data);
			$this->discordNotify(3, $data_string);
		}
		// +5 cp, 25 mpc & 1500 stars
		if($cp > 5 AND $mpc > 24 AND $stars > 1499 ){ 
			$cmd = $prefix."setrole ".$discordID." ".$role4;
			$data = array("content"=> $cmd);  
			$data_string = json_encode($data);
			$this->discordNotify(3, $data_string);
		}
		// 10 cp's, 3000 stars & all map packs
		if($cp > 9 AND $stars > 2999){
			$query = $db->prepare("SELECT count(*) FROM mappacks");
			$query->execute();
			$maxmp = $query->fetchColumn();
			if($mpc == $maxmp){
				$cmd = $prefix."setrole ".$discordID." ".$role5;
				$data = array("content"=> $cmd);  
				$data_string = json_encode($data);
				$this->discordNotify(3, $data_string);
			}
		}
	}
	public function ispositive($value){
		if($value > 0){
			return "+";
		}
	}
	public function charCount($value){
		$char = strlen($value);
		switch($char){
			case 0: $space = "         ";
			break;
			case 1: $space = "        ";
			break;
			case 2: $space = "       ";
			break;
			case 3: $space = "      ";
			break;
			case 4: $space = "     ";
			break;
			case 5: $space = "    ";
			break;
			case 6: $space = "   ";
			break;
			case 7: $space = "  ";
			break;
			case 8: $space = " ";
			break;
			case 9: $space = "";
			break;
		}
		return $space.$value;
	}
	public function charCount2($value){
		$char = strlen($value);
		switch($char){
			case 0: $space = "     ";
			break;
			case 1: $space = "    ";
			break;
			case 2: $space = "   ";
			break;
			case 3: $space = "  ";
			break;
			case 4: $space = " ";
			break;
			case 5: $space = "";
			break;
		}
		return $value.$space;
	}
}
?>