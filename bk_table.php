<?php
    include('send_admin_line.php'); //管理者にLineメッセージを送る

    $db_info = db_info();
    $srv = $db_info['host'];
    $db_user = $db_info['user'];
    $db_pass = $db_info['pass'];
    $db_name = $db_info['name'];

    $second_path  = '/backup_db/';
    $folderPath = "mysqldump_db_";
    $filePath = ROOT_DIRECTOR . $second_path . $folderPath . "date" . date('ymd') . '_' . date('His') . '/';

    //db接続
    $db_link = db_connect();

    if(!$db_link){
        // MySQLに接続できなかったら
        $message = "MySQL Connect Error: " . mysql_error();
    }else{
        // MySQLに接続できたら
        $res = mysqli_query($db_link,"SHOW DATABASES");
        mkdir($filePath, 0755);
        while ($row = mysqli_fetch_assoc($res)) {
            $fileName = $row['Database'] . '_' . date('ymd') . '_' . date('His') . '.sql';
            $dbName = $row['Database'];
            $command = "mysqldump " . $db_name . " --host=" . $srv . " --user=" . $db_user . " --password=" . $db_pass . " > " . $filePath . $fileName;
            system($command);
        }
        $message = "Success Complete Database";
    }

    post_messages($message);