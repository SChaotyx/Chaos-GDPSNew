<?php
session_start();
require_once "../../incl/lib/connection.php";
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
require_once "../../incl/lib/mainLib.php";
$gs = new mainLib();
/*
	generating gauntlettable
*/
if(isset($_GET["page"]) AND is_numeric($_GET["page"]) AND $_GET["page"] > 0){
	$page = ($_GET["page"] - 1) * 10;
	$actualpage = $_GET["page"];
}else{
	$page = 0;
	$actualpage = 1;
}
$x = $page + 1;
$gauntlettable = "";
$query = $db->prepare("SELECT * FROM gauntlets ORDER BY ID ASC LIMIT 10 OFFSET :offset");
$query->bindValue(':offset', $page, PDO::PARAM_INT);
$query->execute();
$result = $query->fetchAll();
foreach($result as &$gauntlet){
	$lvlarray = array();
	for ($y = 1; $y < 6; $y++) {
		$lvlarray[] = $gauntlet["level".$y];
	}
	$lvltable = "";
	foreach($lvlarray as &$lvl){
		if(empty($lvl)) continue;
		$query = $db->prepare("SELECT levelID,levelName,starStars,userID,coins FROM levels WHERE levelID = :levelID");
		$query->execute([':levelID' => $lvl]);
		$level = $query->fetch();
		if($level){
			$lvltable .= "<tr>
							<td>".htmlspecialchars($level["levelID"], ENT_QUOTES)."</td>
							<td>".htmlspecialchars($level["levelName"], ENT_QUOTES)."</td>
							<td>".htmlspecialchars($gs->getUserName($level["userID"]), ENT_QUOTES)."</td>
							<td>".htmlspecialchars($level["starStars"], ENT_QUOTES)."</td>
							<td>".htmlspecialchars($level["coins"], ENT_QUOTES)."</td>
						</tr>";
		}
	}
	$gauntlettable .= "<tr>
					<th scope='row'>$x</th>
					<td>".htmlspecialchars($gs->getGauntletName($gauntlet["ID"]), ENT_QUOTES).'</td>
					<td><a class="dropdown-toggle" href="#" id="gauntletDropdown'.$x.'" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
							Show
						</a>
						<div class="dropdown-menu dropdown-menu-right" aria-labelledby="gauntletDropdown'.$x.'"  style="padding:17px;">
							<table class="table">
								<thead>
									<tr>
										<th>'.$dl->getLocalizedString("ID").'</th>
										<th>'.$dl->getLocalizedString("name").'</th>
										<th>'.$dl->getLocalizedString("author").'</th>
										<th>'.$dl->getLocalizedString("stars").'</th>
										<th>'.$dl->getLocalizedString("userCoins").'</th>
									</tr>
								</thead>
								<tbody>
									'.$lvltable.'
								</tbody>
							</table>
						</div>
					</td>
					</tr>';
	$x++;
}
/*
	bottom row
*/
//getting count
$query = $db->prepare("SELECT count(*) FROM gauntlets");
$query->execute();
$gauntletcount = $query->fetchColumn();
$pagecount = ceil($gauntletcount / 10);
$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
/* 
	printing
*/
$dl->printPage('<table class="table table-inverse">
  <thead>
    <tr>
      <th>#</th>
      <th>'.$dl->getLocalizedString("name").'</th>
      <th>'.$dl->getLocalizedString("levels").'</th>
    </tr>
  </thead>
  <tbody>
    '.$gauntlettable.'
  </tbody>
</table>'
.$bottomrow, true, "stats");
?>