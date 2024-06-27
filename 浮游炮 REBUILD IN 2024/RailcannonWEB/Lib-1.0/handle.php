<?php
require_once(dirname(__DIR__)."/config.php");
use ELDment\HandleException;

function createConnection()
{
    global $config;
    try
    {
        $connection = new mysqli();
        $connection -> options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        $connection -> options(MYSQLI_OPT_READ_TIMEOUT, 3);
        $connection -> real_connect(    $config["Database"]["host"], 
                                        $config["Database"]["user"],
                                        $config["Database"]["password"],
                                        $config["Database"]["dbname"],
                                        (int)$config["Database"]["port"]
                                    );
        if ($connection -> connect_error)
        {
            throw new HandleException("Could not connect to database");
        }
    }
    catch (Exception $error)
    {
        throw new HandleException($error);
    }
    return $connection;
}

function getRailcannonData($steamId)
{
    global $config;
    $currentTime = time();
    if ((int)$currentTime > (int)$config["LimitedExemption"]["start"] && (int)$currentTime < (int)$config["LimitedExemption"]["end"])
    {
        echo json_encode(array("RailCannon" => true, "R" => 235, "G"  => 35, "B" => 40));
        return;
    }
    $connection = createConnection();
    $array = array(
        "RailCannon" => false,
        "Unix" => -1,
        "R" => 255,
        "G" => 255,
        "B" => 255
    );
    if (($result = $connection -> query("SELECT * FROM `railcannon` WHERE `Steam32`='$steamId' AND `Expiretime`>$currentTime")) -> num_rows > 0)
    {
        $feild = $result -> fetch_assoc();
        $array = array(
            "RailCannon" => true,
            "Unix" => (int)$feild["Expiretime"],
            "R" => (int)$feild["R"],
            "G" => (int)$feild["G"],
            "B" => (int)$feild["B"]
        );
    }
    $connection -> close();
    echo json_encode($array);
    return;
}

function updateRailcannonRGB($steamId, $r, $g, $b)
{
    $connection = createConnection();
    if (($connection -> query("SELECT * FROM `railcannon` WHERE `Steam32`='$steamId'")) -> num_rows > 0)
    {
        $connection -> query("UPDATE `railcannon` SET `R`='$r',`G`='$g',`B`='$b' WHERE `Steam32`='$steamId'");
        if (mysqli_affected_rows($connection))
        {
            $connection -> close();
            echo json_encode(array("Status" => true));
            return;
        }
    }
    echo json_encode(array("Status" => false));
    $connection -> close();
    return;
}

function makeCDKey($days, $json = true)
{
    $buffer = json_encode(  array(
                                "_" => time(), "__" => hexdec(uniqid()), 
                                "days" => $days, 
                                "___" => hexdec(uniqid()), "____" => hexdec(date("siHs"))
                            )
                        );
    if ($json)
    {
        echo json_encode(array("Key" => base64_encode($buffer)));
        return;
    }
    else
    {
        return base64_encode($buffer);
    }
}

function exchangeCDKey($steamId, $name, $CDKey)
{
    $connection = createConnection();
    if (($connection -> query("SELECT * FROM `rc_cdkey` WHERE `CDKey`='$CDKey'")) -> num_rows > 0)
    {
        $connection -> close();
        echo json_encode(array("status" => false, "Msg" => "CDKey已经被使用"));
        return;
    }
        
    $buffer = json_decode(base64_decode($CDKey, true), true);

    if (IsNull($buffer["days"]))
    {
        $connection -> close();
        echo json_encode(array("status" => false, "Msg" => "CDKey无效"));
        return;
    }
    
    $exchangeTime = date("Y-m-d h:i:s");
    $connection -> query("INSERT IGNORE INTO `rc_cdkey`(`CDKey`, `Steam`, `Time`) VALUES ('$CDKey','$steamId','$exchangeTime')");
    if (!mysqli_affected_rows($connection))
    {
        $connection -> close();
        echo json_encode(array("status" => false, "Msg" => "未能影响废弃CDKey数据库"));
        return;
    }
        
    $result = $connection -> query("SELECT * FROM `railcannon` WHERE `Steam32`='$steamId'");
    if ($result -> num_rows > 0)
    {
        $expiretime = (int)($result -> fetch_assoc())["Expiretime"] + (int)$buffer["days"] * 86400;
        $connection -> query("UPDATE `railcannon` SET `Expiretime`='$expiretime' WHERE `Steam32`='$steamId'");
        if (!mysqli_affected_rows($connection))
        {
            $connection -> close();
            echo json_encode(array("status" => false, "Msg" => "未能成功更新Railcannon到期时间"));
            return;
        }
    }
    else
    {
        $expiretime = time() + (int)$buffer["days"] * 86400;
        $connection -> query("INSERT IGNORE INTO `railcannon`(`Steam32`, `Expiretime`, `Name`, `R`, `G`, `B`) VALUES ('$steamId','$expiretime','$name','65','230','125')");
        if (!mysqli_affected_rows($connection))
        {
            $connection -> close();
            echo json_encode(array("status" => false, "Msg" => "未能影响Railcannon数据库"));
            return;
        }
    }
    $connection -> close();
    echo json_encode(array("status" => true, "Msg" => "successful"));
    return;
}
?>