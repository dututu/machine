<?php
/**
 * Created by Caesar.
 */

return [
    //  +---------------------------------
    //  url相关配置
    //  +---------------------------------

    'rfidhost' => 'http://115.28.44.151:9080',
    'rfidhostv2' => 'https://t.wemall.com.cn',
    'masterhost' => 'http://115.28.239.81:52010',//https://www.alaishuo.com
    'gboxhost' => 'http://218.108.7.116:8080/api',
    //腾讯cos
    'coshost' => 'http://filetest.wemall.com.cn/',
    'cosregion' => 'ap-beijing',
    'cosbucket' => 'shoptest-1253877534',
    'coskey' => 'AKID2sWu2tmpxrY0EFzbw4ye6KLBnCNK3QAh',
    'cossecret' => 'TjlaBYhcKo7D6lCPO9i3B5lETUIsFOJ1',
    //rfid
    'selOrder' => '/rfidapi/Order/selOrder',
    'selCabinetStockInfo' => '/rfidapi/Kus/list',
    'syncGoods' => '/unattended/commSynchronization/commodityInfoSyn.action',
    'addLabelBuyOrder' => '/unattended/labelBuy/addLabelBuyOrder.action',
    'selLabelBuyOrderStatus' => '/unattended/labelBuy/selLabelBuyOrderStatus.action',
    'deviceStatus' => '/unattended/heart/rfidStatus.action',
    'saveRelationInfo' => '/unattended/cabinet/saveRelationInfo.action',

    //master
    'deviceOpen' => '/container/deviceOpen',
    'deviceOpenStatus' => '/container/deviceOpenQuery',
    'masterStatus' => '/container/masterStatus',
    'deviceRegister' => '/container/deviceRegister',
    'deviceStatusQuery' => '/container/deviceStatusQuery',
    'callbackPay' => '/container/callbackPay',
    'deviceOpenS' => '/container/deviceOpenS',
    //gbox
    'gboxuser' => 'MB00009',
    'gboxsecretkey' => '847e3a997bbd32f49ecc05fc8aafe9a82c1f9789',
    //Access token
    'access-token' => '/access-token',
    //SKU管理
    'sku-mgmt' => '/sku-mgmt',
    //仓存管理
    'stock-mgmt' => '/stock-mgmt',
    //货柜管理
    'box-mgmt' => '/box-mgmt',
    //事件上报
    'event' => '/event',



];
