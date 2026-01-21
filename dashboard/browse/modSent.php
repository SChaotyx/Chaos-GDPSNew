<?php
session_start();
require_once "../../incl/lib/connection.php";
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
require_once "../../incl/lib/mainLib.php";
$gs = new mainLib();
if(!isset($_SESSION["accountID"]) OR $_SESSION["accountID"] == 0){
	header("Location: ../login/login.php");
	exit();
}
if($gs->checkPermission($_SESSION["accountID"], "modTools") == false && 
   $gs->checkPermission($_SESSION["accountID"], "headTools") == false && 
   $gs->checkPermission($_SESSION["accountID"], "elderTools") == false && 
   $gs->checkPermission($_SESSION["accountID"], "adminTools") == false){
	exit($dl->printBox("<h1>NO NO NO</h1><p>This account do not have the permissions to access this tool.</p>"));
}
if(isset($_GET["page"]) AND is_numeric($_GET["page"]) AND $_GET["page"] > 0){
	$page = ($_GET["page"] - 1) * 10;
	$actualpage = $_GET["page"];
}else{
	$page = 0;
	$actualpage = 1;
}
$table = '<table class="table table-inverse">
			<thead>
				<tr>
					<th>'.$dl->getLocalizedString("levelID").'</th>
					<th>'.$dl->getLocalizedString("difficulty").'</th>
					<th>'.$dl->getLocalizedString("stars").'</th>
					<th>'.$dl->getLocalizedString("sendrate").'</th>
					<th>'.$dl->getLocalizedString("time").'</th>
				</tr>
			</thead>
			<tbody>';

$query = $db->prepare("SELECT suggestLevelId, suggestDifficulty, suggestStars, suggestFeatured, suggestAuto, suggestDemon, timestamp 
                       FROM suggest 
                       ORDER BY timestamp DESC 
                       LIMIT 10 OFFSET $page");
$query->execute();
$result = $query->fetchAll();
$x = $page + 1;
foreach($result as &$suggest){
	$levelID = $suggest["suggestLevelId"];
	
	// Determinar dificultad: si es auto o demon, ignorar suggestDifficulty
	$difficulty = "";
	if($suggest["suggestAuto"] == 1){
		$difficulty = $dl->getLocalizedString("autodifficultyset");
	} elseif($suggest["suggestDemon"] == 1){
		$difficulty = "Demon";
	} else {
		// Usar suggestDifficulty solo si no es auto ni demon
		$difficulty = $gs->getDifficulty($suggest["suggestDifficulty"], 0, 0);
	}
	
	// Estrellas
	$stars = $suggest["suggestStars"];
	
	// Suggest Featured
	$suggestFeatured = $suggest["suggestFeatured"];
	$featuredText = "";
	if($suggestFeatured == 0){
		$featuredText = $dl->getLocalizedString("featft0");
	} elseif($suggestFeatured == 1){
		$featuredText = $dl->getLocalizedString("featft1");
	} else {
		$featuredText = "Normal";
	}
	
	// Timestamp (fecha)
	$timestamp = $suggest["timestamp"];
	$date = date("d/m/Y G:i:s", $timestamp);
	
	$table .= "<tr>
				<td>".htmlspecialchars($levelID, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($difficulty, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($stars, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($featuredText, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($date, ENT_QUOTES)."</td>
			</tr>";
	$x++;
}
$table .= "</tbody></table>";
/*
	bottom row
*/
//getting count
$query = $db->prepare("SELECT count(*) FROM suggest");
$query->execute();
$packcount = $query->fetchColumn();
$pagecount = ceil($packcount / 10);
$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($table . $bottomrow, true, "browse");
?>
