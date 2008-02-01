<?php
require_once('httplib.php');
require_once('PHPUnit/Framework.php');

class HTTPTestCase extends PHPUnit_Framework_TestCase
{
    public function testGoogleStatusCode()
    {
        $params = array(
            'q' =>  'hi',
        );
        $http = new HTTPConnection('www.google.com');
        $http->request('GET', '/', $params);
        $response = $http->getresponse();
        $this->assertEquals($response->status, 200);
    }
    public function testUnknownServer()
    {
        $this->setExpectedException('UnknownServerError');
        $http = new HTTPConnection('ishouldexist.invaliddomain.c');
        $http->request('GET', '/', array());
        $response = $http->getresponse();
    }
}
?>
