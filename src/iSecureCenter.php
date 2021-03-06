<?php

/**
 * Created by PhpStorm.
 * User: Yangjiecheng
 * Date: 2019/8/27 0027
 * Time: 下午 15:45
 */

namespace jcore\iSecureCenter;

use Yii;
use yii\base\Component;
use yii\web\HttpException;
use yii\web\BadRequestHttpException;

class iSecureCenter extends Component
{
    CONST EVENT_BEFORE_SEND = 'beforeSend';
    CONST EVENT_AFTER_SEND = 'afterSend';

    //海康威视综合安防管理平台version
    public $version;

    // 合作方接口主机
    public $host;

    //设置OpenAPI接口的上下文
    public $artemisPath = '/artemis';

    //TODO:合作方 多合作方配置，加入一层service
    public $partners;

    public $requestTimeout = 5;

    public $xCaSignatureHeaders = 'x-ca-key';

    private $_service;

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        // TODO: empty($this->partners) 可以抛出异常也可以走数据库取数据取一个默认配置
//        $this->partners['adminPartner'] = [
//            'hikvideo_icenter_appkey' => $dbConfig['hikvideo_icenter_appkey'],
//            'hikvideo_icenter_secret' => $dbConfig['hikvideo_icenter_secret'],
//        ];
    }

    public function getService($partner = 'adminPartner')
    {
        if (!isset($this->partners[$partner])) {
            throw new BadRequestHttpException("找不到该合作方，无法连接到综合安防管理平台");
        }

        $config = $this->partners[$partner];
        $this->_service = Service::getInstance($this->host , $this->artemisPath, $config['hikvideo_icenter_appkey'], $config['hikvideo_icenter_secret']);

        return $this->_service;
    }

    public function send($apiClass, $action, $data = [], $partner = 'adminPartner')
    {
        if (!$this->beforeSend($partner, $apiClass, $action, $data)) {
            return false;
        }

        Yii::info('...Sending Hk Open Api...partner#' . $partner . '#action#' . $action, __METHOD__);
        $response = [];
        try {
            $response = $this->getService($partner)->send($apiClass, $action, $data);
        } catch (\Throwable $throwable) {
            throw new HttpException(200, "无法连接到综合安防管理平台(请检查服务器)", 1001);
        } finally {
            $this->afterSend($partner, $apiClass, $action, $data, $response);
        }

        return empty($response) ? null : $response->getData();
    }



    public function beforeSend($partner, $apiClass, $action, $data)
    {
        $event = new MessageEvent([
            'partner' => $partner,
            'apiClass' => $apiClass,
            'data' => $data,
            'action' => $action
        ]);
        $this->trigger(self::EVENT_BEFORE_SEND, $event);

        return $event->isValid;
    }

    public function afterSend($partner, $apiClass, $action, $data, $response)
    {
        $event = new MessageEvent([
            'partner' => $partner,
            'apiClass' => $apiClass,
            'data' => $data,
            'action' => $action,
            'response' => $response
        ]);

        $this->trigger(self::EVENT_AFTER_SEND, $event);
    }
}