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
	$page = ($_GET["page"] - 1) * 40;
	$actualpage = $_GET["page"];
}else{
	$page = 0;
	$actualpage = 1;
}
$table = '<table class="table table-inverse">
			<thead>
				<tr>
					<th>'.$dl->getLocalizedString("comment").'</th>
					<th>'.$dl->getLocalizedString("likes").'</th>
					<th>'.$dl->getLocalizedString("timeago").'</th>
					<th>'.$dl->getLocalizedString("levelID").'</th>
				</tr>
			</thead>
			<tbody>';
// Obtener el userID del usuario actual
$query = $db->prepare("SELECT userID FROM users WHERE extID=:extID LIMIT 1");
$query->execute([":extID" => $_SESSION["accountID"]]);
$userData = $query->fetch();
if($userData){
	$userID = $userData["userID"];
	
	// Obtener comentarios del usuario con paginación
	$query = $db->prepare("SELECT * FROM comments WHERE userID=:userID ORDER BY timestamp DESC LIMIT 40 OFFSET $page");
	$query->execute([":userID" => $userID]);
	$comments = $query->fetchAll();
	
	foreach($comments as &$comment){
		$actualcomment = $comment["comment"];
		$actualcomment = base64_decode($actualcomment);
		$commentdate = $comment["timestamp"];
		$commentdate = $gs->timeElapsed($commentdate);
		
		$table .= "<tr>
					<td>".htmlspecialchars($actualcomment, ENT_QUOTES)."</td>
					<td>".htmlspecialchars($comment["likes"], ENT_QUOTES)."</td>
					<td>".htmlspecialchars($commentdate, ENT_QUOTES)."</td>
					<td>".htmlspecialchars($comment["levelID"], ENT_QUOTES)."</td>
				</tr>";
	}
	
	//getting count
	$query = $db->prepare("SELECT count(*) FROM comments WHERE userID=:userID");
	$query->execute([':userID' => $userID]);
	$packcount = $query->fetchColumn();
} else {
	$packcount = 0;
}
$table .= "</tbody></table>";
/*
	bottom row
*/
$pagecount = ceil($packcount / 40);
$bottomrow = $dl->generateBottomRow($pagecount, $actualpage);
$dl->printPage($table . $bottomrow, true, "account");
?>
