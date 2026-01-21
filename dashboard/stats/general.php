<?php
session_start();
require_once "../../incl/lib/connection.php";
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
require_once "../../incl/lib/mainLib.php";
$gs = new mainLib();
function genLvlRow($params, $params2, $params3, $params4){
	global $db;
	$row = htmlspecialchars($params3, ENT_QUOTES);
	$query = $db->prepare("SELECT count(*) FROM levels ".$params4." ".$params2);
	$query->execute();
	$row .= "<td>".htmlspecialchars($query->fetchColumn(), ENT_QUOTES)."</td>";
	$query = $db->prepare("SELECT count(*) FROM levels WHERE starStars = 0 AND starFeatured = 0 AND starEpic = 0 ".$params." ".$params2);
	$query->execute();
	$row .= "<td>".htmlspecialchars($query->fetchColumn(), ENT_QUOTES)."</td>";
	$query = $db->prepare("SELECT count(*) FROM levels WHERE starStars!= 0 ".$params." ".$params2);
	$query->execute();
	$row .= "<td>".htmlspecialchars($query->fetchColumn(), ENT_QUOTES)."</td>";
	$query = $db->prepare("SELECT count(*) FROM levels WHERE starStars!= 0 AND starFeatured = 0 AND starEpic = 0 ".$params." ".$params2);
	$query->execute();
	$row .= "<td>".htmlspecialchars($query->fetchColumn(), ENT_QUOTES)."</td>";
	$query = $db->prepare("SELECT count(*) FROM levels WHERE starFeatured = 1 AND starEpic = 0 ".$params." ".$params2);
	$query->execute();
	$row .= "<td>".htmlspecialchars($query->fetchColumn(), ENT_QUOTES)."</td>";
	$query = $db->prepare("SELECT count(*) FROM levels WHERE starEpic = 1 ".$params." ".$params2);
	$query->execute();
	$row .= "<td>".htmlspecialchars($query->fetchColumn(), ENT_QUOTES)."</td></tr>";
	return $row;
}
//  TABLA 1  - NIVELES -
$table = '<table class="table table-inverse">
			<thead>
				<tr>
				    <th>'.$dl->getLocalizedString("Levels").'</th>
					<th>'.$dl->getLocalizedString("Total").'</th>
					<th>'.$dl->getLocalizedString("Unrated").'</th>
					<th>'.$dl->getLocalizedString("Rated").'</th>
					<th>'.$dl->getLocalizedString("OnlyStars").'</th>
					<th>'.$dl->getLocalizedString("Featured").'</th>
					<th>'.$dl->getLocalizedString("Epic").'</th>
				</tr>
			</thead>
			<tbody>';
$table .= '<tr>
<th>'.genLvlRow("","","Total", "").'</th>
<td>'.genLvlRow("AND","starDifficulty = 0 AND starDemon = 0 AND starAuto = 0 AND unlisted = 0", "N/A", "WHERE").'</td>
<td>'.genLvlRow("AND","starAuto = 1  AND unlisted = 0", "Auto", "WHERE").'</td>
<td>'.genLvlRow("AND","starDifficulty = 10 AND starDemon = 0 AND starAuto = 0 AND unlisted = 0", "Easy", "WHERE").'</td>
<td>'.genLvlRow("AND","starDifficulty = 20 AND starDemon = 0 AND starAuto = 0 AND unlisted = 0", "Normal", "WHERE").'</td>
<td>'.genLvlRow("AND","starDifficulty = 30 AND starDemon = 0 AND starAuto = 0 AND unlisted = 0", "Hard", "WHERE").'</td>
<td>'.genLvlRow("AND","starDifficulty = 40 AND starDemon = 0 AND starAuto = 0 AND unlisted = 0", "Harder", "WHERE").'</td>
<td>'.genLvlRow("AND","starDifficulty = 50 AND starDemon = 0 AND starAuto = 0 AND unlisted = 0", "Insane", "WHERE").'</td>
<td>'.genLvlRow("AND","starDemon = 1", "Demon", "WHERE").'</td>
			</tr>';
$table .= '</tbody></table>';
//  TABLA 2  - DEMONS -
$table2 = '<table class="table table-inverse">
			<thead>
				<tr>
				    <th>'.$dl->getLocalizedString("Demons").'</th>
					<th>'.$dl->getLocalizedString("Total").'</th>
					<th>'.$dl->getLocalizedString("Unrated").'</th>
					<th>'.$dl->getLocalizedString("Rated").'</th>
					<th>'.$dl->getLocalizedString("OnlyStars").'</th>
					<th>'.$dl->getLocalizedString("Featured").'</th>
					<th>'.$dl->getLocalizedString("Epic").'</th>
				</tr>
			</thead>
			<tbody>';	
$table2 .= "<tr>
<td>".genLvlRow("AND","starDemon = 1", "Total", "WHERE")."</td>
<td>".genLvlRow("AND","starDemon = 1 AND starDemonDiff = 3", "Easy", "WHERE")."</td>
<td>".genLvlRow("AND","starDemon = 1 AND starDemonDiff = 4", "Medium", "WHERE")."</td>
<td>".genLvlRow("AND","starDemon = 1 AND starDemonDiff = 0", "Hard", "WHERE")."</td>
<td>".genLvlRow("AND","starDemon = 1 AND starDemonDiff = 5", "Insane", "WHERE")."</td>
<td>".genLvlRow("AND","starDemon = 1 AND starDemonDiff = 6", "Extreme", "WHERE")."</td>
		</tr>";
$table2 .= "</tbody></table>";
$dl->printPage($table . $table2, true, "stats");
?>
