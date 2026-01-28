<?php
require_once __DIR__ . "/../render/render.php";

/**
 * discordLib - Class to handle Discord notifications and embeds
 * 
 * This class handles all Discord communication, including notifications,
 * embed generation, images and direct messages.
 */
class discordLib {
	
	// =========================================================================================
	// STATIC PROPERTIES AND CACHE
	// =========================================================================================
	
	// Cache for configuration and connection
	private static $configCache = null;
	private static $connectionCache = null;
	
	// Reusable constant arrays
	private static $trophyMap = null;
	private static $rankMap = null;
	private static $badgeMap = [
		1 => "buttons/starmodbroken.png", 2 => "modbadge/mod.png",
		3 => "modbadge/elder.png", 4 => "modbadge/head.png",
		5 => "modbadge/admin.png", 6 => "modbadge/dev.png",
		7 => "modbadge/owner.png"
	];
	
	private static $pathMap = [
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
	
	private static $colorMap = [
		1 => "16776960", // Rated
		2 => "65280",    // Sent
		3 => "16711680", // Unrate
		4 => "16748288", // Unepic/Unfeat
		5 => "65535",    // Others
		6 => "65412",    // Admin command
		7 => "0"         // Role manage
	];
	
	private static $dmTriggerTitles = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 16, 17, 26, 27];
	
	private static $lengthMap = ["TINY", "SHORT", "MEDIUM", "LONG", "XL"];
	
	private static $officialSongs = [
		"Stereo Madness by ForeverBound", "Back on Track by DJVI", "Polargeist by Step",
		"Dry Out by DJVI", "Base after Base by DJVI", "Can't Let Go by DJVI",
		"Jumper by Waterflame", "Time Machine by Waterflame", "Cycles by DJVI",
		"xStep by DJVI", "Clutterfunk by Waterflame", "Theory of Everything by DJ Nate",
		"Electroman Adventures by Waterflame", "Club Step by DJ Nate", "Electrodynamix by DJ Nate",
		"Hexagon Force by Waterflame", "Blast Processing by Waterflame", "Theory of Everything 2 by DJ Nate",
		"Geometrical Dominator by Waterflame", "Deadlocked by F-777", "Fingerbang by MDK"
	];
	
	private static $demonMap = [0 => "demon0", 3 => "demon3", 4 => "demon4", 5 => "demon5", 6 => "demon6"];
	private static $starMap = [1=>1, 2=>2, 3=>3, 4=>4, 5=>4, 6=>5, 7=>5, 8=>6, 9=>6, 10=>7];
	
	// =========================================================================================
	// PRIVATE HELPER METHODS (CACHE AND CONFIGURATION)
	// =========================================================================================
	
	/**
	 * Gets the configuration from discord.php (cached)
	 */
	private function getConfig() {
		if (self::$configCache === null) {
			include __DIR__ . "/../../config/discord.php";
			self::$configCache = get_defined_vars();
		}
		return self::$configCache;
	}
	
	/**
	 * Gets the value of a configuration variable
	 */
	private function getConfigValue($key, $default = null) {
		$config = $this->getConfig();
		return $config[$key] ?? $default;
	}
	
	/**
	 * Gets the database connection (cached)
	 */
	private function getConnection() {
		if (self::$connectionCache === null) {
			include __DIR__ . "/../lib/connection.php";
			self::$connectionCache = $db;
		}
		return self::$connectionCache;
	}
	
	/**
	 * Inicializa arrays que dependen de emojis (se cargan cuando se necesitan)
	 */
	private function initEmojiMaps() {
		if (self::$trophyMap === null || self::$rankMap === null) {
			include __DIR__ . "/../discord/emojis.php";
			
			if (self::$trophyMap === null) {
				self::$trophyMap = [
					1000 => $icon_top1000, 500 => $icon_top500, 200 => $icon_top200, 
					100 => $icon_top100, 50 => $icon_top50, 10 => $icon_top10, 1 => $icon_top1
				];
			}
			
			if (self::$rankMap === null) {
				self::$rankMap = [
					1 => "$icon_brokenmodstar **DEMOTED :(**\n",
					2 => "$icon_mod **MODERATOR**\n",
					3 => "$icon_head **HEAD MOD**\n",
					4 => "$icon_elder **ELDER MOD**\n",
					5 => "$icon_admin **ADMIN**\n",
				];
			}
		}
	}
	
	/**
	 * Gets the channel ID according to the preset
	 */
	private function getChannelID($id) {
		$config = $this->getConfig();
		switch ($id) {
			case 1: return $config['channel1'] ?? null;
			case 2: return $config['channel2'] ?? null;
			case 3: return $config['channel3'] ?? null;
			default: return $id;
		}
	}
	
	/**
	 * Builds the icon data array from the database
	 */
	private function buildIconData($user) {
		return [
			'iconType' => $user["iconType"],
			'iconID' => $user["icon"],
			'color1' => $user["color1"],
			'color2' => $user["color2"],
			'color3' => $user["color3"],
			'glow' => ($user["accGlow"] == 1)
		];
	}
	
	/**
	 * Builds the icon set data array from the database
	 */
	private function buildIconSetData($user) {
		return [
			'iconType' => $user["iconType"],
			'accs' => [
				0 => $user["accIcon"], 1 => $user["accShip"], 2 => $user["accBall"],
				3 => $user["accBird"], 4 => $user["accDart"], 5 => $user["accRobot"],
				6 => $user["accSpider"], 7 => $user["accSwing"], 8 => $user["accJetpack"]
			],
			'color1' => $user["color1"],
			'color2' => $user["color2"],
			'color3' => $user["color3"],
			'glow' => ($user["accGlow"] == 1)
		];
	}
	
	// =========================================================================================
	// SECTION: MAIN NOTIFICATION SENDERS
	// =========================================================================================
	
	/**
	 * Sends a pre-formatted embed to a specific Discord channel.
	 *
	 * @param int|string $id The channel preset (1 or 2) or a direct channel ID.
	 * @param array $data The embed data.
	 * @param GdImage|array|null $imageResources Single or multiple GD image resources to attach.
	 * @return string|false The response from Discord or false on failure.
	 */
	public function discordNotify($id, $data, $imageResources = null){
		if ($this->getConfigValue('discordEnabled', 0) != 1) return false;

		$channelID = $this->getChannelID($id);
		if ($channelID === null) return false;

		$url = "https://discord.com/api/v10/channels/$channelID/messages";
		$embedContent = $data['embed'] ?? $data;
		$jsonPayload = json_encode(["embeds" => [$embedContent]], JSON_UNESCAPED_UNICODE);

		return $this->_sendDiscordRequest($url, $jsonPayload, $imageResources);
	}

	/**
	 * Notification function for various in-game events.
	 * Builds the embed and sends it to the appropriate channel.
	 *
	 * @param int|string $id Channel preset (1, 2, 3) or a direct channel ID.
	 * @param int $objectID The ID of the object (level, account).
	 * @param int $objectType The type of object (1 for level, 2 for account).
	 * @param int $embedID The embed style to use.
	 * @param int $title The title preset for the embed.
	 * @param int $color The color preset for the embed.
	 * @param int $authorID The account ID of the action performer.
	 * @param int $thumbType The type of thumbnail to generate.
	 * @param int $thumbID The ID used for thumbnail generation.
	 * @param mixed $extra Extra data, often used for star values.
	 * @return string|false The response from Discord or false on failure.
	 */
	public function discordNotifyNew($id, $objectID, $objectType, $embedID, $title, $color, $authorID, $thumbType, $thumbID, $extra){
		$db = $this->getConnection();
		if ($this->getConfigValue('discordEnabled', 0) != 1) {
			return false;
		}

		$channelID = $this->getChannelID($id);
		if ($channelID === null) return false;

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

			$result = $this->embedContent($embedID, $this->title($title), $thumbnailResource, 
				$this->embedColor($color), $this->modBadge($authorID), 
				$this->footerText($authorID), $objectID, $extra);
			$data_string = $result['json'];
			$imageResources = $result['images'];
			
			$query = $db->prepare("SELECT extID FROM levels WHERE levelID = :id");
			$query->execute([':id' => $objectID]);
			$objectID = $query->fetchColumn();
		}

		// Handle account-related notifications
		if ($objectType == 2) {
			$query = $db->prepare("SELECT iconType, icon, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
			$query->execute([':extID' => $objectID]);
			$user = $query->fetch();
			
			$iconImage = null;
			if ($user) {
				$iconRenderer = new IconRenderer();
				$iconImage = $iconRenderer->getIcon($this->buildIconData($user));
			}
			
			$result = $this->accEmbedContent($embedID, $this->title($title), $iconImage, 
				$this->embedColor($color), $this->modBadge($authorID), 
				$this->footerText($authorID), $objectID, $extra);
			$data_string = $result['json'];
			$imageResources = $result['images'];
		}

		// DM notification logic
		$query = $db->prepare("SELECT discordID, discordLinkReq FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $objectID]);
		$discordData = $query->fetch();

		if ($discordData && $discordData["discordLinkReq"] == 1) {
			if (in_array($title, self::$dmTriggerTitles)) {
				$this->discordDMNotify($discordData["discordID"], $data_string, $imageResources);
			}
		}

		$url = "https://discord.com/api/v10/channels/$channelID/messages";
		$dataArray = json_decode($data_string, true);
		$embedContent = $dataArray['embed'] ?? $dataArray;
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
		include __DIR__ . "/../discord/emojis.php";
		$this->initEmojiMaps();
		
		$description = "**All users creatorPoints updated**\n\n**Top Creators:**\n";
		
		if(!empty($result['top_users']) && is_array($result['top_users'])){
			$rank = 1;
			$maxUsers = min(20, count($result['top_users']));
			for($i = 0; $i < $maxUsers; $i++){
				$user = $result['top_users'][$i];
				if(isset($user['userName']) && isset($user['creatorPoints'])){
					$trophy = $icon_globalrank;
					foreach (self::$trophyMap as $rankNum => $trophyIcon) {
						if ($rank < $rankNum + 1) {
							$trophy = $trophyIcon;
							break;
						}
					}
					
					$description .= "$trophy `$rank.` **" . htmlspecialchars($user['userName'], ENT_QUOTES) . "** - $icon_cp `" . round($user['creatorPoints'], 0) . "`\n";
					$rank++;
				}
			}
		} else {
			$description .= "*No users with creator points*";
		}
		
		// Cargar imagen thumbnail
		$thumbnailPath = dirname(__FILE__) . "/../../resources/misc/gdps.png";
		$imageResources = null;
		if(file_exists($thumbnailPath)){
			$thumbnailResource = @imagecreatefrompng($thumbnailPath);
			if($thumbnailResource !== false){
				$imageResources = ['thumb.png' => $thumbnailResource];
			}
		}
		
		$iconhost = $this->getConfigValue('iconhost', '');
		$embedData = [
			"title" => "$icon_cp Creator Points Updated",
			"description" => $description,
			"color" => 0x00ff00,
			"footer" => [
				"icon_url" => ($iconhost . "misc/gdpsbot.png"),
				"text" => "Updated: " . ($result['updated'] ?? 0) . " users | Failed: " . ($result['failed'] ?? 0)
			]
		];
		
		if($imageResources !== null){
			$embedData["thumbnail"] = ["url" => "attachment://thumb.png"];
		}
		
		return $this->discordNotify(1, ['embed' => $embedData], $imageResources);
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
		if ($this->getConfigValue('discordEnabled', 0) != 1) return false;

		$bottoken = $this->getConfigValue('bottoken', '');
		if (empty($bottoken)) return false;

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
		$embedContent = $dataArray['embed'] ?? $dataArray;
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

			$iconhost = $this->getConfigValue('iconhost', '');
			$data = ['embed' => [
				"title" => $actionTitle,
				"description" => $desc,
				"footer" => ["icon_url" => ($iconhost . "misc/gdpsbot.png"), "text" => $levelInfo],
			]];
			$this->discordNotify(2, $data);
		}

		// --- Profiles Section ---
		if ($objectType == 1) {
			$db = $this->getConnection();
			$this->initEmojiMaps();
			
			$userTitle = ":chart_with_upwards_trend: __**" . $objData["userName"] . "'s**__ Stats";

			// Build stats string - show changes if any, otherwise show current stats
			$stats = "";
			$hasChanges = false;
			$statDiffs = [
				"starsDiff" => $icon_star,
				"moonsDiff" => $icon_moon,
				"coinsDiff" => $icon_secretcoin,
				"ucDiff" => $icon_verifycoins,
				"demonsDiff" => $icon_demon,
				"creatorPointsDiff" => $icon_cp,
				"diamondsDiff" => $icon_diamond
			];
			
			foreach ($statDiffs as $diffKey => $icon) {
				$baseKey = str_replace('Diff', '', $diffKey);
				if(isset($objData[$diffKey]) && $objData[$diffKey] != 0) {
					$stats .= "$icon `".$this->charCount($objData[$baseKey])."` ─> `".$this->charCount2($this->ispositive($objData[$diffKey]))."`\n";
					$hasChanges = true;
				}
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
			$rank = self::$rankMap[$roleID] ?? "";

			// Get global leaderboard rank
			$globalRank = "";
			if ($objData["stars"] > 25) {
				$db->query("SET @rownum := 0;");
				$query = $db->prepare("SELECT rank FROM (SELECT @rownum := @rownum + 1 AS rank, extID FROM users WHERE isBanned = '0' AND gameVersion > 19 AND stars > 25 ORDER BY stars DESC) as result WHERE extID=:extid");
				$query->execute([':extid' => $objData["extID"]]);
				$globalPos = $query->fetchColumn();
				if ($globalPos) {
					$globalTrophy = $icon_globalrank;
					foreach (self::$trophyMap as $rankNum => $trophy) {
						if ($globalPos < $rankNum + 1) {
							$globalTrophy = $trophy;
							break;
						}
					}
					$globalRank = "$globalTrophy **Global Rank:** $globalPos \n";
				}
			}

			// Prepare leaderboard info
			$leaderboardInfo = $rank . $globalRank;
			if($leaderboardInfo == "") $leaderboardInfo = "────────────";
					 
			$userInfo = "userID: " . $objData["userID"];

			// 1. Get the thumbnail (current individual icon)
			$query = $db->prepare("SELECT iconType, icon, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
			$query->execute([':extID' => $objData["extID"]]);
			$user = $query->fetch();
			
			$thumbnailIcon = null;
			$iconRenderer = null;
			if ($user) {
				$iconRenderer = new IconRenderer();
				$thumbnailIcon = $iconRenderer->getIcon($this->buildIconData($user));
			}

			// 2. Get the icon set (horizontal strip of other icons)
			$imageSet = null;
			if ($user && $iconRenderer) {
				$query = $db->prepare("SELECT iconType, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
				$query->execute([':extID' => $objData["extID"]]);
				$userSet = $query->fetch();
				
				if ($userSet) {
					$imageSet = $iconRenderer->getIconSet($this->buildIconSetData($userSet), false, false);
				}
			}

			$title = $this->title($action);
			
			// If no stats, set default
			if($stats == "") $stats = "────────────";
			
			$iconhost = $this->getConfigValue('iconhost', '');
			$data = [
				'embed' => [
					"title" => $title,
					"description" => $userTitle,
					"fields" => [
						["name" => "────────────", "value" => $stats, "inline" => true],
						["name" => "────────────", "value" => $leaderboardInfo, "inline" => true]
					],
					"footer" => ["icon_url" => ($iconhost . "misc/gdpsbot.png"), "text" => $userInfo],
					"thumbnail" => ["url" => "attachment://thumb.png"],
					"image" => ["url" => "attachment://icon.png"]
				]
			];

			// Send notification with two attachments
			$this->discordNotify(2, $data, ['icon.png' => $imageSet, 'thumb.png' => $thumbnailIcon]);
		}
	}

	// =========================================================================================
	// SECTION: EMBED CONTENT BUILDERS
	// =========================================================================================

	/**
	 * Returns a preset title string.
	 * @param int $id The ID of the title.
	 * @return string The formatted title.
	 */
	public function title($id){
		include __DIR__ . "/../discord/emojis.php";
		$titles = [
			1 => "$icon_star New Rated Level!!!",
			2 => "$icon_approved New Approved Level!",
			3 => "$icon_failed Command - Unrate",
			4 => "$icon_like Command - Played",
			5 => "$icon_cp Command - Feature",
			6 => "$icon_failed Command - Unfeat",
			7 => "$icon_cp Command - Epic",
			8 => "$icon_failed Command - Unepic",
			9 => "$icon_info Command - Verifycoins",
			10 => "$icon_info Command - Unverifycoins",
			11 => "$icon_daily Command - Daily",
			12 => "$icon_weekly Command - Weekly",
			13 => "$icon_cross Command - Delete",
			14 => "$icon_info Command - Setacc",
			15 => "$icon_succes Rated Demon!!!",
			16 => "$icon_modstar User Promoted!!!",
			17 => "$icon_brokenmodstar User Demoted...",
			18 => "$icon_info User Profile Update!!!",
			19 => "$icon_info Level Updated!!!",
			20 => "$icon_info New recent level uploaded!!!",
			21 => "$icon_search Search result.",
			22 => "$icon_profile User profile",
			23 => "$icon_daily Current Daily Level",
			24 => "$icon_weekly Current Weekly Level",
			25 => "$icon_profile Server Stats",
			26 => "$icon_brokenmodstar Rank degraded...",
			27 => "$icon_succes Your account has been linked!!!",
			28 => "$icon_cp Command - Legendary",
			29 => "$icon_cp Command - Mythic"
		];
		return $titles[$id] ?? "";
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
		$db = $this->getConnection();
		require_once __DIR__ . "/../lib/mainLib.php";
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

		// Extract level data
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
		
		// Calculate CP dynamically using mainLib function (only for levels with stars)
		$cpCount = 0;
		if($level["starStars"] != 0){
			$gs = new mainLib();
			$cpCount = $gs->calculateLevelCP($level["starFeatured"], $level["starEpic"]);
		}

		// Song info
		$songInfo = "";
		$songDesc = "";
		if ($songID == 0) {
			$songDesc = "__**" . (self::$officialSongs[$audioTrack] ?? 'Unknown Song') . "**__";
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

		// Level length
		$lengthText = self::$lengthMap[$levelLength] ?? "NA";

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

		// Preparar strings para mostrar
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
		$embedTemplates = [
			1 => ['embed'=> [
				"title"=> $title,
				"fields"=> [
					["name"=> $levelBy, "value"=> $description],
					["name"=> $userCoinsDisplay, "value"=> $stats],
					["name"=> $songDataDisplay, "value"=> $extraInfoDisplay]],
				"color"=> $color,
				"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
				"thumbnail"=> $thumbnailData,
			]],
			2 => ['embed'=> [
				"title"=> $title,
				"fields"=> [
					["name"=> $levelByCompact, "value"=> $stats],
					["name"=> $userCoinsDisplay, "value"=> $songDataDisplay]],
				"color"=> $color,
				"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
				"thumbnail"=> $thumbnailData,
			]],
			3 => ['embed'=> [
				"title"=> $title,
				"fields"=> [
					["name"=> $levelByCompact, "value"=> $statsCompact],
					["name"=> "Sent Stars: $stars $icon_star", "value"=> "───────────────────"],
					["name"=> $userCoinsDisplay, "value"=> $songDataDisplay]],
				"color"=> $color,
				"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
				"thumbnail"=> $thumbnailData,
			]],
			4 => ['embed'=> [
				"title"=> $title,
				"description"=> "New Daily/weekly level queued!",
				"fields"=> [
					["name"=> $levelByCompact, "value"=> $stats],
					["name"=> $userCoinsDisplay, "value"=> "$icon_length __Is out:__ $stars"]],
				"color"=> $color,
				"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
				"thumbnail"=> $thumbnailData,
			]],
			5 => ['embed'=> [
				"title"=> $title,
				"fields"=> [
					["name"=> "$icon_play __".$levelName."__ by $stars $copyLevelIcon $overObjectsIcon", "value"=> $stats],
					["name"=> $userCoinsDisplay, "value"=> "Old Account: **$userName**"]],
				"color"=> $color,
				"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
				"thumbnail"=> $thumbnailData,
			]],
			6 => [
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
				]
			],
			7 => [
				"content"=> $stars,
				'embed'=> [
					"title"=> $title,
					"fields"=> [
						["name"=> $levelByCompact, "value"=> $stats],
						["name"=> $userCoinsDisplay, "value"=> $songDataDisplay]],
					"color"=> $color,
					"footer"=> ["icon_url"=> $footicon, "text"=> ($foottext.$levelInfoFooter)],
					"thumbnail"=> $thumbnailData,
				]
			]
		];
		
		$data = $embedTemplates[$id] ?? [];
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
		$db = $this->getConnection();
		include __DIR__ . "/../discord/emojis.php";
		$this->initEmojiMaps();

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
		$rank = self::$rankMap[$roleID] ?? "";

		// Get global leaderboard rank
		$globalRank = "";
		if ($userStats["stars"] > 25) {
			$db->query("SET @rownum := 0;");
			$query = $db->prepare("SELECT rank FROM (SELECT @rownum := @rownum + 1 AS rank, extID FROM users WHERE isBanned = '0' AND gameVersion > 19 AND stars > 25 ORDER BY stars DESC) as result WHERE extID=:extid");
			$query->execute([':extid' => $targetAccID]);
			$globalPos = $query->fetchColumn();
			if ($globalPos) {
				$globalTrophy = $icon_globalrank;
				foreach (self::$trophyMap as $rankNum => $trophy) {
					if ($globalPos < $rankNum + 1) {
						$globalTrophy = $trophy;
						break;
					}
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
		$iconRenderer = new IconRenderer();
		$query = $db->prepare("SELECT iconType, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
		$query->execute([':extID' => $targetAccID]);
		$user = $query->fetch();
		
		$iconSetResource = null;
		if ($user) {
			$iconSetResource = $iconRenderer->getIconSet($this->buildIconSetData($user), false, false);
		}
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
		$embedTypes = [
			1 => ['embed'=> $embedBase],
			2 => ["content"=> "<@$stars>, here is the profile of user **" . $userStats["userName"] . "**:", 'embed'=> $embedBase],
			3 => ["content"=> "Congratulations, your account has been linked!", 'embed'=> $embedBase]
		];
		
		$data = $embedTypes[$id] ?? ['embed'=> $embedBase];
		$data_string = json_encode($data);
		return ['json' => $data_string, 'images' => !empty($images) ? $images : null];
	}

	// =========================================================================================
	// SECTION: IMAGE GENERATION
	// =========================================================================================

	/**
	 * Generates a difficulty face image resource.
	 *
	 * @param int $levelID The ID of the level.
	 * @return GdImage|false A GD image resource or false on failure.
	 */
	public function diffthumbnail($levelID){
		chdir(dirname(__FILE__));
		$db = $this->getConnection();
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
			$diffImage = self::$demonMap[$level["starDemonDiff"]] ?? 'demon0';
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
		$faceNum = self::$starMap[$stars] ?? 0;
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
		$imagePath = self::$pathMap[$id] ?? null;

		if ($imagePath !== null) {
			$fullPath = __DIR__ . "/../../resources/" . $imagePath;
			if (file_exists($fullPath)) {
				return imagecreatefrompng($fullPath);
			}
		}
		return null;
	}

	// =========================================================================================
	// SECTION: UTILITY AND HELPER FUNCTIONS
	// =========================================================================================

	/**
	 * Returns a preset embed color code.
	 * @param int $id The ID of the color.
	 * @return string The color code.
	 */
	public function embedColor($id){
		return self::$colorMap[$id] ?? "65535";
	}

	/**
	 * Returns the path to a moderator badge icon.
	 * @param int $accountID The moderator's account ID.
	 * @return string The relative path to the icon.
	 */
	public function modBadge($accountID){
		$iconhost = $this->getConfigValue('iconhost', '');
		if ($accountID == 0) {
			return $iconhost . "misc/gdpsbot.png";
		}

		$db = $this->getConnection();
		$query = $db->prepare("SELECT roleID FROM roleassign WHERE accountID = :id");
		$query->execute([':id' => $accountID]);
		$roleID = $query->fetchColumn();

		$iconPath = self::$badgeMap[$roleID] ?? "buttons/profile.png";
		return $iconhost . $iconPath;
	}

	/**
	 * Returns the footer text, usually the name of the moderator.
	 * @param int $accountID The moderator's account ID.
	 * @return string The footer text.
	 */
	public function footerText($accountID){
		if ($accountID == 0) return "Chaos-Bot";

		$db = $this->getConnection();
		$query = $db->prepare("SELECT userName FROM accounts WHERE accountID = :id");
		$query->execute([':id' => $accountID]);
		$mod = $query->fetchColumn() ?: "Unknown User";

		return "$mod ($accountID)";
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

	// =========================================================================================
	// SECTION: PRIVATE HELPERS
	// =========================================================================================

	/**
	 * Sends a multipart/form-data request to the Discord API.
	 * @param string $url The destination URL.
	 * @param string $jsonPayload The JSON payload for the message.
	 * @param array|null $imageResources An array of image resources to attach.
	 * @return string|false The response from Discord or false on failure.
	 */
	private function _sendDiscordRequest($url, $jsonPayload, $imageResources = null) {
		$bottoken = $this->getConfigValue('bottoken', '');
		if (empty($bottoken)) return false;
		
		$boundary = "----Boundary" . uniqid();

		// Build multipart body
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
