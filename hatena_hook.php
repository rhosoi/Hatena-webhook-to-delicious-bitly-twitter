<?php
require_once 'Services/Delicious.php';
require_once 'HTTP/Client.php';

// はてブwebhooksのkey
define('HATENA_WEBHOOK_KEY', 'YOUR_WEBHOOK_KEY');

// deliciousのユーザーとパスワード
define('DELICIOUS_USER', 'your_your_login');
define('DELICIOUS_PASS', 'your_password');

// bit.lyのユーザーとAPIキー
define('BITLY_USER', 'your_username');
define('BITLY_KEY', 'YOUR_BITLY_API_KEY');

// twitterのユーザーとパスワード
define('TWITTER_USER', 'your_username');
define('TWITTER_PASS', 'your_password');

// CLIで動作確認するためには下のコメントを外して値を変更するのが良いでしょう
//$_POST['key'] = HATENA_WEBHOOK_KEY;
//$_POST['status'] = 'add';
//$_POST['comment'] = '[api][delicious][pear][Services_Delicious][php]ふむ';
//$_POST['title'] ='ヒント: del.icio.us ブックマークを PHP で操作する';
//$_POST['url'] = 'http://www.ibm.com/developerworks/jp/xml/library/x-tipdelicious/index.html';
//$_POST['is_private'] = '0';

if ($_POST['key'] != HATENA_WEBHOOK_KEY) {
	header('HTTP/1.1 403 Forbidden');
	error_log('invalid access from '.$_SERVER['REMOTE_ADDR']);
	exit;
}

if ($_POST['status'] == 'add') {
	error_log('got "add" action from '.$_SERVER['REMOTE_ADDR']);
	// parse tag
	$tags = array();
	if (preg_match_all('/\[(.+)\]/U', $_POST['comment'], $regs, PREG_SET_ORDER)) {
		foreach ($regs as $reg) {
			$tags[] = $reg[1];
		}
	}
	// parse strings
	$comment = preg_replace('/^\[.+\]/', '', $_POST['comment']);
	$title = $_POST['title'];
	$url = $_POST['url'];
	$is_private = $_POST['is_private'];
	$shared = $is_private == '0' ? 'yes' : 'no';
	
	// post to delicious.com
	$sdObj = new Services_Delicious(DELICIOUS_USER, DELICIOUS_PASS);
	$ret = $sdObj->addPost($url, $title, $comment, $tags, null, $shared);
	error_log("add to delicious $url,$title,$comment");
	
	// get short url from bit.ly
	$hcObj =& new HTTP_Client();
	$geturl = 'http://api.bit.ly/shorten?version=2.0.1&login='.BITLY_USER.'&apiKey='.BITLY_KEY.'&longUrl='.$url;
	$ret = $hcObj->get($geturl);
	if ($ret == 200) {
		$res = $hcObj->currentResponse();
		if (isset($res['body'])) {
			$json = json_decode($res['body'], true);
			if(isset($json['results'][$url]['shortUrl'])) {
				$url = $json['results'][$url]['shortUrl'];
				error_log("got bit.ly short url $url");
			}
		}
	}
	
	// post to twitter.com
	$tweet = trim($comment. ' #bookmark '. $title . ' ' . $url);
	$hcObj =& new HTTP_Client(null, array('Authorization'=>'Basic '.base64_encode(TWITTER_USER.':'.TWITTER_PASS)));
	$ret = $hcObj->post('http://twitter.com/statuses/update.xml', array('status' => $tweet));
        $try = 1;
	error_log("twitter post $tweet");
        while ($ret == 408 && $try < 10) {
	    $ret = $hcObj->post('http://twitter.com/statuses/update.xml', array('status' => $tweet));
            $try++;
        }
	if ($ret != 200) {
		error_log("got invalid http status $ret");
	}
	
	header('HTTP/1.1 204 No Content');
	exit;
}

header('HTTP/1.1 202 Accepted');

?>
