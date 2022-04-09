<?php
    require('function.inc.php'); //共通関数群
    require('line_api_info.php'); //LINE_API情報
    require(ROOT_DIRECTOR . '/line_info/line_info.php'); //LINE_APIに接続する際に必要な情報
    require('line_bot.inc.php');

    if (!session_id()) {
        session_start();
    }

    $delete_result = '';

    $db_link = db_connect();

    //初回ログイン時
    if ($_SESSION['access_token'] != '') {
        $url = LINE_LOGIN_V2_PROFILE;
        $access_token = h($_SESSION['access_token']);
        unset($_SESSION['access_token']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $access_token
        ));

        $res['result'] = curl_exec($ch);
        $res['getinfo'] = curl_getinfo($ch);
        curl_close($ch);

        //MessageAPIのレスポンスを記録
        @receipt_curl_response($res['result'], $res['getinfo'], 'GET');

        //レスポンスからbodyを取り出す
        $response = substr($res['result'], $res['getinfo']['header_size']);

        $userdata = json_decode($response);

        $user_id = h($userdata->{'userId'});
        $user_name = h($userdata->{'displayName'});

    } elseif ($_SESSION['user_id'] != '') { //回遊時
        $user_id = h($_SESSION['user_id']);
        $user_name = h($_SESSION['user_name']);
        if ($_GET['modify'] != '') {
            $message_id = h($_GET['modify']);
        }
        if ($_GET['delete'] != '') {
            $message_id = h($_GET['delete']);
            //論理削除
            $sql = sprintf("UPDATE kakeibo SET id = '', groupId = '' WHERE message_id = '%s' AND id = '%s' Limit 1",
                mysqli_real_escape_string($db_link, $message_id),
                mysqli_real_escape_string($db_link, $user_id)
            );

            $res = mysqli_query($db_link, $sql);
            if ($res) {
                $delete_result = 'success';
            } else {
                $delete_result = 'error';
            }
        }
    } else {
        header("HTTP/1.1 404 Not Found");
        include('404.php');
        exit;
    }

    //Kakeiboテーブルからユーザが記帳したグループidを取得
    $sql = sprintf("SELECT * FROM kakeibo WHERE id = '%s' ",
        mysqli_real_escape_string($db_link, $user_id)
    );
    $sql .= "and DATE_FORMAT(insert_time, '%Y%m') > DATE_FORMAT((NOW() - INTERVAL 3 MONTH), '%Y%m') ORDER BY insert_time DESC";
    //$sql .= "ORDER BY insert_time DESC";

    $res = mysqli_query($db_link, $sql);

    $kakeibo_array = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $kakeibo_array[] = $row;
    }

    $group_id_array = [];
    foreach($kakeibo_array as $row) {
        $group_id_array[] = $row['groupId'];
    }
    $group_id_array = array_unique($group_id_array);

    foreach ($group_id_array as $row) {
        if ($row != '') {
            $in_pattern .= '"' . $row . '", ';
        }
    }

    $in_pattern = rtrim($in_pattern, ', ');

    //グループidからグループ名を取得
    $sql = 'SELECT groupId, group_name FROM group_name WHERE groupId IN (' . $in_pattern . ')';
    $group_name_array = [];
    $res = mysqli_query($db_link, $sql);
    while ($row = mysqli_fetch_assoc($res)) {
        $group_name_array[] = $row;
    }
    //ページ回遊に備えてサーバにパラメータ保持
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_name'] = $user_name;

    mysqli_close($db_link);

    $classify_array = classify_spending();
?>
<!DOCTYPE html>
<html>
<head>
    <title>kakeiBot_web</title>
    <meta charset="utf-8"/>
    <meta http-equiv="content-language" content="ja">
    <style type="text/css">
        table {
            table-layout: fixed;
            width: 100%;
        }
        table th {
            width: 150px;
            font-size: 50px;
        }
        td {
            height: 150px;
            font-size: 30px;
            text-align: center;
        }
        button {
            height: 140px;
            width: 90%;
            font-size: 30px;
        }
    </style>
</head>
<body>
<?php 
    echo '<h1>' . trim($user_name) . 'さん専用KakeiBotページ</h1>';
    echo '<h3>過去3ヶ月間のデータを編集可能です</h3>';
    if ($delete_result == 'success') {
        echo '<h3>削除に成功しました</h3><br>';
    }
    if ($delete_result == 'error') {
        echo '<h3>削除に失敗しました。管理者にお問い合わせしてください</h3><br>';
    }
    if ($_GET['modify_result'] == 'true') {
        echo '<h3>修正に成功しました</h3><br>';
    }
    if ($_GET['modify_result'] == 'false') {
        echo '<h3>修正に失敗しました。管理者にお問い合わせしてください</h3><br>';
    }
?>
    <table border="1">
    <tr>
        <th>ch</th>
        <th>値段</th>
        <th>分類</th>
        <th>時間</th>
        <th>修正</th>
        <th>削除</th>
    </tr>
<?php
    foreach ($kakeibo_array as $row1) {
        if ($row1['groupId'] == '') {
            echo '<tr>';
            echo '<td>個人</td>';
            echo '<td>' . h($row1['price']) . '</td>';
            echo '<td>' . h($classify_array[$row1['classify_id']]) . '</td>';
            echo '<td>' . h($row1['insert_time']) . '</td>';
            echo '<td><button type="button" name="modify" onclick="ModifyClick(' . h($row1['message_id']) . ')">修正</button></td>';
            echo '<td><button type="button" name="delete" onclick="DeleteClick(' . h($row1['message_id']) . ')">削除</button></td>';
            echo '</tr>';
        }
    }

    foreach ($group_name_array as $row) {
        foreach ($kakeibo_array as $row1) {
            if ($row1['groupId'] == $row['groupId']) {
                echo '<tr>';
                echo '<td>' . h($row['group_name']) . '</td>';
                echo '<td>' . h($row1['price']) . '</td>';
                echo '<td>' . h($classify_array[$row1['classify_id']]) . '</td>';
                echo '<td>' . h($row1['insert_time']) . '</td>';
                echo '<td><button type="button" name="modify" onclick="ModifyClick(' . h($row1['message_id']) . ')">修正</button></td>';
                echo '<td><button type="button" name="delete" onclick="DeleteClick(' . h($row1['message_id']) . ')">削除</button></td>';
                echo '</tr>';
            }
        }
    }
?>
    </table>
</body>
    <script>
        function ModifyClick(event) {
            var url = 'modify_kakeibo_tb.php?modify=' + event;
            window.location.href = url;
        }
    </script>
    <script>
        function DeleteClick(event) {
            var res = confirm("本当に削除しますか？復元は不可能です!");
            if( res == true ) {
                // OKなら移動
                var url = 'home.php?delete=' + event;
                window.location.href = url;
            } else {
                // キャンセルならアラートボックスを表示
                alert("キャンセルしました");
            }
        }
    </script>
</html>