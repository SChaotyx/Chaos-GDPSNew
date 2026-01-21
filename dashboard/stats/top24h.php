<?php
session_start();
require_once "../../incl/lib/connection.php";
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
require_once "../../incl/lib/mainLib.php";
$gs = new mainLib();
if(isset($_GET["page"]) AND is_numeric($_GET["page"]) AND $_GET["page"] > 0){
	$page = ($_GET["page"] - 1) * 50;
	$actualpage = $_GET["page"];
}else{
	$page = 0;
	$actualpage = 1;
}
$table = '<table class="table table-inverse">
			<thead>
				<tr>
					<th>#</th>
					<th>'.$dl->getLocalizedString("ID").'</th>
					<th>'.$dl->getLocalizedString("User").'</th>
					<th>'.$dl->getLocalizedString("stars").'</th>
				</tr>
			</thead>
			<tbody>';

$starsgain = array();
$time = time() - 86400;
$query = $db->prepare("SELECT * FROM actions WHERE type = '9' AND timestamp > :time");
$query->execute([':time' => $time]);
$result = $query->fetchAll();
foreach($result as &$gain){
	if(!empty($starsgain[$gain["account"]])){
		$starsgain[$gain["account"]] += $gain["value"];
	}else{
		$starsgain[$gain["account"]] = $gain["value"];
	}
}
arsort($starsgain);
$x = $page + 1;
$displayed = 0;
foreach ($starsgain as $userID => $stars){
	if($displayed < $page){
		$displayed++;
		continue;
	}
	if($displayed >= $page + 50){
		break;
	}
	$query = $db->prepare("SELECT userName, isBanned FROM users WHERE userID = :userID LIMIT 1");
	$query->execute([':userID' => $userID]);
	$userinfo = $query->fetch();
	if($userinfo && $userinfo["isBanned"] == 0){
		$username = htmlspecialchars($userinfo["userName"], ENT_QUOTES);
		$table .= "<tr>
						<th scope='row'>".htmlspecialchars($x, ENT_QUOTES)."</th>
						<td>".htmlspecialchars($userID, ENT_QUOTES)."</td>
						<td>".$username."</td>
						<td>".htmlspecialchars($stars, ENT_QUOTES)."</td>
					</tr>";
		$x++;
		$displayed++;
	}
}
$table .= "</tbody></table>";
/*
	bottom row
*/
//getting count - total de usuarios no baneados con estrellas ganadas
$totalCount = 0;
foreach ($starsgain as $userID => $stars){
	$query = $db->prepare("SELECT isBanned FROM users WHERE userID = :userID LIMIT 1");
	$query->execute([':userID' => $userID]);
	$userinfo = $query->fetch();
	if($userinfo && $userinfo["isBanned"] == 0){
		$totalCount++;
	}
}
$pagecount = ceil($totalCount / 50);
$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($table . $bottomrow, true, "stats");
?>
