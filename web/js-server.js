/**
 * Created by LiHongZhen on 2018/6/3.
 */
function setUserid(params) {
    if (!params) return;
        sessionStorage.setItem('userId', params);
}

// function setGoodsInfo(params) {
//     if (!params) return;
//     sessionStorage.setItem('goodsInfo', params);
// }