<?php

namespace Guanhui07\Sudgame;

use Exception;
use GuzzleHttp\Client;

/**
 * 我们这 通知 subgame 的 ，请求 subgame 通知他们 游戏 人员 状态
 * @see https://docs.sud.tech/zh-CN/app/Server/ErrorCode.html 错误码说明
 * @see https://docs.sud.tech/zh-CN/app/Client/Languages/  多语言支持
 * @see https://docs.sud.tech/zh-CN/app/Client/APPFST/CommonState.html sub给客户端通知状态
 * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/ 推送事件列表api
 *
 * @see https://docs.sud.tech/zh-CN/app/Server/HttpsCallback/game/GameExtras.html
 * mg_id 游戏id 飞行棋
 *
 */
class SudGameRequestApi
{
    // 秘钥
    public $secret;
    // 应用id
    public $appId;
    /**
     * @var bool
     */
    public $isDebug = false;

    public function __construct(string $appId,string $secret,bool $isDebug)
    {
        $this->secret = $secret;
        $this->appId = $appId;
        $this->isDebug = $isDebug;
    }

    public static function new(string $appId,string $secret,bool $isDebug): self
    {
        return new self($appId,$secret,$isDebug);
    }

    /**
     * sub api所有接口都从这个链接 配置生成
     * @see https://docs.sud.tech/zh-CN/app/Server/API/ObtainServerEndAPIConfigurations.html
     * @return string
     */
    public function getApiUrl()
    {
        $sign = $this->getUrlSign();
        if ($this->isDebug) { //沙盒环境
            return 'https://sim-asc.sudden.ltd/' . $sign;
        }
        return 'https://asc.sudden.ltd/' . $sign;
    }

    /**
     * 获取uri带的sign
     * @return string
     */
    public function getUrlSign()
    {
        $key = $this->secret;
        $data = $this->appId;
        return hash_hmac("md5", $data, $key);
    }

    /**
     * 获取sud服务端 api列表 有做限流 ，缓存起来
     * @see  https://docs.sud.tech/zh-CN/app/Server/API/ObtainServerEndAPIConfigurations.html
     * get_mg_list: "https://sg-000-mg-sdk.s02.tech/v1/app/server/mg_list"
     * get_mg_info: "https://sg-000-mg-sdk.s02.tech/v1/app/server/mg_info"
     * mg_list: "https://sg-000-mg-proxy.s02.tech/v1/app/server/mg_list"
     * mg_info: "https://sg-000-mg-proxy.s02.tech/v1/app/server/mg_info"
     * get_game_report_info: "https://sg-000-mg-proxy.s02.tech/v1/app/server/game_report_info"
     * get_game_report_info_page: "https://sg-000-mg-proxy.s02.tech/v1/app/server/game_report_info_page"  分页获取
     * query_game_report_info: "https://sg-000-mg-proxy.s02.tech/v1/app/server/query_game_report_info"  查询游戏上报信息
     * report_game_round_bill: "https://sg-000-mg-proxy.s02.tech/v1/app/server/report_game_round_bill"
     * push_event: "https://sg-000-mg-proxy.s02.tech/v1/app/server/push_event"
     * auth_app_list: "https://sg-000-mg-proxy.s02.tech/v1/app/server/auth_app_list"
     * auth_room_list: "https://sg-000-mg-proxy.s02.tech/v1/app/server/auth_room_list"
     * create_order: "https://sg-000-mg-proxy.s02.tech/v1/app/server/create_order"
     * query_order: "https://sg-000-mg-proxy.s02.tech/v1/app/server/query_order"
     * query_match_base: "https://sg-000-mg-proxy.s02.tech/v1/app/server/query_match_base"
     * query_user_settle: "https://sg-000-mg-proxy.s02.tech/v1/app/server/query_user_settle"
     * query_match_round_ids: "https://sg-000-mg-proxy.s02.tech/v1/app/server/query_match_round_ids"
     * get_player_results: "https://sg-000-mg-proxy.s02.tech/v1/app/server/get_player_results"
     */
    public function getApiConfigUrlList()
    {
        $url = $this->getApiUrl();
        $client = new \GuzzleHttp\Client();
        $response = $client->get($url);

        return json_decode($response, true);
    }


    public function random(int $length = 16): string
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size  = $length - $len;
            $bytes = random_bytes($size);

            $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
        }

        return $string;
    }


    /**
     * 获取api请求头Authorization 的hash
     * @see https://docs.sud.tech/zh-CN/app/Server/API/AuthorizationDescription.html
     * curl --location 'https://sim-mg-proxy.s00.tech/v1/app/server/mg_list' \
     * --header 'Authorization: Sud-Auth app_id="1719669845797171201",timestamp="1698912908000",nonce="lFM9MKckbGYiZAQG",signature="eec4e3232ac1e348d5a7ae875dece8f33bb8ddc9"' \
     * --header 'Content-Type: application/json' \
     * --data '{"platform":2}'
     * @param array $body
     * @throws \Exception
     */
    public function getAuthorization(string $body)
    {
        $sudAppId = $this->appId;
        $sudTimestamp = time() * 1000;
        $sudNonce = $this->random(16);
        $signContent = $sudAppId . "\n" . $sudTimestamp . "\n"
            . $sudNonce . "\n" . $body . "\n";
        $appSecret = $this->secret;

        //要加密的签名串
        $signatureString = $signContent;
        $sign = hash_hmac('sha1', $signatureString, $appSecret, false);

        return [
            $sudAppId,
            $sudTimestamp,
            $sudNonce,
            $sign
        ];

    }

    /**
     * 根据key获取api url ,key对应api对应的 类型
     * @param string $key
     * @return mixed
     */
    public function getApiConfigUrl(string $key)
    {
        $body = $this->getApiConfigUrlList();
        return $body['api'][$key];
    }

    /**
     * 获取游戏列表 新版
     * @see https://docs.sud.tech/zh-CN/app/Server/API/ObtainTheGameListV2.html
     *
     * 当前接口针对每个app_id请求限制频率为10次/秒
     * @param int $system 1:iOS 2:Android 3:Web
     */
    public function getGameList($system = 2)
    {
        $url = $this->getApiConfigUrl('mg_list');
        $body = [
            'platform' => $system, //1:iOS 2:Android 3:Web
            'unity_engine_version' => '2020.3.25f1c1', //
        ];

        $json = $this->getCurlData($url, $body);

        return json_decode($json, true);

    }



    /**
     * 查询游戏上报信息 新
     * @see https://docs.sud.tech/zh-CN/app/Server/API/QueryGameReportInformation.html
     * 当前接口针对每个app_id请求限制频率为10次/秒
     */
    public function getGameReportInfo($report_game_info_key = '', $game_round_id = '')
    {
        $url = $this->getApiConfigUrl('query_game_report_info');
        $body = [
            'report_game_info_key' => $report_game_info_key, //上报游戏信息时传入的上报信息key
            'game_round_id' => $game_round_id, //游戏ID
        ];
        $json = $this->getCurlData($url, $body);

        $ret = json_decode($json, true);
        return $ret;

    }

    /**
     * 分页获取房间内游戏上报信息 新
     * @see https://docs.sud.tech/zh-CN/app/Server/API/ObtainTheReportInformationOfGameByPage.html
     * 当前接口针对每个app_id请求限制频率为10次/秒
     */
    public function getGameReportInfoPage($partyId, $page)
    {
        $url = $this->getApiConfigUrl('get_game_report_info_page');
        $body = [
            'app_id' => '', //todo:
            'app_secret' => '', //
            'room_id' => '', //
            'page_no' => '', //
            'page_size' => '', //
        ];
        $json = $this->getCurlData($url, $body);

        return json_decode($json, true);
    }

    /**
     * 推送事件到游戏服务
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventToMgServer.html
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/ 推送事件列表
     * $pushBody = [
     * 'event'=>'user_in', //游戏事件
     * 'mg_id'=>'', //游戏id
     * 'timestamp'=>'', //timestamp
     * 'data'=>[], //事件数据 obj
     * ];
     * @param $event string  游戏事件
     * @param $mg_id string  游戏id SudGameConst::
     * @param $pushBody array  事件数据 obj
     */
    public function pushEventToMgServer(string $event = 'user_in', string $mg_id = '', array $pushBody = [])
    {
        $url = $this->getApiConfigUrl('push_event');
        $body = [
            'event' => $event, //游戏事件
            'mg_id' => $mg_id, //游戏id
            'timestamp' => (string)(time() * 1000), //timestamp
            'data' => $pushBody, //事件数据 obj
        ];

        $json = $this->getCurlData($url, $body);
        return json_decode($json, true);
    }

    /**
     * 游戏内付费下单
     * @see https://docs.sud.tech/zh-CN/app/Server/API/CreateOrder.html
     *
     * $body = [
     * 'out_order_id' => 1, //商户自定义唯一订单号id
     * 'mg_id' => 2, //游戏ID
     * 'room_id' => 2, //房间id
     * 'cmd' => 2, //触发的行为动作
     * 'from_uid' => 2, //付费用户uid
     * 'to_uid' => 2, //目标用户uid
     * 'payload' => [], //附加数据 可选
     * ];
     */
    public function createOrder(array $body)
    {
        $url = $this->getApiConfigUrl('create_order');

        $json = $this->getCurlData($url, $body);
        return json_decode($json, true);
    }

    /**
     * 查询订单
     * @see https://docs.sud.tech/zh-CN/app/Server/API/QueryOrder.html
     */
    public function queryOrder($out_order_id)
    {
        $url = $this->getApiConfigUrl('query_order');
        $body = [
            'out_order_id' => $out_order_id, //商户自定义唯一订单号id
        ];
        $json = $this->getCurlData($url, $body);
        return json_decode($json, true);
    }

    /**
     * 服务端上报每局游戏消耗的货币信息
     * @see https://docs.sud.tech/zh-CN/app/Server/API/ReportAmountOfCurrencyConsumedInEachGameRound.html
     * $body = [
     * 'request_id' => '', //请求id，64个字符以内，需保证唯一性
     * 'mg_id' => '', //游戏id
     * 'room_id' => '', //房间id
     * 'round_id' => '', //游戏局id
     * 'total_amount' => '', //当局游戏总的消耗货币数，精确到小数点后两位
     * 'payment_details' => [], //当局游戏内用户的支付列表信息
     * 'payment_type' => '', //支付类型。枚举值:TICKET: 门票
     * ];
     */
    public function reportGameRoundBil(array $body)
    {
        $url = $this->getApiConfigUrl('report_game_round_bill');

        $json = $this->getCurlData($url, $body);
        return json_decode($json, true);
    }

    /**
     * 用户加入 事件推送
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/UserInReqData.html
     * @see https://docs.sud.tech/zh-CN/app/Client/Languages/    语言 zh-CN en-US hi-IN   id-ID  zh-TW
     * 服务端消息里的language字段，只有你画我猜这类游戏，有用
     * @param string $mg_id 游戏id SudGameConst::
     * @param int $userId
     * @param int $seatIndex 哪个座位  ，默认 -1随机
     * @param int $teamId 1或2  分队伍游戏 默认1
     */
    public function userJoinGame(string $userToken,string $language, int $roomId, string $mg_id, int $seatIndex = -1, int $teamId = 1)
    {

        $body = [
            'code' => $userToken,//用户token
            'room_id' => (string)$roomId,//房间ID
            'mode' => 1,//模式 默认: 1
            'language' => $language,//
            'is_ready' => false,//false:不准备 true:准备
            'seat_index' => $seatIndex,//
            'is_seat_random' => true,//
            'team_id' => $teamId,// 不支持分队的游戏：数值填1；支持分队的游戏：数值填1或2（两支队伍）
        ];

        if ($seatIndex != -1) { // 不随机
            $body['is_seat_random'] = false;
        }
        return $this->pushEventToMgServer('user_in', $mg_id, $body);
    }

    /**
     * 用户退出 事件推送
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/UserOutReqData.html
     * @param string $mg_id 游戏id SudGameConst::
     * @param int $userId
     */
    public function userLeaveGame(int $userId, string $mg_id)
    {
        $body = [
            'uid' => (string)$userId,//用户uid
            'is_cancel_ready' => true,//false:返回错误 true:取消准备
        ];
        return $this->pushEventToMgServer('user_out', $mg_id, $body);
    }



    /**
     * 用户准备和取消准备
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/UserReadyReqData.html
     * @param int $userId
     * @param $is_ready  true 准备 false 取消准备
     * @param string $mg_id 游戏id
     */
    public function userReadyGame(int $userId, $is_ready = true, string $mg_id = '')
    {
        $body = [
            'uid' => (string)$userId,//用户uid
            'is_ready' => $is_ready,
        ];
        return $this->pushEventToMgServer('user_ready', $mg_id, $body);
    }

    public function cancelUserReadyGame(int $userId, $is_ready = false, string $mg_id = '')
    {
        $body = [
            'uid' => (string)$userId,//用户uid
            'is_ready' => $is_ready,
        ];
        return $this->pushEventToMgServer('user_ready', $mg_id, $body);
    }

    /**
     * 游戏开始
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/GameStartReqData.html
     * @param string $mg_id 游戏id
     * @param int $perGameDiamond 每局的费用
     */
    public function userStartGame($roomId, string $mg_id = '', $perGameDiamond = 0, $nowUserId = 0)
    {
        $perGameDiamond = $perGameDiamond ? $perGameDiamond : 1000;
        if (!$roomId) {
            throw new Exception('未获取到room_id');
        }

        $extras = [
            'mg_id' => $mg_id,
//            'start_user_id' => getMyUserId(),
            'per_game_diamond' => $perGameDiamond,
        ];


        $extras = json_encode($extras);

        $body = [
            'room_id' => (string)$roomId,//房间ID
            'report_game_info_extras' => (string)$extras, //透传参数，最大长度1024字节，超过则截断
            'report_game_info_key' => (string)$extras, // 透传参数key，最大长度64字节，接入方服务端，可以根据这个字段来查询一局游戏的数据
        ];

        return $this->pushEventToMgServer('game_start', $mg_id, $body);
    }

    /**
     * 队长更换
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/CaptainChangeReqData.html
     * @param string $mg_id 游戏id SudGameConst::
     * @param int $userId
     */
    public function gameOwner(int $userId, string $mg_id)
    {
        $body = [
            'captain_uid' => (string)$userId,//指定队长ID
        ];
        return $this->pushEventToMgServer('captain_change', $mg_id, $body);
    }

    /**
     * 用户踢人
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/CaptainChangeReqData.html
     * @param string $mg_id 游戏id
     * @param int $userId
     */
    public function userKick(int $userId, string $mg_id)
    {

        $body = [
            'kicked_uid' => (string)$userId,//
        ];
        return $this->pushEventToMgServer('user_kick', $mg_id, $body);
    }

    /**
     * 游戏结束
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/CaptainChangeReqData.html
     * @param int $partyId 'id
     * @param int $userId
     * @param string $mgId SudGameConst::
     */
    public function gameEnd(int $userId,  $roomId, string $mgId = '')
    {
        if (!$roomId) {
            throw new Exception('未获取到room_id');
        }
        $body = [
            'kicked_uid' => (string)$userId,
            'room_id' => (string)$roomId,
        ];
        return $this->pushEventToMgServer('game_end', $mgId, $body);
    }


    /**
     * 加入AI
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/CaptainChangeReqData.html
     * [{
     * "uid":"uid_1",   uid
     * "avatar":"avatar_1",  头像url
     * "name":"name_1",  名字
     * "gender":"male",    male：男 female：女
     * "ai_level":1   添加ai等级    0：简单ai 1：简单ai 2：中级ai 3：高级ai
     * }]
     * @param string $mg_id 游戏id SudGameConst::
     * @param int $partyId 'id
     * @param array $aiPlayers
     */
    public function aiAdd( $roomId, array $aiPlayers = [], string $mg_id = '')
    {
        if (!$roomId) {
            throw new Exception('未获取到room_id');
        }
        $body = [
            'room_id' => (string)$roomId,//
            'ai_players' => $aiPlayers,//AI用户信息 数组
            'is_ready' => 1,//1：自动准备
        ];
        return $this->pushEventToMgServer('ai_add', $mg_id, $body);
    }


    /**
     * 获取房间座位列表
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/RoomInfoReqData.html
     * @param int $partyId 'id
     */
    public function getRoomInfo($roomId, $mgId)
    {
        if (!$roomId) {
            throw new Exception('未获取到room_id');
        }
        $body = [
            'room_id' => (string)$roomId,//
        ];
        return $this->pushEventToMgServer('room_info', $mgId, $body);
    }



    /**
     * 房间清理
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/RoomClearReqData.html
     * @param int $partyId 'id
     * @param string $mg_id 游戏id SudGameConst::
     */
    public function roomClear( $roomId, string $mg_id)
    {
        if (!$roomId) {
            throw new Exception('未获取到room_id');
        }
        $body = [
            'room_id' => (string)$roomId,
        ];
        return $this->pushEventToMgServer('room_clear', $mg_id, $body);
    }

    /**
     * 游戏创建
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/GameCreateReqData.html
     * @param string $mg_id 游戏id SudGameConst::
     */
    public function gameCreate(string $mg_id)
    {
        $body = [
            'mode' => 1,
        ];
        return $this->pushEventToMgServer('game_create', $mg_id, $body);
    }

    /**
     * 游戏删除
     * @see https://docs.sud.tech/zh-CN/app/Server/API/PushEventData/GameDeleteReqData.html
     * @param string $mg_id 游戏id SudGameConst::
     */
    public function gameDelete(string $mg_id = '')
    {
        $body = [
            'mode' => 1,
        ];
        return $this->pushEventToMgServer('game_delete', $mg_id, $body);
    }

    /**
     * 发送subgame 接口
     * @param $body
     * @param $url
     * @return mixed
     * @throws \Exception
     */
    public function getCurlData(string $url, array $body)
    {
        $body = json_encode($body);

        [
            $sudAppId,
            $sudTimestamp,
            $sudNonce,
            $sign
        ] = $this->getAuthorization($body);

        $autuorizationHeader = sprintf('Sud-Auth app_id="%s",timestamp="%s",nonce="%s",signature="%s"',
            $sudAppId, $sudTimestamp, $sudNonce, $sign);
        $json = $this->httpFetchPost($url, $body, [
            'headers' => [
                'Authorization' => $autuorizationHeader,
                'Content-Type' => 'application/json',
            ],
            'is_debug' => true,
        ]);


        return $json;
    }

    /**
     * @param $url
     * @param $body
     * @param $headers
     */
    public function httpFetchPost($url, $body = [], $headers = [])
    {
        $client = new Client();

        try {
            $response = $client->post('https://example.com/api/post - data', [
                'headers' => $headers,
                'json' => $body
            ]);
            $responseBody = $response->getBody();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
        }
        return $responseBody;
    }
    /**
     * 校验回调的签名值
     * 比较签名值 true: 验签成功， false: 验签失败
     * @return bool
     * @see https://docs.sud.tech/zh-CN/app/Server/HttpsCallback/CallbackSignatureVerify.html
     */
    public function verifySignature(array $allHeaders,array $body)
    {
        // SudAppId
        $sudAppId = $allHeaders["sud-appid"][0] ?? '';
        // SudTimestamp
        $sudTimestamp = $allHeaders["sud-timestamp"][0] ?? '';
        // SudNonce
        $sudNonce = $allHeaders["sud-nonce"][0] ?? '';
        // SudSignature
        $sudSignature = $allHeaders["sud-signature"][0] ?? '';



        $signContent = sprintf("%s\n%s\n%s\n%s\n",
            $sudAppId, $sudTimestamp, $sudNonce, json_encode($body));

        $appSecret = $this->secret;


        //要加密的签名串
        $signatureString = $signContent;
        $signature = hash_hmac('sha1', $signatureString, $appSecret);

        $bool = $sudSignature == $signature;
        return $bool;
    }

    /**
     *
     * 获取subgame的 传参 language
     * @param int $userId
     * @return string
     * @see https://docs.sud.tech/zh-CN/app/Client/Languages/  多语言支持
     */
    public function getSubGameLanguage(string $language): string
    {
        if ($language === 'zh_cn') {
            $language = 'zh-CN';
        } elseif ($language === 'zh_tw') {
            $language = 'zh-TW';
        } elseif ($language === 'hi_in') {
            $language = 'hi-IN';
        } elseif ($language === 'id_id') {
            $language = 'id-ID';
        } else {
            $language = 'en-US'; //默认英语
        }

        return $language;
    }



}