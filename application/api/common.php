<<<<<<< HEAD
<?php

function response_success($data=[], $msg=''){

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