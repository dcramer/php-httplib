A replica of the Python httplib designed for lightweight POST and GET requests in PHP.

Current source allows HTTP connections (no SSL) with GET or POST requests::


	$conn = new HTTPConnection('google.com', 80);
	$conn->request('POST', '/', array('q' => 'hi'));

	$response = $conn->getresponse();
	if ($response->status == 200) echo $response->read();