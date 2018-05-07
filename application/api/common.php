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
=======
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
>>>>>>> 6c99d03db863d2927ce1b4008f43a5862e456c83
}