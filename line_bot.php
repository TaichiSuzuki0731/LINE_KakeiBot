<?php
    include('line_api_info.php'); //LINE_API情報
    include('line_info.php'); //LINE_APIに接続する際に必要な情報
    include('function.inc.php'); //共通関数群

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
        $log = '[pro]x-line-request-id => ' . $ary_header['x-line-request-id'] . ' | Method => ' . $method . ' | EndPoint => ' . $res_curl['url'] . ' | StatusCode => ' . $res_curl['http_code'] . ' | date => ' . $ary_header['date'];
        file_put_contents('access.log', $log . "\n", FILE_APPEND);
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
            'Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN
        ));
        $result = curl_exec($ch);
        $res_curl = curl_getinfo($ch);
        curl_close($ch);

        //MessageAPIのレスポンスを記録
        receipt_curl_response($result, $res_curl, 'POST');
    }

    //著名確認用の関数
    function check_signagure($str) {
        // ハッシュ作成
        $hash = hash_hmac('sha256', $str, LINE_CHANNEL_SECRET, true);

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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN));

        $response = curl_exec($ch);
        $res_curl = curl_getinfo($ch);
        curl_close($ch);

        //MessageAPIのレスポンスを記録
        receipt_curl_response($response, $res_curl, 'GET');

        $userdata = json_decode($response);

        return $userdata;
    }

    //名前登録
    function insert_name($db_link, $user_id, $user_name) {
        $sql = sprintf("INSERT INTO line_adminuser (id, linename) VALUES ('%s', '%s')",
            mysqli_real_escape_string($db_link, $user_id),
            mysqli_real_escape_string($db_link, $user_name)
        );

        //登録実行
        mysqli_query($db_link, $sql);

        mysqli_close($db_link);
    }

    //ユーザ情報削除
    function del_user_info($db_link, $user_id) {
        $sql = sprintf("DELETE FROM line_adminuser WHERE id = '%s'",
            mysqli_real_escape_string($db_link, $user_id)
        );

        //削除実行
        mysqli_query($db_link, $sql);

        mysqli_close($db_link);
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . LINE_CHANNEL_ACCESS_TOKEN));

        $response = curl_exec($ch);
        $res_curl = curl_getinfo($ch);
        curl_close($ch);

        //MessageAPIのレスポンスを記録
        receipt_curl_response($response, $res_curl, 'GET');

        $userdata = json_decode($response);

        return $userdata;
    }

    //botがグループに参加した時にインサート
    function insert_group_member($db_link, $group_id, $cnt) {
        $sql = sprintf("INSERT INTO group_count_member (groupId, count, modifiy) VALUES ('%s', '%s', now())",
            mysqli_real_escape_string($db_link, $group_id),
            mysqli_real_escape_string($db_link, $cnt)
        );

        //登録実行
        mysqli_query($db_link, $sql);

        mysqli_close($db_link);
    }

    //メンバー数に変更があった際に更新
    function update_group_member($db_link, $group_id, $cnt) {
        $sql = sprintf("UPDATE group_count_member SET count = '%s', modifiy = now() WHERE groupId = '%s'",
            mysqli_real_escape_string($db_link, $cnt),
            mysqli_real_escape_string($db_link, $group_id)
        );

        //登録実行
        mysqli_query($db_link, $sql);

        mysqli_close($db_link);
    }

    //botが退出した時にデリート
    function delete_group_member($db_link, $group_id) {
        $sql = sprintf("DELETE FROM group_count_member WHERE groupId = '%s'",
            mysqli_real_escape_string($db_link, $group_id)
        );

        //登録実行
        mysqli_query($db_link, $sql);

        mysqli_close($db_link);
    }

    //kakeiboデータ識別IDの生成
    function make_hash_id() {
        $str = date("YmdHis") . "." . substr(explode(".", microtime(true))[1], 0, 3);
        $hased_string = hash('crc32', $str);

        return $hased_string;
    }

    //kakeiboテーブルから毎日ごとの集計を取得
    function get_date_price($db_link, $ch_type, $user_id, $group_id) {
        if ($ch_type == 'user') {
            $user_id = mysqli_real_escape_string($db_link, $user_id);
            $sql = "SELECT DATE_FORMAT(insert_time, '%Y/%m/%d') AS date, sum(price) AS sam_price FROM kakeibo where id = '" . $user_id . "' and groupId = '' GROUP BY DATE_FORMAT(insert_time, '%Y%m%d')";
        } else {
            $group_id = mysqli_real_escape_string($db_link, $group_id);
            $sql = "SELECT DATE_FORMAT(insert_time, '%Y/%m/%d') AS date, sum(price) AS sam_price FROM kakeibo where groupId = '" . $group_id . "' GROUP BY DATE_FORMAT(insert_time, '%Y%m%d')";
        }

        $res = mysqli_query($db_link, $sql);

        return $res;
    }

    //Kakeiboテーブルのデータをユーザによる操作で削除
    function del_kakeibo_deta($db_link, $ch_type, $hash_id, $user_id, $group_id) {
        $sql = 'DELETE FROM kakeibo WHERE ';
        if ($ch_type == 'user') {
            $sql .= sprintf("hash_id = '%s' and id = '%s' and groupId = ''",
                mysqli_real_escape_string($db_link, $hash_id),
                mysqli_real_escape_string($db_link, $user_id)
            );
        } else {
            $sql .= sprintf("hash_id = '%s' and groupId = '%s'",
                mysqli_real_escape_string($db_link, $hash_id),
                mysqli_real_escape_string($db_link, $group_id)
            );
        }
        $sql .= ' limit 1';

        $res = mysqli_query($db_link, $sql);

        return $res;
    } 

    //修正用データの抽出
    function get_del_kakeibo($db_link, $ch_type, $user_id, $group_id) {
        $sql = 'SELECT hash_id, price FROM kakeibo WHERE ';
        if ($ch_type == 'user') {
            $sql .= sprintf("id = '%s' and groupId = ''",
                mysqli_real_escape_string($db_link, $user_id)
            );
        } else {
            $sql .= sprintf("groupId = '%s'",
                mysqli_real_escape_string($db_link, $group_id)
            );
        }
        $sql .= " and DATE_FORMAT(insert_time, '%Y%m') = DATE_FORMAT(NOW(), '%Y%m')";

        $res = mysqli_query($db_link, $sql);

        return $res;
    }

    //処理開始

    //Lineサーバに200を返す
    $response_code = http_response_code(200);

    //Webhook受信時のログ
    $protocol = empty($_SERVER["HTTPS"]) ? "http://" : "https://";
    $thisurl = $protocol . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    $access_log = '[pro]AccessLog => ' . $_SERVER["REMOTE_ADDR"] . ' | Method => ' . $_SERVER['REQUEST_METHOD'] . ' | RequestPath => ' . $thisurl . ' | StatusCode => ' . $response_code . ' | time => ' . date("Y/m/d H:i:s");
    file_put_contents('access.log', $access_log . "\n", FILE_APPEND);

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
    }

    //グループ or トークルームに参加した際はjoin,メンバーが参加した際はmemberJoined 検知した時にメンバー数をカウントする
    if ($event_type == 'join' || $event_type == 'memberJoined' || $event_type == 'leave' || $event_type == 'memberLeft') {
        $get_number_people = count_group_member($ch_type, $group_id);
        if ($get_number_people != '') {
            $cnt = $get_number_people->{"count"};
            if ($event_type == 'join') { //グループにbotが参加
                insert_group_member($db_link, $group_id, $cnt);
            } elseif ($event_type == 'memberJoined' || $event_type == 'memberLeft') { //グループでメンバーが参加 or 退出
                update_group_member($db_link, $group_id, $cnt);
            } else { //グループからbot退出
                delete_group_member($db_link, $group_id);
            }
        }
    }

    //メッセージタイプが「text」以外のときは何も返さず終了
    if ($message_type != "text") {
        // DBとの接続解除
        mysqli_close($db_link);
        exit();
    }

    //全角英数字->半角英数字
    $message_text = mb_convert_kana($message_text, 'n');
    //[ー]=>[-]に変換
    $message_text = str_replace('ー', '-', $message_text);
    //[＠]=>[@]に変換
    $message_text = str_replace('＠', '@', $message_text);

    //ユーザ情報取得
    $sql = sprintf("SELECT linename FROM line_adminuser WHERE id = '%s'",
        mysqli_real_escape_string($db_link, $user_id)
    );
    $res = mysqli_query($db_link, $sql);
    $row = mysqli_fetch_assoc($res);
    if (count($row['linename']) == 0) { //フォローされてない
        $follow_flag = false;
        $line_name = 'ゲスト';
    } else { //フォローされている
        $follow_flag = true;
        $line_name = $row['linename'];
    }

    //グループ or トークルームの場合は人数を取得
    $cnt_member = 0;
    if ($ch_type == 'group' || $ch_type == 'room') {
        $sql = sprintf("SELECT count FROM group_count_member WHERE groupId = '%s'",
            mysqli_real_escape_string($db_link, $group_id)
        );

        $res = mysqli_query($db_link, $sql);
        $row = mysqli_fetch_assoc($res);
        $cnt_member = $row['count'];
    }

    //支出合計を計算
    $sum_price = 0;
    $sql = 'SELECT price FROM kakeibo WHERE ';
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
    // クエリの実行
    $res = mysqli_query($db_link, $sql);
    if ($res != false) {
        while ($row = mysqli_fetch_assoc($res)) {
            $sum_price = $sum_price + $row['price'];
        }
    }

    $insert_flag = false;
    $del_flag = false;

    //返信メッセージ
    if ($message_text == 'いくら') {
        if ($res != false) {
            $return_message_text = '今月の支出は' . $sum_price . '円ニャ';
            if ($cnt_member > 0) {
                $return_message_text .= "\n一人あたり" . number_format($sum_price / $cnt_member, 2) . '円ニャ';
            }
        } else {
            $return_message_text = 'DB_Error_1';
        }
    } elseif ($message_text == 'くわしく') {
        //毎日ごとの金額を集計
        $res = get_date_price($db_link, $ch_type, $user_id, $group_id);
        if ($res != false) {
            while ($row = mysqli_fetch_assoc($res)) {
                $return_message_text .= $row['date'];
                $return_message_text .= '=>¥';
                $return_message_text .= $row['sam_price'];
                $return_message_text .= "\n";
            }
            $return_message_text = substr($return_message_text, 0, -1);
        } else {
            $return_message_text = 'DB_Error_4';
        }
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
                $sql = sprintf("INSERT INTO kakeibo (hash_id, id, groupId, price, ch_type) VALUES ('%s', '%s', '%s', '%s', '%s')",
                    make_hash_id(),
                    mysqli_real_escape_string($db_link, $user_id),
                    mysqli_real_escape_string($db_link, $group_id),
                    mysqli_real_escape_string($db_link, $message_text),
                    mysqli_real_escape_string($db_link, $ch_type)
                );
                // クエリの実行
                $res = mysqli_query($db_link, $sql);
                if ($res) {
                    $sum_price = $sum_price + $message_text;
                    $return_message_text = "記録しましたニャ\n今月の支出合計は" . $sum_price . "円となりますニャ";
                    if ($cnt_member > 0) {
                        $return_message_text .= "\n一人あたり" . number_format($sum_price / $cnt_member, 2) . '円ニャ';
                    }
                } else {
                    $return_message_text = 'DB_Error_2';
                }
            } else {
                $return_message_text = "「-(ハイフン)」の位置は先頭のみニャ\nまた、-は2回以上は使えませんにゃ〜〜";
            }
        } else { //未フォロー記録不可
            $return_message_text = "友達登録がされていませんにゃ〜〜\nKakeiBotとととととと友達になってくださいニャ、、、。";
        }
    } elseif ($message_text == '修正') {
        $res = get_del_kakeibo($db_link, $ch_type, $user_id, $group_id);
        $return_message_text = "消したい家計簿データの【】内の文字列を@の後につけて送信してくださいニャ〜\n\n";
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $return_message_text .= '【' . $row['hash_id'] . '】¥' . $row['price'] . "\n";
            }
            $return_message_text = substr($return_message_text, 0, -1);
        } else {
            $return_message_text = 'DB_Error_5';
        }
    } elseif (strpos($message_text, '@') !== false) {
        //@の位置が[0]のみ
        $mb_str = mb_strpos($message_text, '@');
        if ($mb_str === 0) {
            $del_flag = true;
        }
        //@が1個以下のみTRUE
        if (substr_count($message_text, '@') > 1) {
            $del_flag = false;
        }
        if ($del_flag) {
            $message_text = (str_replace('@', '', $message_text));
            $res = del_kakeibo_deta($db_link, $ch_type, $message_text, $user_id, $group_id);
            if ($res) {
                $return_message_text = $message_text . "を削除したニャ";
            } else {
                $return_message_text = $hash_id . 'DB_Error_3';
            }
        } else {
            $return_message_text = "「@」の位置は先頭のみニャ\nまた、@は2回以上は使えませんにゃ〜〜";
        }
    } elseif ($message_text == 'お-い') {
        $return_message_text = <<<EOT
・支出がいくらか知りたい場合は「いくら」と聞いてくださいニャ

・新たな支出の登録は「1000」と入力して送ってくださると嬉しいニャ

・修正したい場合は「-1000」のように数字の前に「-(ハイフン)」を入力して送ってくださいニャ。*支出の記録は友達登録していただいている方のみが可能ニャ。他にも「修正」と送ってくれると入力自体を消すことも可能にゃ

・グループやトークルームで使った場合は、そのチャンネル内での合計支出を出せますニャ。またグループ内のメンバー数で割った一人当たりの支出も出力されますニャ

・「くわしく」と送ると毎日毎の支出が確認できますニャ
EOT;
    } else {
        exit();
    }

    // DBとの接続解除
    mysqli_close($db_link);

    $line_name = $line_name != '' ? $line_name . "さん\n" : "";
    $text = $line_name . $return_message_text;

    //返信実行
    sending_messages($replyToken, $message_type, $text);
