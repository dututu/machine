<?php
function result($status, $message='' , $data=[]) {
    return [
        'code' => intval($status),
        'msg' => $message,
        'data' => $data,
    ];
}
function status($status, $message='') {
    return [
        'code' => intval($status),
        'msg' => $message,
    ];
}