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
			$userTitle = ":chart_with_upwards_trend: __**" . $objData["userName"] . "'s**__ Stats";

			$stats = "$icon_star `".$this->charCount($objData["stars"])."` ─> `".$this->charCount2($this->ispositive($objData["starsDiff"]).$objData["starsDiff"])."`\n".
					 "$icon_diamond `".$this->charCount($objData["diamonds"])."` ─> `".$this->charCount2($this->ispositive($objData["diamondsDiff"]).$objData["diamondsDiff"])."`\n".
					 "$icon_secretcoin `".$this->charCount($objData["coins"])."` ─> `".$this->charCount2($this->ispositive($objData["coinsDiff"]).$objData["coinsDiff"])."`\n".
					 "$icon_verifycoins `".$this->charCount($objData["uc"])."` ─> `".$this->charCount2($this->ispositive($objData["ucDiff"]).$objData["ucDiff"])."`\n".
					 "$icon_demon `".$this->charCount($objData["demons"])."` ─> `".$this->charCount2($this->ispositive($objData["demonsDiff"]).$objData["demonsDiff"])."`";
					 
			$userInfo = "userID: " . $objData["userID"];

			// 1. Get the thumbnail (current individual icon)
			$thumbnailIcon = $this->iconProfile($objData["extID"]);

			// 2. Get the icon set (horizontal strip of other icons)
			$imageSet = $this->iconSetProfile($objData["extID"]);

			$data = [
				'embed' => [
					"title" => "$icon_info User Stats Updated!!!",
					"description" => $userTitle . "\n\n" . $stats,
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
			case 18: $title = "$icon_info User Stats Updated!!!"; break;
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
		$statsDisplay = "$icon_star `".$this->charCount($userStats["stars"])."` \n $icon_diamond `".$this->charCount($userStats["diamonds"])."` \n $icon_secretcoin `".$this->charCount($userStats["coins"])."` \n $icon_verifycoins `".$this->charCount($userStats["userCoins"])."` \n $icon_demon `".$this->charCount($userStats["demons"])."` \n $icon_cp `".$this->charCount($userStats["creatorPoints"])."`";
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
			$rateResource = imagecreatefrompng("resource/$rateImage.png");
			$diffResource = imagecreatefrompng("resource/$diffImage.png");
			$starResource = imagecreatefrompng("resource/$starImage.png");
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
	 * @param bool $saveImage Whether to save the generated image to disk.
	 * @return array|false An array of GD image resources, keyed by part, or false on failure.
	 */
	public function iconGenerator($iconType, $id, $color1Id, $color2Id, $color3Id, $glowEnabled, $targetPart, $saveImage) {
		// --- 1. Load Internal Palette ---
		$jsonPath = __DIR__ . '/colors.json';
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

		$savePath = dirname(__DIR__, 2) . "/resources/iconprofile";
		$pathBase = "{$savePath}/{$baseName}_c{$color1Id}_c{$color2Id}_c{$color3Id}";

		$plistFile = __DIR__ . "/resource/icons/{$baseName}-hd.plist";
		$spriteSheetFile = __DIR__ . "/resource/icons/{$baseName}-hd.png";
		if (!file_exists($plistFile) || !file_exists($spriteSheetFile)) return false;

		if ($saveImage && !file_exists($savePath)) {
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

				if (strpos($n, '_glow_') !== false) { 
					if (!$glowEnabled) continue; 
					$order = 1; $tint = $c3; 
				} elseif (strpos($n, '_3_') !== false) { 
					$order = 2; $useTint = false; 
				} elseif (strpos($n, '_2_') !== false) { 
					$order = 3; $tint = $c2; 
				} elseif (strpos($n, 'extra') !== false) { 
					$order = 5; $useTint = false; 
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
				$rawLayers[] = [
					'img' => $piece, 'order' => $order, 'num' => $pieceNum,
					'offX' => (int)$offset[0], 'offY' => (int)$offset[1]
				];
			}
		}

		// --- 4. Final Assembly ---
		$groups = [];
		foreach ($rawLayers as $c) { $groups[$c['num']][] = $c; }
		$result = [];

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
				imagecopy($final, $c['img'], $posX, $posY, 0, 0, $w, $h);
				imagedestroy($c['img']);
			}

			if ($saveImage) {
				$suffix = ($num !== "full") ? "_" . $num : "";
				imagepng($final, $pathBase . $suffix . ".png");
			}
			$result[$num] = $final;
		}

		imagedestroy($spriteSheet);
		return $result;
	}

	/**
	 * Generates the main profile icon for a user.
	 *
	 * @param int $accountID The user's account ID.
	 * @return GdImage|null A GD image resource for the icon.
	 */
	public function iconProfile($accountID){
		include __DIR__ . "/../lib/connection.php";

		$query = $db->prepare("SELECT iconType, icon, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
		$query->execute([':extID' => $accountID]);
		$user = $query->fetch();

		if (!$user) return null;

		$iconArray = $this->iconGenerator(
			$user["iconType"], $user["icon"], $user["color1"],
			$user["color2"], $user["color3"], ($user["accGlow"] == 1),
			null, false
		);

		if (!$iconArray) return null;

		// For normal icons, 'full' will exist. For multipart (robot/spider), it won't.
		// In that case, we just grab the first available part as a fallback.
		return $iconArray['full'] ?? reset($iconArray);
	}

	/**
	 * Generates a horizontal strip of all user icons except the currently equipped one.
	 *
	 * @param int $accountID The user's account ID.
	 * @return GdImage|null A GD image resource for the icon set.
	 */
	public function iconSetProfile($accountID) {
		include __DIR__ . "/../lib/connection.php";

		$query = $db->prepare("SELECT * FROM users WHERE extID = :extID");
		$query->execute([':extID' => $accountID]);
		$user = $query->fetch();
		if (!$user) return null;

		$glow = ($user["accGlow"] == 1);
		$accs = [
			0 => $user["accIcon"], 1 => $user["accShip"], 2 => $user["accBall"],
			3 => $user["accBird"], 4 => $user["accDart"], 5 => $user["accRobot"],
			6 => $user["accSpider"], 7 => $user["accSwing"]
		];

		$iconsToDraw = [];
		foreach ($accs as $type => $iconID) {
			if ($type != $user["iconType"]) {
				$iconsToDraw[$type] = $iconID;
			}
		}

		$iconW = 100; $iconH = 115; $sideMargin = 5;
		$canvasW = (count($iconsToDraw) * $iconW) + ($sideMargin * 2);
		$canvasH = $iconH;

		$base = imagecreatetruecolor($canvasW, $canvasH);
		imagesavealpha($base, true);
		imagefill($base, 0, 0, imagecolorallocatealpha($base, 0, 0, 0, 127));
		
		$currentX = $sideMargin; 
		foreach ($iconsToDraw as $type => $iconID) {
			$iconResult = $this->iconGenerator($type, $iconID, $user["color1"], $user["color2"], $user["color3"], $glow, 1, false);
			if (!$iconResult) continue;

			$parts = isset($iconResult['full']) ? [$iconResult['full']] : $iconResult;
			foreach ($parts as $part) {
				if ($part) {
					$pW = imagesx($part); $pH = imagesy($part);
					$posY = ($canvasH - $pH) / 2;
					$offsetX = ($iconW - $pW) / 2;
					imagecopy($base, $part, $currentX + $offsetX, $posY, 0, 0, $pW, $pH);
					imagedestroy($part);
				}
			}
			$currentX += $iconW;
		}

		return $base;
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
	 * Automatically assigns Discord roles based on in-game stats.
	 * @param int $accountID The user's account ID.
	 * @return bool False if role assignment is disabled or user is not found.
	 */
	public function roleAssign($accountID){
		include __DIR__ . "/../lib/connection.php";
		include __DIR__ . "/../../config/discord.php";
		if($roleAssign != 1 || $discordEnabled != 1) return false;

		$query = $db->prepare("SELECT discordID, discordLinkReq FROM accounts WHERE accountID=:accountID");
		$query->execute([':accountID' => $accountID]);
		$discord = $query->fetch();
		if(!$discord || $discord["discordLinkReq"] != 1) return false;

		$query = $db->prepare("SELECT stars, creatorPoints, completedMapPacks FROM users WHERE extID=:accountID");
		$query->execute([':accountID' => $accountID]);
		$userstats = $query->fetch();
		if(!$userstats) return false;

		$discordID = $discord["discordID"];
		$stars = $userstats["stars"];
		$cp = $userstats["creatorPoints"];
		$mpc = $userstats["completedMapPacks"];

		$this->discordNotify(3, ["content" => $prefix."setrole ".$discordID." ".$role1]); // Member role
		if ($stars > 499) {
			$this->discordNotify(3, ["content" => $prefix."setrole ".$discordID." ".$role2]); // +500 stars
		}
		if ($cp > 4 && $stars > 749) {
			$this->discordNotify(3, ["content" => $prefix."setrole ".$discordID." ".$role3]); // +5 rated levels & 750 stars
		}
		if ($cp > 5 && $mpc > 24 && $stars > 1499) {
			$this->discordNotify(3, ["content" => $prefix."setrole ".$discordID." ".$role4]); // +5 cp, 25 mpc & 1500 stars
		}
		if ($cp > 9 && $stars > 2999) {
			$maxmp = $db->query("SELECT count(*) FROM mappacks")->fetchColumn();
			if ($mpc == $maxmp) {
				$this->discordNotify(3, ["content" => $prefix."setrole ".$discordID." ".$role5]); // 10 cp, 3000 stars & all map packs
			}
		}
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