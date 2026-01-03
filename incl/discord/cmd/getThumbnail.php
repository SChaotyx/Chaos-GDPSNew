<?php
include __DIR__ . "/../../../config/discord.php";
$stars = $_POST["starStars"];
$feature =  $_POST["starFeatured"];
$epic =  $_POST["starEpic"];
$demondiff = $_POST["starDemonDiff"];
$difficulty = $_POST["starDifficulty"];
$diffauto = $_POST["starAuto"];
$diffdemon = $_POST["starDemon"];
$rateimg = "ratena";
if($feature > 0){
    $rateimg = "ratefeat";
}
if($epic == 1){
    $rateimg = "rateepic";
}
//DIFF CHECK
switch($difficulty){
    case 0: $diffimg = "diff0"; // NA
    break;
    case 10: $diffimg = "diff10"; // EASY
    break;
    case 20: $diffimg = "diff20"; // NORMAL
    break;
    case 30: $diffimg = "diff30"; // HARD
    break;
    case 40: $diffimg = "diff40"; // HARDER
    break;
    case 50: $diffimg = "diff50"; // INSANE
}
if($diffauto == 1){
    $diffimg = "auto"; //AUTO
}
if($diffdemon == 1){
    switch($demondiff){
        case 0: $diffimg = "demon0"; //HARD DEMON
        break;
        case 3: $diffimg = "demon3"; //EASY DEMON
        break;
        case 4: $diffimg = "demon4"; //MEDIUM DEMON
        break;
        case 5: $diffimg = "demon5"; //INSANE DEMON
        break;
        case 6: $diffimg = "demon6"; //EXTREME DEMON
        break;
    }
}
//STARS CHECK
switch($stars){
    case 0: $str = "str0";
    break;
    case 1: $str = "str1";
    break;
    case 2: $str = "str2";
    break;
    case 3: $str = "str3";
    break;
    case 4: $str = "str4";
    break;
    case 5: $str = "str5";
    break;
    case 6: $str = "str6";
    break;
    case 7: $str = "str7";
    break;
    case 8: $str = "str8";
    break;
    case 9: $str = "str9";
    break;
    case 10: $str = "str10";
    break;
}
//GENERATE FILENAME
$filename = "../../../resources/difficulty/".$rateimg.$diffimg.$str.".png";
$imgurl = "difficulty/".$rateimg.$diffimg.$str.".png";
//CHECK ALREADY EXIST IMG
if (file_exists($filename)) {
    echo $iconhost.$imgurl;
}else{
    //CREATE IMAGE
    $png = imagecreatefrompng("../resource/$rateimg.png");
    $png2 = imagecreatefrompng("../resource/$diffimg.png");
    $png3 = imagecreatefrompng("../resource/$str.png");
    imagesavealpha($png, true);
    $sizex = imagesx($png);
    $sizey = imagesy($png);
    imagecopyresampled( $png, $png2, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
    imagecopyresampled( $png, $png3, 0, 0, 0, 0, $sizex, $sizey, $sizex, $sizey);
    imagepng($png, $filename);
    echo $iconhost.$imgurl;
}
?>