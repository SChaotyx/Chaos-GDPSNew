<?php
session_start();
require_once "../../incl/lib/connection.php";
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
require_once "../../incl/lib/mainLib.php";
$gs = new mainLib();
if(isset($_GET["page"]) AND is_numeric($_GET["page"]) AND $_GET["page"] > 0){
	$page = ($_GET["page"] - 1) * 100;
	$actualpage = $_GET["page"];
}else{
	$page = 0;
	$actualpage = 1;
}
$table = '<table class="table table-inverse">
			<thead>
				<tr>
					<th>'.$dl->getLocalizedString("ID").'</th>
					<th>'.$dl->getLocalizedString("User").'</th>
					<th>'.$dl->getLocalizedString("Rank").'</th>
					<th>'.$dl->getLocalizedString("lastSeen").'</th>
				</tr>
			</thead>
			<tbody>';

// Obtener total de mods y ex-mods
$query = $db->prepare("SELECT count(*) FROM roleassign WHERE roleID != 1");
$query->execute();
$totalMods = $query->fetchColumn();

$query = $db->prepare("SELECT count(*) FROM roleassign WHERE roleID = 1");
$query->execute();
$totalExMods = $query->fetchColumn();

// Calcular qué mostrar en esta página
$itemsPerPage = 100;
$allUsers = array();

if($page * $itemsPerPage < $totalMods){
	// Esta página contiene mods
	$modsOffset = $page * $itemsPerPage;
	$modsLimit = min($itemsPerPage, $totalMods - $modsOffset);
	
	$query = $db->prepare("SELECT * FROM roleassign WHERE roleID != 1 ORDER BY roleID DESC LIMIT :limit OFFSET :offset");
	$query->bindValue(':limit', $modsLimit, PDO::PARAM_INT);
	$query->bindValue(':offset', $modsOffset, PDO::PARAM_INT);
	$query->execute();
	$mods = $query->fetchAll();
	$allUsers = array_merge($allUsers, $mods);
	
	// Si hay espacio, agregar ex-mods
	$remaining = $itemsPerPage - count($allUsers);
	if($remaining > 0 && $totalExMods > 0){
		$query = $db->prepare("SELECT * FROM roleassign WHERE roleID = 1 ORDER BY assignID DESC LIMIT :limit");
		$query->bindValue(':limit', $remaining, PDO::PARAM_INT);
		$query->execute();
		$exMods = $query->fetchAll();
		$allUsers = array_merge($allUsers, $exMods);
	}
} else {
	// Esta página solo contiene ex-mods
	$exModsOffset = ($page * $itemsPerPage) - $totalMods;
	$exModsLimit = min($itemsPerPage, $totalExMods - $exModsOffset);
	
	if($exModsLimit > 0){
		$query = $db->prepare("SELECT * FROM roleassign WHERE roleID = 1 ORDER BY assignID DESC LIMIT :limit OFFSET :offset");
		$query->bindValue(':limit', $exModsLimit, PDO::PARAM_INT);
		$query->bindValue(':offset', $exModsOffset, PDO::PARAM_INT);
		$query->execute();
		$exMods = $query->fetchAll();
		$allUsers = array_merge($allUsers, $exMods);
	}
}

$x = $page + 1;
foreach($allUsers as &$action){
	//detecting User
	$accountID = $action["accountID"];
	$accountName = $gs->getAccountName($accountID);
	if(!$accountName){
		$accountName = "Unknown";
	}
	//detecting rank
	$rank = $dl->getLocalizedString("rank".$action["roleID"]);
	
	// Obtener información del usuario
	$query = $db->prepare("SELECT userName, lastPlayed FROM users WHERE extID = :id LIMIT 1");
	$query->execute([':id' => $action["accountID"]]);
	$userInfo = $query->fetch();
	
	if($userInfo){
		$userName = $userInfo["userName"];
		$time = $userInfo["lastPlayed"];
		$time = $gs->timeElapsed2($time);
	} else {
		$userName = "Unknown";
		$time = "N/A";
	}
	
	$table .= "<tr>
				<td>".htmlspecialchars($action["accountID"], ENT_QUOTES)."</td>
				<td>".htmlspecialchars($userName, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($rank, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($time, ENT_QUOTES)."</td>
			</tr>";
	$x++;
}
$table .= "</tbody></table>";
/*
	bottom row
*/
//getting count - total de mods + ex-mods
$query = $db->prepare("SELECT count(*) FROM roleassign WHERE roleID != 1");
$query->execute();
$modsCount = $query->fetchColumn();

$query = $db->prepare("SELECT count(*) FROM roleassign WHERE roleID = 1");
$query->execute();
$exModsCount = $query->fetchColumn();

$packcount = $modsCount + $exModsCount;
$pagecount = ceil($packcount / 100);
$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($table . $bottomrow, true, "browse");
?>
