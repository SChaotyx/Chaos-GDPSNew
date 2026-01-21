<?php
session_start();
include "../../incl/lib/connection.php";
require "../../incl/lib/generatePass.php";
require_once "../../incl/lib/exploitPatch.php";
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
$ep = new exploitPatch();
if(!isset($_SESSION["accountID"]) OR $_SESSION["accountID"] == 0){
	header("Location: ../login/login.php");
	exit();
}
if(isset($_POST["userName"]) && isset($_POST["newusr"]) && isset($_POST["password"]) && 
   !empty($_POST["userName"]) && !empty($_POST["newusr"]) && !empty($_POST["password"])){
	$userName = $ep->remove($_POST["userName"]);
	$newusr = $ep->remove($_POST["newusr"]);
	$password = $_POST["password"];
	$generatePass = new generatePass();
	$pass = $generatePass->isValidUsrname($userName, $password);
	if ($pass == 1) {
		// Validate username length
		if(strlen($newusr) > 20){
			$dl->printBox("<h1>".$dl->getLocalizedString("changeUsername").'</h1>
			                       <p>Username too long - 20 characters max. <a href="account/changeUsername.php">Try again</a></p>',"account");
			exit();
		}
		// Check if username already exists
		$query = $db->prepare("SELECT count(*) FROM accounts WHERE userName = :newUserName");
		$query->execute([":newUserName" => $newusr]);
		if($query->fetchColumn() > 0){
			$dl->printBox("<h1>".$dl->getLocalizedString("changeUsername").'</h1>
			                       <p>Account with this nickname already exists! <a href="account/changeUsername.php">Try again</a></p>',"account");
			exit();
		}
		// Update accounts table
		$query = $db->prepare("UPDATE accounts SET userName=:newusr WHERE userName=:userName");	
		$query->execute([':newusr' => $newusr, ':userName' => $userName]);
		if($query->rowCount() == 0){
			$dl->printBox("<h1>".$dl->getLocalizedString("changeUsername").'</h1>
			                       <p>Invalid password or nonexistant account. <a href="account/changeUsername.php">Try again</a></p>',"account");
		}else{
			// Update users table
			$query = $db->prepare("UPDATE users SET userName=:newusr WHERE extID=:accountID");	
			$query->execute([':newusr' => $newusr, ':accountID' => $_SESSION["accountID"]]);
			// Update levels table
			$query = $db->prepare("UPDATE levels SET userName=:newusr WHERE extID=:accountID");	
			$query->execute([':newusr' => $newusr, ':accountID' => $_SESSION["accountID"]]);
			
			$dl->printBox("<h1>".$dl->getLocalizedString("changeUsername").'</h1>
			                       <p>Username changed. <a href="">Please click here to continue.</a></p>',"account");
		}
	}else{
		$dl->printBox("<h1>".$dl->getLocalizedString("changeUsername").'</h1>
		                       <p>Invalid password or nonexistant account. <a href="account/changeUsername.php">Try again</a></p>',"account");
	}
}else{
	$dl->printBox('<h1>'.$dl->getLocalizedString("changeUsername").'</h1>
				<form action="" method="post">
					<div class="form-group">
						<label for="usernameField">Old Username</label>
						<input type="text" class="form-control" id="usernameField" name="userName" placeholder="Enter old username">
					</div>
					<div class="form-group">
						<label for="newusrField">New Username</label>
						<input type="text" class="form-control" id="newusrField" name="newusr" placeholder="Enter new username">
					</div>							
					<div class="form-group">
						<label for="passwordField">Password</label>
						<input type="password" class="form-control" id="passwordField" name="password" placeholder="Password">
					</div>
					<button type="submit" class="btn btn-primary btn-block">'.$dl->getLocalizedString("changeUsername").'</button>
				</form>',"account");
}
?>
