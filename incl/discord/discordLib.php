<?php
class discordLib {
	// -----------------------------------------------------------------------------------------
	// SECTION: Core Notification Senders
	// -----------------------------------------------------------------------------------------

	/**
	 * Sends a pre-formatted embed to a specific Discord channel.
	 *
	 * @param int|string $id The channel preset (1 or 2) or a direct channel ID.
	 * @param array $data The embed data.
	 * @param GdImage|array|null $imageResources Single or multiple GD image resources to attach.
	 * @return string|false The response from Discord or false on failure.
	 */
	public function discordNotify($id, $data, $imageResources = null){
		include __DIR__ . "/../../config/discord.php";
		if ($discordEnabled != 1) return false;

		switch ($id) {
			case 1: $channelID = $channel1; break;
			case 2: $channelID = $channel2; break;
			default: $channelID = $id; break;
		}

		$url = "https://discord.com/api/v10/channels/$channelID/messages";
		$embedContent = isset($data['embed']) ? $data['embed'] : $data;
		$jsonPayload = json_encode(["embeds" => [$embedContent]], JSON_UNESCAPED_UNICODE);

		return $this->_sendDiscordRequest($url, $jsonPayload, $imageResources);
	}

	/**
	 * A comprehensive notification function for various in-game events.
	 * It builds the embed and sends it to the appropriate channel.
	 *
	 * @param int|string $id Channel preset (1, 2, 3) or a direct channel ID.
	 * @param int $objectID The ID of the object (level, account).
	 * @param int $objectType The type of object (1 for level, 2 for account).
	 * @param int $embedID The style of the embed to use.
	 * @param int $title The title preset for the embed.
	 * @param int $color The color preset for the embed.
	 * @param int $authorID The account ID of the action performer.
	 * @param int $thumbType The type of thumbnail to generate.
	 * @param int $thumbID The ID used for thumbnail generation.
	 * @param mixed $extra Extra data, often used for star values.
	 * @return string|false The response from Discord or false on failure.
	 */
	public function discordNotifyNew($id, $objectID, $objectType, $embedID, $title, $color, $authorID, $thumbType, $thumbID, $extra){
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php";
		if ($discordEnabled != 1) {
			return false;
		}

		switch ($id) {
			case 1: $channelID = $channel1; break; // Mod actions
			case 2: $channelID = $channel2; break; // Public actions
			case 3: $channelID = $channel3; break; // Botspam commands
			default: $channelID = $id; break;
		}

		$imageResources = null;
		$data_string = "";

		// Handle level-related notifications
		if ($objectType == 1) {
			$thumbnailResource = null;
			switch ($thumbType) {
				case 1: $thumbnailResource = $this->diffthumbnail($objectID); break;
				case 2: $thumbnailResource = $this->thumbnail($thumbID); break;
				case 3: $thumbnailResource = $this->iconSent($extra, $thumbID); break;
			}

			$result = $this->embedContent($embedID, $this->title($title), $thumbnailResource, $this->embedColor($color), $this->modBadge($authorID), $this->footerText($authorID), $objectID, $extra);
			$data_string = $result['json'];
			$imageResources = $result['images'];
			
			$query = $db->prepare("SELECT extID FROM levels WHERE levelID = :id");
			$query->execute([':id' => $objectID]);
			$objectID = $query->fetchColumn();
		}

		// Handle account-related notifications
		if ($objectType == 2) {
			$result = $this->accEmbedContent($embedID, $this->title($title), $this->iconProfile($objectID), $this->embedColor($color), $this->modBadge($authorID), $this->footerText($authorID), $objectID, $extra);
			$data_string = $result['json'];
			$imageResources = $result['images'];
		}

		// DM Notification Logic
		$query = $db->prepare("SELECT discordID, discordLinkReq FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $objectID]);
		$discordData = $query->fetch();

		if ($discordData && $discordData["discordLinkReq"] == 1) {
			// List of titles that should trigger a DM
			$dmTriggerTitles = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 16, 17, 26, 27];
			if (in_array($title, $dmTriggerTitles)) {
				$this->discordDMNotify($discordData["discordID"], $data_string, $imageResources);
			}
		}

		$url = "https://discord.com/api/v10/channels/$channelID/messages";
		$dataArray = json_decode($data_string, true);
		$embedContent = isset($dataArray['embed']) ? $dataArray['embed'] : $dataArray;
		$jsonPayload = json_encode(["embeds" => [$embedContent]], JSON_UNESCAPED_UNICODE);

		return $this->_sendDiscordRequest($url, $jsonPayload, $imageResources);
	}

	/**
	 * Sends a notification when all creator points are updated.
	 *
	 * @param array $result The result from updateAllCPs() containing updated, failed, and top_users.
	 * @return string|false The response from Discord or false on failure.
	 */
	public function notifyUpdateCPAll($result){
		include __DIR__ . "/../../config/discord.php";
		include __DIR__ . "/../discord/emojis.php";
		
		$description = "**All users creatorPoints updated**\n\n";
		$description .= "**Top Creators:**\n";
		
		$trophyMap = [1000 => $icon_top1000, 500 => $icon_top500, 200 => $icon_top200, 100 => $icon_top100, 50 => $icon_top50, 10 => $icon_top10, 1 => $icon_top1];
		
		if(!empty($result['top_users']) && is_array($result['top_users'])){
			$rank = 1;
			$maxUsers = min(20, count($result['top_users']));
			for($i = 0; $i < $maxUsers; $i++){
				$user = $result['top_users'][$i];
				if(isset($user['userName']) && isset($user['creatorPoints'])){
					//Get trophy for this rank (same logic as in accEmbedContent)
					$trophy = $icon_globalrank; //default
					foreach ($trophyMap as $rankNum => $trophyIcon) {
						if ($rank < $rankNum + 1) {
							$trophy = $trophyIcon;
						}
					}
					
					$description .= "$trophy `$rank.` **" . htmlspecialchars($user['userName'], ENT_QUOTES) . "** - $icon_cp `" . round($user['creatorPoints'], 0) . "`\n";
					$rank++;
				}
			}
		} else {
			$description .= "*No users with creator points*";
		}
		
		//Load thumbnail image
		$thumbnailPath = dirname(__FILE__) . "/../../resources/misc/gdps.png";
		$imageResources = null;
		if(file_exists($thumbnailPath)){
			$thumbnailResource = @imagecreatefrompng($thumbnailPath);
			if($thumbnailResource !== false){
				$imageResources = ['thumb.png' => $thumbnailResource];
			}
		}
		
		$embedData = [
			"title" => "$icon_cp Creator Points Updated",
			"description" => $description,
			"color" => 0x00ff00,
			"footer" => [
				"icon_url" => ($iconhost . "misc/gdpsbot.png"),
				"text" => "Updated: " . (isset($result['updated']) ? $result['updated'] : 0) . " users | Failed: " . (isset($result['failed']) ? $result['failed'] : 0)
			]
		];
		
		//Add thumbnail if available
		if($imageResources !== null){
			$embedData["thumbnail"] = ["url" => "attachment://thumb.png"];
		}
		
		$data = ['embed' => $embedData];
		
		return $this->discordNotify(1, $data, $imageResources);
	}

	/**
	 * Sends a direct message (DM) to a user.
	 *
	 * @param string $discordID The user's Discord ID.
	 * @param string $data_string The JSON string of the message payload.
	 * @param array|null $imageResources Images to attach.
	 * @return string|false The response from Discord or false on failure.
	 */
	public function discordDMNotify($discordID, $data_string, $imageResources = null){
		include __DIR__ . "/../../config/discord.php";
		if ($discordEnabled != 1) return false;

		// Create a DM channel with the user
		$url = "https://discord.com/api/v10/users/@me/channels";
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["recipient_id" => $discordID]));
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'User-Agent: Chaos-Bot (1.1)',
			'Content-type: application/json',
			'Authorization: Bot ' . $bottoken
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);
		curl_close($ch);

		$responseDecode = json_decode($response, true);
		if (!isset($responseDecode["id"])) return false;

		// Send the message to the created DM channel
		$dmChannelID = $responseDecode["id"];
		$url = "https://discord.com/api/v10/channels/$dmChannelID/messages";
		$dataArray = json_decode($data_string, true);
		$embedContent = isset($dataArray['embed']) ? $dataArray['embed'] : $dataArray;
		$jsonPayload = json_encode(["embeds" => [$embedContent]], JSON_UNESCAPED_UNICODE);

		return $this->_sendDiscordRequest($url, $jsonPayload, $imageResources);
	}

	/**
	 * Handles public action notifications (e.g., level uploads, stats updates).
	 *
	 * @param int $objectType The type of object (0 for level, 1 for profile).
	 * @param array $objData The data associated with the object.
	 * @param int $action The action being performed.
	 */
	public function publicAction($objectType, $objData, $action){
		include __DIR__ . "/../../config/discord.php";
		include __DIR__ . "/../discord/emojis.php";

		// --- Levels Section ---
		if ($objectType == 0) {
			$levelName = $objData["levelName"];
			$userName = $objData["userName"];
			$original = $objData["original"];

			$copy = ($original > 0) ? $icon_copy : "";
			$desc = "$icon_play **__" . $levelName . "__** by $userName $copy";
			$levelInfo = "levelID: " . $objData["levelID"];

			switch ($action) {
				case 1: $actionTitle = "$icon_info New recent level uploaded!!!"; break;
				case 2: $actionTitle = "$icon_info Level Updated!!!"; break;
				default: $actionTitle = "$icon_info Level Activity"; break;
			}

			$data = ['embed' => [
				"title" => $actionTitle,
				"description" => $desc,
				"footer" => ["icon_url" => ($iconhost . "misc/gdpsbot.png"), "text" => $levelInfo],
			]];
			$this->discordNotify(2, $data);
		}

		// --- Profiles Section ---
		if ($objectType == 1) {
			include __DIR__ . "/../lib/connection.php";
			
			$userTitle = ":chart_with_upwards_trend: __**" . $objData["userName"] . "'s**__ Stats";

			// Build stats string - show changes if any, otherwise show current stats
			$stats = "";
			$hasChanges = false;
			
			if(isset($objData["starsDiff"]) && $objData["starsDiff"] != 0) {
				$stats .= "$icon_star `".$this->charCount($objData["stars"])."` ─> `".$this->charCount2($this->ispositive($objData["starsDiff"]))."`\n";
				$hasChanges = true;
			}
			if(isset($objData["moonsDiff"]) && $objData["moonsDiff"] != 0) {
				$stats .= "$icon_moon `".$this->charCount($objData["moons"])."` ─> `".$this->charCount2($this->ispositive($objData["moonsDiff"]))."`\n";
				$hasChanges = true;
			}
			if(isset($objData["coinsDiff"]) && $objData["coinsDiff"] != 0) {
				$stats .= "$icon_secretcoin `".$this->charCount($objData["coins"])."` ─> `".$this->charCount2($this->ispositive($objData["coinsDiff"]))."`\n";
				$hasChanges = true;
			}
			if(isset($objData["ucDiff"]) && $objData["ucDiff"] != 0) {
				$stats .= "$icon_verifycoins `".$this->charCount($objData["uc"])."` ─> `".$this->charCount2($this->ispositive($objData["ucDiff"]))."`\n";
				$hasChanges = true;
			}
			if(isset($objData["demonsDiff"]) && $objData["demonsDiff"] != 0) {
				$stats .= "$icon_demon `".$this->charCount($objData["demons"])."` ─> `".$this->charCount2($this->ispositive($objData["demonsDiff"]))."`\n";
				$hasChanges = true;
			}
			if(isset($objData["creatorPointsDiff"]) && $objData["creatorPointsDiff"] != 0) {
				$stats .= "$icon_cp `".$this->charCount($objData["creatorPoints"])."` ─> `".$this->charCount2($this->ispositive($objData["creatorPointsDiff"]))."`\n";
				$hasChanges = true;
			}
			if(isset($objData["diamondsDiff"]) && $objData["diamondsDiff"] != 0) {
				$stats .= "$icon_diamond `".$this->charCount($objData["diamonds"])."` ─> `".$this->charCount2($this->ispositive($objData["diamondsDiff"]))."`\n";
				$hasChanges = true;
			}
			
			// If no stat changes but profile was updated (icons/colors/glow), show current stats
			if(!$hasChanges && isset($objData["stars"])) {
				$stats = "$icon_star `".$this->charCount($objData["stars"])."` \n".
						 "$icon_moon `".$this->charCount($objData["moons"])."` \n".
						 "$icon_secretcoin `".$this->charCount($objData["coins"])."` \n".
						 "$icon_verifycoins `".$this->charCount($objData["uc"])."` \n".
						 "$icon_demon `".$this->charCount($objData["demons"])."` \n".
						 "$icon_cp `".$this->charCount($objData["creatorPoints"])."` \n".
						 "$icon_diamond `".$this->charCount($objData["diamonds"])."`";
			}
			
			// Get user rank
			$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :id LIMIT 1");
			$query->execute([':id' => $objData["extID"]]);
			$roleID = $query->fetchColumn() ?: 0;
			$rankMap = [
				1 => "$icon_brokenmodstar **DEMOTED :(**\n",
				2 => "$icon_mod **MODERATOR**\n",
				3 => "$icon_head **HEAD MOD**\n",
				4 => "$icon_elder **ELDER MOD**\n",
				5 => "$icon_admin **ADMIN**\n",
			];
			$rank = $rankMap[$roleID] ?? "";

			// Get global leaderboard rank
			$globalRank = "";
			if ($objData["stars"] > 25) {
				$db->query("SET @rownum := 0;");
				$query = $db->prepare("SELECT rank FROM (SELECT @rownum := @rownum + 1 AS rank, extID FROM users WHERE isBanned = '0' AND gameVersion > 19 AND stars > 25 ORDER BY stars DESC) as result WHERE extID=:extid");
				$query->execute([':extid' => $objData["extID"]]);
				$globalPos = $query->fetchColumn();
				if ($globalPos) {
					$trophyMap = [1000 => $icon_top1000, 500 => $icon_top500, 200 => $icon_top200, 100 => $icon_top100, 50 => $icon_top50, 10 => $icon_top10, 1 => $icon_top1];
					$globalTrophy = $icon_globalrank;
					foreach ($trophyMap as $rankNum => $trophy) {
						if ($globalPos < $rankNum + 1) $globalTrophy = $trophy;
					}
					$globalRank = "$globalTrophy **Global Rank:** $globalPos \n";
				}
			}

			// Prepare leaderboard info
			$leaderboardInfo = $rank . $globalRank;
			if($leaderboardInfo == "") $leaderboardInfo = "────────────";
					 
			$userInfo = "userID: " . $objData["userID"];

			// 1. Get the thumbnail (current individual icon)
			$thumbnailIcon = $this->iconProfile($objData["extID"]);

			// 2. Get the icon set (horizontal strip of other icons)
			$imageSet = $this->iconSetProfile($objData["extID"]);

			$title = $this->title($action);
			
			// If no stats, set default
			if($stats == "") $stats = "────────────";
			
			$data = [
				'embed' => [
					"title" => $title,
					"description" => $userTitle,
					"fields" => [
						["name" => "────────────", "value" => $stats, "inline" => true],
						["name" => "────────────", "value" => $leaderboardInfo, "inline" => true]
					],
					"footer" => ["icon_url" => ($iconhost . "misc/gdpsbot.png"), "text" => $userInfo],
					"thumbnail" => ["url" => "attachment://thumb.png"], // Current icon
					"image" => ["url" => "attachment://icon.png"]      // Icon set below
				]
			];

			// Send notification with two attachments
			$this->discordNotify(2, $data, ['icon.png' => $imageSet, 'thumb.png' => $thumbnailIcon]);
		}
	}

	// -----------------------------------------------------------------------------------------
	// SECTION: Embed Content Builders
	// -----------------------------------------------------------------------------------------

	/**
	 * Returns a preset title string.
	 * @param int $id The ID of the title.
	 * @return string The formatted title.
	 */
	public function title($id){
		include __DIR__ . "/../discord/emojis.php";
		$title = "";
		switch($id){
			case 1: $title = "$icon_star New Rated Level!!!"; break;
			case 2: $title = "$icon_approved New Approved Level!"; break;
			case 3: $title = "$icon_failed Command - Unrate"; break;
			case 4: $title = "$icon_like Command - Played"; break;
			case 5: $title = "$icon_cp Command - Feature"; break;
			case 6: $title = "$icon_failed Command - Unfeat"; break;
			case 7: $title = "$icon_cp Command - Epic"; break;
			case 8: $title = "$icon_failed Command - Unepic"; break;
			case 9: $title = "$icon_info Command - Verifycoins"; break;
			case 10: $title = "$icon_info Command - Unverifycoins"; break;
			case 11: $title = "$icon_daily Command - Daily"; break;
			case 12: $title = "$icon_weekly Command - Weekly"; break;
			case 13: $title = "$icon_cross Command - Delete"; break;
			case 14: $title = "$icon_info Command - Setacc"; break;
			case 15: $title = "$icon_succes Rated Demon!!!"; break;
			case 16: $title = "$icon_modstar User Promoted!!!"; break;
			case 17: $title = "$icon_brokenmodstar User Demoted..."; break;
			case 18: $title = "$icon_info User Profile Update!!!"; break;
			case 19: $title = "$icon_info Level Updated!!!"; break;
			case 20: $title = "$icon_info New recent level uploaded!!!"; break;
			case 21: $title = "$icon_search Search result."; break;
			case 22: $title = "$icon_profile User profile"; break;
			case 23: $title = "$icon_daily Current Daily Level"; break;
			case 24: $title = "$icon_weekly Current Weekly Level"; break;
			case 25: $title = "$icon_profile Server Stats"; break;
			case 26: $title = "$icon_brokenmodstar Rank degraded..."; break;
			case 27: $title = "$icon_succes Your account has been linked!!!"; break;
			case 28: $title = "$icon_cp Command - Legendary"; break;
			case 29: $title = "$icon_cp Command - Mythic"; break;
		}
	return $title;
	}

	/**
	 * Builds the embed content for a level.
	 *
	 * @param int $id The embed style preset.
	 * @param string $title The embed title.
	 * @param GdImage|null $thumbnailResource A GD resource for the thumbnail.
	 * @param string $color The embed color.
	 * @param string $footicon The footer icon URL part.
	 * @param string $foottext The footer text.
	 * @param int $levelID The level ID.
	 * @param mixed $stars Extra data (e.g., star value).
	 * @return array An array containing the 'json' payload and 'images' to attach.
	 */
	public function embedContent($id, $title, $thumbnailResource, $color, $footicon, $foottext, $levelID, $stars){
		include __DIR__ . "/../lib/connection.php";
		require_once __DIR__ . "/../lib/mainLib.php";
		include __DIR__ . "/../../config/discord.php";
		include __DIR__ . "/../discord/emojis.php";

		$imageResources = null;
		$thumbnailData = [];
		if ($thumbnailResource !== null) {
			$imageResources = ['thumb.png' => $thumbnailResource];
			$thumbnailData = ["url" => "attachment://thumb.png"];
		}

		// Get level data
		$query = $db->prepare("SELECT * FROM levels WHERE levelID = :lvlid");
		$query->execute([':lvlid' => $levelID]);
		$level = $query->fetch();

		if (!$level) return ['json' => '{}', 'images' => null];

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
		
		// Calculate CP dynamically using mainLib function (only for levels with stars)
		$cpCount = 0;
		if($level["starStars"] != 0){
			$gs = new mainLib();
			$cpCount = $gs->calculateLevelCP($level["starFeatured"], $level["starEpic"]);
		}

		// Song Info
		$songInfo = "";
		$songDesc = "";
		if ($songID == 0) {
			$officialSongs = [
				"Stereo Madness by ForeverBound", "Back on Track by DJVI", "Polargeist by Step",
				"Dry Out by DJVI", "Base after Base by DJVI", "Can't Let Go by DJVI",
				"Jumper by Waterflame", "Time Machine by Waterflame", "Cycles by DJVI",
				"xStep by DJVI", "Clutterfunk by Waterflame", "Theory of Everything by DJ Nate",
				"Electroman Adventures by Waterflame", "Club Step by DJ Nate", "Electrodynamix by DJ Nate",
				"Hexagon Force by Waterflame", "Blast Processing by Waterflame", "Theory of Everything 2 by DJ Nate",
				"Geometrical Dominator by Waterflame", "Deadlocked by F-777", "Fingerbang by MDK"
			];
			$songDesc = "__**" . ($officialSongs[$audioTrack] ?? 'Unknown Song') . "**__";
		} else {
			$query = $db->prepare("SELECT * FROM songs WHERE ID = :id");
			$query->execute([':id' => $songID]);
			if ($query->rowCount() > 0) {
				$song = $query->fetch();
				$songDesc =  "__" . $song["name"] . "__ by " . $song["authorName"];
				$songInfo = "SongID: " . $songID . " - Size: " . $song["size"] . "MB";
				if ($songID < 5000000) {
					$songInfo .= "\n" . $icon_play . '[Play on Newgrounds](https://www.newgrounds.com/audio/listen/' . $songID . ')';
				}
			} else {
				$songDesc = "*unknown*";
			}
		}

		// Handle empty description
		if (empty($levelDesc)) {
			$desc = " No description provided ";
		}

		// Handle coins
		$coinsDisplay = "None";
		if ($coins > 0) {
			$coinIcon = $starCoins == 1 ? $icon_verifycoins : $icon_unverifycoins;
			$coinsDisplay = str_repeat("$coinIcon ", $coins);
		}

		// Set like/dislike icon
		$likeIcon = ($likes < 0) ? $icon_dislike : $icon_like;

		// Level Length
		$lengthMap = ["TINY", "SHORT", "MEDIUM", "LONG", "XL"];
		$lengthText = $lengthMap[$levelLength] ?? "NA";

		// +40K objects icon
		$overObjectsIcon = ($objects > 40000) ? $icon_objecto : "";

		// Copy level indicator
		$copyLevelText = "";
		$copyLevelIcon = "";
		if (!empty($original)) {
			$copyLevelText = "$icon_copy**Original:** $original";
			$copyLevelIcon = $icon_copy;
		}
		if ($original == 1) {
			$copyLevelText = "$icon_copy**Original Reupload:** " . $originalReup;
		}

		$cpCountStr = ($cpCount != 0) ? "$icon_cp `" . $this->charCount($cpCount) . "`\n" : "";

		// Prepare display strings
		$levelBy = "$icon_play __" . $levelName . "__ by $userName";
		$description = "**Description:** $desc";
		$userCoinsDisplay = "Coins: $coinsDisplay";
		$stats = "$icon_download2 `".$this->charCount($downloads)."` \n $likeIcon `".$this->charCount($likes)."` \n $icon_length `".$this->charCount($lengthText)."`\n".$cpCountStr."───────────────────\n";
		$songDataDisplay = ":musical_note: $songDesc";
		$extraInfoDisplay = $songInfo . " \n───────────────────\n**Level ID:** $levelID \n**Level Version:** $levelVersion \n**Objects count:** $objects $overObjectsIcon \n**Stars requested:** $requestedStars \n$copyLevelText";
		$levelByCompact = "$icon_play __" . $levelName . "__ by $userName $copyLevelIcon $overObjectsIcon";
		$statsCompact = "$icon_download2 `".$this->charCount($downloads)."` \n $likeIcon `".$this->charCount($likes)."` \n $icon_length `".$this->charCount($lengthText)."`\n".$cpCountStr;
		$levelInfoFooter = " | Level ID: $levelID";

		// Build JSON based on embed type
		$data = [];
		switch($id){
			case 1: // Full embed
				$data = ['embed'=> [
					"title"=> $title,
					"fields"=> [
						["name"=> $levelBy, "value"=> $description],
						["name"=> $userCoinsDisplay, "value"=> $stats],
						["name"=> $songDataDisplay, "value"=> $extraInfoDisplay]],
					"color"=> $color,
					"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
					"thumbnail"=> $thumbnailData,
				]];
				break;
			case 2: // Compact embed
				$data = ['embed'=> [
					"title"=> $title,
					"fields"=> [
						["name"=> $levelByCompact, "value"=> $stats],
						["name"=> $userCoinsDisplay, "value"=> $songDataDisplay]],
					"color"=> $color,
					"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
					"thumbnail"=> $thumbnailData,
				]];
				break;
			case 3: // Sent level
				$data = ['embed'=> [
					"title"=> $title,
					"fields"=> [
						["name"=> $levelByCompact, "value"=> $statsCompact],
						["name"=> "Sent Stars: $stars $icon_star", "value"=> "───────────────────"],
						["name"=> $userCoinsDisplay, "value"=> $songDataDisplay]],
					"color"=> $color,
					"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
					"thumbnail"=> $thumbnailData,
				]];
				break;
			case 4: // New daily/weekly queued
				$data = ['embed'=> [
					"title"=> $title,
					"description"=> "New Daily/weekly level queued!",
					"fields"=> [
						["name"=> $levelByCompact, "value"=> $stats],
						["name"=> $userCoinsDisplay, "value"=> "$icon_length __Is out:__ $stars"]],
					"color"=> $color,
					"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
					"thumbnail"=> $thumbnailData,
				]];
				break;
			case 5: // !setacc command
				$data = ['embed'=> [
					"title"=> $title,
					"fields"=> [
						["name"=> "$icon_play __".$levelName."__ by $stars $copyLevelIcon $overObjectsIcon", "value"=> $stats],
						["name"=> $userCoinsDisplay, "value"=> "Old Account: **$userName**"]],
					"color"=> $color,
					"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
					"thumbnail"=> $thumbnailData,
				]];
				break;
			case 6: // Full with user tag
				$data = [
					"content"=> $stars,
					'embed'=> [
						"title"=> $title,
						"fields"=> [
							["name"=> $levelBy, "value"=> $description],
							["name"=> $userCoinsDisplay, "value"=> $stats],
							["name"=> $songDataDisplay, "value"=> $extraInfoDisplay]],
						"color"=> $color,
						"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
						"thumbnail"=> $thumbnailData,
					]];
				break;
			case 7: // Compact with user tag
				$data = [
					"content"=> $stars,
					'embed'=> [
						"title"=> $title,
						"fields"=> [
							["name"=> $levelByCompact, "value"=> $stats],
							["name"=> $userCoinsDisplay, "value"=> $songDataDisplay]],
						"color"=> $color,
						"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
						"thumbnail"=> $thumbnailData,
					]];
				break;
		}
		$data_string = json_encode($data);
		return ['json' => $data_string, 'images' => $imageResources];
	}

	/**
	 * Builds the embed content for a user account.
	 *
	 * @param int $id The embed style preset.
	 * @param string $title The embed title.
	 * @param GdImage|null $thumbnail A GD resource for the thumbnail icon.
	 * @param string $color The embed color.
	 * @param string $footicon The footer icon URL part.
	 * @param string $foottext The footer text.
	 * @param int $targetAccID The target account ID.
	 * @param mixed $stars Extra data (often used for tagging users).
	 * @return array An array containing the 'json' payload and 'images' to attach.
	 */
	public function accEmbedContent($id, $title, $thumbnail, $color, $footicon, $foottext, $targetAccID, $stars){
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php";
		include __DIR__ . "/../discord/emojis.php";

		// Get user social links
		$query = $db->prepare("SELECT youtubeurl, twitter, twitch, discordID, discordLinkReq FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $targetAccID]);
		$userLinks = $query->fetch();

		$socials = "";
		if ($userLinks) {
			$socials .= !empty($userLinks["youtubeurl"]) ? "$icon_youtube [**YouTube**](https://www.youtube.com/channel/".$userLinks["youtubeurl"].")\n" : "";
			$socials .= !empty($userLinks["twitter"]) ? "$icon_twitter [**Twitter**](https://www.twitter.com/".$userLinks["twitter"].")\n" : "";
			$socials .= !empty($userLinks["twitch"]) ? "$icon_twitch [**Twitch**](https://www.twitch.tv/".$userLinks["twitch"].")\n" : "";
			$socials .= ($userLinks["discordLinkReq"] == 1) ? "$icon_discord **<@".$userLinks["discordID"].">**\n" : "";
		}

		// Get user stats
		$query = $db->prepare("SELECT * FROM users WHERE extID = :extID");
		$query->execute([':extID' => $targetAccID]);
		$userStats = $query->fetch();

		if (!$userStats) {
			$data_string = json_encode(["content"=> "This account exists but does not have a profile."]);
			return ['json' => $data_string, 'images' => null];
		}

		// Get user rank
		$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :id LIMIT 1");
		$query->execute([':id' => $targetAccID]);
		$roleID = $query->fetchColumn() ?: 0;
		$rankMap = [
			1 => "$icon_brokenmodstar **DEMOTED :(**\n",
			2 => "$icon_mod **MODERATOR**\n",
			3 => "$icon_head **HEAD MOD**\n",
			4 => "$icon_elder **ELDER MOD**\n",
			5 => "$icon_admin **ADMIN**\n",
		];
		$rank = $rankMap[$roleID] ?? "";

		// Get global leaderboard rank
		$globalRank = "";
		if ($userStats["stars"] > 25) {
			$db->query("SET @rownum := 0;");
			$query = $db->prepare("SELECT rank FROM (SELECT @rownum := @rownum + 1 AS rank, extID FROM users WHERE isBanned = '0' AND gameVersion > 19 AND stars > 25 ORDER BY stars DESC) as result WHERE extID=:extid");
			$query->execute([':extid' => $targetAccID]);
			$globalPos = $query->fetchColumn();
			if ($globalPos) {
				$trophyMap = [1000 => $icon_top1000, 500 => $icon_top500, 200 => $icon_top200, 100 => $icon_top100, 50 => $icon_top50, 10 => $icon_top10, 1 => $icon_top1];
				$globalTrophy = $icon_globalrank;
				foreach ($trophyMap as $rankNum => $trophy) {
					if ($globalPos < $rankNum + 1) $globalTrophy = $trophy;
				}
				$globalRank = "$globalTrophy **Global Rank:** $globalPos \n";
			}
		}

		// Get creator leaderboard rank
		$creatorRank = "";
		if ($userStats["creatorPoints"] > 0) {
			$db->query("SET @rownum := 0;");
			$query = $db->prepare("SELECT rank FROM (SELECT @rownum := @rownum + 1 AS rank, extID FROM users WHERE isCreatorBanned = '0' AND gameVersion > 19 AND creatorPoints > 0 ORDER BY creatorPoints DESC) as result WHERE extID=:extid");
			$query->execute([':extid' => $targetAccID]);
			$creatorPos = $query->fetchColumn();
			if ($creatorPos) {
				$creatorRank = "$icon_creatorrank **Creator Rank:** $creatorPos \n";
			}
		}

		// Prepare strings for display
		$userTitle = "**:chart_with_upwards_trend: " . $userStats["userName"] . "'s stats**";
		$statsDisplay = "$icon_star `".$this->charCount($userStats["stars"])."` \n $icon_moon `".$this->charCount($userStats["moons"])."` \n $icon_secretcoin `".$this->charCount($userStats["coins"])."` \n $icon_verifycoins `".$this->charCount($userStats["userCoins"])."` \n $icon_demon `".$this->charCount($userStats["demons"])."` \n $icon_cp `".$this->charCount($userStats["creatorPoints"])."` \n $icon_diamond `".$this->charCount($userStats["diamonds"])."`";
		$leaderboardInfo = $rank . $globalRank . $creatorRank . $socials;
		$userInfoFooter = " | UserID: " . $userStats["userID"] . " | AccID: $targetAccID";

		// Prepare images for sending
		$images = [];
		if ($thumbnail) {
			$images['thumb.png'] = $thumbnail;
		}
		$iconSetResource = $this->iconSetProfile($targetAccID);
		if ($iconSetResource) {
			$images['icon.png'] = $iconSetResource;
		}

		// Build the base embed structure
		$embedBase = [
			"title"=> $title,
			"description"=> $userTitle,
			"fields"=> [
				["name"=> "────────────", "value"=> $statsDisplay, "inline"=> true],
				["name"=> "────────────", "value"=> $leaderboardInfo, "inline"=> true]],
			"color"=> $color,
			"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$userInfoFooter)]
		];

		if ($thumbnail) $embedBase["thumbnail"] = ["url"=> "attachment://thumb.png"];
		if ($iconSetResource) $embedBase["image"] = ["url"=> "attachment://icon.png"];

		// Build final JSON based on embed type
		$data = [];
		switch($id){
			case 1: // Full embed
				$data = ['embed'=> $embedBase];
				break;
			case 2: // Tagged by bot
				$data = ["content"=> "<@$stars>, here is the profile of user **" . $userStats["userName"] . "**:", 'embed'=> $embedBase];
				break;
			case 3: // DM notification for linking account
				$data = ["content"=> "Congratulations, your account has been linked!", 'embed'=> $embedBase];
				break;
		}
		
		$data_string = json_encode($data);
		return ['json' => $data_string, 'images' => !empty($images) ? $images : null];
	}

	// -----------------------------------------------------------------------------------------
	// SECTION: Image Generation
	// -----------------------------------------------------------------------------------------

	/**
	 * Generates a difficulty face image resource.
	 *
	 * @param int $levelID The ID of the level.
	 * @return GdImage|false A GD image resource or false on failure.
	 */
	public function diffthumbnail($levelID){
		chdir(dirname(__FILE__));
		include __DIR__ . "/../lib/connection.php";
		$query = $db->prepare("SELECT starStars, starFeatured, starEpic, starDemonDiff, starDifficulty, starAuto, starDemon FROM levels WHERE levelID = :lvlid");
		$query->execute([':lvlid' => $levelID]);
		$level = $query->fetch();
		if (!$level) return false;

		// Determine rating flair (featured, epic, etc.)
		$rateImage = "ratena";
		if ($level["starFeatured"] == 1) $rateImage = "ratefeat";
		if ($level["starEpic"] == 1) $rateImage = "rateepic";
		if ($level["starEpic"] == 2) $rateImage = "ratelegendary";
		if ($level["starEpic"] == 3) $rateImage = "ratemythic";

		// Determine difficulty face
		$diffImage = "diff" . $level["starDifficulty"];
		if ($level["starAuto"] == 1) $diffImage = "auto";
		if ($level["starDemon"] == 1) {
			$demonMap = [0 => "demon0", 3 => "demon3", 4 => "demon4", 5 => "demon5", 6 => "demon6"];
			$diffImage = $demonMap[$level["starDemonDiff"]] ?? 'demon0';
		}

		// Determine star value image
		$starImage = "str" . $level["starStars"];
		
		// Generate filename for caching
		$filename = "../../resources/difficulty/{$rateImage}{$diffImage}{$starImage}.png";
		if (file_exists($filename)) {
			return imagecreatefrompng($filename);
		} else {
			// Create image since it doesn't exist in cache
			$rateResource = imagecreatefrompng("resources/diff/$rateImage.png");
			$diffResource = imagecreatefrompng("resources/diff/$diffImage.png");
			$starResource = imagecreatefrompng("resources/diff/$starImage.png");
			imagesavealpha($rateResource, true);
			$sx = imagesx($rateResource);
			$sy = imagesy($rateResource);
			imagecopy($rateResource, $diffResource, 0, 0, 0, 0, $sx, $sy);
			imagecopy($rateResource, $starResource, 0, 0, 0, 0, $sx, $sy);
			imagepng($rateResource, $filename);
			return $rateResource;
		}
	}

	/**
	 * Creates an image resource for a "sent" level thumbnail based on stars.
	 *
	 * @param int $stars Number of stars.
	 * @param int $feature Whether the level is featured (1) or just rated (0).
	 * @return GdImage|null A GD image resource or null if not found.
	 */
	public function iconSent($stars, $feature){
		$prefix = ($feature == 1) ? "feat" : "rate";
		$starMap = [1=>1, 2=>2, 3=>3, 4=>4, 5=>4, 6=>5, 7=>5, 8=>6, 9=>6, 10=>7];
		$faceNum = $starMap[$stars] ?? 0;
		$faceIconPath = "diff/sent/$prefix/$faceNum.png";

		$fullPath = __DIR__ . "/../../resources/" . $faceIconPath;
		if (file_exists($fullPath)) {
			return imagecreatefrompng($fullPath);
		}
		return null;
	}

	/**
	 * Returns a generic thumbnail image resource based on an ID.
	 *
	 * @param int $id The ID of the thumbnail preset.
	 * @return GdImage|null A GD image resource or null if not found.
	 */
	public function thumbnail($id){
		$pathMap = [
			1 => "diff/sent/rate/0.png",    // Unrate
			2 => "levels/like.png",         // Played
			3 => "diff/sent/feat/0.png",    // Feature
			4 => "diff/sent/rate/0.png",    // Unfeat
			5 => "diff/0.png",              // Epic
			6 => "diff/sent/feat/0.png",    // Unepic
			7 => "player/user_coin.png",    // Verify
			8 => "player/user_coin_unverified.png", // Unverify
			9 => "misc/daily.png",          // Daily
			10 => "misc/weekly.png",        // Weekly
			11 => "buttons/delete.png",     // Delete
			12 => "buttons/copy_button.png",// Setacc
			13 => "buttons/user_button.png" // USER
		];
		$imagePath = $pathMap[$id] ?? null;

		if ($imagePath !== null) {
			$fullPath = __DIR__ . "/../../resources/" . $imagePath;
			if (file_exists($fullPath)) {
				return imagecreatefrompng($fullPath);
			}
		}
		return null;
	}

	/**
	 * Generates a single user icon from game assets.
	 * This is the primary, modern icon generator.
	 *
	 * @param int $iconType Type of icon (0:cube, 1:ship, etc.).
	 * @param int $id The ID of the icon asset.
	 * @param int $color1Id The ID for the primary color.
	 * @param int $color2Id The ID for the secondary color.
	 * @param int $color3Id The ID for the glow color.
	 * @param bool $glowEnabled Whether the glow layer should be rendered.
	 * @param int|null $targetPart For multipart icons (robot/spider), which part to render.
	 * @param bool $glowOnly Whether to only render glow layers.
	 * @param string|null $imageRes Image resolution quality ('uhd', 'hd', '' for low, or null for auto-detect).
	 * @return array|false An array of GD image resources, keyed by part, or false on failure.
	 */
	public function iconGenerator($iconType, $id, $color1Id, $color2Id, $color3Id, $glowEnabled, $targetPart, $glowOnly = false, $imageRes = null) {
		// Load config for iconRenderDebug
		include __DIR__ . "/../../config/discord.php";
		
		// --- 1. Load Internal Palette ---
		$jsonPath = __DIR__ . '/resources/colors.json';
		if (!file_exists($jsonPath)) return false;

		$json = file_get_contents($jsonPath);
		$colorsData = json_decode($json, true);
		$palette = [];
		foreach ($colorsData as $c) { 
			$palette[$c['id']] = [$c['r'], $c['g'], $c['b']]; 
		}

		// --- 2. Configure Paths & Names ---
		$types = [0=>'player', 1=>'ship', 2=>'player_ball', 3=>'bird', 4=>'dart', 5=>'robot', 6=>'spider', 7=>'swing', 8=>'jetpack'];
		$typeName = $types[$iconType] ?? 'player';
		$formattedId = sprintf("%02d", $id);
		$baseName = "{$typeName}_{$formattedId}";

		$savePath = __DIR__ . "/iconRender/iconGenerator";
		$pathBase = "{$savePath}/{$baseName}_c{$color1Id}_c{$color2Id}_c{$color3Id}";

		// Determinar qué calidad usar
		$plistFile = null;
		$spriteSheetFile = null;
		
		if ($imageRes !== null) {
			// Usar calidad específica solicitada
			$qualitySuffix = $imageRes === '' ? '' : '-' . $imageRes;
			$testPlist = __DIR__ . "/resources/icons/{$baseName}{$qualitySuffix}.plist";
			$testPng = __DIR__ . "/resources/icons/{$baseName}{$qualitySuffix}.png";
			
			if (file_exists($testPlist) && file_exists($testPng)) {
				$plistFile = $testPlist;
				$spriteSheetFile = $testPng;
			}
		} else {
			// Si es null, usar la menor calidad disponible (orden: sin prefijo > hd > uhd)
			$qualities = ['', '-hd', '-uhd'];
			
			foreach ($qualities as $quality) {
				$testPlist = __DIR__ . "/resources/icons/{$baseName}{$quality}.plist";
				$testPng = __DIR__ . "/resources/icons/{$baseName}{$quality}.png";
				
				if (file_exists($testPlist) && file_exists($testPng)) {
					$plistFile = $testPlist;
					$spriteSheetFile = $testPng;
					break;
				}
			}
		}
		
		if (!$plistFile || !$spriteSheetFile) return false;

		if ($iconRenderDebug && !file_exists($savePath)) {
			mkdir($savePath, 0777, true);
		}

		// --- 3. Process Sprites ---
		$spriteSheet = imagecreatefrompng($spriteSheetFile);
		$xml = simplexml_load_file($plistFile);
		$c1 = $palette[$color1Id]; $c2 = $palette[$color2Id]; $c3 = $palette[$color3Id];
		$rawLayers = [];

		foreach ($xml->dict->dict[0]->children() as $node) {
			if ($node->getName() == 'key') { $keyName = (string)$node; }
			elseif ($node->getName() == 'dict') {
				$data = []; $lastKey = "";
				foreach ($node->children() as $s) {
					if ($s->getName() == 'key') $lastKey = (string)$s;
					else $data[$lastKey] = ($s->getName() == 'true' ? true : ($s->getName() == 'false' ? false : (string)$s));
				}

				$pieceNum = "full";
				if ($iconType == 5 || $iconType == 6) { // Robot or Spider
					if (preg_match('/' . $baseName . '_(\d{2})/', $keyName, $matches)) {
						$pieceNum = $matches[1];
					}
				}

				if ($targetPart !== null && $pieceNum !== "full" && $pieceNum !== sprintf("%02d", $targetPart)) {
					continue;
				}

				$n = strtolower($keyName);
				$order = 4; $tint = $c1; $useTint = true;
				$isGlow = (strpos($n, '_glow_') !== false);

				if ($isGlow) { 
					// Glow layer handling
					if (!$glowEnabled) continue; // Skip if glow not enabled
					if ($glowOnly) {
						// glowOnly mode: only process glow layers
						$order = 1; $tint = $c3; 
					} else {
						// Normal mode: include glow layers normally
						$order = 1; $tint = $c3; 
					}
				} else {
					// Non-glow layer handling
					if ($glowOnly) continue; // Skip non-glow in glowOnly mode
					
					if (strpos($n, '_3_') !== false) { 
						$order = 2; $useTint = false; 
					} elseif (strpos($n, '_2_') !== false) { 
						$order = 3; $tint = $c2; 
					} elseif (strpos($n, 'extra') !== false) { 
						$order = 5; $useTint = false; 
					}
				}

				$rect = explode(',', str_replace(['{','}',' '], '', $data['textureRect']));
				$isRot = ($data['textureRotated'] === true);
				$w = $isRot ? (int)$rect[3] : (int)$rect[2];
				$h = $isRot ? (int)$rect[2] : (int)$rect[3];

				$piece = imagecreatetruecolor($w, $h);
				imagealphablending($piece, false); imagesavealpha($piece, true);
				imagefill($piece, 0, 0, imagecolorallocatealpha($piece, 0, 0, 0, 127));
				imagecopy($piece, $spriteSheet, 0, 0, (int)$rect[0], (int)$rect[1], $w, $h);

				if ($isRot) {
					$piece = imagerotate($piece, 90, imagecolorallocatealpha($piece, 0, 0, 0, 127));
					imagealphablending($piece, false); imagesavealpha($piece, true);
				}

				if ($useTint) $piece = $this->tintImage($piece, $tint);

				$offset = explode(',', str_replace(['{','}',' '], '', $data['spriteOffset']));
				
				// Determine which color ID was used for this piece
				$usedColorId = null;
				if ($useTint) {
					if ($isGlow) {
						$usedColorId = $color3Id; // Glow uses color3
					} elseif (strpos($n, '_2_') !== false) {
						$usedColorId = $color2Id; // _2_ uses color2
					} else {
						$usedColorId = $color1Id; // Default uses color1
					}
				}
				
				$rawLayers[] = [
					'img' => $piece, 'order' => $order, 'num' => $pieceNum,
					'offX' => (int)$offset[0], 'offY' => (int)$offset[1],
					'keyName' => $keyName, 'usedColorId' => $usedColorId
				];
			}
		}

		// --- 4. Final Assembly ---
		$groups = [];
		foreach ($rawLayers as $c) { $groups[$c['num']][] = $c; }
		$result = [];
		
		// Determine actual quality used for filename
		$actualQuality = $imageRes;
		if ($actualQuality === null) {
			// Auto-detect quality
			$qualities = ['-uhd', '-hd', ''];
			foreach ($qualities as $q) {
				$testPlist = __DIR__ . "/resources/icons/{$baseName}{$q}.plist";
				if (file_exists($testPlist)) {
					$actualQuality = ($q === '') ? '' : substr($q, 1);
					break;
				}
			}
			if ($actualQuality === null) $actualQuality = '';
		}
		$qualitySuffix = ($actualQuality === '') ? '' : '_' . $actualQuality;

		foreach ($groups as $num => $components) {
			usort($components, function($a, $b) { return $a['order'] <=> $b['order']; });

			$minX = 9999; $maxX = -9999; $minY = 9999; $maxY = -9999;
			foreach ($components as $c) {
				$w = imagesx($c['img']); $h = imagesy($c['img']);
				$x1 = $c['offX'] - ($w / 2); $x2 = $c['offX'] + ($w / 2);
				$y1 = $c['offY'] - ($h / 2); $y2 = $c['offY'] + ($h / 2);
				if ($x1 < $minX) $minX = $x1; if ($x2 > $maxX) $maxX = $x2;
				if ($y1 < $minY) $minY = $y1; if ($y2 > $maxY) $maxY = $y2;
			}

			$finalW = (int)ceil($maxX - $minX);
			$finalH = (int)ceil($maxY - $minY);
			$final = imagecreatetruecolor($finalW, $finalH);
			imagealphablending($final, false); imagesavealpha($final, true);
			imagefill($final, 0, 0, imagecolorallocatealpha($final, 0, 0, 0, 127));
			imagealphablending($final, true);

			foreach ($components as $c) {
				$w = imagesx($c['img']); $h = imagesy($c['img']);
				$posX = ($c['offX'] - ($w / 2)) - $minX;
				$posY = $finalH - (($c['offY'] + ($h / 2)) - $minY);
				
				// Save individual piece if debug is enabled
				if ($iconRenderDebug) {
					// Extract base name from keyName (remove .png extension)
					$spriteName = str_replace('.png', '', $c['keyName']);
					
					// Build filename: spriteName_cColorId-quality.png
					// Example: bird_01_001_c30-uhd.png, bird_01_glow_001_c10-uhd.png
					$pieceFilename = $spriteName;
					if ($c['usedColorId'] !== null) {
						$pieceFilename .= '_c' . $c['usedColorId'];
					}
					// Use hyphen between color and quality
					if ($actualQuality !== '') {
						$pieceFilename .= '-' . $actualQuality;
					}
					$pieceFilename .= '.png';
					
					$piecePath = $savePath . '/' . $pieceFilename;
					imagesavealpha($c['img'], true);
					imagealphablending($c['img'], false);
					imagepng($c['img'], $piecePath);
				}
				
				imagecopy($final, $c['img'], $posX, $posY, 0, 0, $w, $h);
				imagedestroy($c['img']);
			}

			// Only save if not a complete image with glow (glowEnabled=true and glowOnly=false)
			// We want to save individual pieces and pieces without glow, but not complete images with glow
			if ($iconRenderDebug && !($glowEnabled && !$glowOnly && $num === "full")) {
				// Build filename: icontype_iconid_numero de pieza_color1_color2_color3_glow(1,0)_calidad.png
				$pieceNum = ($num !== "full") ? sprintf("%02d", (int)$num) : "01";
				$filename = sprintf(
					'%s_%02d_%s_%d_%d_%d_glow%d%s.png',
					$typeName,
					$id,
					$pieceNum,
					$color1Id,
					$color2Id,
					$color3Id,
					$glowEnabled ? 1 : 0,
					$qualitySuffix
				);
				$filepath = $savePath . '/' . $filename;
				imagesavealpha($final, true);
				imagealphablending($final, false);
				imagepng($final, $filepath);
			}
			$result[$num] = $final;
		}

		imagedestroy($spriteSheet);
		return $result;
	}

	/**
	 * Generates the main profile icon for a user.
	 *
	 * @param int|null $accountID The user's account ID. If null, uses provided parameters.
	 * @param string|null $imageRes Image resolution quality (default: from $iconProfileRes config, or 'uhd').
	 * @param int|null $iconType Icon type (used if accountID is null).
	 * @param int|null $icon Icon ID (used if accountID is null).
	 * @param int|null $color1 Primary color ID (used if accountID is null).
	 * @param int|null $color2 Secondary color ID (used if accountID is null).
	 * @param int|null $color3 Glow color ID (used if accountID is null).
	 * @param bool|null $glowEnabled Whether glow is enabled (used if accountID is null).
	 * @param bool $saveImage Whether to save the generated image to disk.
	 * @return GdImage|null A GD image resource for the icon.
	 */
	public function iconProfile($accountID = null, $imageRes = null, $iconType = null, $icon = null, $color1 = null, $color2 = null, $color3 = null, $glowEnabled = null, $saveImage = false){
		// If accountID is provided, get data from database
		if ($accountID !== null) {
			include __DIR__ . "/../lib/connection.php";

			$query = $db->prepare("SELECT iconType, icon, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
			$query->execute([':extID' => $accountID]);
			$user = $query->fetch();

			if (!$user) return null;

			$iconType = $user["iconType"];
			$icon = $user["icon"];
			$color1 = $user["color1"];
			$color2 = $user["color2"];
			$color3 = $user["color3"];
			$glowEnabled = ($user["accGlow"] == 1);
		}

		// Validate required parameters
		if ($iconType === null || $icon === null || $color1 === null || $color2 === null || $color3 === null || $glowEnabled === null) {
			return null;
		}

		// Use default quality from config if not specified
		if ($imageRes === null) {
			include __DIR__ . "/../../config/discord.php";
			$imageRes = $iconProfileRes ?? 'uhd';
		}

		// Use iconBuilder (handles both multipart and simple icons) with specified quality
		$iconImage = $this->iconBuilder(
			$iconType,
			$icon,
			$color1,
			$color2,
			$color3,
			$glowEnabled,
			$imageRes
		);

		// Save image if requested
		if ($saveImage && $iconImage !== null) {
			$savePath = dirname(__DIR__, 2) . "/resources/iconprofile";
			if (!file_exists($savePath)) {
				mkdir($savePath, 0777, true);
			}
			
			// Map icon types to names
			$typeNames = [
				0 => 'player', 1 => 'ship', 2 => 'player_ball', 3 => 'bird',
				4 => 'dart', 5 => 'robot', 6 => 'spider', 7 => 'swing', 8 => 'jetpack'
			];
			$typeName = $typeNames[$iconType] ?? 'player';
			
			// Determine actual quality used (should already be resolved, but keep as fallback)
			$actualQuality = $imageRes;
			if ($actualQuality === null) {
				include __DIR__ . "/../../config/discord.php";
				$actualQuality = $iconProfileRes ?? 'uhd';
			}
			
			// Build filename: icontype_iconid_color1_color2_color3_glow(1,0)_calidad.png
			$qualitySuffix = ($actualQuality === '') ? '' : '_' . $actualQuality;
			$filename = sprintf(
				'%s_%02d_%d_%d_%d_glow%d%s.png',
				$typeName,
				$icon,
				$color1,
				$color2,
				$color3,
				$glowEnabled ? 1 : 0,
				$qualitySuffix
			);
			$filepath = $savePath . '/' . $filename;
			
			// Save the image
			imagesavealpha($iconImage, true);
			imagealphablending($iconImage, false);
			imagepng($iconImage, $filepath);
		}

		return $iconImage;
	}

	/**
	 * Generates a horizontal strip of user icons.
	 * By default, excludes the currently equipped icon. Set $fullset to true to include all icons.
	 *
	 * @param int|null $accountID The user's account ID. If null, uses provided parameters.
	 * @param bool $fullset If true, includes the currently equipped icon. If false, excludes it (default).
	 * @param bool $includeJetpack Whether to include the jetpack (type 8) in the set. Default is false.
	 * @param string|null $imageRes Image resolution quality (default: from $iconSetRes config, or 'hd').
	 * @param int|null $iconType Current icon type (used if accountID is null).
	 * @param array|null $accs Array of icon IDs by type [0=>icon, 1=>ship, ...] (used if accountID is null).
	 * @param int|null $color1 Primary color ID (used if accountID is null).
	 * @param int|null $color2 Secondary color ID (used if accountID is null).
	 * @param int|null $color3 Glow color ID (used if accountID is null).
	 * @param bool|null $glowEnabled Whether glow is enabled (used if accountID is null).
	 * @param bool $saveImage Whether to save the generated image to disk.
	 * @return GdImage|null A GD image resource for the icon set.
	 */
	public function iconSetProfile($accountID = null, $fullset = false, $includeJetpack = false, $imageRes = null, $iconType = null, $accs = null, $color1 = null, $color2 = null, $color3 = null, $glowEnabled = null, $saveImage = false) {
		// Load config for iconRenderDebug
		include __DIR__ . "/../../config/discord.php";
		
		// If accountID is provided, get data from database
		if ($accountID !== null) {
			include __DIR__ . "/../lib/connection.php";

			$query = $db->prepare("SELECT * FROM users WHERE extID = :extID");
			$query->execute([':extID' => $accountID]);
			$user = $query->fetch();
			if (!$user) return null;

			$iconType = $user["iconType"];
			$accs = [
				0 => $user["accIcon"], 1 => $user["accShip"], 2 => $user["accBall"],
				3 => $user["accBird"], 4 => $user["accDart"], 5 => $user["accRobot"],
				6 => $user["accSpider"], 7 => $user["accSwing"], 8 => $user["accJetpack"]
			];
			$color1 = $user["color1"];
			$color2 = $user["color2"];
			$color3 = $user["color3"];
			$glowEnabled = ($user["accGlow"] == 1);
		}

		// Validate required parameters
		if ($iconType === null || $accs === null || $color1 === null || $color2 === null || $color3 === null || $glowEnabled === null) {
			return null;
		}

		// Use default quality from config if not specified
		if ($imageRes === null) {
			include __DIR__ . "/../../config/discord.php";
			$imageRes = $iconSetRes ?? 'hd';
		}

		$glow = $glowEnabled;
		$iconsToDraw = [];
		foreach ($accs as $type => $iconID) {
			// Exclude jetpack (type 8) if includeJetpack is false
			if ($type == 8 && !$includeJetpack) {
				continue;
			}
			// If fullset is true, include all icons. Otherwise, exclude the currently equipped one.
			if ($fullset || $type != $iconType) {
				$iconsToDraw[$type] = $iconID;
			}
		}

		// Calculate canvas dimensions based on horizontal alignment
		// Spacing scales with quality: base 25 for HD (x2), so x1 = 12.5, x2 = 25, x4 = 50
		$qualityScale = 2.0; // Default to HD (x2)
		if ($imageRes === '') {
			$qualityScale = 1.0; // Low quality (x1)
		} elseif ($imageRes === 'uhd') {
			$qualityScale = 4.0; // UHD quality (x4)
		} elseif ($imageRes === 'hd') {
			$qualityScale = 2.0; // HD quality (x2)
		}
		$baseSpacing = 25; // Base spacing for HD (x2)
		$iconSpacing = (int)($baseSpacing * ($qualityScale / 2.0)); // Scale spacing based on quality
		$maxIconHeight = 0;
		$totalWidth = 0;
		$iconImages = [];
		
		// Initialize icon cache if not exists (static cache for this function call)
		static $iconCache = [];
		
		// First pass: get all icon images and calculate dimensions
		foreach ($iconsToDraw as $type => $iconID) {
			// Create cache key for this icon
			$cacheKey = sprintf('%d_%d_%d_%d_%d_%d_%s', $type, $iconID, $color1, $color2, $color3, $glow ? 1 : 0, $imageRes ?? 'null');
			
			// Check cache first
			if (isset($iconCache[$cacheKey])) {
				// Clone cached image to avoid destroying it when we destroy the copy
				$cachedImg = $iconCache[$cacheKey];
				$cachedW = imagesx($cachedImg);
				$cachedH = imagesy($cachedImg);
				$iconImage = imagecreatetruecolor($cachedW, $cachedH);
				imagealphablending($iconImage, false);
				imagesavealpha($iconImage, true);
				imagecopy($iconImage, $cachedImg, 0, 0, 0, 0, $cachedW, $cachedH);
			} else {
				// Use iconBuilder (handles both multipart and simple icons) with medium quality
				$iconImage = $this->iconBuilder($type, $iconID, $color1, $color2, $color3, $glow, $imageRes);
				
				// Cache the icon if successfully generated
				if ($iconImage) {
					$iconCache[$cacheKey] = $iconImage;
					// Create a copy for use (so we can destroy it later without affecting cache)
					$cachedW = imagesx($iconImage);
					$cachedH = imagesy($iconImage);
					$iconCopy = imagecreatetruecolor($cachedW, $cachedH);
					imagealphablending($iconCopy, false);
					imagesavealpha($iconCopy, true);
					imagecopy($iconCopy, $iconImage, 0, 0, 0, 0, $cachedW, $cachedH);
					$iconImage = $iconCopy;
				}
			}
			
			if ($iconImage) {
				$pW = imagesx($iconImage);
				$pH = imagesy($iconImage);
				$iconImages[] = [
					'image' => $iconImage,
					'width' => $pW,
					'height' => $pH
				];
				if ($pH > $maxIconHeight) {
					$maxIconHeight = $pH;
				}
				$totalWidth += $pW;
			}
		}
		
		// Calculate total canvas width: sum of all icon widths + spacing between them (no side margins)
		$iconCount = count($iconImages);
		$canvasW = $totalWidth + ($iconSpacing * ($iconCount - 1));
		$canvasH = $maxIconHeight; // Height is the tallest icon

		$base = imagecreatetruecolor($canvasW, $canvasH);
		imagesavealpha($base, true);
		imagefill($base, 0, 0, imagecolorallocatealpha($base, 0, 0, 0, 127));
		
		// Map icon types to names
		$typeNames = [
			0 => 'player', 1 => 'ship', 2 => 'player_ball', 3 => 'bird',
			4 => 'dart', 5 => 'robot', 6 => 'spider', 7 => 'swing', 8 => 'jetpack'
		];
		
		// Build icon type sequence for filename (use type IDs and icon IDs: type_iconID)
		$iconTypeSequence = [];
		foreach ($iconsToDraw as $type => $iconID) {
			$iconTypeSequence[] = $type . '_' . sprintf('%02d', $iconID);
		}
		
		// Align icons horizontally with spacing (no side margins)
		$currentX = 0;
		foreach ($iconImages as $iconData) {
			$iconImage = $iconData['image'];
			$pW = $iconData['width'];
			$pH = $iconData['height'];
			
			// Align vertically (center in canvas height)
			$offsetY = ($canvasH - $pH) / 2;
			
			imagecopy($base, $iconImage, $currentX, $offsetY, 0, 0, $pW, $pH);
			imagedestroy($iconImage);
			
			// Move to next position (icon width + spacing)
			$currentX += $pW + $iconSpacing;
		}

		// Save image if requested
		if ($saveImage && $base !== null) {
			$savePath = dirname(__DIR__, 2) . "/resources/iconprofile/iconSet";
			if (!file_exists($savePath)) {
				mkdir($savePath, 0777, true);
			}
			
			// Determine actual quality used (should already be resolved, but keep as fallback)
			$actualQuality = $imageRes;
			if ($actualQuality === null) {
				include __DIR__ . "/../../config/discord.php";
				$actualQuality = $iconSetRes ?? 'hd';
			}
			
			// Build filename: icon1type_icon2type..._color1_color2_color3_glow_enabled (1,0)_calidad.png
			$typeSequenceStr = implode('_', $iconTypeSequence);
			$qualitySuffix = ($actualQuality === '') ? '' : '_' . $actualQuality;
			$filename = sprintf(
				'%s_c%d_c%d_c%d_glow%d%s.png',
				$typeSequenceStr,
				$color1,
				$color2,
				$color3,
				$glow ? 1 : 0,
				$qualitySuffix
			);
			$filepath = $savePath . '/' . $filename;
			
			// Save the image
			imagesavealpha($base, true);
			imagealphablending($base, false);
			imagepng($base, $filepath);
		}

		return $base;
	}

	/**
	 * Icon builder that handles both multipart icons (Robot/Spider) and simple icons.
	 * For multipart icons, composes multiple pieces based on an AnimDesc JSON file.
	 * For simple icons, uses iconGenerator directly.
	 * This function uses iconGenerator as a provider for individual pieces.
	 *
	 * @param int $iconType The icon type (5 for Robot, 6 for Spider, others for simple icons).
	 * @param int $iconID The icon ID to use.
	 * @param int $color1 Primary color ID.
	 * @param int $color2 Secondary color ID.
	 * @param int $color3 Glow color ID.
	 * @param bool $glowEnabled Whether glow is enabled.
	 * @param string|null $imageRes Image resolution quality ('uhd', 'hd', '' for low, or null for auto-detect).
	 * @return GdImage|null A GD image resource for the icon, or null on failure.
	 */
	public function iconBuilder($iconType, $iconID, $color1, $color2, $color3, $glowEnabled, $imageRes = null) {
		// Load config for iconRenderDebug
		include __DIR__ . "/../../config/discord.php";
		
		// Determine AnimDesc key name from iconType
		$animDescKeyMap = [
			5 => 'robot',
			6 => 'spider'
		];
		
		// Map icon types to names
		$typeNames = [
			0 => 'player', 1 => 'ship', 2 => 'player_ball', 3 => 'bird',
			4 => 'dart', 5 => 'robot', 6 => 'spider', 7 => 'swing', 8 => 'jetpack'
		];
		$typeName = $typeNames[$iconType] ?? 'player';
		
		// If not a multipart icon type, use iconGenerator directly
		if (!isset($animDescKeyMap[$iconType])) {
			$iconArray = $this->iconGenerator(
				$iconType,
				$iconID,
				$color1,
				$color2,
				$color3,
				$glowEnabled,
				null,
				false,
				$imageRes
			);
			
			if (!$iconArray) return null;
			
			// Get the full icon or first piece
			$iconImage = $iconArray['full'] ?? reset($iconArray);
			
			// Crop to edges to remove any extra transparent space
			if ($iconImage) {
				$iconImage = $this->_cropToEdges($iconImage);
			}
			
			// Save image to iconRender/iconBuilder if debug is enabled
			if ($iconRenderDebug) {
				$savePath = __DIR__ . "/iconRender/iconBuilder";
				if (!file_exists($savePath)) {
					mkdir($savePath, 0777, true);
				}
				
				// Determine actual quality used (if null, detect it)
				$actualQuality = $imageRes;
				if ($actualQuality === null) {
					// Auto-detect: try to find which quality was actually loaded
					$formattedId = sprintf("%02d", $iconID);
					$baseName = "{$typeName}_{$formattedId}";
					$qualities = ['-uhd', '-hd', ''];
					foreach ($qualities as $q) {
						$testPlist = __DIR__ . "/resources/icons/{$baseName}{$q}.plist";
						if (file_exists($testPlist)) {
							$actualQuality = ($q === '') ? '' : substr($q, 1); // Remove leading dash
							break;
						}
					}
					if ($actualQuality === null) $actualQuality = ''; // Default to low
				}
				
				// Build filename: icontype_iconid_numero de pieza_color1_color2_color3_glow(1,0)_calidad.png
				// For simple icons, use "01" as piece number
				$pieceNum = "01";
				$qualitySuffix = ($actualQuality === '') ? '' : '_' . $actualQuality;
				$filename = sprintf(
					'%s_%02d_%s_%d_%d_%d_glow%d%s.png',
					$typeName,
					$iconID,
					$pieceNum,
					$color1,
					$color2,
					$color3,
					$glowEnabled ? 1 : 0,
					$qualitySuffix
				);
				$filepath = $savePath . '/' . $filename;
				
				// Save the image
				imagesavealpha($iconImage, true);
				imagealphablending($iconImage, false);
				imagepng($iconImage, $filepath);
			}
			
			return $iconImage;
		}
		
		$animDescKey = $animDescKeyMap[$iconType];
		
		// Load AnimDesc JSON file (unified file)
		$jsonPath = __DIR__ . "/resources/AnimDesc.json";
		if (!file_exists($jsonPath)) {
			return null;
		}

		$jsonContent = file_get_contents($jsonPath);
		$animDescData = json_decode($jsonContent, true);
		if (!$animDescData || !isset($animDescData[$animDescKey])) {
			return null;
		}
		
		// Get the sprite data for the requested icon type
		$animDesc = $animDescData[$animDescKey];
		
		if (empty($animDesc)) {
			return null;
		}

		// Parse sprites and apply transformations (without glow first)
		$sprites = [];
		$glowSprites = [];
		
		foreach ($animDesc as $spriteKey => $spriteData) {
			// Extract data
			$pieceNum = (int)$spriteData['piece'];
			$position = $this->_parseVector2($spriteData['position']);
			$scale = $this->_parseVector2($spriteData['scale']);
			$rotation = (float)$spriteData['rotation'];
			$flipped = $this->_parseVector2($spriteData['flipped']);
			$zValue = (int)$spriteData['zValue'];

			// Get piece from iconGenerator (without glow)
			$iconResult = $this->iconGenerator(
				$iconType,
				$iconID,
				$color1,
				$color2,
				$color3,
				false, // No glow for main pieces
				$pieceNum,
				false, // glowOnly = false
				$imageRes
			);

			if ($iconResult) {
				// Get the piece (it should be in the array with key matching the piece number)
				$pieceKey = sprintf("%02d", $pieceNum);
				$originalPiece = $iconResult[$pieceKey] ?? reset($iconResult);

				if ($originalPiece) {
					// Crop piece to edges to remove empty space before transformation
					$piece = $this->_cropToEdges($originalPiece);
					
					// Save individual piece if debug is enabled (before transformation)
					if ($iconRenderDebug) {
						$savePath = __DIR__ . "/iconRender/iconBuilder";
						if (!file_exists($savePath)) {
							mkdir($savePath, 0777, true);
						}
						
						// Determine actual quality used
						$actualQuality = $imageRes;
						if ($actualQuality === null) {
							$actualQuality = 'hd'; // Default to hd
						}
						
						// Build filename: icontype_iconid_numero de pieza_color1_color2_color3_glow(1,0)_calidad.png
						$pieceNumStr = sprintf("%02d", $pieceNum);
						$qualitySuffix = ($actualQuality === '') ? '' : '_' . $actualQuality;
						$filename = sprintf(
							'%s_%02d_%s_%d_%d_%d_glow%d%s.png',
							$typeName,
							$iconID,
							$pieceNumStr,
							$color1,
							$color2,
							$color3,
							$glowEnabled ? 1 : 0,
							$qualitySuffix
						);
						$filepath = $savePath . '/' . $filename;
						
						// Save the piece
						imagesavealpha($piece, true);
						imagealphablending($piece, false);
						imagepng($piece, $filepath);
					}
					
					// Apply transformations
					$transformed = $this->_transformPiece($piece, $scale, $rotation, $flipped);

					$sprites[] = [
						'image' => $transformed,
						'position' => $position,
						'scale' => $scale,
						'flipped' => $flipped,
						'rotation' => $rotation,
						'piece' => $pieceNum,
						'zValue' => $zValue
					];

					// Clean up unused pieces from iconResult and cropped piece if different from original
					foreach ($iconResult as $key => $img) {
						if ($img !== $transformed) {
							imagedestroy($img);
						}
					}
					// Destroy cropped piece if it's different from original
					if ($piece !== $originalPiece) {
						imagedestroy($piece);
					}
				}
			}

			// Get glow piece if glow is enabled
			if ($glowEnabled) {
				$glowResult = $this->iconGenerator(
					$iconType,
					$iconID,
					$color1,
					$color2,
					$color3,
					true, // Glow enabled
					$pieceNum,
					true, // glowOnly = true
					$imageRes
				);

				if ($glowResult) {
					$pieceKey = sprintf("%02d", $pieceNum);
					$originalGlowPiece = $glowResult[$pieceKey] ?? reset($glowResult);

					if ($originalGlowPiece) {
						// Crop glow piece to edges to remove empty space before transformation
						$glowPiece = $this->_cropToEdges($originalGlowPiece);
						
						// Save individual glow piece if debug is enabled (before transformation)
						if ($iconRenderDebug) {
							$savePath = __DIR__ . "/iconRender/iconBuilder";
							if (!file_exists($savePath)) {
								mkdir($savePath, 0777, true);
							}
							
							// Determine actual quality used
							$actualQuality = $imageRes;
							if ($actualQuality === null) {
								$actualQuality = 'hd'; // Default to hd
							}
							
							// Build filename: icontype_iconid_numero de pieza_color1_color2_color3_glow(1,0)_calidad.png
							// For glow pieces, we'll add "_glow" suffix to distinguish them
							$pieceNumStr = sprintf("%02d", $pieceNum);
							$qualitySuffix = ($actualQuality === '') ? '' : '_' . $actualQuality;
							$filename = sprintf(
								'%s_%02d_%s_glow_%d_%d_%d_glow%d%s.png',
								$typeName,
								$iconID,
								$pieceNumStr,
								$color1,
								$color2,
								$color3,
								$glowEnabled ? 1 : 0,
								$qualitySuffix
							);
							$filepath = $savePath . '/' . $filename;
							
							// Save the glow piece
							imagesavealpha($glowPiece, true);
							imagealphablending($glowPiece, false);
							imagepng($glowPiece, $filepath);
						}
						
						// Apply same transformations
						$transformedGlow = $this->_transformPiece($glowPiece, $scale, $rotation, $flipped);

						$glowSprites[] = [
							'image' => $transformedGlow,
							'position' => $position,
							'scale' => $scale,
							'flipped' => $flipped,
							'rotation' => $rotation,
							'piece' => $pieceNum,
							'zValue' => -1 // Glow goes behind everything
						];

						// Clean up unused pieces from glowResult and cropped piece if different from original
						foreach ($glowResult as $key => $img) {
							if ($img !== $transformedGlow) {
								imagedestroy($img);
							}
						}
						// Destroy cropped glow piece if it's different from original
						if ($glowPiece !== $originalGlowPiece) {
							imagedestroy($glowPiece);
						}
					}
				}
			}
		}

		if (empty($sprites) && empty($glowSprites)) {
			return null;
		}

		// Combine glow sprites (behind) with main sprites
		$allSprites = array_merge($glowSprites, $sprites);

		// Sort by zValue (ascending - lower zValue renders first/behind)
		usort($allSprites, function($a, $b) {
			return $a['zValue'] <=> $b['zValue'];
		});

		// Calculate scale factors based on image resolution
		// AnimDesc coordinates are based on HD (2x scale)
		// Quality scales: '' = 1x (low), 'hd' = 2x (medium), 'uhd' = 4x (high)
		$qualityScale = 2.0; // Default to HD (2x)
		if ($imageRes === '') {
			$qualityScale = 1.0; // Low quality (1x)
		} elseif ($imageRes === 'uhd') {
			$qualityScale = 4.0; // UHD quality (4x)
		} elseif ($imageRes === 'hd') {
			$qualityScale = 2.0; // HD quality (2x)
		}
		// If $imageRes is null, use the actual loaded quality from iconGenerator
		// We'll default to HD (2x) in this case
		
		// Base constants for HD (2x scale)
		$BASE_ICON_SIZE = 300;
		$BASE_SCALE_FACTOR = 2.15;
		
		// Scale constants based on quality
		$ICON_SIZE = $BASE_ICON_SIZE * ($qualityScale / 2.0);
		$SCALE_FACTOR = $BASE_SCALE_FACTOR * ($qualityScale / 2.0);
		
		// Calculate canvas size based on all transformed sprites
		// First pass: find min/max coordinates considering sprite centers and dimensions
		// Following Java logic: translate(position.x * 4.0, -position.y * 4.0)
		$minX = 9999;
		$maxX = -9999;
		$minY = 9999;
		$maxY = -9999;

		foreach ($allSprites as $sprite) {
			// Image is already transformed (scaled, flipped, rotated)
			$finalW = imagesx($sprite['image']);
			$finalH = imagesy($sprite['image']);
			$pos = $sprite['position'];
			$scale = $sprite['scale'];
			$flipped = $sprite['flipped'] ?? ['x' => 0, 'y' => 0];
			$pieceNum = $sprite['piece'] ?? -1;

			// Apply Java transformation: translate(position.x * 4.0, -position.y * 4.0)
			$translatedX = $pos['x'] * $SCALE_FACTOR;
			$translatedY = -$pos['y'] * $SCALE_FACTOR; // Y is inverted
			
			// After scale, adjust center: translate(ICON_WIDTH * (1 / (2 * scale.x) - 0.5), ...)
			// Avoid division by zero
			$scaleX = ($scale['x'] != 0) ? $scale['x'] : 1.0;
			$scaleY = ($scale['y'] != 0) ? $scale['y'] : 1.0;
			$centerAdjustX = $ICON_SIZE * ((1 / (2 * $scaleX)) - 0.5);
			$centerAdjustY = $ICON_SIZE * ((1 / (2 * $scaleY)) - 0.5);
			
			// For piece 02 (spider legs), try without center adjustment to fix displacement
			if ($pieceNum == 2) {
				$finalX = $translatedX;
				$finalY = $translatedY;
			} else {
				// If flipped horizontally, invert the center adjustment X
				if (($flipped['x'] ?? 0) == 1) {
					$centerAdjustX = -$centerAdjustX;
				}
				
				$finalX = $translatedX + $centerAdjustX;
				$finalY = $translatedY + $centerAdjustY;
			}
			
			// Calculate bounding box (position is center, use final dimensions)
			// For rotated images, we need to consider the diagonal to ensure we capture all corners
			$halfW = $finalW / 2;
			$halfH = $finalH / 2;
			
			// If rotated, calculate diagonal distance to ensure we capture all corners
			$rotation = $sprite['rotation'] ?? 0;
			if ($rotation != 0) {
				// Calculate diagonal to handle rotation
				$diagonal = sqrt($halfW * $halfW + $halfH * $halfH);
				$x1 = $finalX - $diagonal;
				$x2 = $finalX + $diagonal;
				$y1 = $finalY - $diagonal;
				$y2 = $finalY + $diagonal;
			} else {
				$x1 = $finalX - $halfW;
				$x2 = $finalX + $halfW;
				$y1 = $finalY - $halfH;
				$y2 = $finalY + $halfH;
			}

			if ($x1 < $minX) $minX = $x1;
			if ($x2 > $maxX) $maxX = $x2;
			if ($y1 < $minY) $minY = $y1;
			if ($y2 > $maxY) $maxY = $y2;
			
			// Special handling for spider (iconType 6) piece 02 - ensure we capture bottom edge correctly
			// Add a small buffer for piece 02 to account for positioning issues that cause margin below
			if ($pieceNum == 2) {
				// For spider legs (piece 02), add extra buffer to bottom to ensure we capture all pixels
				// This accounts for the fact that piece 02 doesn't use centerAdjustY
				$extraBottom = $finalH * 0.5; // 50% extra buffer for bottom to ensure we capture all pixels
				$y2WithBuffer = $y2 + $extraBottom;
				if ($y2WithBuffer > $maxY) $maxY = $y2WithBuffer;
			}
		}

		// Calculate canvas dimensions with padding to avoid clipping
		// Add padding to ensure we capture all pixels, especially for rotated/scaled images
		$padding = 50; // Increased safety padding to ensure no clipping
		$contentWidth = $maxX - $minX;
		$contentHeight = $maxY - $minY;
		
		// Ensure minimum size
		if ($contentWidth < 1) $contentWidth = 1;
		if ($contentHeight < 1) $contentHeight = 1;
		
		// Calculate offset to position content with padding
		$offsetX = -$minX + $padding;
		$offsetY = -$minY + $padding;

		// Create temporary canvas with padding (we'll crop it later)
		$canvasW = (int)ceil($contentWidth + ($padding * 2));
		$canvasH = (int)ceil($contentHeight + ($padding * 2));
		$canvas = imagecreatetruecolor($canvasW, $canvasH);
		imagesavealpha($canvas, true);
		imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
		imagealphablending($canvas, true);

		// Render all sprites (glow first, then main pieces)
		// Darkening factor for first 3 non-glow sprites (all same darkness, not too dark)
		$darkeningFactor = 0.50; // 75% brightness (25% darker)
		
		// Count non-glow sprites to identify first 3
		$nonGlowSpriteIndex = 0;
		
		foreach ($allSprites as $sprite) {
			$img = $sprite['image'];
			$zValue = (int)($sprite['zValue'] ?? 0);
			$pos = $sprite['position'];
			$scale = $sprite['scale'] ?? ['x' => 1.0, 'y' => 1.0];
			$flipped = $sprite['flipped'] ?? ['x' => 0, 'y' => 0];
			
			// Darken first 3 non-glow sprites (zValue >= 0)
			if ($zValue >= 0) {
				if ($nonGlowSpriteIndex < 3) {
					$darkenedImg = $this->_darkenImage($img, $darkeningFactor);
					if ($darkenedImg && $darkenedImg !== $img) {
						$img = $darkenedImg;
					}
				}
				$nonGlowSpriteIndex++;
			}
			
			$w = imagesx($img);
			$h = imagesy($img);

			// Apply Java transformation logic
			// 1. translate(position.x * 4.0, -position.y * 4.0)
			$translatedX = $pos['x'] * $SCALE_FACTOR;
			$translatedY = -$pos['y'] * $SCALE_FACTOR; // Y is inverted
			
			// 2. scale(scale.x, scale.y) - already applied in _transformPiece
			// 3. translate(ICON_WIDTH * (1 / (2 * scale.x) - 0.5), ICON_HEIGHT * (1 / (2 * scale.y) - 0.5))
			// Avoid division by zero
			$scaleX = ($scale['x'] != 0) ? $scale['x'] : 1.0;
			$scaleY = ($scale['y'] != 0) ? $scale['y'] : 1.0;
			$centerAdjustX = $ICON_SIZE * ((1 / (2 * $scaleX)) - 0.5);
			$centerAdjustY = $ICON_SIZE * ((1 / (2 * $scaleY)) - 0.5);
			
			// Get piece number to check if special handling is needed
			$pieceNum = $sprite['piece'] ?? -1;
			
			// For piece 02 (spider legs), try without center adjustment to fix displacement
			if ($pieceNum == 2) {
				$finalX = $translatedX;
				$finalY = $translatedY;
			} else {
				// If flipped horizontally, invert the center adjustment X
				if (($flipped['x'] ?? 0) == 1) {
					$centerAdjustX = -$centerAdjustX;
				}
				
				$finalX = $translatedX + $centerAdjustX;
				$finalY = $translatedY + $centerAdjustY;
			}
			
			// Get final dimensions (image is already scaled)
			$finalW = imagesx($img);
			$finalH = imagesy($img);
			
			// Convert to canvas coordinates (add offset to move from negative to positive)
			// Position is center, so subtract half width/height
			$canvasX = (int)($finalX + $offsetX - ($finalW / 2));
			$canvasY = (int)($finalY + $offsetY - ($finalH / 2));

			imagecopy($canvas, $img, $canvasX, $canvasY, 0, 0, $finalW, $finalH);
			
			// Destroy darkened image if it's different from original sprite image
			if ($img !== $sprite['image']) {
				imagedestroy($img);
			}
			// Original sprite image will be destroyed later in cleanup
		}

		// Use _getCropBounds to find actual content bounds and crop to remove all empty space
		$cropData = $this->_getCropBounds($canvas);
		if ($cropData) {
			$croppedCanvas = imagecreatetruecolor($cropData['width'], $cropData['height']);
			imagesavealpha($croppedCanvas, true);
			imagefill($croppedCanvas, 0, 0, imagecolorallocatealpha($croppedCanvas, 0, 0, 0, 127));
			imagealphablending($croppedCanvas, true);
			
			imagecopy($croppedCanvas, $canvas, 0, 0, $cropData['x'], $cropData['y'], $cropData['width'], $cropData['height']);
			imagedestroy($canvas);
			$canvas = $croppedCanvas;
		}

		// Save image to iconRender/iconBuilder if debug is enabled
		if ($iconRenderDebug) {
			$savePath = __DIR__ . "/iconRender/iconBuilder";
			if (!file_exists($savePath)) {
				mkdir($savePath, 0777, true);
			}
			
			// Determine actual quality used
			$actualQuality = $imageRes;
			if ($actualQuality === null) {
				// Default to hd if null (since we use hd scale factors)
				$actualQuality = 'hd';
			}
			
			// Map icon types to names
			$typeNames = [
				0 => 'player', 1 => 'ship', 2 => 'player_ball', 3 => 'bird',
				4 => 'dart', 5 => 'robot', 6 => 'spider', 7 => 'swing', 8 => 'jetpack'
			];
			$typeName = $typeNames[$iconType] ?? 'player';
			
			// Build filename: icontype_iconid_numero de pieza_color1_color2_color3_glow(1,0)_calidad.png
			// For multipart icons, the individual pieces are already saved above, 
			// this is the final assembled icon - we'll use "00" to indicate it's the complete assembled version
			$pieceNum = "00";
			$qualitySuffix = ($actualQuality === '') ? '' : '_' . $actualQuality;
			$filename = sprintf(
				'%s_%02d_%s_%d_%d_%d_glow%d%s.png',
				$typeName,
				$iconID,
				$pieceNum,
				$color1,
				$color2,
				$color3,
				$glowEnabled ? 1 : 0,
				$qualitySuffix
			);
			$filepath = $savePath . '/' . $filename;
			
			// Save the image
			imagesavealpha($canvas, true);
			imagealphablending($canvas, false);
			imagepng($canvas, $filepath);
		}

		return $canvas;
	}

	/**
	 * Crops an image to its edges, removing all transparent space around it.
	 *
	 * @param GdImage $image The image to crop.
	 * @return GdImage The cropped image (or original if no crop needed).
	 */
	private function _cropToEdges($image) {
		$cropData = $this->_getCropBounds($image);
		if (!$cropData) {
			return $image; // Return original if no content found
		}
		
		// Check if crop is needed (if bounds match image size exactly, no crop needed)
		$w = imagesx($image);
		$h = imagesy($image);
		if ($cropData['x'] == 0 && $cropData['y'] == 0 && 
		    $cropData['width'] == $w && $cropData['height'] == $h) {
			return $image; // No crop needed
		}
		
		$cropped = imagecreatetruecolor($cropData['width'], $cropData['height']);
		imagesavealpha($cropped, true);
		imagefill($cropped, 0, 0, imagecolorallocatealpha($cropped, 0, 0, 0, 127));
		imagealphablending($cropped, true);
		
		imagecopy($cropped, $image, 0, 0, $cropData['x'], $cropData['y'], 
		          $cropData['width'], $cropData['height']);
		
		return $cropped;
	}

	/**
	 * Gets the bounding box of non-transparent pixels in an image.
	 *
	 * @param GdImage $image The image to analyze.
	 * @return array|null An array with 'x', 'y', 'width', 'height' keys, or null if image is fully transparent.
	 */
	private function _getCropBounds($image) {
		$w = imagesx($image);
		$h = imagesy($image);
		
		$minX = $w;
		$maxX = 0;
		$minY = $h;
		$maxY = 0;
		
		$hasContent = false;
		
		// Find bounding box of non-transparent pixels
		// Use a very strict threshold (alpha < 125) to ensure we capture all visible pixels
		// Scan from bottom to top for Y to ensure we capture the lowest pixel first
		for ($y = $h - 1; $y >= 0; $y--) {
			for ($x = 0; $x < $w; $x++) {
				$rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));
				// Consider pixels that are not fully transparent (alpha < 125 for very strict detection)
				if ($rgba['alpha'] < 125) {
					$hasContent = true;
					if ($x < $minX) $minX = $x;
					if ($x > $maxX) $maxX = $x;
					if ($y < $minY) $minY = $y;
					if ($y > $maxY) $maxY = $y;
				}
			}
		}
		
		if (!$hasContent) {
			return null;
		}
		
		$width = $maxX - $minX + 1;
		$height = $maxY - $minY + 1;
		
		// Ensure minimum size
		if ($width < 1) $width = 1;
		if ($height < 1) $height = 1;
		
		return [
			'x' => $minX,
			'y' => $minY,
			'width' => $width,
			'height' => $height
		];
	}

	/**
	 * Helper function to parse a Vector2 string like "{x, y}" into an array.
	 *
	 * @param string $vectorString The vector string to parse.
	 * @return array An array with 'x' and 'y' keys.
	 */
	private function _parseVector2($vectorString) {
		$cleaned = str_replace(['{', '}', ' '], '', $vectorString);
		$parts = explode(',', $cleaned);
		return [
			'x' => (float)($parts[0] ?? 0),
			'y' => (float)($parts[1] ?? 0)
		];
	}

	/**
	 * Applies transformations (scale, rotation, flip) to a piece image.
	 *
	 * @param GdImage $image The source image.
	 * @param array $scale Array with 'x' and 'y' scale values.
	 * @param float $rotation Rotation in degrees.
	 * @param array $flipped Array with 'x' and 'y' flip flags (0 or 1).
	 * @return GdImage The transformed image.
	 */
	private function _transformPiece($image, $scale, $rotation, $flipped) {
		// Validate input image first
		if (!is_resource($image) && !($image instanceof \GdImage)) {
			return $image;
		}
		
		$w = imagesx($image);
		$h = imagesy($image);
		
		// Validate original image dimensions
		if ($w <= 0 || $h <= 0) {
			return $image;
		}

		// Apply scale
		$scaledW = (int)($w * $scale['x']);
		$scaledH = (int)($h * $scale['y']);

		// Ensure minimum dimensions to avoid errors
		if ($scaledW < 1) $scaledW = 1;
		if ($scaledH < 1) $scaledH = 1;

		$scaled = imagescale($image, $scaledW, $scaledH);
		if ($scaled === false) {
			$scaled = $image; // Fallback to original
		} else {
			// Verify scaled image has valid dimensions
			$checkW = imagesx($scaled);
			$checkH = imagesy($scaled);
			if ($checkW <= 0 || $checkH <= 0) {
				imagedestroy($scaled);
				$scaled = $image; // Fallback to original if invalid
			}
		}

		// Apply flip
		if (($flipped['x'] ?? 0) == 1) {
			imageflip($scaled, IMG_FLIP_HORIZONTAL);
		}
		if (($flipped['y'] ?? 0) == 1) {
			imageflip($scaled, IMG_FLIP_VERTICAL);
		}

		// Apply rotation (after scale and flip)
		if (abs($rotation) > 0.01) {
			// Normalize rotation to -180 to 180 range (better for rotation)
			$normalizedRotation = fmod($rotation, 360);
			if ($normalizedRotation > 180) {
				$normalizedRotation -= 360;
			} elseif ($normalizedRotation < -180) {
				$normalizedRotation += 360;
			}

			// Only rotate if significant (handle near-360 rotations as near-0)
			if (abs($normalizedRotation) > 0.1) {
				// Verify image has valid dimensions before rotating
				$rotW = imagesx($scaled);
				$rotH = imagesy($scaled);
				
				// Double-check dimensions are valid and reasonable
				if ($rotW > 0 && $rotH > 0 && $rotW <= 10000 && $rotH <= 10000) {
					$transparent = @imagecolorallocatealpha($scaled, 0, 0, 0, 127);
					if ($transparent !== false) {
						// Use @ to suppress warnings, but check result
						$rotated = @imagerotate($scaled, -$normalizedRotation, $transparent);
						
						// Verify rotation succeeded
						if ($rotated !== false && is_resource($rotated) || ($rotated instanceof \GdImage)) {
							// Verify rotated image has valid dimensions
							$rotatedW = imagesx($rotated);
							$rotatedH = imagesy($rotated);
							if ($rotatedW > 0 && $rotatedH > 0) {
								imagealphablending($rotated, false);
								imagesavealpha($rotated, true);

								// Clean up scaled if different from original
								if ($scaled !== $image) {
									imagedestroy($scaled);
								}
								$scaled = $rotated;
							} else {
								// Rotated image is invalid, destroy it
								imagedestroy($rotated);
							}
						}
						// If rotation failed, continue with scaled image
					}
				}
			}
		}

		return $scaled;
	}

	/**
	 * Darkens an image by a given factor (0.0 to 1.0).
	 * Factor 1.0 = no change, 0.5 = 50% brightness, 0.0 = black.
	 *
	 * @param GdImage $image The source image.
	 * @param float $factor Darkening factor (1.0 = no change, lower = darker).
	 * @return GdImage The darkened image.
	 */
	private function _darkenImage($image, $factor) {
		if (!$image) {
			return null;
		}
		
		$w = imagesx($image);
		$h = imagesy($image);
		
		if ($w <= 0 || $h <= 0) {
			return $image;
		}
		
		// Create a copy to darken
		$darkened = imagecreatetruecolor($w, $h);
		if (!$darkened) {
			return $image;
		}
		
		imagesavealpha($darkened, true);
		imagealphablending($darkened, false);
		
		// Process each pixel - use direct color extraction for truecolor images
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++) {
				$colorInt = imagecolorat($image, $x, $y);
				
				// Extract RGBA from 32-bit integer
				// For truecolor images: 0xRRGGBBAA format where AA is 0-127 (GD inverted alpha)
				$a = ($colorInt >> 24) & 0x7F; // Alpha: 0 (opaque) to 127 (transparent)
				$r = ($colorInt >> 16) & 0xFF;
				$g = ($colorInt >> 8) & 0xFF;
				$b = $colorInt & 0xFF;
				
				// Only darken non-transparent pixels
				if ($a < 127) {
					// Apply darkening factor
					$r = (int)($r * $factor);
					$g = (int)($g * $factor);
					$b = (int)($b * $factor);
					
					// Ensure values are in valid range
					$r = max(0, min(255, $r));
					$g = max(0, min(255, $g));
					$b = max(0, min(255, $b));
					
					$color = imagecolorallocatealpha($darkened, $r, $g, $b, $a);
					if ($color !== false) {
						imagesetpixel($darkened, $x, $y, $color);
					}
				} else {
					// Keep full transparency
					imagesetpixel($darkened, $x, $y, imagecolorallocatealpha($darkened, 0, 0, 0, 127));
				}
			}
		}
		
		imagealphablending($darkened, true);
		imagesavealpha($darkened, true);
		
		return $darkened;
	}

	// -----------------------------------------------------------------------------------------
	// SECTION: Utility and Helper Functions
	// -----------------------------------------------------------------------------------------

	/**
	 * Returns a preset embed color code.
	 * @param int $id The ID of the color.
	 * @return string The color code.
	 */
	public function embedColor($id){
		$colorMap = [
			1 => "16776960", // Rated
			2 => "65280",    // Sent
			3 => "16711680", // Unrate
			4 => "16748288", // Unepic/Unfeat
			5 => "65535",    // Others
			6 => "65412",    // Admin command
			7 => "0"         // Role manage
		];
		return $colorMap[$id] ?? "65535";
	}

	/**
	 * Returns the path to a moderator badge icon.
	 * @param int $accountID The moderator's account ID.
	 * @return string The relative path to the icon.
	 */
	public function modBadge($accountID){
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php"; // For $iconhost
		if ($accountID == 0) {
			return $iconhost . "misc/gdpsbot.png";
		}

		$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :id");
		$query->execute([':id' => $accountID]);
		$roleID = $query->fetchColumn();

		$badgeMap = [
			1 => "buttons/starmodbroken.png", 2 => "modbadge/mod.png",
			3 => "modbadge/elder.png", 4 => "modbadge/head.png",
			5 => "modbadge/admin.png", 6 => "modbadge/dev.png",
			7 => "modbadge/owner.png"
		];
		$iconPath = $badgeMap[$roleID] ?? "buttons/profile.png";
		return $iconhost . $iconPath;
	}

	/**
	 * Returns the footer text, usually the name of the moderator.
	 * @param int $accountID The moderator's account ID.
	 * @return string The footer text.
	 */
	public function footerText($accountID){
		include __DIR__ . "/../lib/connection.php";
		if ($accountID == 0) return "Chaos-Bot";

		$query = $db->prepare("SELECT userName FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $accountID]);
		$mod = $query->fetchColumn() ?: "Unknown User";

		return "$mod ($accountID)";
	}

	/**
	 * Tints a GD image resource with a specific color while preserving shading.
	 * @param GdImage $img The source image resource.
	 * @param array $color An array with [R, G, B] values.
	 * @return GdImage The tinted image resource.
	 */
	public function tintImage($img, $color) {
		imagesavealpha($img, true);
		$w = imagesx($img); $h = imagesy($img);
		for($y=0; $y<$h; $y++) {
			for($x=0; $x<$w; $x++) {
				$rgba = imagecolorsforindex($img, imagecolorat($img, $x, $y));
				if ($rgba['alpha'] == 127) continue;
				// Multiply RGB channels
				$r = ($color[0]/255) * $rgba['red']; 
				$g = ($color[1]/255) * $rgba['green']; 
				$b = ($color[2]/255) * $rgba['blue'];
				imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, min(255, $r), min(255, $g), min(255, $b), $rgba['alpha']));
			}
		}
		return $img;
	}


	/**
	 * Prepends a '+' if the value is positive.
	 * @param int $value The input number.
	 * @return string The number with a prepended sign if positive.
	 */
	public function ispositive($value){
		return ($value > 0) ? "+" . $value : $value;
	}

	/**
	 * Left-pads a value with spaces for alignment.
	 * @param string $value The string to pad.
	 * @return string The padded string.
	 */
	public function charCount($value){
		return str_pad($value, 9, " ", STR_PAD_LEFT);
	}

	/**
	 * Right-pads a value with spaces for alignment.
	 * @param string $value The string to pad.
	 * @return string The padded string.
	 */
	public function charCount2($value){
		return str_pad($value, 5, " ", STR_PAD_RIGHT);
	}

	// -----------------------------------------------------------------------------------------
	// SECTION: Private Helpers
	// -----------------------------------------------------------------------------------------

	/**
	 * Sends a multipart/form-data request to the Discord API.
	 * @param string $url The destination URL.
	 * @param string $jsonPayload The JSON payload for the message.
	 * @param array|null $imageResources An array of image resources to attach.
	 * @return string|false The response from Discord or false on failure.
	 */
	private function _sendDiscordRequest($url, $jsonPayload, $imageResources = null) {
		include __DIR__ . "/../../config/discord.php"; // For $bottoken
		$boundary = "----Boundary" . uniqid();

		// Build Multipart Body
		$body = "--$boundary\r\n";
		$body .= "Content-Disposition: form-data; name=\"payload_json\"\r\n";
		$body .= "Content-Type: application/json\r\n\r\n";
		$body .= $jsonPayload . "\r\n";

		if ($imageResources !== null) {
			if (!is_array($imageResources)) {
				$imageResources = ['icon.png' => $imageResources];
			}
			foreach ($imageResources as $filename => $resource) {
				$imageData = null;

				if (is_resource($resource) || $resource instanceof GdImage) {
					ob_start();
					imagesavealpha($resource, true);
					imagealphablending($resource, false);
					imagepng($resource, null, 9);
					$imageData = ob_get_clean();
					imagedestroy($resource);
				}
				elseif (is_array($resource) && isset($resource['data'])) {
					$imageData = $resource['data'];
				}
				elseif (is_string($resource) && strlen($resource) > 0) {
					$imageData = $resource;
				}

				if ($imageData !== null) {
					$body .= "--$boundary\r\n";
					$body .= "Content-Disposition: form-data; name=\"file_$filename\"; filename=\"$filename\"\r\n";
					$body .= "Content-Type: image/png\r\n\r\n";
					$body .= $imageData . "\r\n";
				}
			}
		}
		$body .= "--$boundary--\r\n";

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Authorization: Bot $bottoken",
			"Content-Type: multipart/form-data; boundary=$boundary"
		]);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
}
?>