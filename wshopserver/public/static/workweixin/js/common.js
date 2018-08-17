var common = {
    start: '{$date}',
    end: '{$date}',
    search: {},
    url_rank: '',
    url_trend: '',
    url_order: '',
    init: function (start, end) {
        this.start = start;
        this.end = end;
        this.get_sort();
        this.get_type();
        return this;
    },
    get_search: function () {
        this.search.start = this.start;
        this.search.end = this.end;
        return this.search;
    },
    get_sdate: function () {
        var _this = this;
        $("#start_date").calendar({
            dateFormat: 'yyyy-mm-dd',
            value: [_this.start],
            maxDate: _this.end,
            onChange: function (p, values) {
                _this.start = values[0];
            },
            onClose: function (p) {
                //$("#end_date").calendar('destroy')
                //_this.get_edate();
                var host = window.location.host;
                window.location.href = host + '?start=' + _this.start + '&end=' + _this.end;
            }
        });
    },
    get_edate: function () {
        var _this = this;
        $("#end_date").calendar({
            dateFormat: 'yyyy-mm-dd',
            value: [_this.end],
            minDate: _this.start,
            onChange: function (p, values) {
                _this.end = values[0];
            },
            onClose: function (p) {
                //$("#start_date").calendar('destroy');
                //_this.get_sdate();
                window.location.href = host + '?start=' + _this.start + '&end=' + _this.end;
            }
        });
    },
    get_sort: function () {
        var _this = this;

        //排序 1 desc 0 asc
        $(document).on('click', '.icon-sort__asc', function () {
            $(this).addClass('icon-sort__hover').removeClass('icon-sort__asc');
            _this.search.sort = 0;
            _this.search.page = 1;
            _this.get_transactionRanking(_this.url_rank, false);
        })
        $(document).on('click', '.icon-sort__hover', function () {
            $(this).addClass('icon-sort__asc').removeClass('icon-sort__hover');
            _this.search.sort = 1;
            _this.search.page = 1;
            _this.get_transactionRanking(_this.url_rank, false);
        })

    },
    get_type: function () {
        var _this = this;
        $('.weui-select-new').change(function () {
            var type = $(this).val();
            _this.search.type = type;
            _this.search.page = 1;
            _this.get_transactionRanking(_this.url_rank, false);
        })
    },
    //获取机柜排行榜
    get_transactionRanking: function (url, append) {
        $.ajaxSetup({
            async: false
        });
        var _this = this;
        this.url_rank = url;
        var search = _this.get_search();
        var r = false;
        $('.weui-loadmore').show();
        if (!append)
            $('.ranking').html('');
        $.post(url, search, function (d) {
            $('.weui-loadmore').hide();
            if (d.errcode == 0) {
                var html = '';
                var index = $('.ranking .rank').length;
                _this.search.page = d.data.current_page + 1;
                $.each(d.data.data, function (i, v) {
                    index++;
                    if (v.rfidtypecode == 1)
                        var text = '高频';
                    else if (v.rfidtypecode == 2)
                        var text = '超高频';
                    else
                        var text = '重力柜';
                    html += '<div class="weui-flex rank" data-machineid="' + v.machineid + '">';
                    html += '<div class="rank-num">' + index + '</div>';
                    html += '<div class="weui-flex__item">';
                    html += '<div class="font-color">机柜编号:' + v.containerid + ' / ' + v.merchantname + '</div>';
                    html += '<div class="weui-flex font-color">';
                    html += '<div class="weui-flex__item">类型：' + text + '</div>';
                    html += '<div class="weui-flex__item">代理商：' + v.username + '</div>';
                    //html += '<div class="">最后交易时间：05-01</div>';
                    html += '</div>';
                    html += '<div class="weui-flex font-weight">';
                    html += '<div class="weui-flex__item">收入：<span>' + v.pensincome + '笔/'   + v.amountincome + '元</span></div>';
                    html += '<div class=" weui-flex__item">退款：<span>' + v.pensrefund + '笔/' + v.amountrefund + '元</span></div></div>';
                    html += '<div class="weui-flex font-weight">';
                    html += '<div class="weui-flex__item">实收：<span>'   + v.realincome + '元</span></div>';
                    html += '<div class=" weui-flex__item">累计：<span>' +   v.amounaccum + '元</span></div></div>';

                    html += '</div></div>';
                })

                $('.ranking').append(html);
                r = d.data.hasMore;
            } else {
                $.alert(d.errmsg);
            }

        })

        return r;
    },
    //饼图
    get_circle:function(url,type) {
        this.url_trend = url;
        var search = this.get_search();
        search.type = type
        var option = {};
        $.ajaxSetup({
            async: false
        });
        $.post(url, search, function (d) {
            if (d.errcode == 0) {
                var legend = [];
                var series = [];
                var classname = [];
                $.each(d.data.arr[0].n, function (i, v) {
                    legend.push(v.t);
                    var obj = {};
                    obj.name = v[0];
                    obj.value = v[1];
                    series.push(obj);
                    classname.push(v[0]);
                })
                console.log(series)
                option = {

                    tooltip: {
                        trigger: 'item',
                        formatter: "{a} <br/>{b} : {c} ({d}%)"
                    },
                    legend: {
                        orient: 'vertical',
                        left: 'right',
                        data: classname
                    },
                    series: [
                        {
                            name: d.data.arr[0].t,
                            type: 'pie',
                            radius: '55%',
                            center: ['50%', '60%'],
                            label: {
                                normal: {
                                    formatter: '{b}:\n{d}% ',
                                    //position: 'inner'
                                }
                            },
                            data: series,
                            itemStyle: {
                                emphasis: {
                                    shadowBlur: 10,
                                    shadowOffsetX: 0,
                                    shadowColor: 'rgba(0, 0, 0, 0.5)'
                                }
                            }
                        }
                    ]
                };
            } else {
                $.alert(d.errmsg);
            }
        })

        return option;
    },
    //获取走势图
    get_machinetrend: function (url) {
        this.url_trend = url;
        var search = this.get_search();
        var option = {};
        $.ajaxSetup({
            async: false
        });
        $.post(url, search, function (d) {
            if (d.errcode == 0) {
                var legend = [];
                var series = [];
                $.each(d.data.arr, function (i, v) {
                    legend.push(v.t);
                    var obj = {};
                    obj.name = v.t;
                    obj.type = 'line';
                    obj.stack = '总量';
                    obj.areaStyle = {normal: {}};
                    obj.data = v.n;
                    series.push(obj);
                })
                console.log(d.data.arr_t)
                option = {
                    title: {
                        text: ''
                    },
                    tooltip: {
                        trigger: 'axis',
                        axisPointer: {
                            type: 'cross',
                            label: {
                                backgroundColor: '#6a7985'
                            }
                        }
                    },
                    legend: {
                        data: d.data.arr_t,
                    },
                    grid: {
                        left: '3%',
                        right: '4%',
                        bottom: '3%',   
                        containLabel: true
                    },
                    xAxis: [
                        {
                            type: 'category',
                            boundaryGap: false,
                            data: d.data.arr_x,
                        }
                    ],
                    yAxis: [
                        {
                            type: 'value'
                        }
                    ],
                    series: series
                };
            } else {
                $.alert(d.errmsg);
            }
        })

        return option;
    },

    //获取销售列表
    get_order_list: function (url) {
        $.ajaxSetup({
            async: false
        });
        var _this = this;
        this.url_order = url;
        var search = _this.get_search();
        var r = false;
        $('.weui-loadmore').show();
        $.post(url, search, function (d) {
            $('.weui-loadmore').hide();
            if (d.errcode == 0) {
                var html = '';
                _this.search.page = d.data.current_page + 1;
                $.each(d.data.data, function (i, v) {
                    html += ' <div class="weui-flex content-detail">';
                    html += ' <div class="weui-flex__item">';
                    html += ' <div class="">订单编号：' + v.orderno + '</div>';
                    html += ' <div class="">下单时间：' + v.createtime + '</div>';

                    if (v.totalgoods == 0) {
                        html += ' <div class="weui-flex">';
                        html += ' <div class="photo "></div></div>';
                    } else {
                        html += ' <div class="weui-flex" style="flex-wrap: wrap;">';
                        $.each(v.goods, function (ind, vo) {
                            if (ind == 5) return;
                            /*html += ' <div class="photo"><img src="' + vo.picurl + '" class="photo__thumb" alt=""></div>';*/
                            
                            html = html+''+
                            	'<div class="weui-cell" style="justify-content:space-around;"> '+
                            '<img src="' + vo.picurl + '" style="width: 60px;height: 60px;"/> '+
                            '<div style="flex:1;"> '+
                            '<p>商品名称：' + (vo.goodsname) + '</p> '+
                            '<p>价格：' + (vo.unitfee/100).toFixed(2) + '元</p> '+
                            '<p class="weui-flex" style="align-content:space-around;"><span>规格：' + vo.spec + '</span> '+
                            ' '+
                            '&nbsp;&nbsp;&nbsp;&nbsp;<span>数量：' + vo.amount + '</span> '+
                            '&nbsp;&nbsp;&nbsp;&nbsp;<span>金额：' + (vo.totalfee/100).toFixed(2) + '元</span></p> '+
                            '</div></div><br> '+
                       '';
                     
                            
                            
                            
                        })
                        html += '</div><br>';
                    }
                    html += ' <div class="weui-flex">';
                    html += ' <div class="">共 ' + v.totalgoods + ' 件商品 &nbsp</div>';
                    html += ' <div class="weui-flex__item">实付金额：' + v.payfee + '元</div>';
                    if (v.orderstatus == 8 || v.orderstatus == 5)
                        html += '<span class="order-status color-green">' + v.text + '</span>';
                    else if (v.orderstatus == 7 || v.orderstatus == 4)
                        html += '<span class="order-status color-gray">' + v.text + '</span>';
                    else if (v.orderstatus == 6)
                        html += '<span class="order-status color-red">' + v.text + '</span>';
                    else
                        html += '<span class="order-status color-orange">' + v.text + '</span>';

                    html += '</div></div></div>';
                })

                $('.content-list').append(html);
                r = d.data.hasMore;
            } else {
                $.alert(d.errmsg);
            }

        })

        return r;
    },

    //获取商户排行榜
    get_merchantRanking: function (url, append) {
        $.ajaxSetup({
            async: false
        });
        var _this = this;
        this.url_rank = url;
        var search = _this.get_search();
        var r = false;
        $('.weui-loadmore').show();
        if (!append)
            $('.ranking').html('');
        $.post(url, search, function (d) {
            $('.weui-loadmore').hide();
            if (d.errcode == 0) {
                var html = '';
                var index = $('.ranking .rank').length;
                _this.search.page = d.data.current_page + 1;
                $.each(d.data.data, function (i, v) {
                    index++;
                    html += '<div class="weui-flex rank" data-merchantid = ' + v.merchantid + '>';
                    html += '<div class="rank-left">排名<br>' + index + '</div>';
                    html += '<div class="weui-flex__item">';
                    html += '<div class="">商户名称：' + v.merchantname + '</div>';
                    html += '<div class="weui-flex">';
                    html += '<div class="weui-flex__item">机柜数量：<span class="tt">' + v.mtotalnummachine + '</span></div>';
                    html += '<div class="weui-flex__item">交易金额：' + v.stotalsales + '</div>';
                    html += '</div></div></div>';
                })

                $('.ranking').append(html);
                r = d.data.hasMore;
            } else {
                $.alert(d.errmsg);
            }

        })

        return r;
    },

    //获取商户的机柜
    get_merchant_machine: function (url, append) {
        $.ajaxSetup({
            async: false
        });
        var _this = this;
        this.url_rank = url;
        var search = _this.get_search();
        var r = false;
        $('.weui-loadmore').show();
        var html = '';
        if (!append)
            $('.list').html(html);
        $.post(url, search, function (d) {
            $('.weui-loadmore').hide();
            if (d.errcode == 0) {
                _this.search.page = d.data.current_page + 1;
                $.each(d.data.data, function (i, v) {
                    html += '<div  class="weui-flex content-detail" data-machineid="'+ v.machineid+'">';
                    html += '<div class="weui-flex__item">';
                    html += '<div class="">机柜编号：'+ v.containerid+'</div>';
                    html += '<div class="weui-flex">';
                    html += '<div class="weui-flex__item">交易流水额：'+ v.stotalsales+'元</div>';
                    html += '<div class="">机柜状态：'+ v.businessstatus_text+'</div>';
                    html += '</div>';
                    html += '<div class="weui-flex">';
                    html += '<div class="weui-flex__item">识别模式：'+ v.rfidtypename+'</div>';
                    html += '<div class="weui-flex__item">柜门：'+ v.doortypename+'</div>';
                    html += '<div class="">类型：'+ v.funcname+'</div>';
                    html += '</div>';
                    html += '<div class="">机柜安放位置：'+ v.dailaddress+'</div>';
                    html += '</div>';
                    html += '</div>';
                })

                $('.list').append(html);
                r = d.data.hasMore;
            } else {
                $.alert(d.errmsg);
            }

        })

        return r;
    },
    //销售概览
    get_saledtotal:function(url) {
        this.url_trend = url;
        var search = this.get_search();
        $.ajaxSetup({
            async: false
        });
        $.post(url, search, function (d) {
            if (d.errcode==0){
                $('.totalsales').html('¥' + d.data.totalsales + '/' + d.data.soldnum)
                $('.totalpens').html(d.data.totalpens)
                $('.perconsumption').html(d.data.perconsumption)
                $('.wxdeductedamount').html(d.data.wxdeductedamount)
                $('.amountstoredvalue').html(d.data.amountstoredvalue)
                $('.numarrears').html(d.data.numarrears)
                $('.wxpaymentpens').html(d.data.wxpaymentpens)
                
            } else {
                $.alert(d.errmsg);
            }
        })
    },
    //用户概览
    get_usertotal:function(url) {
        this.url_trend = url;
        var search = this.get_search();
        $.ajaxSetup({
            async: false
        });
        $.post(url, search, function (d) {
            if (d.errcode == 0) {
                $('.totalnumusers').html(d.data.numpurchased+'/'+d.data.totalnumusers)
                $('.newusers').html(d.data.newusers + '(' + d.data.newusersratio + '%)')
                $('.newstoredvalueusers').html(d.data.newstoredvalueusers + '(' + d.data.newstoredvalueusersratio+'%)')
                $('.newstoredvalue').html(d.data.newstoredvalue)
                $('.amountincome').html(d.data.amountincome)
                $('.numpurchased').html(d.data.numpurchased)
                $('.perconsumption').html(d.data.perconsumption)

            } else {
                $.alert(d.errmsg);
            }
        })
    }
}


