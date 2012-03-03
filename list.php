<?php /* Copyright (c) 2012, Derrick Coetzee (User:Dcoetzee)
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright notice, this
   list of conditions and the following disclaimer.

 * Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

include 'common.php';

$namespaces = array('', 'Talk', 'User', 'User talk', 'Wikipedia', 'Wikipedia talk', 'File', 'File talk', 'MediaWiki', 'MediaWiki talk', 'Template', 'Template talk', 'Help', 'Help talk', 'Category', 'Category talk', 'Portal', 'Portal talk', 'Book', 'Book talk');

$months = array('', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');

function build_date($matches) {
  global $months;
  return $matches[3] . " " . $months[intval($matches[2])] . " " . $matches[1];
}

/* These two functions convert a Mediawiki timestamp to a more human-readable string
   representation. */
function timestamp_to_date($timestamp)
{
  return preg_replace_callback('/([0-9][0-9][0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])/', 'build_date', $timestamp);
}

function timestamp_to_time($timestamp)
{
  return preg_replace('/([0-9][0-9][0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])/', '\4:\5', $timestamp);
}

function tusc_login_valid($username, $language, $project, $password) {
  # Note: TUSC script does not work over HTTPS
  $url = 'http://toolserver.org/~magnus/tusc.php';
  $fields = array(
		  'check'=>1,
		  'botmode'=>1,
		  'user'=>$username,
		  'language'=>$language,
		  'project'=>$project,
		  'password'=>$password,
		  );
  $result = post($url, $fields);
  if ($result == '1') {
    return 'yes';
  } elseif ($result == '0') {
    return 'no';
  } elseif ($result[0] == 'W') {
    return 'wrongproject';
  } else {
    die("Unexpected result from TUSC");
  }
}

function get_full_title($namespace, $title) {
  global $namespaces;
  if ($namespaces[$namespace]) {
    return wiki_decode($namespaces[$namespace] . ':' . $title);
  } else {
    return wiki_decode($title);
  }
}

function wikitext_to_html($text, $title) {
  while (preg_match('/(.*)\[\[([^\|\]]*)\|([^\]]*)\]\](.*)/', $text, $m)) {
    $text = $m[1] . '<a href="//en.wikipedia.org/wiki/' . wiki_urlencode($m[2]) . '">' . htmlspecialchars($m[3]) . '</a>' . $m[4];
  }
  while (preg_match('/(.*)\[\[([^\]]*)\]\](.*)/', $text, $m)) {
    $text = $m[1] . '<a href="//en.wikipedia.org/wiki/' . wiki_urlencode($m[2]) . '">' . htmlspecialchars($m[2]) . '</a>' . $m[4];
  }
  while (preg_match('/(.*)\/\*(.*?)\*\/(.*)/', $text, $m)) {
    $text = $m[1] . '<a href="//en.wikipedia.org/wiki/' . wiki_urlencode($title) . '#' . str_replace('%', '.', wiki_urlencode(trim($m[2]))) . '">→</a><font color="#888888">' . trim($m[2]) . ':</font>' . $m[3];
  }
  return $text;
}

function cmp_timestamp_rev($entrya, $entryb) {
  return $entrya['timestamp'] < $entryb['timestamp'];
}

# Entry point
setup_session();

$username = html_entity_decode($_POST['username'], ENT_QUOTES, 'UTF-8');
$language = html_entity_decode($_POST['language'], ENT_QUOTES, 'UTF-8');
$project  = html_entity_decode($_POST['project'],  ENT_QUOTES, 'UTF-8');
$password = html_entity_decode($_POST['password'], ENT_QUOTES, 'UTF-8');
if (!isset($_SESSION['authenticated']) && tusc_login_valid($username, $language, $project, $password) != 'yes') {
echo <<<EOT
<html><head><title>Invalid or expired TUSC login</title></head>
<body><h1>Invalid or expired TUSC login</h1>
<p>Your TUSC login was invalid or has expired. Please <a href=".">log in again</a>. Make sure you specified the correct username, language, project, and password. Note that for Wikimedia Commons you must specify "commons" for the language and "wikimedia" for the project.</p>
<p>If you have not yet created a TUSC login, <a href="//toolserver.org/~magnus/tusc.php">register one first</a>.</body>
</body></html>
EOT;
exit();
}

# Get starting time to measure time elapsed later.
$time_start = microtime_float();

connect_to_database();

if (!isset($_SESSION['authenticated']))
{
  session_regenerate_id();
  $_SESSION['initiated'] = true;
  $_SESSION['authenticated'] = true;
  $_SESSION['username'] = $username;
}
header("Content-Type: text/html; charset=utf-8");

$user_name = wiki_decode(sanitize_username($_SESSION['username']));
$user_name_url = wiki_urlencode($user_name);

echo <<<EOT
<html><head><title>Followed users</title>
<link rel="stylesheet" type="text/css" href="class.css" />
</head>
<body>
<p><a href="//en.wikipedia.org/wiki/User:$user_name_url">$user_name</a> <a href=".">Log in page</a> <a href="logout.php">Log out</a></p>
<h1>Followed users</h1>
<form name="" action="list.php" method="get">
Follow a new user: <input type="text" name="follow"> <input type="submit" value="Follow" />
</form>
<form name="" action="list.php" method="get">
Unfollow a user: <input type="text" name="unfollow"> <input type="submit" value="Unfollow" />
</form>
EOT;

create_tables();
$user_id = get_user_id($user_name);

try {
  if ($_GET['follow']) {
    $followee = wiki_decode(sanitize_username($_GET['follow'], ENT_QUOTES, 'UTF-8'));
    add_followee($user_id, $followee);
  }

  if ($_GET['unfollow']) {
    $followee = wiki_decode(sanitize_username($_GET['unfollow'], ENT_QUOTES, 'UTF-8'));
    remove_followee($user_id, $followee);
  }
}
catch (NonExistentUserException $e) {
  print '<p><font color="#ff0000">Specified user does not exist.</font></p>';
}

$query = "SELECT following_followee, following_followee_text FROM " . $followed_users_table . " " .
  "WHERE following_follower = " . $user_id;
$result = mysql_query($query) or die(mysql_error());
$entries = array();
$users_with_no_edits=array();
while ($row = mysql_fetch_array($result)) {
  if ($row['following_followee'] == 0) {
    $query2 = "SELECT rev_id, rev_len, rev_minor_edit, rev_timestamp, rev_comment, page_id, page_namespace, page_title FROM revision INNER JOIN page ON rev_page=page_id " .
              "WHERE rev_user_text='" . mysql_real_escape_string($row['following_followee_text']) . "' ";
    $followee_username = $row['following_followee_text'];
  } else {
    $query2 = "SELECT rev_id, rev_len, rev_minor_edit, rev_timestamp, rev_comment, page_id, page_namespace, page_title, user_name FROM (revision INNER JOIN page ON rev_page=page_id) INNER JOIN user ON rev_user=user_id " .
              "WHERE rev_user=" . $row['following_followee'] . " ";
  }
  $query2 .=  "ORDER BY rev_timestamp DESC LIMIT 1";
  $result2 = mysql_query($query2) or die(mysql_error());
  $row2 = mysql_fetch_array($result2);
  if (!$row2) {
    $users_with_no_edits[] = $followee_username;
    continue; # This user has no edits ever
  }
  if ($row['following_followee'] != 0) {
    $followee_username = wiki_decode($row2['user_name']);
  }
  $full_title = get_full_title($row2['page_namespace'], $row2['page_title']);
  $entries[] = array(
		     'id' => $row2['rev_id'],
		     'len' => $row2['rev_len'],
		     'minor_edit' => $row2['rev_minor_edit'],
		     'timestamp' => $row2['rev_timestamp'],
		     'editsummary' => wikitext_to_html($row2['rev_comment'], $full_title),
		     'page_id' => $row2['page_id'],
		     'title' => $full_title,
		     'username' => $followee_username
		     );
}

usort($entries, 'cmp_timestamp_rev');

$can_block = in_array('block', get_user_rights($user_name));

$prev_date = '';
print '<ul>';
foreach( $entries as $entry ) {
  $date = timestamp_to_date($entry['timestamp']);
  if ($date != $prev_date) {
    print "</ul><h4>$date</h4><ul>";
  }
  $prev_date = $date;
  print '<li>';

  $query = "SELECT rev_id, rev_len FROM revision " .
    "WHERE rev_timestamp < " . $entry['timestamp'] . " " .
    "AND rev_page = " . $entry['page_id'] . " " .
    "ORDER BY rev_timestamp DESC " .
    "LIMIT 1";
  $result = mysql_query($query) or die(mysql_error());
  $row = mysql_fetch_array($result);
  if ($row) {
    $old_rev_id = $row['rev_id'];
    $old_rev_len = $row['rev_len'];
  } else {
    $old_rev_id = '';
    $old_rev_len = 0;
  }

  $title_encoded = wiki_urlencode($entry['title']);
  $username_encoded = wiki_urlencode($entry['username']);
  print '(<a href="//en.wikipedia.org/w/index.php?title=' . $title_encoded . "&diff=" . $entry['id'] . "&oldid=$old_rev_id" . '">diff</a> | <a href="//en.wikipedia.org/w/index.php?title=' . $title_encoded . '&action=history">hist</a>)';
  print " . . ";
  if ($old_rev_id == '') {
    print '<b>N</b> ';
  }
  if ($entry['minor_edit']) {
    print '<b>m</b> ';
  }
  print '<a href="//en.wikipedia.org/wiki/' . $title_encoded . '">' . $entry['title'] . '</a>; ';
  $time = timestamp_to_time($entry['timestamp']);
  print $time;
  print " . . ";
  print "(" . encode_signed_number($entry['len'] - $old_rev_len) . ")";
  print " . . ";
  print '<a href="//en.wikipedia.org/wiki/User:' . $username_encoded . '">' . htmlspecialchars($entry['username']) . '</a> ';
  print '(<a href="//en.wikipedia.org/wiki/User_talk:' . $username_encoded . '">talk</a>' .
    ' | <a href="//en.wikipedia.org/wiki/Special:Contributions/' . $username_encoded . '">contribs</a>';
  if ($can_block) {
    print ' | <a href="//en.wikipedia.org/wiki/Special:Block/' . $username_encoded . '">block</a>';
  }
  print ') ';
  print '(<i>' . $entry['editsummary']. '</i>) ';
  print '[<a href="list.php?unfollow=' . urlencode($entry['username']) . '">unfollow</a>]';
  print '</li>';
}
print '</ul>';

if (count($users_with_no_edits) > 0) {
  print '<h4>Followed users with no edits</h4>';
  print '<ul>';
  foreach ($users_with_no_edits as $username) {
    $username_encoded = wiki_urlencode($username);
    print '<li>';
    print '<a href="//en.wikipedia.org/wiki/User:' . $username_encoded . '">' . htmlspecialchars($username) . '</a> ';
    print '(<a href="//en.wikipedia.org/wiki/User_talk:' . $username_encoded . '">talk</a>';
    if ($can_block) {
      print ' | <a href="//en.wikipedia.org/wiki/Special:Block/' . $username_encoded . '">block</a>';
    }
    print ") ";
    print '[<a href="list.php?unfollow=' . urlencode($username) . '">unfollow</a>]';
    print '</li>';
  }
  print '</ul>';
}

$time_delta = microtime_float() - $time_start;
printf('<p><small>This report generated by <a href="//toolserver.org/~dcoetzee/followedusers/">Followed users</a> at ' . date('c')  . ' in %0.2f sec.</small></p>', $time_delta);
echo '</body></html>';
?>