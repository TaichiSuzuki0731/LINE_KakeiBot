<?php
    include('send_admin_line.php'); //管理者にLineメッセージを送る

    $unlink_files = '';

    // access_logのフォルダ
    $path1 = '/compress_folder/';
    // mysql_dumpのフォルダ
    $path2 = '/backup_db/';

    // access_logは4世代まで保存
    $ago1 = date("Y-m-d", strtotime("-30 day"));
    // dbのバックアップは2世代まで保存
    $ago2 = date("Y-m-d", strtotime("-14 day"));

    // access_logのフォルダ内の対象ファイルの収集
    $log_list = glob(ROOT_DIRECTOR . $path1 . '{*.zip}', GLOB_BRACE);

    foreach ($log_list as $file) {
        // ファイルのタイムスタンプを取得
        $unixdate = filemtime($file);
        // タイムスタンプを日付のフォーマットに変更
        $filedate = date("Y-m-d", $unixdate);
        if($filedate < $ago1){
            unlink($file); //削除実行
            $unlink_files .= $file . "\n";
        }
    }

    // dbのバックアップ内のフォルダ・ファイルを収集
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(ROOT_DIRECTOR . $path2,
                FilesystemIterator::CURRENT_AS_FILEINFO |
                FilesystemIterator::KEY_AS_PATHNAME |
                FilesystemIterator::SKIP_DOTS
        )
    );

    $loop_cnt = 0;
    foreach($files as $file_info) {
        if (!$file_info->isFile()) {
            continue;
        }
        $del_files[$loop_cnt] = $file_info;
        $del_folders[$loop_cnt] = ROOT_DIRECTOR . $path2 . mb_strstr(str_replace(ROOT_DIRECTOR . $path2, '', $del_files[$loop_cnt]), '/', true);
        $loop_cnt += 1;
    }

    //重複配列データ削除
    $del_folders = array_unique($del_folders);

    // mysql_dumpファイル削除
    foreach ($del_files as $row) {
        if (is_file($row)) {
            $unixdate = filemtime($row);
            $filedate = date("Y-m-d", $unixdate);
            if($filedate < $ago2) {
                unlink($row);
                $unlink_files .= $row . "\n";
            }
        }
    }

    // mysql_dumpフィルダ削除
    foreach ($del_folders as $row) {
        if (is_dir($row)) {
            $unixdate = filemtime($row);
            $filedate = date("Y-m-d", $unixdate);
            if($filedate < $ago2) {
                rmdir($row);
                $unlink_files .= $row . "\n";
            }
        }
    }


    if ($unlink_files != '') {
        $message = "↓unlink_file\n" . $unlink_files;
    } else {
        $message = "No_unlink_file";
    }

    post_messages($message);