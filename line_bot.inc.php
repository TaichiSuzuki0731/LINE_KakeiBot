<?php
    //curlレスポンスを収集
    function receipt_curl_response($result, $res_curl, $method) {
        $strHead = substr($result, 0, $res_curl['header_size']);
        $_header = str_replace("\r", '', $strHead);
        $tmp_header = explode("\n", $_header);
        $ary_header = [];
        foreach ($tmp_header as $row_data) {
            $tmp = explode(': ', $row_data);
            $key = trim($tmp[0]);
            if ( $key == '' ) {
                continue;
            }
            $val = str_replace($key.': ', '', $row_data);
            $ary_header[$key] = trim($val);
        }
        $log = '[dev]x-line-request-id => ' . $ary_header['x-line-request-id'] . ' | Method => ' . $method . ' | EndPoint => ' . $res_curl['url'] . ' | StatusCode => ' . $res_curl['http_code'] . ' | date => ' . $ary_header['date'];
        file_put_contents(ROOT_DIRECTOR . '/compress_folder/access.log', $log . "\n", FILE_APPEND);
    }

    //分類機能
    function classify_spending() {
        $spending = [
            0 => '未設定',
            1 => '食料費',
            2 => '生活費',
            3 => '衣服費',
            4 => '美健費',
            5 => '交際費',
            6 => '交通費',
            7 => '娯楽費',
            8 => '医療費',
            9 => '通信費',
            10 => '光熱費',
            11 => '住居費',
            12 => '慶弔費',
            13 => 'その他'
        ];

        return $spending;
    }