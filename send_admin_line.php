<?php
    include('line_api_info.php'); //LINE_API情報
    include('line_info.php'); //LINE_APIに接続する際に必要な情報
    include('function.inc.php'); //共通関数群

    //特定のユーザにメッセージを送る
    function post_messages($message) {
        // リクエストヘッダ
        $header = [
            'Authorization: Bearer ' . DEV_LINE_CHANNEL_ACCESS_TOKEN,
            'Content-Type: application/json'
        ];

        // 送信するメッセージの下準備
        $post_values[] = 
            [
                "type" => "text",
                "text" => $message
            ];
        // 送信するデータ
        $post_data = [
            "to" => DEV_LINE_PRIVATE_ID,
            "messages" => $post_values
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, LINE_PUSH_URL);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post_data));
        $result = curl_exec($curl);
        curl_close($curl);
    }