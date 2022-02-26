<?php
    include('send_admin_line.php'); //管理者にLineメッセージを送る

    $message = 'copy_access_log: ';

    $second_path  = '/compress_folder/';
    $compress_file = ROOT_DIRECTOR . $second_path . 'access.log';
    $file = ROOT_DIRECTOR . $second_path . 'compress_access_log_' . date(Ymd) . '.zip';

    // 圧縮・解凍するためのオブジェクト生成
    $zip = new ZipArchive();

    $result = $zip->open($file, ZipArchive::CREATE);
    if ($result === true) {
        // 圧縮
        $zip->addFile($compress_file);
        // ファイルを生成
        $zip->close();

        //ファイルの中身を削除
        file_put_contents($compress_file,'');

        $message .= 'NO_Error';
    } else {
        $message .= $result;
    }

    post_messages($message);