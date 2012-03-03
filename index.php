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
setup_session();
?>
<html>
<head>
<title>Followed users</title>
</head>
<body>
<h1>Followed users</h1>

<p><i>Followed users</i> is an English Wikipedia tool that allows you to keep a list of users that you follow, similar to a watchlist, and lists the most recent edit by each user. The idea was conceived by <a href="//en.wikipedia.org/wiki/User:Quintucket">User:Quintucket</a>.</p>

<p>This has many uses, such as keeping an eye on warned users, collaborating with users in a team, mentoring, following students in a class, or just for learning from the edits of others. All followed user lists are public.</p>

<p><b>Javascript features</b>: To add "Follow user"/"Unfollow user" options to your toolbox and a link to Followed users to the upper-right, <a href="//en.wikipedia.org/w/index.php?title=Special:MyPage/common.js&action=edit&editintro=User:Dcoetzee/Followed_users_js_notice">edit your common.js</a> and add "<code>importScript("User:Dcoetzee/Followed_users.js");</code>".</p>

<h2>View my followed users list</h2>

<?php
if (!isset($_SESSION['authenticated'])) {
echo <<<EOT
Log in to view your list of followed users and add and remove users from your list.

<p><b>TUSC login</b></p>

<form name="list" action="list.php" method="post">
Username: <input type="text" name="username"><br/>
Language: <input type="text" name="language"> (e.g., "en" or "commons")<br/>
Project: <input type="text" name="project"> (e.g. "wikipedia" or "wikimedia")<br/>
Password: <input type="password" name="password"><br/>
<input type="submit" value="Log in" />
</form>

<p><a href="//toolserver.org/~magnus/tusc.php">Register a new TUSC login</a></p>
EOT;
} else {
echo <<<EOT
<p>You are already logged in.</p>

<p><a href="list.php">View your followed users list</a></p>

<p><a href="logout.php">Log out</a></p>
EOT;
}
?>

<h2>View which users others are following</h2>

<?php
connect_to_database();
$query = "SELECT COUNT(DISTINCT following_follower) AS count FROM " . $followed_users_table;
$result = mysql_query($query);
if ($result) {
  $row = mysql_fetch_array($result);
  print "<p>There are currently <b>" . $row['count'] . "</b> users using Followed users.</p>";
}
?>

<form name="following" action="followedby.php" method="get">
Who is this user following: <input type="text" name="username"><input type="submit" value="View" />
</form>

<form name="followedby" action="following.php" method="get">
Who is following this user: <input type="text" name="username"><input type="submit" value="View" />
</form>

<h2>Notes</h2>

<p>If you have any questions about <i>Followed users</i>, please contact its author Derrick Coetzee at <a href="//en.wikipedia.org/wiki/User_talk:Dcoetzee">his talk page on English Wikipedia</a>.</p>

<p>The PHP source for <i>Followed users</i> is available under the <a href="http://www.opensource.org/licenses/bsd-license.php">Simplified BSD License</a>. It must be on Toolserver to run. (<a href="../downloads/followedusers.tar.gz">.tar.gz</a>) (<a href="../downloads/followedusers.zip">.zip</a>) Latest version <a href="https://github.com/wikigit/Followed-users">available from Github</a>.</p>

</body>
</html>
