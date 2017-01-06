<?php
header('Content-Type: application/json');

require_once("Services/Init/classes/class.ilInitialisation.php");
ilInitialisation::initILIAS();

if($ilAuth->getAuth())
{
    $data = [
        "phpsessid" => (string) session_id(),
        "authenticated" => (boolean) true,
        "userid" => (integer) $ilUser->getId(),
        "username" => (string) $ilUser->getLastname()." ".$ilUser->getFirstname(),
    ];
}
else
{
    $data = [
        "phpsessid" => (string) "",
        "authenticated" => (boolean) false,
        "userid" => (integer) 0,
        "username" => (string) "",
    ];
}

echo json_encode($data);

?>