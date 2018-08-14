/**
 * 根据切图时参考的设计稿宽度, 针对对应分辨率的设备, 自动调整页面 rem 的基准值
 */
(function () {
    var currentRootFontSize = getComputedStyle(document.documentElement, null).getPropertyValue("font-size").replace('px', '');

    function _autoAdjustRootEm() {
        // 切图时参考的设计稿宽度
        var originalWidth = 750;
        document.documentElement.style.fontSize =1.8*document.documentElement.clientWidth / originalWidth * currentRootFontSize + 'px';
    }

    // 页面加载完成前, 预调整 root em
    if (document.documentElement.clientWidth > 180) {
        _autoAdjustRootEm();
    }

    //注册 resize 事件
    window.addEventListener('resize', _autoAdjustRootEm, false);
})();