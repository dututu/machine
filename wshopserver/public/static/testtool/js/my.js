var url = "https://shop.wemall.com.cn/hardwaredetection/testbox/";
var url2 = "https://shop.wemall.com.cn/hardwaredetection/index/";


function getDevInfo() {
    var dev_uid = getCookie(dev_uid);
    if (dev_uid.length>0) {
        $.ajax({
            type: "POST",
            url: url + 'getDevInfo',
            data: { devuid: dev_uid},
            dataType: dataType,
            success: function (res) {
                //请求成功
                
            }
        });
    } else {
        //等待输入
    }
    
}
function bind(machineid) {
    var dev_uid = getCookie("dev_uid");
    var ss = $('#sn').html();

    $.ajax({
        type: "POST",
        url: url + 'bind',
        data: { devuid: dev_uid,
            containerid:machineid,csn:ss},
        dataType: 'json',
        success: function (res) {
            //请求成功
            if (res.code==0) {
                alert('绑定成功')
                $('.dev_search').removeClass('weui-btn_primary');
                $('.dev_search').addClass('uned');
                $('.dev_search').unbind();
            } else {
                alert(res.msg);
            }
        }
    });
}
function getMachineInfo(machineid) {
    console.log(1);
    if (machineid.length > 0) {
        $.ajax({
            type: "POST",
            url: url2 + 'getmachineinfo',
            data: {machineid:machineid},
            dataType: 'json',
            success: function (res) {
                if (res.code == undefined) {
                    $('#containerid').html(res.containerid)
                    var rfidtype = '';
                    if (res.rfidtypecode == 1) {
                        rfidtype = '高频'
                    } else if (res.rfidtypecode == 2) {
                        rfidtype = '超高频'

                    } else if (res.rfidtypecode == 3) {
                        rfidtype = '重力柜'

                    } else {
                        rfidtype = '未知'
                    }
                    console.log(rfidtype)
                    $('#rfidtypecode').html(rfidtype)
                    if (res.boxdevid != null && res.boxdevid != '' && res.boxdevid != undefined) {
                        $('#rfidtypecode').append('<p>dev_uid:<span id=\"rfidtypecode\">' + res.boxdevuid + '</span></p>')
                        $('.dev_search').removeClass('weui-btn_primary');
                        $('.dev_search').addClass('uned');
                    } else {
                        $('.dev_search').addClass('weui-btn_primary');
                        $('.dev_search').removeClass('uned');
                        $('.dev_search').on('click', function () {
                            bind(res.containerid);
                        })
                    }
                    $('#marchineInfo').show()
                } else {
                    alert(res.msg);
                }
            }
        });
    } else {
        //等待输入
    }
}
function getCookie(c_name) {
    if (document.cookie.length > 0) {
        c_start = document.cookie.indexOf(c_name + "=")
        if (c_start != -1) {
            c_start = c_start + c_name.length + 1
            c_end = document.cookie.indexOf(";", c_start)
            if (c_end == -1) c_end = document.cookie.length
            return unescape(document.cookie.substring(c_start, c_end))
        }
    }
    return ""
}

function setCookie(c_name, value, expiredays) {
    var exdate = new Date()
    exdate.setDate(exdate.getDate() + expiredays)
    document.cookie = c_name + "=" + escape(value) +
        ((expiredays == null) ? "" : ";expires=" + exdate.toGMTString())
}
function loopArray(array) {
    var list = '';
    for (var i = 0; i < array.length; i++) {
        list += array[i] + ';';
    }
    return list;
}