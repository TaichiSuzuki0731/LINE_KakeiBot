<?php
    require('function.inc.php'); //共通関数群
    require('line_api_info.php'); //LINE_API情報
    require(ROOT_DIRECTOR . '/line_info/line_info.php'); //LINE_APIに接続する際に必要な情報
    include('line_bot.inc.php');

    if (!session_id()) {
        session_start();
    }

    $db_link = db_connect();

    if ($_SESSION['user_id'] != '' && !$_GET['execution']) {
        if ($_GET['modify'] == '') {
            header("HTTP/1.1 404 Not Found");
            echo '<h3>ページ遷移に失敗しました。管理者にお問い合わせしてください</h3><br>';
            exit();
        }
        $user_id = h($_SESSION['user_id']);
        $message_id = h($_GET['modify']);
        //unset($_SESSION['user_id']);

        $sql = sprintf("SELECT * FROM kakeibo WHERE message_id = '%s' AND id = '%s' ",
            mysqli_real_escape_string($db_link, $message_id),
            mysqli_real_escape_string($db_link, $user_id)
        );

        $res = mysqli_query($db_link, $sql);

        $kakeibo_deta = mysqli_fetch_object($res);

        $time = strstr($kakeibo_deta->{'insert_time'}, ' ');
        $date = str_replace($time, '', $kakeibo_deta->{'insert_time'});
        $classify_array = classify_spending();
    } elseif ($_SESSION['user_id'] != '' && $_GET['execution']) {
        $price = h($_GET['input_price']);
        $classify_id = h($_GET['input_classify']);
        $insert_date = h($_GET['input_date']);
        $insert_time = h($_GET['input_time']);
        $user_id = h($_SESSION['user_id']);
        $message_id = h($_GET['modify']);

        $insert_date_time = $insert_date . ' ' . $insert_time;
        $sql = 'UPDATE kakeibo SET ';

        $sql .= sprintf("price = '%s', classify_id = '%s', insert_time = '%s' WHERE message_id = '%s' AND id = '%s' Limit 1",
            mysqli_real_escape_string($db_link, $price),
            mysqli_real_escape_string($db_link, $classify_id),
            mysqli_real_escape_string($db_link, $insert_date_time),
            mysqli_real_escape_string($db_link, $message_id),
            mysqli_real_escape_string($db_link, $user_id)
        );

        $res = mysqli_query($db_link, $sql);
        if ($res) {
            $redirect_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/home.php?modify_result=true';
        } else {
            $redirect_url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . '/home.php?modify_result=false';
        }
        header("Location: {$redirect_url}");
    } else {
        header("HTTP/1.1 404 Not Found");
        include('404.php');
        exit;
    }

    mysqli_close($db_link);
?>
<!DOCTYPE html>
<html>
<head>
    <title>kakeiBot_web</title>
    <meta charset="utf-8"/>
    <meta http-equiv="content-language" content="ja">
    <style type="text/css">
        body {
            zoom: 400%
        }
    </style>
</head>
<body>
<div>
    <form method='GET' action=''>
        <input type='hidden' name='modify' value='<?php echo h($message_id) ?>'>
        <p>金額: <input type='number' name='input_price' min='1' value = <?php echo h($kakeibo_deta->{'price'})?>></p>
        <p>分類: 
            <select name='input_classify' id='pet-select'>
<?php
    foreach ($classify_array as $key => $row) {
        if ($key == $kakeibo_deta->{'classify_id'}) {
            echo '<option value=' . h($key) .' selected>' . h($row) .'</option>';
        } else {
            echo '<option value=' . h($key) .' >' . h($row) .'</option>';
        }
    }
?>
            </select>
        </p>
        <p>日付: <input type='date' name='input_date' value = <?php echo h($date)?>></p>
        <p>時間: <input type='time' name='input_time' step='1' value = <?php echo h($time)?>></p>
        <p>
            <button type='button' onclick='backbrowser()'>戻る</button>&emsp;&emsp;&emsp; 
            <input type='submit' name = 'execution' value='修正'>
        </p>
    </form>
</div>
</body>
    <script>
        function backbrowser() {
            var url = 'https://st0731-dev-srv.moo.jp/home.php';
            window.location.href = url;
        }
    </script>
</html>