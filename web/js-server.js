/**
 * Created by LiHongZhen on 2018/6/3.
 */
function setUserid(params) {
    if (!params) return;
    if(!sessionStorage.userId){
        sessionStorage.setItem('userId', params);
    }
}

function removeUserid() {
   delete sessionStorage.userId;
}