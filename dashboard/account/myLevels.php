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
					<th>'.$dl->getLocalizedString("ID").'</th>
					<th>'.$dl->getLocalizedString("name").'</th>
					<th>'.$dl->getLocalizedString("difficulty").'</th>
					<th>'.$dl->getLocalizedString("stars").'</th>
					<th>'.$dl->getLocalizedString("Rate").'</th>
					<th>'.$dl->getLocalizedString("uploaddate").'</th>
					<th>'.$dl->getLocalizedString("updatedate").'</th>
					<th>'.$dl->getLocalizedString("ratedate").'</th>
				</tr>
			</thead>
			<tbody>';

$query = $db->prepare("SELECT * FROM levels WHERE extID=:extID AND unlisted=0 ORDER BY levelID DESC LIMIT 10 OFFSET $page");
$query->execute([":extID" => $_SESSION["accountID"]]);
$result = $query->fetchAll();
foreach($result as &$level){
		$difficultyset = $dl->getLocalizedString("difficultyset".$level["starDifficulty"]);
	if($level["starDifficulty"] == 50){
		if($level["starDemon"] == 1){
			$difficultyset = $dl->getLocalizedString("demondifficultyset".$level["starDemonDiff"]);
		}
		if($level["starAuto"] == 1){
			$difficultyset = $dl->getLocalizedString("autodifficultyset");
		}
	}
	if($level["starStars"] == 0){
			$rate = $dl->getLocalizedString("N/A");
	}
	if($level["starStars"] > 0){
		if($level["starEpic"] == 0 AND $level["starFeatured"] == 0){
			$rate = $dl->getLocalizedString("onlyRate");
		}
		if($level["starFeatured"] == 1){
			$rate = $dl->getLocalizedString("Featured");
		}
		if($level["starEpic"] == 1){
			$rate = $dl->getLocalizedString("Epic");
		}
	}
	$uploaddate = $level["uploadDate"] ;
	$uploaddate = $gs->timeElapsed2($uploaddate);
	$updatedate = $level["updateDate"] ;
	$updatedate = $gs->timeElapsed2($updatedate);
	if($level["updateDate"] == $level["uploadDate"]){
			$updatedate = $dl->getLocalizedString("N/A");
	}
	$ratedate = $level["rateDate"] ;
	$ratedate = $gs->timeElapsed2($ratedate);
	if($level["rateDate"] == 0){
			$ratedate = $dl->getLocalizedString("N/A");
	}
	$table .= "<tr>
				<td>".htmlspecialchars($level["levelID"], ENT_QUOTES)."</td>
				<td>".htmlspecialchars($level["levelName"], ENT_QUOTES)."</td>
				<td>".htmlspecialchars($difficultyset, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($level["starStars"], ENT_QUOTES)."</td>
				<td>".htmlspecialchars($rate, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($uploaddate, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($updatedate, ENT_QUOTES)."</td>
				<td>".htmlspecialchars($ratedate, ENT_QUOTES)."</td>
			</tr>";
}
$table .= "</tbody></table>";
/*
	bottom row
*/
//getting count
$query = $db->prepare("SELECT count(*) FROM levels WHERE extID=:extID AND unlisted=0");
$query->execute([':extID' => $_SESSION["accountID"]]);
$packcount = $query->fetchColumn();
$pagecount = ceil($packcount / 10);
$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($table . $bottomrow, true, "account");
?>
