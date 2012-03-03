<?php
include 'common.php';

connect_to_database();
create_tables();

switch ($_SERVER['HTTP_ORIGIN']) {
    case 'http://en.wikipedia.org': case 'https://en.wikipedia.org':
    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: PROPFIND, PROPPATCH, COPY, MOVE, DELETE, MKCOL, LOCK, UNLOCK, PUT, GETLIB, VERSION-CONTROL, CHECKIN, CHECKOUT, UNCHECKOUT, REPORT, UPDATE, CANCELUPLOAD, HEAD, OPTIONS, GET, POST');
    header('Access-Control-Allow-Headers: Overwrite, Destination, Content-Type, Depth, User-Agent, X-File-Size, X-Requested-With, If-Modified-Since, X-File-Name, Cache-Control');
    # header('Access-Control-Allow-Methods: GET, OPTIONS');
    # header('Access-Control-Allow-Headers: Content-Type');
    break;
}
if ($_GET['action'] == 'query') {
  if ($_GET['prop'] == 'iswatching') {
    $follower_user_id = get_user_id(wiki_decode($_GET['follower']));
    $followee_user_id = get_user_id(wiki_decode($_GET['followee']));
    $query = "SELECT COUNT(*) AS count FROM " . $followed_users_table . " " .
      "WHERE following_follower = " . $follower_user_id . " " .
      "AND following_followee = " . $followee_user_id;
    $result = mysql_query($query) or die(mysql_error());
    $row = mysql_fetch_array($result);
    if ($row['count']) {
      print 'yes';
    } else {
      print 'no';
    }
  } else {
    print 'prop not set or unknown prop';
  }
} else if ($_GET['action'] == 'follow' || $_GET['action'] == 'unfollow') {
  session_start();
  if (!isset($_SESSION['authenticated']))
  {
    print 'unauthenticated';
    exit();
  }
  $user_id = get_user_id(wiki_decode(sanitize_username($_SESSION['username'])));
  $followee = wiki_decode(sanitize_username($_GET['username']));
  try {
    if ($_GET['action'] == 'follow') {
      add_followee($user_id, $followee);
    } else {
      remove_followee($user_id, $followee);
    }
  }
  catch (NonExistentUserException $e) {
    print 'no such user';
    return;
  }

  print 'ok';
} else {
  print 'action not set or unknown action';
}
?>
