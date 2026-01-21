<?php
session_start();
require_once "../../incl/lib/connection.php";
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
require_once "../../incl/lib/mainLib.php";
$gs = new mainLib();
/*
	generating modtable
*/
$modtable = "";
$accounts = implode(",",$gs->getAccountsWithPermission("toolModactions"));
if($accounts == ""){
	$dl->printBox(sprintf($dl->getLocalizedString("errorNoAccWithPerm"), "Moderator"), false, "browse");
	exit();
}
$query = $db->prepare("SELECT accountID, userName FROM accounts WHERE accountID IN ($accounts) ORDER BY userName ASC");
$query->execute();
$result = $query->fetchAll();
$row = 0;
foreach($result as &$mod){
	$row++;
	$query = $db->prepare("SELECT lastPlayed FROM users WHERE extID = :id LIMIT 1");
	$query->execute([':id' => $mod["accountID"]]);
	$userInfo = $query->fetch();
	if($userInfo){
		$time = $userInfo["lastPlayed"];
		$time = $gs->timeElapsed2($time);
	} else {
		$time = "N/A";
	}
	$query = $db->prepare("SELECT count(*) FROM modactions WHERE account = :id AND type = 100");
	$query->execute([':id' => $mod["accountID"]]);
	$actionscount = $query->fetchColumn();
	$modtable .= "<tr>
					<th scope='row'>".htmlspecialchars($row, ENT_QUOTES)."</th>
					<td>".htmlspecialchars($mod["userName"], ENT_QUOTES)."</td>
					<td>".htmlspecialchars($actionscount, ENT_QUOTES)."</td>
					<td>".htmlspecialchars($time, ENT_QUOTES)."</td>
				</tr>";
}

/* 
	printing
*/
$table = '<table class="table table-inverse">
  <thead>
    <tr>
      <th>#</th>
      <th>'.$dl->getLocalizedString("mod").'</th>
      <th>'.$dl->getLocalizedString("sendcount").'</th>
      <th>'.$dl->getLocalizedString("lastSeen").'</th>
    </tr>
  </thead>
  <tbody>
    '.$modtable.'
  </tbody>
</table>';
$dl->printPage($table, true, "browse");
?>
