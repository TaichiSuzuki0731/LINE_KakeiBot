<?php
    include('line_info.php');
    include('function.inc.php');

    //メッセージの送信
    function sending_messages($accessToken, $replyToken, $message_type, $return_message_text){
        //レスポンスフォーマット
        $response_format_text = [
            "type" => $message_type,
            "text" => $return_message_text
        ];

        //ポストデータ
        $post_data = [
            "replyToken" => $replyToken,
            "messages" => [$response_format_text]
        ];

        //curl実行
        $ch = curl_init("https://api.line.me/v2/bot/message/reply");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charser=UTF-8',
            'Authorization: Bearer ' . $accessToken
        ));
        $result = curl_exec($ch);
        curl_close($ch);
    }

    //処理開始
    //ユーザーからのメッセージ取得
    $json_string = file_get_contents('php://input');
    $json_object = json_decode($json_string);

    //file_put_contents('sam.json', $json_string, FILE_APPEND); //debug

    //取得データ
    $replyToken   = h($json_object->{"events"}[0]->{"replyToken"});             //返信用トークン
    $message_type = h($json_object->{"events"}[0]->{"message"}->{"type"});    //メッセージタイプ
    $message_id   = h($json_object->{"events"}[0]->{"message"}->{"id"});        //メッセージID
    $message_text = h($json_object->{"events"}[0]->{"message"}->{"text"});    //メッセージ内容
    $timestamp    = h($json_object->{"events"}[0]->{"timestamp"});               //タイムスタンプ
    $ch_type      = h($json_object->{"events"}[0]->{"source"}->{"type"});          //チャンネルのタイプ
    $user_id      = h($json_object->{"events"}[0]->{"source"}->{"userId"});        //user_id
    $group_id     = h($json_object->{"events"}[0]->{"source"}->{"groupId"});      //group_id

    //メッセージタイプが「text」以外のときは何も返さず終了
    if($message_type != "text") exit;

    //db接続
    $db_link = db_connect();

    //支出合計を計算
    $sum_price = 0;
    $sql = 'SELECT price FROM kakeibo';
    // クエリの実行
    $res = mysqli_query($db_link, $sql);
    if ($res != false) {
        while ($row = mysqli_fetch_assoc($res)) {
            $sum_price = $sum_price + $row['price'];
        }
    }

    $insert_flag = false;

    //返信メッセージ
    if ($message_text == 'いくら？') {
        if ($res != false) {
            $return_message_text = '今月の支出は' . $sum_price . '円です';
        } else {
            $return_message_text = 'DB_Error_1';
        } 
    } else if (strpos($message_text,'@') !== false) {
        //@,-,1~9のみをTRUE
        if (preg_match("/^[-@0-9]+$/", $message_text)) {
            //-の位置が[1]かfalseとなる場合のみTRUE
            $mb_str = mb_strpos($message_text, '-');
            if ($mb_str == 1 || $mb_str == false) {
                $insert_flag = true;
            }
            //-が1個以下のみTRUE
            if (substr_count($message_text, '-') > 1) {
                $insert_flag = false;
            }
            if ($insert_flag) {
                $message_text = h(str_replace('@', '', $message_text));
                $sql = sprintf("INSERT INTO kakeibo (id, groupId, message_id, price, ch_type, time_stamp) VALUES ('%s', '%s', '%s', '%s', '%s', '%s')",
                    mysqli_real_escape_string($db_link, $user_id),
                    mysqli_real_escape_string($db_link, $group_id),
                    mysqli_real_escape_string($db_link, $message_id),
                    mysqli_real_escape_string($db_link, $message_text),
                    mysqli_real_escape_string($db_link, $ch_type),
                    mysqli_real_escape_string($db_link, $timestamp)
                );
                // クエリの実行
                $res = mysqli_query($db_link, $sql);
                if ($res) {
                    $sum_price = $sum_price + $message_text;
                    $return_message_text = 'DBに格納しました。今月の支出合計は' . $sum_price . '円となります';
                } else {
                    $return_message_text = 'DB_Error_2';
                }
    
            } else {
                $return_message_text = '「-」の位置は@の次です。また、-は2回以上は使えません';
            }
        } else {
            $return_message_text = '支出入力時に使える文字は「@,-」と半角数字です';
        }
    } else {
        $return_message_text = '支出がいくらか知りたい場合は「いくら？」と聞いてください。新たな支出の登録は「@1000」のように半角英数字の前に@をつくて送ってくださると嬉しいです。修正したい場合は「@-1000」のように@の後ろに-をつけてください';
    }

    // DBとの接続解除
    mysqli_close($db_link);

    //返信実行
    sending_messages(LINE_CHANNEL_ACCESS_TOKEN, $replyToken, $message_type, $return_message_text);
