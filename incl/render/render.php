<?php
/**
 * IconRenderer - Class for generating user icons
 * 
 * This class handles all icon generation, including simple icons,
 * multipart icons (robot/spider), icon profiles and icon sets.
 */
class IconRenderer {
	// Cache for configuration
	private static $configCache = null;
	
	// Cache for JSON files
	private static $colorsPaletteCache = null;
	private static $animDescCache = null;
	
	// Reusable constant arrays
	private static $typeNames = [
		0 => 'player', 1 => 'ship', 2 => 'player_ball', 3 => 'bird',
		4 => 'dart', 5 => 'robot', 6 => 'spider', 7 => 'swing', 8 => 'jetpack'
	];
	
	private static $animDescKeyMap = [
		5 => 'robot',
		6 => 'spider'
	];
	
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
	 * Loads and caches the color palette
	 */
	private function getColorsPalette() {
		if (self::$colorsPaletteCache === null) {
			$jsonPath = __DIR__ . '/colors.json';
			if (!file_exists($jsonPath)) {
				return false;
			}
			
			$json = file_get_contents($jsonPath);
			$colorsData = json_decode($json, true);
			if (!$colorsData) {
				return false;
			}
			
			$palette = [];
			foreach ($colorsData as $c) { 
				$palette[$c['id']] = [$c['r'], $c['g'], $c['b']]; 
			}
			self::$colorsPaletteCache = $palette;
		}
		return self::$colorsPaletteCache;
	}
	
	/**
	 * Loads and caches AnimDesc.json
	 */
	private function getAnimDesc() {
		if (self::$animDescCache === null) {
			$jsonPath = __DIR__ . "/AnimDesc.json";
			if (!file_exists($jsonPath)) {
				return null;
			}
			
			$jsonContent = file_get_contents($jsonPath);
			$animDescData = json_decode($jsonContent, true);
			self::$animDescCache = $animDescData;
		}
		return self::$animDescCache;
	}
	
	/**
	 * Generates the main profile icon for a user.
	 *
	 * @param array $iconData Array containing icon information:
	 *   - 'iconType' (int): Type of icon (0:cube, 1:ship, etc.)
	 *   - 'iconID' (int): The ID of the icon
	 *   - 'color1' (int): Primary color ID
	 *   - 'color2' (int): Secondary color ID
	 *   - 'color3' (int): Glow color ID
	 *   - 'glow' (bool): Whether glow is enabled
	 * @param string|null $imageRes Image resolution quality ('uhd', 'hd', '' for low, or null for auto-detect from config).
	 * @param bool $saveImage Whether to save the generated image to disk.
	 * @return GdImage|null A GD image resource for the icon.
	 */
	public function getIcon($iconData, $imageRes = null, $saveImage = false){
		// Validate required parameters
		if (!is_array($iconData)) {
			return null;
		}

		$iconType = $iconData['iconType'] ?? null;
		$iconID = $iconData['iconID'] ?? null;
		$color1 = $iconData['color1'] ?? null;
		$color2 = $iconData['color2'] ?? null;
		$color3 = $iconData['color3'] ?? null;
		$glowEnabled = $iconData['glow'] ?? null;

		if ($iconType === null || $iconID === null || $color1 === null || $color2 === null || $color3 === null || $glowEnabled === null) {
			return null;
		}

		// Use default quality from config if not specified
		if ($imageRes === null) {
			$imageRes = $this->getConfigValue('iconProfileRes', 'uhd');
		}

		// Use iconBuilder (handles both multipart and simple icons) with specified quality
		$iconImage = $this->iconBuilder(
			$iconType,
			$iconID,
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
			
			$typeName = self::$typeNames[$iconType] ?? 'player';
			
			// Determine actual quality used (should already be resolved, but keep as fallback)
			$actualQuality = $imageRes ?? $this->getConfigValue('iconProfileRes', 'uhd');
			
			// Build filename: icontype_iconid_color1_color2_color3_glow(1,0)_quality.png
			$qualitySuffix = ($actualQuality === '') ? '' : '_' . $actualQuality;
			$filename = sprintf(
				'%s_%02d_%d_%d_%d_glow%d%s.png',
				$typeName,
				$iconID,
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
	 * @param array $iconSetData Array containing icon set information:
	 *   - 'iconType' (int): Current icon type
	 *   - 'accs' (array): Array of icon IDs by type [0=>icon, 1=>ship, ...]
	 *   - 'color1' (int): Primary color ID
	 *   - 'color2' (int): Secondary color ID
	 *   - 'color3' (int): Glow color ID
	 *   - 'glow' (bool): Whether glow is enabled
	 * @param bool $fullset If true, includes the currently equipped icon. If false, excludes it (default).
	 * @param bool $includeJetpack Whether to include the jetpack (type 8) in the set. Default is false.
	 * @param string|null $imageRes Image resolution quality ('uhd', 'hd', '' for low, or null for auto-detect from config).
	 * @param bool $saveImage Whether to save the generated image to disk.
	 * @return GdImage|null A GD image resource for the icon set.
	 */
	public function getIconSet($iconSetData, $fullset = false, $includeJetpack = false, $imageRes = null, $saveImage = false) {
		// Validate required parameters
		if (!is_array($iconSetData)) {
			return null;
		}

		$iconType = $iconSetData['iconType'] ?? null;
		$accs = $iconSetData['accs'] ?? null;
		$color1 = $iconSetData['color1'] ?? null;
		$color2 = $iconSetData['color2'] ?? null;
		$color3 = $iconSetData['color3'] ?? null;
		$glowEnabled = $iconSetData['glow'] ?? null;

		if ($iconType === null || $accs === null || $color1 === null || $color2 === null || $color3 === null || $glowEnabled === null) {
			return null;
		}

		// Use default quality from config if not specified
		if ($imageRes === null) {
			$imageRes = $this->getConfigValue('iconSetRes', 'hd');
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
		$qualityScaleMap = ['' => 1.0, 'hd' => 2.0, 'uhd' => 4.0];
		$qualityScale = $qualityScaleMap[$imageRes] ?? 2.0; // Default to HD (x2)
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
			$actualQuality = $imageRes ?? $this->getConfigValue('iconSetRes', 'hd');
			
			// Build filename: icon1type_icon2type..._color1_color2_color3_glow_enabled (1,0)_quality.png
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
	private function iconGenerator($iconType, $id, $color1Id, $color2Id, $color3Id, $glowEnabled, $targetPart, $glowOnly = false, $imageRes = null) {
		// --- 1. Load Internal Palette ---
		$palette = $this->getColorsPalette();
		if ($palette === false) {
			return false;
		}

		// --- 2. Configure Paths & Names ---
		$typeName = self::$typeNames[$iconType] ?? 'player';
		$formattedId = sprintf("%02d", $id);
		$baseName = "{$typeName}_{$formattedId}";

		$savePath = __DIR__ . "/iconRender/iconGenerator";
		$pathBase = "{$savePath}/{$baseName}_c{$color1Id}_c{$color2Id}_c{$color3Id}";

		// Determine which quality to use
		$plistFile = null;
		$spriteSheetFile = null;
		$basePath = __DIR__ . "/icons/{$baseName}";
		
		if ($imageRes !== null) {
			// Use specific requested quality
			$qualitySuffix = $imageRes === '' ? '' : '-' . $imageRes;
			$testPlist = "{$basePath}{$qualitySuffix}.plist";
			$testPng = "{$basePath}{$qualitySuffix}.png";
			
			if (file_exists($testPlist) && file_exists($testPng)) {
				$plistFile = $testPlist;
				$spriteSheetFile = $testPng;
			}
		} else {
			// If null, use the lowest available quality (order: no prefix > hd > uhd)
			$qualities = ['', '-hd', '-uhd'];
			
			foreach ($qualities as $quality) {
				$testPlist = "{$basePath}{$quality}.plist";
				$testPng = "{$basePath}{$quality}.png";
				
				if (file_exists($testPlist) && file_exists($testPng)) {
					$plistFile = $testPlist;
					$spriteSheetFile = $testPng;
					break;
				}
			}
		}
		
		if (!$plistFile || !$spriteSheetFile) return false;

		$iconRenderDebug = $this->getConfigValue('iconRenderDebug', false);
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
				$testPlist = __DIR__ . "/icons/{$baseName}{$q}.plist";
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
				$iconRenderDebug = $this->getConfigValue('iconRenderDebug', false);
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
			$iconRenderDebug = $this->getConfigValue('iconRenderDebug', false);
			if ($iconRenderDebug && !($glowEnabled && !$glowOnly && $num === "full")) {
				// Build filename: icontype_iconid_piece_number_color1_color2_color3_glow(1,0)_quality.png
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
	private function iconBuilder($iconType, $iconID, $color1, $color2, $color3, $glowEnabled, $imageRes = null) {
		$typeName = self::$typeNames[$iconType] ?? 'player';
		
		// If not a multipart icon type, use iconGenerator directly
		if (!isset(self::$animDescKeyMap[$iconType])) {
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
			$iconRenderDebug = $this->getConfigValue('iconRenderDebug', false);
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
						$testPlist = __DIR__ . "/icons/{$baseName}{$q}.plist";
						if (file_exists($testPlist)) {
							$actualQuality = ($q === '') ? '' : substr($q, 1); // Remove leading dash
							break;
						}
					}
					if ($actualQuality === null) $actualQuality = ''; // Default to low
				}
				
				// Build filename: icontype_iconid_piece_number_color1_color2_color3_glow(1,0)_quality.png
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
		
		$animDescKey = self::$animDescKeyMap[$iconType];
		
		// Load AnimDesc JSON file (unified file, cached)
		$animDescData = $this->getAnimDesc();
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
					$iconRenderDebug = $this->getConfigValue('iconRenderDebug', false);
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
						
						// Build filename: icontype_iconid_piece_number_color1_color2_color3_glow(1,0)_quality.png
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
						$iconRenderDebug = $this->getConfigValue('iconRenderDebug', false);
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
							
							// Build filename: icontype_iconid_piece_number_color1_color2_color3_glow(1,0)_quality.png
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
		$qualityScaleMap = ['' => 1.0, 'hd' => 2.0, 'uhd' => 4.0];
		$qualityScale = $qualityScaleMap[$imageRes] ?? 2.0; // Default to HD (2x)
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
		$iconRenderDebug = $this->getConfigValue('iconRenderDebug', false);
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
			
			$typeName = self::$typeNames[$iconType] ?? 'player';
			
			// Build filename: icontype_iconid_piece_number_color1_color2_color3_glow(1,0)_quality.png
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
	 * Tints a GD image resource with a specific color while preserving shading.
	 * Optimized version using direct pixel manipulation.
	 * @param GdImage $img The source image resource.
	 * @param array $color An array with [R, G, B] values.
	 * @return GdImage The tinted image resource.
	 */
	private function tintImage($img, $color) {
		imagesavealpha($img, true);
		$w = imagesx($img);
		$h = imagesy($img);
		
		// Pre-calculate color multipliers
		$colorR = $color[0] / 255;
		$colorG = $color[1] / 255;
		$colorB = $color[2] / 255;
		
		// Cache color allocations to avoid repeated calls
		$colorCache = [];
		
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++) {
				$colorInt = imagecolorat($img, $x, $y);
				
				// Extract RGBA from 32-bit integer (faster than imagecolorsforindex)
				$a = ($colorInt >> 24) & 0x7F; // Alpha: 0 (opaque) to 127 (transparent)
				if ($a == 127) continue; // Skip fully transparent pixels
				
				$r = ($colorInt >> 16) & 0xFF;
				$g = ($colorInt >> 8) & 0xFF;
				$b = $colorInt & 0xFF;
				
				// Multiply RGB channels
				$newR = (int)min(255, $r * $colorR);
				$newG = (int)min(255, $g * $colorG);
				$newB = (int)min(255, $b * $colorB);
				
				// Use cache key to avoid repeated allocations
				$cacheKey = sprintf('%d_%d_%d_%d', $newR, $newG, $newB, $a);
				if (!isset($colorCache[$cacheKey])) {
					$colorCache[$cacheKey] = imagecolorallocatealpha($img, $newR, $newG, $newB, $a);
				}
				
				imagesetpixel($img, $x, $y, $colorCache[$cacheKey]);
			}
		}
		return $img;
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
		// Optimized: use direct pixel access instead of imagecolorsforindex
		// Scan from bottom to top for Y to ensure we capture the lowest pixel first
		for ($y = $h - 1; $y >= 0; $y--) {
			for ($x = 0; $x < $w; $x++) {
				$colorInt = imagecolorat($image, $x, $y);
				// Extract alpha from 32-bit integer (faster than imagecolorsforindex)
				$a = ($colorInt >> 24) & 0x7F;
				// Consider pixels that are not fully transparent (alpha < 125 for very strict detection)
				if ($a < 125) {
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
}
?>
