<?php
session_start();
require "incl/dashboardLib.php";
$dl = new dashboardLib();
require "../incl/render/render.php";
require "../incl/lib/connection.php";

// Mapa de tipos de iconos
$iconTypes = [
    0 => 'player',
    1 => 'ship',
    2 => 'player_ball',
    3 => 'bird',
    4 => 'dart',
    5 => 'robot',
    6 => 'spider',
    7 => 'swing',
    8 => 'jetpack'
];

// Cargar colores disponibles
$colorsJson = file_get_contents(__DIR__ . '/../incl/render/colors.json');
$colorsData = json_decode($colorsJson, true);
$availableColorIds = $colorsData ? array_column($colorsData, 'id') : [];

// Escanear iconos disponibles por tipo
$iconsDir = __DIR__ . '/../incl/render/icons';
$availableIconsByType = [];

foreach ($iconTypes as $typeId => $typeName) {
    $pattern = $iconsDir . '/' . $typeName . '_*.plist';
    $files = glob($pattern);
    $iconIds = [];
    
    if (!empty($files)) {
        foreach ($files as $file) {
            if (preg_match('/' . preg_quote($typeName, '/') . '_(\d+)(?:-hd|-uhd)?\.plist/', basename($file), $matches)) {
                $iconId = (int)$matches[1];
                if (!in_array($iconId, $iconIds)) {
                    $iconIds[] = $iconId;
                }
            }
        }
        sort($iconIds);
    }
    $availableIconsByType[$typeId] = $iconIds;
}

// Variables iniciales
$selectedIconType = -1;
$selectedQuality = 'hd';
$selectedGlow = 'random';
$selectedColor1 = null;
$selectedColor2 = null;
$selectedColor3 = null;
$selectedIncludeJetpack = 'yes'; // Default: include jetpack in the dropdown

// Procesar solicitudes GET para mostrar imágenes
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['icon']) || isset($_GET['iconset']))) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: image/png');
    $iconRenderer = new IconRenderer();
    
    if (isset($_GET['icon'])) {
        $params = json_decode(base64_decode($_GET['icon']), true);
        if ($params) {
            $iconData = [
                'iconType' => $params['iconType'],
                'iconID' => $params['iconID'] ?? $params['icon'] ?? null,
                'color1' => $params['color1'],
                'color2' => $params['color2'],
                'color3' => $params['color3'],
                'glow' => $params['glowEnabled']
            ];
            $image = $iconRenderer->getIcon(
                $iconData,
                $params['imageRes'] ?? 'uhd',
                false
            );
            
            if ($image) {
                imagesavealpha($image, true);
                imagealphablending($image, false);
                imagepng($image);
                imagedestroy($image);
                die();
            }
        }
    } else if (isset($_GET['iconset'])) {
        $params = json_decode(base64_decode($_GET['iconset']), true);
        if ($params) {
            // Si hay accountID, obtener datos de la BD; si no, usar los parámetros directamente
            if (isset($params['accountID'])) {
                require "../incl/lib/connection.php";
                $query = $db->prepare("SELECT iconType, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
                $query->execute([':extID' => $params['accountID']]);
                $user = $query->fetch();
                
                if ($user) {
                    $iconSetData = [
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
                } else {
                    $iconSetData = null;
                }
            } else {
                $iconSetData = [
                    'iconType' => $params['iconType'],
                    'accs' => $params['accs'],
                    'color1' => $params['color1'],
                    'color2' => $params['color2'],
                    'color3' => $params['color3'],
                    'glow' => $params['glowEnabled']
                ];
            }
            
            if ($iconSetData) {
                $image = $iconRenderer->getIconSet(
                    $iconSetData,
                    $params['fullset'] ?? true,
                    $params['includeJetpack'] ?? false,
                    $params['imageRes'] ?? 'hd',
                    false
                );
            } else {
                $image = null;
            }
            
            if ($image) {
                imagesavealpha($image, true);
                imagealphablending($image, false);
                imagepng($image);
                imagedestroy($image);
                die();
            }
        }
    }
    
    http_response_code(404);
    die();
}

// Procesar formularios POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($isAjax) {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    try {
        $iconRenderer = new IconRenderer();
        
        if (isset($_POST['generate_icon'])) {
            $selectedType = isset($_POST['icon_type']) ? (int)$_POST['icon_type'] : null;
            $selectedQuality = isset($_POST['quality']) ? $_POST['quality'] : 'uhd';
            $selectedIconType = $selectedType;
            
            if ($selectedType === -1 || $selectedType === null) {
                $typesWithIcons = array_filter($availableIconsByType, function($icons) {
                    return !empty($icons);
                });
                if (!empty($typesWithIcons)) {
                    $selectedType = array_rand($typesWithIcons);
                }
            }
            
            if ($selectedType !== null && isset($availableIconsByType[$selectedType]) && !empty($availableIconsByType[$selectedType])) {
                // Si se proporciona un ID, validarlo y usarlo; si no, usar uno aleatorio
                if (isset($_POST['icon_id']) && $_POST['icon_id'] !== '') {
                    $requestedId = (int)$_POST['icon_id'];
                    if (in_array($requestedId, $availableIconsByType[$selectedType])) {
                        $iconId = $requestedId;
                    } else {
                        // ID no vรกlido, usar uno aleatorio
                        $iconId = $availableIconsByType[$selectedType][array_rand($availableIconsByType[$selectedType])];
                    }
                } else {
                    $iconId = $availableIconsByType[$selectedType][array_rand($availableIconsByType[$selectedType])];
                }
                
                // Usar colores seleccionados o aleatorios
                if (isset($_POST['color1']) && $_POST['color1'] !== '') {
                    $color1 = (int)$_POST['color1'];
                } else {
                    $color1 = $availableColorIds[array_rand($availableColorIds)];
                }
                if (isset($_POST['color2']) && $_POST['color2'] !== '') {
                    $color2 = (int)$_POST['color2'];
                } else {
                    $color2 = $availableColorIds[array_rand($availableColorIds)];
                }
                if (isset($_POST['color3']) && $_POST['color3'] !== '') {
                    $color3 = (int)$_POST['color3'];
                } else {
                    $color3 = $availableColorIds[array_rand($availableColorIds)];
                }
                
                $glowEnabled = false;
                $selectedGlow = isset($_POST['glow']) ? $_POST['glow'] : 'random';
                if ($selectedGlow === 'yes') {
                    $glowEnabled = true;
                } elseif ($selectedGlow === 'no') {
                    $glowEnabled = false;
                } else {
                    $glowEnabled = (bool)rand(0, 1);
                }
                
                $imageRes = ($selectedQuality === 'low' || $selectedQuality === '') ? '' : $selectedQuality;
                
                try {
                    $iconData = [
                        'iconType' => $selectedType,
                        'iconID' => $iconId,
                        'color1' => $color1,
                        'color2' => $color2,
                        'color3' => $color3,
                        'glow' => $glowEnabled
                    ];
                    $image = $iconRenderer->getIcon(
                        $iconData,
                        $imageRes,
                        false
                    );
                    
                    if ($image !== null && $image !== false) {
                        $generatedIconData = [
                            'iconType' => $selectedType,
                            'iconID' => $iconId,
                            'color1' => $color1,
                            'color2' => $color2,
                            'color3' => $color3,
                            'glowEnabled' => $glowEnabled,
                            'imageRes' => $imageRes
                        ];
                        
                        if ($isAjax) {
                            while (ob_get_level()) {
                                ob_end_clean();
                            }
                            if (!headers_sent()) {
                                header('Content-Type: application/json; charset=utf-8');
                            }
                            $imageUrl = '?icon=' . urlencode(base64_encode(json_encode($generatedIconData))) . '&t=' . time();
                            echo json_encode(['success' => true, 'imageUrl' => $imageUrl], JSON_UNESCAPED_SLASHES);
                            die();
                        }
                        
                        imagedestroy($image);
                    } else {
                        if ($isAjax) {
                            while (ob_get_level()) {
                                ob_end_clean();
                            }
                            if (!headers_sent()) {
                                header('Content-Type: application/json; charset=utf-8');
                            }
                            echo json_encode(['success' => false, 'error' => 'Error generating icon'], JSON_UNESCAPED_SLASHES);
                            die();
                        }
                    }
                } catch (Exception $e) {
                    if ($isAjax) {
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
                        if (!headers_sent()) {
                            header('Content-Type: application/json; charset=utf-8');
                        }
                        echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
                        die();
                    }
                }
            } else {
                if ($isAjax) {
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                    }
                    echo json_encode(['success' => false, 'error' => 'Invalid icon type'], JSON_UNESCAPED_SLASHES);
                    die();
                }
            }
        } elseif (isset($_POST['generate_iconset'])) {
            $selectedQuality = isset($_POST['quality']) ? $_POST['quality'] : 'hd';
            $selectedGlow = isset($_POST['glow']) ? $_POST['glow'] : 'random';
            $selectedIncludeJetpack = isset($_POST['include_jetpack']) ? $_POST['include_jetpack'] : 'yes';
            $includeJetpack = ($selectedIncludeJetpack === 'yes');
            
            // Buscar accountID si se proporciona userName o accountID
            $accountID = null;
            $userInput = isset($_POST['user_input']) ? trim($_POST['user_input']) : '';
            
            if (!empty($userInput)) {
                // Si es numรฉrico, asumir que es accountID/extID
                if (is_numeric($userInput)) {
                    $accountID = $userInput;
                } else {
                    // Buscar por userName en la tabla users
                    $query = $db->prepare("SELECT extID FROM users WHERE userName LIKE :userName LIMIT 1");
                    $query->execute([':userName' => $userInput]);
                    if ($query->rowCount() > 0) {
                        $accountID = $query->fetchColumn();
                    } else {
                        // Si no se encuentra en users, buscar en accounts
                        $query = $db->prepare("SELECT accountID FROM accounts WHERE userName LIKE :userName LIMIT 1");
                        $query->execute([':userName' => $userInput]);
                        if ($query->rowCount() > 0) {
                            $accountID = $query->fetchColumn();
                        }
                    }
                }
            }
            
            // Si no se encontrรณ usuario y se proporcionรณ input, devolver error
            if (!empty($userInput) && $accountID === null) {
                if ($isAjax) {
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                    }
                    echo json_encode(['success' => false, 'error' => 'User not found'], JSON_UNESCAPED_SLASHES);
                    die();
                }
            }
            
            // Si se encontrรณ un usuario, usar sus datos; si no, usar valores aleatorios
            if ($accountID !== null) {
                // Usar los datos del usuario
                $imageRes = ($selectedQuality === 'low' || $selectedQuality === '') ? '' : $selectedQuality;
                
                try {
                    $query = $db->prepare("SELECT iconType, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
                    $query->execute([':extID' => $accountID]);
                    $user = $query->fetch();
                    
                    if ($user) {
                        $iconSetData = [
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
                        $image = $iconRenderer->getIconSet(
                            $iconSetData,
                            true,
                            $includeJetpack,
                            $imageRes,
                            false
                        );
                    } else {
                        $image = null;
                    }
                } catch (Exception $e) {
                    if ($isAjax) {
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
                        if (!headers_sent()) {
                            header('Content-Type: application/json; charset=utf-8');
                        }
                        echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
                        die();
                    }
                    $image = null;
                }
            } else {
                // Usar valores aleatorios o seleccionados
                if (isset($_POST['color1']) && $_POST['color1'] !== '') {
                    $color1 = (int)$_POST['color1'];
                } else {
                    $color1 = $availableColorIds[array_rand($availableColorIds)];
                }
                if (isset($_POST['color2']) && $_POST['color2'] !== '') {
                    $color2 = (int)$_POST['color2'];
                } else {
                    $color2 = $availableColorIds[array_rand($availableColorIds)];
                }
                if (isset($_POST['color3']) && $_POST['color3'] !== '') {
                    $color3 = (int)$_POST['color3'];
                } else {
                    $color3 = $availableColorIds[array_rand($availableColorIds)];
                }
                
                $glowEnabled = false;
                if ($selectedGlow === 'yes') {
                    $glowEnabled = true;
                } elseif ($selectedGlow === 'no') {
                    $glowEnabled = false;
                } else {
                    $glowEnabled = (bool)rand(0, 1);
                }
                
                $accs = [];
                foreach ($iconTypes as $typeId => $typeName) {
                    if (isset($availableIconsByType[$typeId]) && !empty($availableIconsByType[$typeId])) {
                        $accs[$typeId] = $availableIconsByType[$typeId][array_rand($availableIconsByType[$typeId])];
                    } else {
                        $accs[$typeId] = 1;
                    }
                }
                
                $typesWithIcons = array_filter($availableIconsByType, function($icons) {
                    return !empty($icons);
                });
                $iconType = !empty($typesWithIcons) ? array_rand($typesWithIcons) : 0;
                
                $imageRes = ($selectedQuality === 'low' || $selectedQuality === '') ? '' : $selectedQuality;
                
                try {
                    $iconSetData = [
                        'iconType' => $iconType,
                        'accs' => $accs,
                        'color1' => $color1,
                        'color2' => $color2,
                        'color3' => $color3,
                        'glow' => $glowEnabled
                    ];
                    $image = $iconRenderer->getIconSet(
                        $iconSetData,
                        true,
                        $includeJetpack,
                        $imageRes,
                        false
                    );
                } catch (Exception $e) {
                    if ($isAjax) {
                        while (ob_get_level()) {
                            ob_end_clean();
                        }
                        if (!headers_sent()) {
                            header('Content-Type: application/json; charset=utf-8');
                        }
                        echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
                        die();
                    }
                    $image = null;
                }
            }
            
            if ($image !== null && $image !== false) {
                // Si se usรณ accountID, necesitamos obtener los datos para la URL
                if ($accountID !== null) {
                    $query = $db->prepare("SELECT iconType, accIcon, accShip, accBall, accBird, accDart, accRobot, accSpider, accSwing, accJetpack, color1, color2, color3, accGlow FROM users WHERE extID = :extID");
                    $query->execute([':extID' => $accountID]);
                    $user = $query->fetch();
                    if ($user) {
                        $generatedIconSetData = [
                            'iconType' => $user['iconType'],
                            'accs' => [
                                0 => $user['accIcon'],
                                1 => $user['accShip'],
                                2 => $user['accBall'],
                                3 => $user['accBird'],
                                4 => $user['accDart'],
                                5 => $user['accRobot'],
                                6 => $user['accSpider'],
                                7 => $user['accSwing'],
                                8 => $user['accJetpack']
                            ],
                            'color1' => $user['color1'],
                            'color2' => $user['color2'],
                            'color3' => $user['color3'],
                            'glowEnabled' => ($user['accGlow'] == 1),
                            'fullset' => true,
                            'imageRes' => $imageRes,
                            'includeJetpack' => $includeJetpack
                        ];
                    } else {
                        // Fallback si no se puede obtener los datos
                        $generatedIconSetData = [
                            'accountID' => $accountID,
                            'fullset' => true,
                            'imageRes' => $imageRes,
                            'includeJetpack' => $includeJetpack
                        ];
                    }
                } else {
                    // Valores aleatorios
                    $generatedIconSetData = [
                        'iconType' => $iconType,
                        'accs' => $accs,
                        'color1' => $color1,
                        'color2' => $color2,
                        'color3' => $color3,
                        'glowEnabled' => $glowEnabled,
                        'fullset' => true,
                        'imageRes' => $imageRes,
                        'includeJetpack' => $includeJetpack
                    ];
                }
                
                if ($isAjax) {
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                    }
                    $imageUrl = '?iconset=' . urlencode(base64_encode(json_encode($generatedIconSetData))) . '&t=' . time();
                    echo json_encode(['success' => true, 'imageUrl' => $imageUrl], JSON_UNESCAPED_SLASHES);
                    die();
                }
                
                imagedestroy($image);
            } else {
                if ($isAjax) {
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    if (!headers_sent()) {
                        header('Content-Type: application/json; charset=utf-8');
                    }
                    echo json_encode(['success' => false, 'error' => 'Error generating Icon Set'], JSON_UNESCAPED_SLASHES);
                    die();
                }
            }
        }
        
        if ($isAjax) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'error' => 'No valid action specified'], JSON_UNESCAPED_SLASHES);
            die();
        }
    } catch (Throwable $e) {
        if ($isAjax) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES);
            die();
        }
    }
}

// Generar HTML
$html = '
<div class="container-fluid container-box">
    <div class="card">
        <div class="card-block buffer">
            <h1><i class="fa fa-picture-o" aria-hidden="true"></i> '.$dl->getLocalizedString("iconRender").'</h1>
            
            <div class="row">
                <div class="col-12">
                    <div class="card" style="background-color: #2f3136; border: 1px solid #40444b;">
                        <div class="card-body" style="padding: 10px;">
                            <h5 class="card-title" style="margin-bottom: 10px; font-size: 1.1em;">'.$dl->getLocalizedString("iconRenderOptions").'</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label for="quality" style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderQuality").'</label>
                                        <select name="quality" id="quality" class="form-control" onchange="updateQuality(this.value);">
                                            <option value="low" ' . (($selectedQuality == 'low' || $selectedQuality == '') ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderQualityLow").'</option>
                                            <option value="hd" ' . ($selectedQuality == 'hd' ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderQualityMedium").'</option>
                                            <option value="uhd" ' . ($selectedQuality == 'uhd' ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderQualityHigh").'</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label for="glow" style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderGlow").'</label>
                                        <select name="glow" id="glow" class="form-control" onchange="updateGlow(this.value);">
                                            <option value="random" ' . ($selectedGlow == 'random' ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderRandom").'</option>
                                            <option value="yes" ' . ($selectedGlow == 'yes' ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderYes").'</option>
                                            <option value="no" ' . ($selectedGlow == 'no' ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderNo").'</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderColor1").'</label>
                                        <input type="hidden" name="color1" id="color1" value="' . ($selectedColor1 !== null ? $selectedColor1 : '') . '">
                                        <div id="color1Preview" class="color-preview" style="width: 100%; height: 30px; border: 2px solid #40444b; border-radius: 4px; background-color: #2f3136; cursor: pointer; position: relative; margin-bottom: 5px;" onclick="toggleColorPicker(1);">
                                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #a7a8aa; font-size: 0.8em;">' . ($selectedColor1 !== null ? 'Color ' . $selectedColor1 : 'Random') . '</div>
                                        </div>
                                        <div id="color1Picker" class="color-picker-container" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid #40444b; border-radius: 4px; padding: 10px; background-color: #36393f; position: absolute; z-index: 1000; width: 300px;">
                                            <div class="color-picker-grid" style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 5px;">
                                                <div class="color-box" data-color-id="" onclick="selectColor(1, null);" style="width: 100%; aspect-ratio: 1; border: 2px solid #40444b; border-radius: 4px; background-color: #2f3136; cursor: pointer; position: relative;" title="Random">
                                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #a7a8aa; font-size: 0.7em; text-align: center;">R</div>
                                                </div>';
foreach ($colorsData as $color) {
    $colorHex = sprintf("#%02x%02x%02x", $color['r'], $color['g'], $color['b']);
    $selected = ($selectedColor1 == $color['id']) ? 'border: 3px solid #7289da !important;' : 'border: 2px solid #40444b;';
    $html .= '<div class="color-box" data-color-id="' . $color['id'] . '" onclick="selectColor(1, ' . $color['id'] . ');" style="width: 100%; aspect-ratio: 1; ' . $selected . ' border-radius: 4px; background-color: rgb(' . $color['r'] . ',' . $color['g'] . ',' . $color['b'] . '); cursor: pointer;" title="Color ' . $color['id'] . '"></div>';
}
$html .= '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderColor2").'</label>
                                        <input type="hidden" name="color2" id="color2" value="' . ($selectedColor2 !== null ? $selectedColor2 : '') . '">
                                        <div id="color2Preview" class="color-preview" style="width: 100%; height: 30px; border: 2px solid #40444b; border-radius: 4px; background-color: #2f3136; cursor: pointer; position: relative; margin-bottom: 5px;" onclick="toggleColorPicker(2);">
                                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #a7a8aa; font-size: 0.8em;">' . ($selectedColor2 !== null ? 'Color ' . $selectedColor2 : 'Random') . '</div>
                                        </div>
                                        <div id="color2Picker" class="color-picker-container" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid #40444b; border-radius: 4px; padding: 10px; background-color: #36393f; position: absolute; z-index: 1000; width: 300px;">
                                            <div class="color-picker-grid" style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 5px;">
                                                <div class="color-box" data-color-id="" onclick="selectColor(2, null);" style="width: 100%; aspect-ratio: 1; border: 2px solid #40444b; border-radius: 4px; background-color: #2f3136; cursor: pointer; position: relative;" title="Random">
                                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #a7a8aa; font-size: 0.7em; text-align: center;">R</div>
                                                </div>';
foreach ($colorsData as $color) {
    $colorHex = sprintf("#%02x%02x%02x", $color['r'], $color['g'], $color['b']);
    $selected = ($selectedColor2 == $color['id']) ? 'border: 3px solid #7289da !important;' : 'border: 2px solid #40444b;';
    $html .= '<div class="color-box" data-color-id="' . $color['id'] . '" onclick="selectColor(2, ' . $color['id'] . ');" style="width: 100%; aspect-ratio: 1; ' . $selected . ' border-radius: 4px; background-color: rgb(' . $color['r'] . ',' . $color['g'] . ',' . $color['b'] . '); cursor: pointer;" title="Color ' . $color['id'] . '"></div>';
}
$html .= '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderGlow").'</label>
                                        <input type="hidden" name="color3" id="color3" value="' . ($selectedColor3 !== null ? $selectedColor3 : '') . '">
                                        <div id="color3Preview" class="color-preview" style="width: 100%; height: 30px; border: 2px solid #40444b; border-radius: 4px; background-color: #2f3136; cursor: pointer; position: relative; margin-bottom: 5px;" onclick="toggleColorPicker(3);">
                                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #a7a8aa; font-size: 0.8em;">' . ($selectedColor3 !== null ? 'Glow ' . $selectedColor3 : 'Random') . '</div>
                                        </div>
                                        <div id="color3Picker" class="color-picker-container" style="display: none; max-height: 200px; overflow-y: auto; border: 1px solid #40444b; border-radius: 4px; padding: 10px; background-color: #36393f; position: absolute; z-index: 1000; width: 300px;">
                                            <div class="color-picker-grid" style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 5px;">
                                                <div class="color-box" data-color-id="" onclick="selectColor(3, null);" style="width: 100%; aspect-ratio: 1; border: 2px solid #40444b; border-radius: 4px; background-color: #2f3136; cursor: pointer; position: relative;" title="Random">
                                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #a7a8aa; font-size: 0.7em; text-align: center;">R</div>
                                                </div>';
foreach ($colorsData as $color) {
    $colorHex = sprintf("#%02x%02x%02x", $color['r'], $color['g'], $color['b']);
    $selected = ($selectedColor3 == $color['id']) ? 'border: 3px solid #7289da !important;' : 'border: 2px solid #40444b;';
    $html .= '<div class="color-box" data-color-id="' . $color['id'] . '" onclick="selectColor(3, ' . $color['id'] . ');" style="width: 100%; aspect-ratio: 1; ' . $selected . ' border-radius: 4px; background-color: rgb(' . $color['r'] . ',' . $color['g'] . ',' . $color['b'] . '); cursor: pointer;" title="Color ' . $color['id'] . '"></div>';
}
$html .= '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row" style="margin-top: 10px;">
                <div class="col-12">
                    <div class="card" style="background-color: #2f3136; border: 1px solid #40444b;">
                        <div class="card-body" style="padding: 10px;">
                            <h5 class="card-title" style="margin-bottom: 10px; font-size: 1.1em;">'.$dl->getLocalizedString("iconRenderGenerateIcon").'</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label for="icon_type" style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderIconType").'</label>
                                <select name="icon_type" id="icon_type" class="form-control" onchange="updateIconType();">
                                    <option value="-1" ' . ($selectedIconType == -1 ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderRandom").'</option>';
$iconTypeLabels = [
    'player' => 'iconRenderIconTypePlayer',
    'ship' => 'iconRenderIconTypeShip',
    'player_ball' => 'iconRenderIconTypePlayerBall',
    'bird' => 'iconRenderIconTypeBird',
    'dart' => 'iconRenderIconTypeDart',
    'robot' => 'iconRenderIconTypeRobot',
    'spider' => 'iconRenderIconTypeSpider',
    'swing' => 'iconRenderIconTypeSwing',
    'jetpack' => 'iconRenderIconTypeJetpack'
];
foreach ($iconTypes as $typeId => $typeName) {
    if (isset($availableIconsByType[$typeId]) && !empty($availableIconsByType[$typeId])) {
        $typeKey = isset($iconTypeLabels[$typeName]) ? $iconTypeLabels[$typeName] : null;
        $typeLabel = $typeKey ? $dl->getLocalizedString($typeKey) : '';
        if (empty($typeLabel)) {
            $typeLabel = ucfirst(str_replace('_', ' ', $typeName));
        }
        $html .= '<option value="' . $typeId . '" ' . ($selectedIconType == $typeId ? 'selected' : '') . '>' . $typeLabel . ' (' . count($availableIconsByType[$typeId]) . ' ' . $dl->getLocalizedString("iconRenderIcons") . ')</option>';
    }
}
$html .= '
                                </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label for="icon_id" style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderIconID").'</label>
                                        <input type="number" name="icon_id" id="icon_id" class="form-control" placeholder="'.$dl->getLocalizedString("iconRenderLeaveEmpty").'" min="1" style="height: 32px; font-size: 0.9em;" />
                                        <small class="form-text text-muted" id="iconIdHelp" style="font-size: 0.8em;">'.$dl->getLocalizedString("iconRenderMax").' <span id="maxIconId">-</span></small>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-block" onclick="generateIcon();" style="margin-top: 5px; padding: 6px; font-size: 0.9em;">
                                <i class="fa fa-image" aria-hidden="true"></i> '.$dl->getLocalizedString("iconRenderGenerateIcon").'
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row" style="margin-top: 10px;">
                <div class="col-12">
                    <div class="card" style="background-color: #2f3136; border: 1px solid #40444b;">
                        <div class="card-body" style="padding: 10px;">
                            <h5 class="card-title" style="margin-bottom: 10px; font-size: 1.1em;">'.$dl->getLocalizedString("iconRenderGenerateIconSet").'</h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label for="user_input" style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderUsernameOrAccountID").'</label>
                                        <input type="text" name="user_input" id="user_input" class="form-control" placeholder="'.$dl->getLocalizedString("iconRenderLeaveEmpty").'" style="height: 32px; font-size: 0.9em;" />
                                        <small class="form-text text-muted" style="font-size: 0.8em;">'.$dl->getLocalizedString("iconRenderUsernameOrAccountIDDesc").'</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group" style="margin-bottom: 8px;">
                                        <label for="include_jetpack" style="margin-bottom: 3px; font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderIncludeJetpack").'</label>
                                        <select name="include_jetpack" id="include_jetpack" class="form-control" style="height: 32px; font-size: 0.9em;">
                                            <option value="yes" ' . ($selectedIncludeJetpack == 'yes' ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderYes").'</option>
                                            <option value="no" ' . ($selectedIncludeJetpack == 'no' ? 'selected' : '') . '>'.$dl->getLocalizedString("iconRenderNo").'</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-block" onclick="generateIconSet();" style="margin-top: 5px; padding: 6px; font-size: 0.9em;">
                                <i class="fa fa-th" aria-hidden="true"></i> '.$dl->getLocalizedString("iconRenderGenerateIconSet").'
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row" style="margin-top: 10px;">
                <div class="col-12">
                    <div class="card" style="background-color: #2f3136; border: 1px solid #40444b;">
                        <div class="card-body" style="padding: 10px;">
                            <h5 class="card-title" style="margin-bottom: 10px; font-size: 1.1em;">'.$dl->getLocalizedString("iconRenderPreview").'</h5>
                            <div class="preview-image" id="previewContainer">
                                <div class="placeholder" id="previewPlaceholder" style="font-size: 0.9em;">'.$dl->getLocalizedString("iconRenderPressButton").'</div>
                                <img id="previewImage" style="display: none;" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    body {
        background-color: #36393e !important;
    }
    
    .container-box {
        background-color: #36393e !important;
        max-width: 100% !important;
        width: 100% !important;
        display: block !important;
        justify-content: flex-start !important;
        align-items: flex-start !important;
        height: auto !important;
    }
    
    .card-block.buffer {
        margin-left: 10px !important;
        margin-right: 10px !important;
    }
    
    @media (min-width: 992px) {
        .card-block.buffer {
            margin-left: 15px !important;
            margin-right: 15px !important;
        }
    }
    
    .container-fluid.container-box {
        padding-left: 0.5% !important;
        padding-right: 0.5% !important;
        max-width: 100% !important;
        width: 100% !important;
    }
    
    .container-fluid.container-box .card {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    @media (min-width: 768px) {
        .container-fluid.container-box {
            padding-left: 1% !important;
            padding-right: 1% !important;
        }
    }
    
    @media (min-width: 992px) {
        .container-fluid.container-box {
            padding-left: 1.5% !important;
            padding-right: 1.5% !important;
        }
    }
    
    @media (min-width: 1200px) {
        .container-fluid.container-box {
            padding-left: 2% !important;
            padding-right: 2% !important;
        }
    }
    
    @media (min-width: 1400px) {
        .container-fluid.container-box {
            padding-left: 2.5% !important;
            padding-right: 2.5% !important;
        }
    }
    
    @media (min-width: 1600px) {
        .container-fluid.container-box {
            padding-left: 3% !important;
            padding-right: 3% !important;
        }
    }
    
    .card {
        background-color: #2f3136 !important;
    }
    
    .preview-image {
        background: #2f3136;
        border: 2px dashed #40444b;
        border-radius: 4px;
        padding: 10px;
        min-height: 150px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }
    
    .preview-image .placeholder {
        color: #a7a8aa;
        font-size: 0.9em;
    }
    
    .form-control {
        height: 32px;
        font-size: 0.9em;
        padding: 4px 8px;
    }
    
    .card-body {
        padding: 10px !important;
    }
    
    .card-title {
        margin-bottom: 10px !important;
        font-size: 1.1em !important;
    }
    
    .preview-image #previewImage {
        max-width: 100%;
        height: auto;
        border-radius: 5px;
        margin: 0 auto;
        display: block;
    }
    
    .loading {
        color: #d6ddde;
        font-size: 1.1em;
        font-weight: 600;
    }
</style>

<script>
    let currentQuality = "' . htmlspecialchars($selectedQuality) . '";
    let currentGlow = "' . htmlspecialchars($selectedGlow) . '";
    
    // Traducciones
    const translations = {
        generatingIcon: "' . htmlspecialchars($dl->getLocalizedString("iconRenderGeneratingIcon")) . '",
        generatingIconSet: "' . htmlspecialchars($dl->getLocalizedString("iconRenderGeneratingIconSet")) . '",
        errorGeneratingIcon: "' . htmlspecialchars($dl->getLocalizedString("iconRenderErrorGeneratingIcon")) . '",
        errorGeneratingIconSet: "' . htmlspecialchars($dl->getLocalizedString("iconRenderErrorGeneratingIconSet")) . '"
    };
    
    // Datos de iconos disponibles por tipo
    const availableIconsByType = ' . json_encode($availableIconsByType) . ';
    
    function updateQuality(quality) {
        currentQuality = quality;
    }
    
    function updateGlow(glow) {
        currentGlow = glow;
    }
    
    // Datos de colores disponibles
    const colorsData = ' . json_encode($colorsData) . ';
    
    function toggleColorPicker(colorNum) {
        const picker = document.getElementById("color" + colorNum + "Picker");
        // Cerrar otros pickers
        for (let i = 1; i <= 3; i++) {
            if (i !== colorNum) {
                document.getElementById("color" + i + "Picker").style.display = "none";
            }
        }
        // Toggle el picker actual
        if (picker.style.display === "none" || picker.style.display === "") {
            picker.style.display = "block";
        } else {
            picker.style.display = "none";
        }
    }
    
    function selectColor(colorNum, colorId) {
        const hiddenInput = document.getElementById("color" + colorNum);
        const preview = document.getElementById("color" + colorNum + "Preview");
        const picker = document.getElementById("color" + colorNum + "Picker");
        
        // Actualizar el input hidden
        hiddenInput.value = colorId !== null ? colorId : "";
        
        // Actualizar la vista previa
        if (colorId === null || colorId === "") {
            preview.style.backgroundColor = "#2f3136";
            preview.querySelector("div").textContent = "Random";
        } else {
            const color = colorsData.find(c => c.id == colorId);
            if (color) {
                const rgb = "rgb(" + color.r + "," + color.g + "," + color.b + ")";
                preview.style.backgroundColor = rgb;
                const label = colorNum === 3 ? "Glow" : "Color";
                preview.querySelector("div").textContent = label + " " + colorId;
            }
        }
        
        // Actualizar el borde del cuadro seleccionado
        const allBoxes = picker.querySelectorAll(".color-box");
        allBoxes.forEach(box => {
            if (box.getAttribute("data-color-id") == (colorId !== null ? colorId : "")) {
                box.style.border = "3px solid #7289da";
            } else {
                box.style.border = "2px solid #40444b";
            }
        });
        
        // Cerrar el picker
        picker.style.display = "none";
    }
    
    // Cerrar pickers al hacer clic fuera
    document.addEventListener("click", function(event) {
        if (!event.target.closest(".color-preview") && !event.target.closest(".color-picker-container")) {
            for (let i = 1; i <= 3; i++) {
                document.getElementById("color" + i + "Picker").style.display = "none";
            }
        }
    });
    
    // Inicializar vistas previas de colores al cargar
    document.addEventListener("DOMContentLoaded", function() {
        for (let i = 1; i <= 3; i++) {
            const hiddenInput = document.getElementById("color" + i);
            const preview = document.getElementById("color" + i + "Preview");
            const colorId = hiddenInput.value;
            
            if (colorId === "" || colorId === null) {
                preview.style.backgroundColor = "#2f3136";
                preview.querySelector("div").textContent = "Random";
            } else {
                const color = colorsData.find(c => c.id == colorId);
                if (color) {
                    const rgb = "rgb(" + color.r + "," + color.g + "," + color.b + ")";
                    preview.style.backgroundColor = rgb;
                    const label = i === 3 ? "Glow" : "Color";
                    preview.querySelector("div").textContent = label + " " + colorId;
                }
            }
        }
    });
    
    function updateIconType() {
        const iconType = document.getElementById("icon_type").value;
        const iconIdInput = document.getElementById("icon_id");
        const maxIconIdSpan = document.getElementById("maxIconId");
        
        if (iconType === "-1" || iconType === "") {
            iconIdInput.disabled = true;
            iconIdInput.value = "";
            maxIconIdSpan.textContent = "-";
            return;
        }
        
        const typeId = parseInt(iconType);
        if (availableIconsByType[typeId] && availableIconsByType[typeId].length > 0) {
            iconIdInput.disabled = false;
            const maxId = Math.max(...availableIconsByType[typeId]);
            maxIconIdSpan.textContent = maxId;
            iconIdInput.max = maxId;
            
            // Validar el ID actual si existe y ajustar al mรกximo si supera
            const currentId = parseInt(iconIdInput.value);
            if (currentId && currentId > maxId) {
                iconIdInput.value = maxId;
            }
        } else {
            iconIdInput.disabled = true;
            maxIconIdSpan.textContent = "-";
        }
    }
    
    // Validar ID cuando se ingrese
    document.addEventListener("DOMContentLoaded", function() {
        const iconIdInput = document.getElementById("icon_id");
        const iconTypeSelect = document.getElementById("icon_type");
        
        iconIdInput.addEventListener("input", function() {
            const iconType = iconTypeSelect.value;
            if (iconType === "-1" || iconType === "") {
                return;
            }
            
            const typeId = parseInt(iconType);
            if (availableIconsByType[typeId] && availableIconsByType[typeId].length > 0) {
                const maxId = Math.max(...availableIconsByType[typeId]);
                const currentId = parseInt(this.value);
                
                if (currentId && currentId > maxId) {
                    // Ajustar automรกticamente al mรกximo
                    this.value = maxId;
                }
                this.setCustomValidity("");
            }
        });
        
        iconIdInput.addEventListener("blur", function() {
            const iconType = iconTypeSelect.value;
            if (iconType === "-1" || iconType === "") {
                return;
            }
            
            const typeId = parseInt(iconType);
            if (availableIconsByType[typeId] && availableIconsByType[typeId].length > 0) {
                const maxId = Math.max(...availableIconsByType[typeId]);
                const currentId = parseInt(this.value);
                
                if (currentId && currentId > maxId) {
                    // Ajustar automรกticamente al mรกximo al perder el foco
                    this.value = maxId;
                }
            }
        });
        
        // Inicializar al cargar
        updateIconType();
    });
    
    function generateIcon() {
        const iconType = document.getElementById("icon_type").value;
        const iconId = document.getElementById("icon_id").value;
        const previewImage = document.getElementById("previewImage");
        const previewPlaceholder = document.getElementById("previewPlaceholder");
        
        previewPlaceholder.innerHTML = "<div class=\"loading\">" + translations.generatingIcon + "</div>";
        previewPlaceholder.style.display = "block";
        previewImage.style.display = "none";
        
        const formData = new FormData();
        formData.append("generate_icon", "1");
        formData.append("icon_type", iconType);
        if (iconId) {
            formData.append("icon_id", iconId);
        }
        formData.append("quality", currentQuality);
        formData.append("glow", currentGlow);
        const color1 = document.getElementById("color1").value;
        const color2 = document.getElementById("color2").value;
        const color3 = document.getElementById("color3").value;
        if (color1) formData.append("color1", color1);
        if (color2) formData.append("color2", color2);
        if (color3) formData.append("color3", color3);
        
        fetch("", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error("HTTP error! status: " + response.status);
            }
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                return response.text().then(text => {
                    console.error("Respuesta no es JSON:", text.substring(0, 200));
                    throw new Error("The server returned HTML instead of JSON.");
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.imageUrl) {
                previewImage.src = data.imageUrl + "&t=" + Date.now();
                previewImage.style.display = "block";
                previewPlaceholder.style.display = "none";
            } else {
                previewPlaceholder.innerHTML = "<div class=\"placeholder\">" + (data.error || "'.$dl->getLocalizedString("iconRenderErrorGeneratingIcon").'") + "</div>";
                previewPlaceholder.style.display = "block";
                previewImage.style.display = "none";
            }
        })
        .catch(error => {
            console.error("Error:", error);
            previewPlaceholder.innerHTML = "<div class=\"placeholder\">Error: " + error.message + "</div>";
            previewPlaceholder.style.display = "block";
            previewImage.style.display = "none";
        });
    }
    
    function generateIconSet() {
        const previewImage = document.getElementById("previewImage");
        const previewPlaceholder = document.getElementById("previewPlaceholder");
        const userInput = document.getElementById("user_input").value;
        
        previewPlaceholder.innerHTML = "<div class=\"loading\">" + translations.generatingIconSet + "</div>";
        previewPlaceholder.style.display = "block";
        previewImage.style.display = "none";
        
        const formData = new FormData();
        formData.append("generate_iconset", "1");
        formData.append("quality", currentQuality);
        formData.append("glow", currentGlow);
        const includeJetpack = document.getElementById("include_jetpack").value;
        formData.append("include_jetpack", includeJetpack);
        if (userInput) {
            formData.append("user_input", userInput);
        }
        const color1 = document.getElementById("color1").value;
        const color2 = document.getElementById("color2").value;
        const color3 = document.getElementById("color3").value;
        if (color1) formData.append("color1", color1);
        if (color2) formData.append("color2", color2);
        if (color3) formData.append("color3", color3);
        
        fetch("", {
            method: "POST",
            headers: {
                "X-Requested-With": "XMLHttpRequest"
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error("HTTP error! status: " + response.status);
            }
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                return response.text().then(text => {
                    console.error("Respuesta no es JSON:", text.substring(0, 200));
                    throw new Error("The server returned HTML instead of JSON.");
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.imageUrl) {
                previewImage.src = data.imageUrl + "&t=" + Date.now();
                previewImage.style.display = "block";
                previewPlaceholder.style.display = "none";
            } else {
                previewPlaceholder.innerHTML = "<div class=\"placeholder\">" + (data.error || translations.errorGeneratingIconSet) + "</div>";
                previewPlaceholder.style.display = "block";
                previewImage.style.display = "none";
            }
        })
        .catch(error => {
            console.error("Error:", error);
            previewPlaceholder.innerHTML = "<div class=\"placeholder\">Error: " + error.message + "</div>";
            previewPlaceholder.style.display = "block";
            previewImage.style.display = "none";
        });
    }
</script>';

$dl->printPage($html, false);
?>
