<?php
    include('line_api_info.php'); //LINE_API情報
    include('function.inc.php'); //共通関数群
    include(ROOT_DIRECTOR . '/line_info/line_info.php'); //LINE_APIに接続する際に必要な情報
    include('line_bot.inc.php');

    //curl実行
    function exec_curl ($ch) {
        $res = [];

        $res['result'] = curl_exec($ch);
        $res['getinfo'] = curl_getinfo($ch);
        curl_close($ch);

        return $res;
    }

    //Webhook受信時のログ
    function receipt_webhook_request($response_code, $server_info) {
        $protocol = empty($server_info["HTTPS"]) ? "http://" : "https://";
        $thisurl = $protocol . $server_info["HTTP_HOST"] . $server_info["REQUEST_URI"];
        $access_log = '[dev]AccessLog => ' . $server_info["REMOTE_ADDR"] . ' | Method => ' . $server_info['REQUEST_METHOD'] . ' | RequestPath => ' . $thisurl . ' | StatusCode => ' . $response_code . ' | time => ' . date("Y/m/d H:i:s");
        file_put_contents(ROOT_DIRECTOR . '/compress_folder/access.log', $access_log . "\n", FILE_APPEND);
    }

    //メッセージの送信
    function sending_messages($replyToken, $message_type, $return_message_text){
        //レスポンスフォーマット
        $response_format_text = [
            "type" => $message_type,
            "text" => $return_message_text
        ];

        //ポストデータ
        $post_data["replyToken"] = $replyToken;
        $post_data["messages"] = [
            $response_format_text
        ];

        //curl実行
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, LINE_REPLY_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charser=UTF-8',
            'Authorization: Bearer ' . DEV_LINE_CHANNEL_ACCESS_TOKEN
        ));

        $res = exec_curl($ch);

        @receipt_curl_response($res['result'], $res['getinfo'], 'POST');
        exit();
    }

    // 支出分類Flexメッセージ送信
    function send_fles_message($send_json, $replyToken){
        $send_array = json_decode($send_json);

        //ポストデータ
        $post_data["replyToken"] = $replyToken;
        $post_data["messages"] = [
            $send_array
        ];

        //curl実行
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, LINE_REPLY_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charser=UTF-8',
            'Authorization: Bearer ' . DEV_LINE_CHANNEL_ACCESS_TOKEN
        ));
        $res = exec_curl($ch);

        @receipt_curl_response($res['result'], $res['getinfo'], 'POST');
        exit();
    }

    //著名確認用の関数
    function check_signagure($str) {
        // ハッシュ作成
        $hash = hash_hmac('sha256', $str, DEV_LINE_CHANNEL_SECRET, true);

        // Signature作成
        $sig = base64_encode($hash);

        return $sig;
    }

    //ユーザ情報を取得
    function get_line_user_profile($user_id) {
        $url = 'https://api.line.me/v2/bot/profile/' . $user_id;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . DEV_LINE_CHANNEL_ACCESS_TOKEN));

        $res = exec_curl($ch);

        //MessageAPIのレスポンスを記録
        @receipt_curl_response($res['result'], $res['getinfo'], 'GET');

        //レスポンスからbodyを取り出す
        $response = substr($res['result'], $res['getinfo']['header_size']);

        $userdata = json_decode($response);
        return $userdata;
    }

    //名前登録
    function insert_name($db_link, $user_id, $user_name) {
        $sql = sprintf("INSERT INTO dev_line_adminuser (id, linename) VALUES ('%s', '%s')",
            mysqli_real_escape_string($db_link, $user_id),
            mysqli_real_escape_string($db_link, $user_name)
        );

        //登録実行
        mysqli_query($db_link, $sql);
    }

    //ユーザ情報削除
    function del_user_info($db_link, $user_id) {
        $sql = sprintf("DELETE FROM dev_line_adminuser WHERE id = '%s'",
            mysqli_real_escape_string($db_link, $user_id)
        );

        //削除実行
        mysqli_query($db_link, $sql);
    }

    //メンバーをカウント
    function count_group_member($ch_type, $group_id) {
        $userdata = '';

        if ($ch_type == 'group') {
            $url = 'https://api.line.me/v2/bot/group/' . $group_id . '/members/count';
        } elseif ($ch_type == 'room') {
            $url = 'https://api.line.me/v2/bot/room/' . $group_id . '/members/count';
        } else {
            return $userdata;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . DEV_LINE_CHANNEL_ACCESS_TOKEN));

        $res = exec_curl($ch);

        //MessageAPIのレスポンスを記録
        @receipt_curl_response($res['result'], $res['getinfo'], 'GET');

        //レスポンスからbodyを取り出す
        $response = substr($res['result'], $res['getinfo']['header_size']);

        $userdata = json_decode($response);

        return $userdata;
    }

    //botがグループに参加した時にインサート
    function insert_group_member($db_link, $group_id, $cnt) {
        $sql = sprintf("INSERT INTO dev_group_count_member (groupId, count, modifiy) VALUES ('%s', '%s', now())",
            mysqli_real_escape_string($db_link, $group_id),
            mysqli_real_escape_string($db_link, $cnt)
        );

        //登録実行
        mysqli_query($db_link, $sql);
    }

    //メンバー数に変更があった際に更新
    function update_group_member($db_link, $group_id, $cnt) {
        $sql = sprintf("UPDATE dev_group_count_member SET count = '%s', modifiy = now() WHERE groupId = '%s'",
            mysqli_real_escape_string($db_link, $cnt),
            mysqli_real_escape_string($db_link, $group_id)
        );

        //登録実行
        mysqli_query($db_link, $sql);
    }

    //botが退出した時にデリート
    function delete_group_member($db_link, $group_id) {
        $sql = sprintf("DELETE FROM dev_group_count_member WHERE groupId = '%s'",
            mysqli_real_escape_string($db_link, $group_id)
        );

        //登録実行
        mysqli_query($db_link, $sql);
    }

    //kakeiboテーブルから毎日ごとの集計を取得
    function get_date_price($db_link, $ch_type, $user_id, $group_id) {
        $sql = "SELECT DATE_FORMAT(insert_time, '%Y/%m/%d') AS date, sum(price) AS sam_price FROM dev_kakeibo where ";
        if ($ch_type == 'user') {
            $user_id = mysqli_real_escape_string($db_link, $user_id);
            $sql .= "id = '" . $user_id . "' and groupId = ''";
        } else {
            $group_id = mysqli_real_escape_string($db_link, $group_id);
            $sql .= "groupId = '" . $group_id . "'";
        }
        $sql .= " and DATE_FORMAT(insert_time, '%Y%m') = DATE_FORMAT(NOW(), '%Y%m') GROUP BY DATE_FORMAT(insert_time, '%Y%m%d')";

        $res = mysqli_query($db_link, $sql);

        return $res;
    }

    //kakeiboテーブルから分類ごとの集計を取得
    function get_classify_price($db_link, $ch_type, $user_id, $group_id) {
        $sql = "SELECT classify_id, sum(price) AS sam_price FROM dev_kakeibo where ";
        if ($ch_type == 'user') {
            $sql .= sprintf("id = '%s' and ch_type = 'user'",
                mysqli_real_escape_string($db_link, $user_id)
            );
        } else {
            $sql .= sprintf("groupId = '%s'",
                mysqli_real_escape_string($db_link, $group_id)
            );
        }
        $sql .= " and DATE_FORMAT(insert_time, '%Y%m') = DATE_FORMAT(NOW(), '%Y%m') group by classify_id";

        $res = mysqli_query($db_link, $sql);

        return $res;
    }

    //kakeiboテーブルから毎月ごとの集計
    function get_monthly_price($db_link, $ch_type, $user_id, $group_id) {
        $sql = "SELECT DATE_FORMAT(insert_time, '%Y/%m') AS monthly, sum(price) AS sam_price FROM dev_kakeibo where ";
        if ($ch_type == 'user') {
            $sql .= sprintf("id = '%s' AND groupId = '' ",
                mysqli_real_escape_string($db_link, $user_id)
            );
        } else {
            $sql .= sprintf("groupId = '%s' ",
                mysqli_real_escape_string($db_link, $group_id)
            );
        }
        $sql .= "GROUP BY DATE_FORMAT(insert_time, '%Y%m')";

        $res = mysqli_query($db_link, $sql);

        return $res;
    }

    //ユーザがフォロー外した時にKakeiboテーブルのデータを全削除
    function del_kakeibo_all_deta($db_link, $ch_type, $user_id, $group_id) {
        $sql = 'DELETE FROM dev_kakeibo WHERE ';
        if ($ch_type == 'user') {
            $sql .= sprintf("id = '%s' and groupId = ''",
                mysqli_real_escape_string($db_link, $user_id)
            );
        } else {
            $sql .= sprintf("groupId = '%s'",
                mysqli_real_escape_string($db_link, $group_id)
            );
        }

        mysqli_query($db_link, $sql);
    }

    //kakeiboテーブルにデータをインサート
    function insert_kakeibo($db_link, $message_id, $user_id, $group_id, $message_text, $ch_type) {
        $sql = sprintf("INSERT INTO dev_kakeibo (message_id, id, groupId, price, ch_type) VALUES ('%s', '%s', '%s', '%s', '%s')",
            mysqli_real_escape_string($db_link, $message_id),
            mysqli_real_escape_string($db_link, $user_id),
            mysqli_real_escape_string($db_link, $group_id),
            mysqli_real_escape_string($db_link, $message_text),
            mysqli_real_escape_string($db_link, $ch_type)
        );

        // クエリの実行
        $res = mysqli_query($db_link, $sql);

        return $res;
    }

    //kakeiboテーブルの日付の一番新しいデータのclassify_idを更新
    function update_kakeibo_classify($db_link, $user_id, $group_id, $message_text, $ch_type) {
        if ($ch_type == 'user') {
            $sql = sprintf("UPDATE dev_kakeibo SET classify_id = '%s' WHERE id = '%s' AND groupId = '' ORDER BY insert_time DESC Limit 1",
                mysqli_real_escape_string($db_link, $message_text),
                mysqli_real_escape_string($db_link, $user_id)
            );
        } else {
            $sql = sprintf("UPDATE dev_kakeibo SET classify_id = '%s' WHERE id = '%s' AND groupId = '%s' ORDER BY insert_time DESC Limit 1",
                mysqli_real_escape_string($db_link, $message_text),
                mysqli_real_escape_string($db_link, $user_id),
                mysqli_real_escape_string($db_link, $group_id)
            );
        }
        // クエリの実行
        $res = mysqli_query($db_link, $sql);

        return $res;
    }

    //支出合計を計算
    function sum_kakeibo_price($db_link, $ch_type, $group_id, $user_id) {
        $sum_price = 0;
        $sql = 'SELECT price FROM dev_kakeibo WHERE ';
        //グループ会計
        if ($ch_type == 'group' || $ch_type == 'room') {
            $sql .= sprintf("groupId = '%s'",
                mysqli_real_escape_string($db_link, $group_id)
            );
        } else { //個人会計
            $sql .= sprintf("id = '%s' and ch_type = 'user'",
                mysqli_real_escape_string($db_link, $user_id)
            );
        }
        $sql .= " and DATE_FORMAT(insert_time, '%Y%m') = DATE_FORMAT(NOW(), '%Y%m')";

        // クエリの実行
        $res = mysqli_query($db_link, $sql);
        if ($res != false) {
            while ($row = mysqli_fetch_assoc($res)) {
                $sum_price = $sum_price + $row['price'];
            }
        }

        return $sum_price;
    }

    //グループ or トークルームの場合は人数を取得
    function count_groupa_member($db_link, $ch_type, $group_id) {
        $sql = sprintf("SELECT count FROM dev_group_count_member WHERE groupId = '%s'",
            mysqli_real_escape_string($db_link, $group_id)
        );

        $res = mysqli_query($db_link, $sql);
        $row = mysqli_fetch_assoc($res);

        return $row['count'];
    }

    //ユーザネーム取得
    function get_user_name($db_link, $user_id) {
        $sql = sprintf("SELECT linename FROM dev_line_adminuser WHERE id = '%s'",
            mysqli_real_escape_string($db_link, $user_id)
        );
        $res = mysqli_query($db_link, $sql);
        $row = mysqli_fetch_assoc($res);

        return $row['linename'];
    }

    //Lineグループ名を取得
    function get_group_name($group_id) {
        $url = 'https://api.line.me/v2/bot/group/' . $group_id . '/summary';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . DEV_LINE_CHANNEL_ACCESS_TOKEN));

        $res = exec_curl($ch);

        //MessageAPIのレスポンスを記録
        @receipt_curl_response($res['result'], $res['getinfo'], 'GET');

        //レスポンスからbodyを取り出す
        $response = substr($res['result'], $res['getinfo']['header_size']);

        $userdata = json_decode($response);
        return $userdata;
    }

    //botがグループに参加した際にグループ名を登録
    function insert_group_name($db_link, $group_id, $group_name) {
        $sql = sprintf("INSERT INTO dev_group_name (groupId, group_name) VALUES ('%s', '%s')",
            mysqli_real_escape_string($db_link, $group_id),
            mysqli_real_escape_string($db_link, $group_name)
        );

        //登録実行
        mysqli_query($db_link, $sql);
    }

    //botがグループから退出時した際にデータを削除
    function delete_group_name($db_link, $group_id) {
        $sql = sprintf("DELETE FROM dev_group_name WHERE groupId = '%s' limit 1",
            mysqli_real_escape_string($db_link, $group_id)
        );

        //登録実行
        mysqli_query($db_link, $sql);
    }

    //データベースエラー時のメッセージ送信
    function send_db_error($error_code, $replyToken, $message_type) {
        $return_message_text = 'ErrorCode:' . $error_code . '管理者エラーコードを教えてくださいにゃ';
        mysqli_close($db_link);
        sending_messages($replyToken, $message_type, $return_message_text);
    }

    //処理開始
    date_default_timezone_set('Asia/Tokyo');
    $now_time = date("Y-m-d G:i:s");
    //↓メンテナンス時間を設定
    $end_maintenance_time   = '2022-02-26 16:00:00'; //YYYY-mm-dd GG:ii:ss

    //Lineサーバに200を返す
    $response_code = http_response_code(200);

    //Webhook受信時のログ
    $server_info = $_SERVER;
    receipt_webhook_request($response_code, $server_info);

    //ユーザーからのメッセージ取得
    $json_string = file_get_contents('php://input');

    // HTTPヘッダーを取得
    $headers = getallheaders();

    // HTTPヘッダーから、署名検証用データを取得
    $headerSignature = $headers["X-Line-Signature"];
    //著名の確認
    $sig = check_signagure($json_string);
    // 確認
    if ($sig != $headerSignature) {
        exit();
    }

    //jsonデコード
    $json_object = json_decode($json_string);

    //取得データを変数に格納
    $event_type   = h($json_object->{"events"}[0]->{"type"});                   //イベントタイプ
    $replyToken   = h($json_object->{"events"}[0]->{"replyToken"});             //返信用トークン
    $message_id   = h($json_object->{"events"}[0]->{"message"}->{"id"});        //メッセージID
    $message_type = h($json_object->{"events"}[0]->{"message"}->{"type"});      //メッセージタイプ
    $message_text = h($json_object->{"events"}[0]->{"message"}->{"text"});      //メッセージ内容
    $ch_type      = h($json_object->{"events"}[0]->{"source"}->{"type"});       //チャンネルのタイプ
    $user_id      = h($json_object->{"events"}[0]->{"source"}->{"userId"});     //user_id
    $group_id     = h($json_object->{"events"}[0]->{"source"}->{"groupId"});    //group_id

    //db接続
    $db_link = db_connect();

    //ユーザ登録
    if ($event_type == 'follow') {
        $user_name = get_line_user_profile($user_id); //Lineの名前を取得
        insert_name($db_link, $user_id, $user_name->{"displayName"});
    }

    //ユーザ情報削除
    if ($event_type == 'unfollow') {
        del_user_info($db_link, $user_id);
        del_kakeibo_all_deta($db_link, $ch_type, $user_id, $group_id);
    }

    //グループ or トークルームに参加した際はjoin,メンバーが参加した際はmemberJoined 検知した時にメンバー数をカウントする
    if ($event_type == 'join' || $event_type == 'memberJoined' || $event_type == 'leave' || $event_type == 'memberLeft') {
        $get_number_people = count_group_member($ch_type, $group_id);
        $cnt = $get_number_people->{"count"};
        if ($event_type == 'join') { //グループにbotが参加
            insert_group_member($db_link, $group_id, $cnt);
            $group_name = get_group_name($group_id);
            insert_group_name($db_link, $group_id, $group_name->{"groupName"});
        } elseif ($event_type == 'memberJoined' || $event_type == 'memberLeft') { //グループでメンバーが参加 or 退出
            update_group_member($db_link, $group_id, $cnt);
        } else { //グループからbot退出
            delete_group_member($db_link, $group_id);
            del_kakeibo_all_deta($db_link, $ch_type, $user_id, $group_id);
            delete_group_name($db_link, $group_id);
        }
    }

    //メッセージタイプが「text」以外のときは何も返さず終了
    if ($message_type != "text") {
        // DBとの接続解除
        mysqli_close($db_link);
        exit();
    }

    //改行削除
    $message_text = str_replace(array("\r\n", "\r", "\n"), '', $message_text);
    //全角数字->半角数字
    $message_text = mb_convert_kana($message_text, 'n');
    //[ー]=>[-]に変換
    $message_text = str_replace('ー', '-', $message_text);
    //先頭語尾空白があった際に削除
    $message_text = trim($message_text);

    //ユーザネーム取得
    $name = get_user_name($db_link, $user_id);
    if (count($name) == 0) { //フォローされてない
        $follow_flag = false;
        $line_name = "ゲストさん\n";
    } else { //フォローされている
        $follow_flag = true;
        $line_name = $name . "さん\n";
    }

    //メンテナンス時間
    if ($now_time < $end_maintenance_time) {
        if ($user_id != LINE_PRIVATE_ID && $user_id != DEV_LINE_PRIVATE_ID) {
            $return_message_text = $end_maintenance_time . "まで\nメンテナンス中にゃー🐱";
            mysqli_close($db_link);
            sending_messages($replyToken, $message_type, $line_name . $return_message_text);
        }
    }

    //グループ or トークルームの場合は人数を取得
    $cnt_member = 0;
    if ($ch_type == 'group' || $ch_type == 'room') {
        $cnt_member = count_groupa_member($db_link, $ch_type, $group_id);
    }

    $insert_flag = false;
    $del_flag = false;
    $upd_flag = false;

    //返信メッセージ
    if ($message_text == 'いくら' || $message_text == '幾ら') {
        //支出合計を計算
        $sum_price = sum_kakeibo_price($db_link, $ch_type, $group_id, $user_id);
        $return_message_text = '今月の支出は' . $sum_price . '円ニャ';
        if ($cnt_member > 1) {
            $return_message_text .= "\n一人あたり" . number_format($sum_price / $cnt_member, 2) . '円ニャ';
        }
    } elseif ($message_text == 'くわしく' || $message_text == '詳しく') {
        $path = ROOT_DIRECTOR . '/json/output_detail_spending.json';
        $json = file_get_contents($path);
        $base_json = '{
            "type": "text",
            "text": "%s",
            "size": "lg"
            },';
        //毎月ごとの金額を集計
        $res = get_monthly_price($db_link, $ch_type, $user_id, $group_id);
        if (!$res) {
            send_db_error(7, $replyToken, $message_type);
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $text = $row['monthly'];
            $text .= ' => ¥';
            $text .= $row['sam_price'];
            $add_json3 .= sprintf($base_json, $text);
        }

        //毎日ごとの金額を集計
        $res = get_date_price($db_link, $ch_type, $user_id, $group_id);
        if (!$res) {
            send_db_error(1, $replyToken, $message_type);
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $text = $row['date'];
            $text .= ' => ¥';
            $text .= $row['sam_price'];
            $add_json .= sprintf($base_json, $text);
        }

        //分類ごとの金額を集計
        $res = get_classify_price($db_link, $ch_type, $user_id, $group_id);
        if (!$res) {
            send_db_error(2, $replyToken, $message_type);
        }

        $spending_array = classify_spending();
        while ($row = mysqli_fetch_assoc($res)) {
            $spending_num = $row['classify_id'];
            $text = $spending_array[$spending_num];
            $text .= ' => ¥';
            $text .= $row['sam_price'];
            $add_json2 .= sprintf($base_json, $text);
        }

        $json = sprintf($json, $add_json3, $add_json, $add_json2);
        mysqli_close($db_link);
        send_fles_message($json, $replyToken);
    } elseif (preg_match("/^[-0-9]+$/", $message_text)) { //-,1~9のみをTRUE
        if ($follow_flag) { //フォロー済み記録可
            //-の位置が[0]かfalseとなる場合のみTRUE
            $mb_str = mb_strpos($message_text, '-');
            if ($mb_str === 0 || $mb_str === false) {
                $insert_flag = true;
            }
            //-が1個以下のみTRUE
            if (substr_count($message_text, '-') > 1) {
                $insert_flag = false;
            }
            if ($insert_flag) {
                insert_kakeibo($db_link, $message_id, $user_id, $group_id, $message_text, $ch_type);
                $path = ROOT_DIRECTOR . '/json/classification.json';
                $send_json = file_get_contents($path);
                mysqli_close($db_link);
                send_fles_message($send_json, $replyToken);
            } else {
                $return_message_text = "「-(ハイフン)」の位置は先頭のみニャ\nまた、-は2回以上は使えませんにゃ〜〜";
            }
        } else { //未フォロー記録不可
            $return_message_text = "友達登録がされていませんにゃ〜〜\nKakeiBotとととととと友達になってくださいニャ、、、。";
        }
    } elseif ($message_text == 'しゅうせい' || $message_text == '修正') {
        $return_message_text = "↓URLから修正ページに移動して修正してくださいニャ〜\n\n";
        $return_message_text .=  "https://st0731-dev-srv.moo.jp/dev_index.php";
    } elseif (strpos($message_text, '!') !== false) {
        $message_text = str_replace('!', '', $message_text);
        if ($follow_flag) {
            $res = update_kakeibo_classify($db_link, $user_id, $group_id, $message_text, $ch_type);
            if (!$res) {
                send_db_error(6, $replyToken, $message_type);
            }
        } else { //未フォロー記録不可
            $return_message_text = "友達登録がされていませんにゃ〜〜\nKakeiBotとととととと友達になってくださいニャ、、、。";
            mysqli_close($db_link);
            sending_messages($replyToken, $message_type, $line_name . $return_message_text);

        }

        $spending_array = classify_spending();
        $return_message_text = $spending_array[$message_text] . "に分類したにゃ\n\n";
        $sum_price = sum_kakeibo_price($db_link, $ch_type, $group_id, $user_id);
        $return_message_text .= '今月の支出は' . $sum_price . '円ニャ';
        if ($cnt_member > 1) {
            $return_message_text .= "\n一人あたり" . number_format($sum_price / $cnt_member, 2) . '円ニャ';
        }
    } elseif ($message_text == 'お-い') {
        $return_message_text = <<<EOT
・支出がいくらか知りたい場合は「いくら」と聞いてくださいニャ

・新たな支出の登録は「1000」,「-1000」と入力して送ってくださると嬉しいニャ。その後に支出分類を聞かれるから答えて欲しいニャ。*支出の記録は友達登録していただいている方のみが可能ニャ。

・他にも「修正」と送ってくれると入力自体を消したり、支出分類を修正出来るにゃ。

・グループやトークルームで使った場合は、そのチャンネル内での合計支出を出せますニャ。またグループ内のメンバー数で割った一人当たりの支出も出力されますニャ

・「くわしく」と送ると毎日毎の支出が確認できますニャ

・友達登録を解除するデータが全部消えるから気をつけるにゃ🐱
EOT;
    } else {
        exit();
    }

    // DBとの接続解除
    mysqli_close($db_link);

    //返信実行
    sending_messages($replyToken, $message_type, $line_name . $return_message_text);

    //file_put_contents('sam.txt', serialize($res) . "\n", FILE_APPEND); //debug