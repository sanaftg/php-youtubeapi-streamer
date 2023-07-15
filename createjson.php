<?php
require_once (dirname(__FILE__) . '/vendor/autoload.php');

const API_KEY = "{APIキー}";
const JSON_PATH = __DIR__.'/{JSONファイル名}.json';

$channelIds = array(
    'チャンネルID_1',
    'チャンネルID_2',
    'チャンネルID_3'
);

//認証
function getClient() 
{
    $client = new Google_Client();
    $client->setApplicationName("{アプリ名}");
    $client->setDeveloperKey(API_KEY);
    return $client;
}


//動画取得
function searchVideos($vids) 
{
    foreach(array_chunk($vids,50) as $chunk){
        $youtube = new Google_Service_YouTube(getClient());

        $params = [
            'id' => implode(",",$chunk)
        ];

        try {
            $searchResponse = $youtube->videos->listVideos('snippet', $params);
        } catch (Google_Service_Exception $e) {
            echo htmlspecialchars($e->getMessage());
            exit;
        } catch (Google_Exception $e) {
            echo htmlspecialchars($e->getMessage());
            exit;
        }

        foreach ($searchResponse['items'] as $search_result) {
            $videos[] = $search_result;
        }
    }
    return $videos;
}

// チャンネルフィードからビデオID取得
function getVideoIds($chId){
    //フィードのURLをセット
    $feed='https://www.youtube.com/feeds/videos.xml?channel_id='.$chId;
    //フィードを読み込み
    $xml = simplexml_load_file($feed);
    //配列に変換
    $obj = get_object_vars($xml);
    //動画情報を変数に格納
    $obj_entry = $obj["entry"];
    //動画のトータル件数を取得
    $total = count($obj_entry);
    $vids = array();
    //動画が存在するかどうかチェック
    if( $total != 0 ){
        for ($i=0; $i < $total; $i++) { 
            foreach ($obj_entry[$i] as $key => $value) {
                if( $key=='id'){
                    //動画IDを変数に格納（yt:video:XXXXという形式なので手前の文字列を置換処理も挟む）
                    $vids[] = str_replace('yt:video:', '', $value[0]);
                }else{
                    continue;//残りの処理をスキップ
                }
            }
        }
    }
    return $vids;
}

// 優先順位をつけていい感じにする、配信中＞終了済＞配信予定
function getVideoPriority($video){
    $priority = 0;
    switch($video['snippet']['liveBroadcastContent']){
        case "live":
            $priority = 3;
        break;
        case "none":
            $priority = 2;
        break;
        case "upcoming":
            $priority = 1;
        break;
    }
    return $priority;
}

// 動画IDの取得
$vids = array();
// データ作成
$now = new Datetime();
$appdata = array(
    "chs" => array(),
    "date" => $now->format("Y-m-d H:i:s")
);

foreach($channelIds as $chId){
    $vids = array_merge($vids,getVideoIds($chId));
    sleep(1);
}

if(count($vids) > 0){
    $videos = searchVideos($vids);
}
$channels = array();
foreach($videos as $video){
    $channels[$video['snippet']['channelId']][] = $video;
}

//JSON用に整理
foreach ($channels as $key => $value) {
    $new = $value[0];
    foreach($value as $video){
        if(getVideoPriority($new) < getVideoPriority($video)){
            $new = $video;
        }
    }
    $appdata["chs"][] = array(
        "id" => $key,
        "videoId" => $new['id'],
        "live"=>$new['snippet']['liveBroadcastContent'] == "live" ? 1:0,
        "thumb" => $new['snippet']['thumbnails']['high']['url']
    );
}

file_put_contents(JSON_PATH , json_encode($appdata));

?>
