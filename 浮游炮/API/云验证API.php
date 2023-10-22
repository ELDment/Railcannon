<?php
@header('Content-type: application/json');
ini_set('display_errors', 0);

function Getip()
{
    $realip;
    if (isset($_SERVER))
    {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"]))
        {
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } 
        else if (isset($_SERVER["HTTP_CLIENT_IP"]))
        {
            $realip = $_SERVER["HTTP_CLIENT_IP"];
        }else{
            $realip = $_SERVER["REMOTE_ADDR"];
        }
    }else{
        if (getenv("HTTP_X_FORWARDED_FOR"))
        {
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        }
        else if (getenv("HTTP_CLIENT_IP"))
        {
            $realip = getenv("HTTP_CLIENT_IP");
        }else{
            $realip = getenv("REMOTE_ADDR");
        } 
    }
    return $realip;
}

function main()
{
    $server = array(
        "112.101.123.107", //测试
        "103.135.102.98" //测试
    );
    $VisitorIP = Getip();
    if (!in_array($VisitorIP, $server))
    {
        echo json_encode(array("Permission" => false, "IP" => $VisitorIP));
        exit;
    }
    echo json_encode(array("Permission" => true, "IP" => $VisitorIP));
}

main()
?>