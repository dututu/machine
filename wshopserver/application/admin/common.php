<?php
function pagedata($page,$total,$records, $rows) {
    return [
        'page' => intval($page),
        'total' => intval($total),
        'records' => intval($records),
        'rows' => $rows,
    ];
}
function result($status, $message='' , $data=[]) {
    return [
        'code' => intval($status),
        'msg' => $message,
        'data' => $data,
    ];
}