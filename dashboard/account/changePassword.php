<?php
session_start();
include "../../incl/lib/connection.php";
include_once "../../config/security.php";
require "../../incl/lib/generatePass.php";
require_once "../../incl/lib/exploitPatch.php";
include_once "../../incl/lib/defuse-crypto.phar";
require "../incl/dashboardLib.php";
$dl = new dashboardLib();
use Defuse\Crypto\KeyProtectedByPassword;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
$ep = new exploitPatch();
if(!isset($_SESSION["accountID"]) OR $_SESSION["accountID"] == 0){
	header("Location: ../login/login.php");
	exit();
}
if(!empty($_POST["userName"]) AND !empty($_POST["oldpassword"]) AND !empty($_POST["newpassword"])){
	$userName = $ep->remove($_POST["userName"]);
	$oldpass = $_POST["oldpassword"];
	$newpass = $_POST["newpassword"];
	$salt = "";
	$generatePass = new generatePass();
	$pass = $generatePass->isValidUsrname($userName, $oldpass);
	if ($pass == 1) {
		//creating pass hash
		$passhash = password_hash($newpass, PASSWORD_DEFAULT);
		$query = $db->prepare("UPDATE accounts SET password=:password, salt=:salt WHERE userName=:userName");	
		$query->execute([':password' => $passhash, ':userName' => $userName, ':salt' => $salt]);
		
		// Get accountID for GJP2 and save data encryption
		$query = $db->prepare("SELECT accountID FROM accounts WHERE userName=:userName");	
		$query->execute([':userName' => $userName]);
		$accountID = $query->fetchColumn();
		
		// Assign GJP2
		$generatePass->assignGJP2($accountID, $newpass);
		
		// Handle save data encryption if enabled
		if($cloudSaveEncryption == 1){
			$saveData = file_get_contents("../../data/accounts/$accountID");
			if(file_exists("../../data/accounts/keys/$accountID")){
				$protected_key_encoded = file_get_contents("../../data/accounts/keys/$accountID");
				if($protected_key_encoded != ""){
					$protected_key = KeyProtectedByPassword::loadFromAsciiSafeString($protected_key_encoded);
					$user_key = $protected_key->unlockKey($oldpass);
					try {
						$saveData = Crypto::decrypt($saveData, $user_key);
					} catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
						$dl->printBox("<h1>".$dl->getLocalizedString("changePassword").'</h1>
						                       <p>Unable to update save data encryption. Password changed but save data encryption was not updated. <a href="account/changePassword.php">Try again</a></p>',"account");
						exit();
					}
					$protected_key = KeyProtectedByPassword::createRandomPasswordProtectedKey($newpass);
					$protected_key_encoded = $protected_key->saveToAsciiSafeString();
					$user_key = $protected_key->unlockKey($newpass);
					$saveData = Crypto::encrypt($saveData, $user_key);
					file_put_contents("../../data/accounts/$accountID",$saveData);
					file_put_contents("../../data/accounts/keys/$accountID",$protected_key_encoded);
				}
			}
		}
		
		$dl->printBox("<h1>".$dl->getLocalizedString("changePassword").'</h1>
		                       <p>Password changed. <a href="">Please click here to continue.</a></p>',"account");
	}else{
		$dl->printBox("<h1>".$dl->getLocalizedString("changePassword").'</h1>
		                       <p>Invalid password or nonexistant account. <a href="account/changePassword.php">Try again</a></p>',"account");
	}
}else{
	$dl->printBox('<h1>'.$dl->getLocalizedString("changePassword").'</h1>
				<form action="" method="post">
					<div class="form-group">
								<label for="usernameField">Username</label>
								<input type="text" class="form-control" id="usernameField" name="userName" placeholder="Enter username">
							</div>
							<div class="form-group">
								<label for="oldpassField">Old password</label>
								<input type="password" class="form-control" id="oldpassField" name="oldpassword" placeholder="Old Password">
							</div>							
							<div class="form-group">
								<label for="newpassField">New password</label>
								<input type="password" class="form-control" id="newpassField" name="newpassword" placeholder="New password">
							</div>
					<button type="submit" class="btn btn-primary btn-block">'.$dl->getLocalizedString("changePassword").'</button>
				</form>',"account");
}
?>
