<?php

function response_success($data=[], $msg=''){
// 允许 runapi.showdoc.cc 发起的跨域请求
header("Access-Control-Allow-Origin: http://runapi.showdoc.cc"); 
header("Access-Control-Allow-Credentials : true"); 
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Connection, User-Agent, Cookie");
	$result = array(
		'code' => 200,
		'data' => $data,
		'msg' => $msg,
	);

	json($result, 200)->send();
	exit;
}

function response_error($data=[], $msg=''){
	$result = array(
		'code' => 400,
		'data' => $data,
		'msg' => $msg,
	);

	json($result, 400)->send();
	exit;
}