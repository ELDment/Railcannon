<?php
@header("Content-type: application/json");
ini_set("display_errors", 0);
require_once(dirname(__FILE__)."/Lib-1.0/handle.php");
require_once(dirname(__FILE__)."/Lib-1.0/exception.php");
use ELDment\HandleException;

function Parse($arg)
{
    return (IsNull($_POST[$arg]) ? (IsNull($_GET[$arg]) ? "null" : trim($_GET[$arg])): trim($_POST[$arg]));
}

function IsNull($arg)
{
    return ((is_null(trim($arg))) ? true : ((trim($arg) == "") ? true : ((mb_strlen(trim($arg), "utf-8") == 0) ? true : false)));
}

function main()
{
    $steamId    = Parse("Steam32");
    $operation  = Parse("Operate");
    $rValue     = Parse("R");
    $gValue     = Parse("G");
    $bValue     = Parse("B");
    $key        = Parse("Key");
    $name       = Parse("Name");
    $cdkPasscode= Parse("Passcode");
    $cdkDays    = Parse("Days");
    $cdkCount   = Parse("Count");
    global $config;
    switch ($operation)
    {
        case "Update":
            if ($steamId == "null") throw new HandleException("Steam32 is null");
            if ($rValue == "null") throw new HandleException("R is null");
            if ($gValue == "null") throw new HandleException("G is null");
            if ($bValue == "null") throw new HandleException("B is null");
            if (!is_numeric($rValue)) throw new HandleException("R is invaild");
            if (!is_numeric($gValue)) throw new HandleException("G is invaild");
            if (!is_numeric($bValue)) throw new HandleException("B is invaild");
            updateRailcannonRGB($steamId, (int)$rValue, (int)$gValue, (int)$bValue);
            break;
        case "CDKey":
            if ($steamId == "null") throw new HandleException("Steam32 is null");
            if ($key == "null") throw new HandleException("Key is null");
            exchangeCDKey($steamId, $name, $key);
            break;
        case "Make":
            if ($cdkDays == "null") throw new HandleException("Days is null");
            if ($cdkPasscode == "null") throw new HandleException("Passcode is null");
            if ($cdkPasscode != $config["MakeCDKey"]["Passcode"]) throw new HandleException("Passcode is invaild");
            if ($cdkCount == "null")
            {
                makeCDKey($cdkDays);
            }
            else
            {
                $array = [];
                for ($i = 0; $i < $cdkCount; $i++)
                {
                    $CDKey["Key"] = makeCDKey($cdkDays, false);
                    $array[] = $CDKey;
                }
                echo json_encode($array);
            }
            break;
        case "Query":
            if ($steamId == "null") throw new HandleException("Steam32 is null");
            getRailcannonData($steamId);
            break;
        default:
            throw new HandleException("Operate is invaild");
    }
}

main();
?>