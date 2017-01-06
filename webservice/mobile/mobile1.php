<?php
session_start();
header('Content-Type: application/json');

define("MYSQL_USER", "icef-info");
define("MYSQL_PASS", "w6NPaW42x25A5xdD");
define("MYSQL_DB", "icef-info");
define("MYSQL_HOST", "localhost");

$authenticated = false;
$userid = 0;
$userlogin = "";
$username = "";

$conn = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB);

if(isset($_POST['login']) && $_POST['login'] != "" && isset($_POST['password']) && $_POST['password'] != "")
{
	$userlogin = $_POST['login'];
	$password_plain = $_POST['password'];
	$password_crypt = md5($_POST['password']);
	
	$user = $conn->query("SELECT CONCAT_WS(' ', `lastname`, `firstname`) as `username`, `usr_id` FROM `usr_data` WHERE `login`='{$userlogin}' AND `passwd`='{$password_crypt}' LIMIT 1;");
	
	if($user->num_rows > 0)
	{
		$authenticated = true;
		$userrow = $user->fetch_row();
		$userid = $userrow['usr_id'];
		$username = $userrow['username'];
	}
	else
	{
		$authenticated = false;
	}
}
else
{
	$authenticated = false;
}

$data = [
	"phpsessid" => (string) session_id(),
	"authenticated" => (boolean) $authenticated,
	"userid" => (integer) $userid,
	"username" => (string) $username,
];

echo json_encode($data);

?>