<?php
@header('Content-type: application/json');
ini_set('display_errors', 0);
include_once(dirname(__FILE__).'/GetArg.php');
include_once(dirname(__FILE__).'/Setting.php');

function connect()
{
    $Setting = new Setting();
    $Database = $Setting -> Setting_DataBase;
    $conn = new mysqli();
    $conn -> options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $conn -> options(MYSQLI_OPT_READ_TIMEOUT, 10);
    $conn -> real_connect($Database['dbhost'], $Database['username'], $Database['password'], $Database['dbname'], (int)$Database['port']);
    if ($conn -> connect_error)
    {
        http_response_code(502);
        exit(502);
    }
    return $conn;
}

function get_data($SteamId)
{
    //========================特定时间限免浮游炮========================//
    if ((int)time() > 1674230400 && (int)time() < 1674338400)
    {
        $rc = true;
        $tarr = array(
            'RailCannon' => $rc,
            'R' => 235,
            'G' => 35,
            'B' => 40
        );
        echo json_encode($tarr);
        exit;
    }
    //========================特定时间限免浮游炮========================//
    $conn = connect();
    $time = time();
    $sql = "SELECT * FROM `railcannon` WHERE `Steam32`='$SteamId' AND `Expiretime`>$time";
    $result = $conn -> query($sql);
    $Bool;$R;$G;$B;$eunix;
    if ($result -> num_rows > 0)
    {
        $Bool = true;
        while($row = $result -> fetch_assoc())
        {
            $R = (int)$row['R'];
            $G = (int)$row['G'];
            $B = (int)$row['B'];
            $eunix = $row['Expiretime'];
        }
    }else{
        $Bool = false;$R = 255;$G = 255;$B = 255;$eunix = -1;
    }
    $arr = array(
        'RailCannon' => $Bool,
        'Unix' => (int)$eunix,
        'R' => $R,
        'G' => $G,
        'B' => $B
    );
    echo json_encode($arr);
    $conn -> close();
    exit;
}

function update_rgb($SteamId, $r, $g, $b)
{
    $conn = connect();
    $bool = true;$boolR = true;$boolG = true;$boolB = true;
    $sql = "SELECT * FROM `railcannon` WHERE `Steam32`='$SteamId'";
    $result = $conn -> query($sql);
    if ($result -> num_rows > 0)
    {
        $sql = "UPDATE `railcannon` SET `R`='$r' WHERE `Steam32`='$SteamId'";
        if (!mysqli_query($conn, $sql)) 
        {
        	$bool = false;
        	$boolR = false;
        }
        $sql = "UPDATE `railcannon` SET `G`='$g' WHERE `Steam32`='$SteamId'";
        if (!mysqli_query($conn, $sql)) 
        {
        	$bool = false;
        	$boolG = false;
        }
        $sql = "UPDATE `railcannon` SET `B`='$b' WHERE `Steam32`='$SteamId'";
        if (!mysqli_query($conn, $sql)) 
        {
        	$bool = false;
        	$boolB = false;
        }
    }else{$bool = false;}
    $arr = array(
        'Status' => $bool,
        'SetR' => $boolR,
        'SetG' => $boolG,
        'SetB' => $boolB
    );
    echo json_encode($arr);
    $conn -> close();
    exit;
}

function make_key($days, $fill = false)
{
    $buffer = json_encode(array('_str_' => uniqid(), 'days' => $days, '_int_' => rand(-100000000, 100000000)));
    $temp = base64_encode($buffer);
    if (!$fill)
    {
        echo json_encode(array('Key' => $temp));
        exit;
    }
    return $temp;
}

function check_key($SteamId, $SteamName, $CDKey)
{
    $temp = base64_decode($CDKey, true);
    if ($temp == false)
    {
        echo json_encode(array('status' => false, 'Msg' => '无效卡密#1'));
        exit;
    }
    $buffer = json_decode($temp, true);
    if (json_last_error() != JSON_ERROR_NONE)
    {
        echo json_encode(array('status' => false, 'Msg' => "无效卡密#2"));
        exit;
    }
    if (!array_key_exists('days', $buffer))
    {
        echo json_encode(array('status' => false, 'Msg' => '无效卡密#3'));
        exit;
    }
    $days = $buffer['days'];
    $sql = "SELECT * FROM `railcannon` WHERE `Steam32`='$SteamId'";
    $conn = connect();
    $result = $conn -> query($sql);
    
    if ($result -> num_rows > 0)
    {
        $eunix;
        while($row = $result -> fetch_assoc())
        {
            $eunix = $row['Expiretime'];
        }
        $Vtime = (int)$days * 86400;
        $Vtime = $eunix + $Vtime;

        $sql1 = "UPDATE `railcannon` SET `Expiretime`='$Vtime' WHERE `Steam32`='$SteamId'";
        $conn -> query($sql1);
        if (!mysqli_affected_rows($conn))
        {
            $conn -> close();
            echo json_encode(array('status' => false, 'Msg' => '未能成功更新Railcannon到期时间'));
            exit;
        }
        
        $sql2 = "SELECT * FROM `rc_cdkey` WHERE `CDKey`='$CDKey'";
        $result2 = $conn -> query($sql2);
        if ($result2 -> num_rows > 0)
        {
            $conn -> close();
            echo json_encode(array('status' => false, 'Msg' => '卡密已经被使用'));
            exit;
        }

        $time = date('Y-m-d h:i:s', time());
        $sql3 = "INSERT IGNORE INTO `rc_cdkey`(`CDKey`, `Steam`, `Time`) VALUES ('$CDKey','$SteamId','$time')";
        $conn -> query($sql3);
        if (!mysqli_affected_rows($conn))
        {
            $conn -> close();
            echo json_encode(array('status' => false, 'Msg' => '未能影响废弃卡密数据库'));
            exit;
        }
    }else{
        $sql2_ = "SELECT * FROM `rc_cdkey` WHERE `CDKey`='$CDKey'";
        $result2_ = $conn -> query($sql2_);
        if ($result2_ -> num_rows > 0)
        {
            $conn -> close();
            echo json_encode(array('status' => false, 'Msg' => '卡密已经被使用'));
            exit;
        }
        
        $Vtime1 = (int)$days * 86400;
        $Vtime1 = time() + $Vtime1;
        $sql4 = "INSERT IGNORE INTO `railcannon`(`Steam32`, `Expiretime`, `name`, `R`, `G`, `B`) VALUES ('$SteamId','$Vtime1','$SteamName','65','230','125')";
        $conn -> query($sql4);
        if (!mysqli_affected_rows($conn))
        {
            $conn -> close();
            echo json_encode(array('status' => false, 'Msg' => '未能影响浮游炮数据库'));
            exit;
        }
    }
    $conn -> close();
    echo json_encode(array('status' => true, 'Msg' => '成功'));
    exit;
}

function main()
{
    $Class_Arg = new Arg();
    $GetID = $Class_Arg -> Parse('Steam32');
    $GetOp = $Class_Arg -> Parse('Operate');
    if ($GetOp == 'Update')
    {
        if ($GetID == 'Invaild Arg')
        {
            http_response_code(502);
            exit(502);
        }
        $GetR = $Class_Arg -> Parse('R');
        $GetG = $Class_Arg -> Parse('G');
        $GetB = $Class_Arg -> Parse('B');
        if ($GetR == 'Invaild Arg' || $GetG == 'Invaild Arg' || $GetB == 'Invaild Arg')
        {
            http_response_code(502);
            exit(502);
        }
        if ($GetR > 255 || $GetR < 0 || $GetG > 255 || $GetG < 0 || $GetB > 255 || $GetB < 0)
        {
            http_response_code(502);
            exit(502);
        }
        update_rgb($GetID, $GetR, $GetG, $GetB);
    }
    else if ($GetOp == 'Invaild Arg')
    {
        if ($GetID == 'Invaild Arg')
        {
            http_response_code(502);
            exit(502);
        }
        get_data($GetID);
    }
    else if ($GetOp == 'CDKey')
    {
        if ($GetID == 'Invaild Arg')
        {
            http_response_code(502);
            exit(502);
        }
        $GetKey = $Class_Arg -> Parse('Key');
        $GetName = $Class_Arg -> Parse('Name');
        if ($GetKey == 'Invaild Arg' || $GetName == 'Invaild Arg')
        {
            http_response_code(502);
            exit(502);
        }
        check_key($GetID, $GetName, $GetKey);
    }
    else if ($GetOp == 'Make')
    {
        $GetDays = $Class_Arg -> Parse('Days');
        $GetCount = $Class_Arg -> Parse('Count');
        if ($GetDays == 'Invaild Arg')
        {
            http_response_code(502);
            exit(502);
        }
        if ($GetCount == 'Invaild Arg')
            make_key($GetDays);
        else{
            $arr = [];
            for ($i = 0; $i < $GetCount; $i++)
            {
                $CDKey['Key'] = make_key($GetDays, true);
                $arr[] = $CDKey;
            }
            echo json_encode($arr);
            exit;
        }
    }
}

main();
?>
