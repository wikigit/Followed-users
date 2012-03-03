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

/* Name of the table storing who is following who. You should set it to
   a table you have access to. */
$followed_users_table = 'u_dcoetzee.followedusers_following';

/* Get current time as a floating-point number of seconds since some reference
   point. */
function microtime_float()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

function post($url, $fields) {
  $fields_string = '';
  foreach ($fields as $key=>$value) {
    $fields_string .= $key . '=' . urlencode($value) . '&';
  }
  rtrim($fields_string,'&');

  $ch = curl_init();
  curl_setopt($ch,CURLOPT_URL,$url);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
  curl_setopt($ch,CURLOPT_POST,count($fields));
  curl_setopt($ch,CURLOPT_POSTFIELDS,$fields_string);
  curl_setopt($ch,CURLOPT_USERAGENT,'Toolserver: followedusers (http://toolserver.org/~dcoetzee/followedusers/)');
  $result = curl_exec($ch);
  curl_close($ch);
  return $result;
}

function get_user_rights($username) {
  $url = 'https://en.wikipedia.org/w/api.php';
  $fields = array(
		  'action'=>'query',
		  'format'=>'xml',
		  'list'=>'allusers',
		  'aufrom'=>$username,
		  'auto'=>$username,
		  'auprop'=>'rights',
		  );
  $xml = post($url, $fields);
  preg_match_all('/<r>([^<]*)/', $xml, $matches);
  return $matches[1];
}

function connect_to_database() {
  $toolserver_mycnf = parse_ini_file("/home/".get_current_user()."/.my.cnf");
  $db = mysql_connect('enwiki-p.userdb.toolserver.org', $toolserver_mycnf['user'], $toolserver_mycnf['password']) or die(mysql_error());
  mysql_select_db('enwiki_p', $db) or die(mysql_error());
}

function create_tables() {
  global $followed_users_table;
  mysql_query("CREATE TABLE IF NOT EXISTS " . $followed_users_table . "(" .
	      "   following_follower int unsigned NOT NULL, INDEX(following_follower)," .
	      "   following_followee int unsigned NOT NULL, INDEX(following_followee)," .
	      "   PRIMARY KEY(following_follower, following_followee)" .
	      ")") or die(mysql_error());
}

class NonExistentUserException extends Exception { }

function get_user_id($user_name) {
  $query = "SELECT user_id FROM user WHERE user_name='" . mysql_real_escape_string($user_name) . "'";
  $result = mysql_query($query) or die(mysql_error());
  $row = mysql_fetch_array($result);
  if ($row) {
    return $row['user_id'];
  } else {
    return 0;
  }
}

function is_ip_address($str) {
  $match = preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $str);
  if ($match) {
    $nums = preg_split('/\./', $str);
    foreach ($nums as $num) {
      if ($num > 255) return false;
    }
    return true;
  }
  return false;
}

function get_user_id_and_text($user_name) {
  $user_id = get_user_id($user_name);
  if ($user_id == 0) {
    if (is_ip_address($user_name)) {
      $user_text = "'" . mysql_real_escape_string($user_name) . "'";
    } else {
      throw new NonExistentUserException();
    }
  } else {
    $user_text = "''";
  }
  return array($user_id, $user_text);
}

function ucfirst_utf8($str) {
  return mb_convert_case(mb_substr($str, 0, 1, "UTF-8"), MB_CASE_UPPER, "UTF-8") . mb_substr($str, 1, PHP_INT_MAX, "UTF-8");
}

function sanitize_username($user_name) {
  return ucfirst_utf8(trim(html_entity_decode($user_name, ENT_QUOTES, 'UTF-8')));
}

function wiki_urlencode($name) {
  return urlencode(str_replace(' ', '_', $name));
}

function wiki_decode($name) {
  return str_replace('_', ' ', $name);
}

function encode_signed_number($number) {
  if ($number > 500) {
    return "<font color=\"#008800\"><b>+$number</b></font>";
  } else if ($number > 0) {
    return "<font color=\"#008800\">+$number</font>";
  } else if ($number == 0) {
    return "<font color=\"#888888\">0</font>";
  } else if ($number > -500) {
    return "<font color=\"#880000\">$number</font>";
  } else {
    return "<font color=\"#880000\"><b>$number</b></font>";
  }
}

function add_followee($follower_user_id, $followee) {
  global $followed_users_table;
  list ($followee_user_id, $followee_user_text) = get_user_id_and_text($followee);
  $query = "SELECT COUNT(*) AS count FROM " . $followed_users_table . " " .
    "WHERE following_follower = " . $follower_user_id . " AND following_followee=" . $followee_user_id . " AND following_followee_text = " . $followee_user_text;
  $result = mysql_query($query) or die(mysql_error());
  $row = mysql_fetch_array($result);
  if ($row['count'] == 0) {
    $query = "INSERT INTO " . $followed_users_table . " " .
      "VALUES (" . $follower_user_id . "," . $followee_user_id . "," . $followee_user_text . ")";
    mysql_query($query) or die(mysql_error());
  }
}

function remove_followee($follower_user_id, $followee) {
  global $followed_users_table;
  list ($followee_user_id, $followee_user_text) = get_user_id_and_text($followee);
  $query = "DELETE FROM " . $followed_users_table . " " .
    "WHERE following_follower = " . $follower_user_id . " " .
    "AND following_followee = " . $followee_user_id . " " .
    "AND following_followee_text = " . $followee_user_text;
  mysql_query($query) or die(mysql_error());
}

function setup_session() {
  session_start();

  if (!isset($_SESSION['initiated']))
  {
    session_regenerate_id();
    $_SESSION['initiated'] = true;
  }
}

?>
