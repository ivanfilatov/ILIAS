<?php
ini_set("display_errors", 1);
ini_set("error_reporting", "E_ALL");
/*
 * What you need in ILIAS:
 *
 * - Activate the ILIAS SOAP interface in "Administration" > "Server" > "SOAP"
 * - Create an ILIAS user that should be used, in our example: user "soap", password "soappw"
 * - Login manually with this user in ILIAS and accepct the user agreement. It is a common issue (at least in older
 *   ILIAS versions) that access for the soap user has been denied, because of the missing agreement.
 */

header('Content-Type: application/json');

/*
 * Modify the following variables to match your ILIAS environment
 */
$ilias_base_url = "http://127.0.0.1/";   // this your base ILIAS url, you should use https in productive environments
$ilias_client = "icef";                                 // the client ID (see ILIAS setup) of your ILIAS client
$command = $_POST['command'];
$outputData = [];

const COMMAND_LOGIN = "login";
const COMMAND_USERID = "getUserIdBySid";
const COMMAND_GETUSERXML = "getUserXML";
const COMMAND_GETUSERSFORCONTAINER = "getUsersForContainer";
const COMMAND_GETTREECHILDS = "getTreeChilds";
const COMMAND_LOGOUT = "logout";

/*
 * General initialisation
 */

// we use the nusoap lib for our client, see http://sourceforge.net/projects/nusoap/
require_once("./lib/nusoap.php");

// setting up the soap client
$wsdl = $ilias_base_url."/webservice/soap/server.php?wsdl"; // ILIAS url of soap wsdl
$client = new nusoap_client($wsdl, true);


/*
 * SOAP Calls
 *
 * This is the interesting part. You should always start with a call to the login operation. The login operation
 * will (if succeeded) return a session id. You will need this session ID for almost all other operations. When
 * you are finished, you should call the logout operation.
 *
 * You can generate a list of all operations by simply accessing http://<youriliasserver>/webservice/soap/server.php
 * e.g. http://www.ilias.de/docu/webservice/soap/server.php
 *
 * Some of the soap operations make use of XML. You usually find the corresponding DTD or XSD files in the subdirectory
 * "xml" of your ILIAS installation.
 */

$outputData = [];
$output = "";

switch ($command) {
    case COMMAND_LOGIN: {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $loginParams = [
            "client" => $ilias_client,
            "username" => $username,
            "password" => $password,
        ];
        $loginResult = $client->call(COMMAND_LOGIN, $loginParams);
        if ($loginResult && is_string($loginResult)) {
            $outputData = [
                'status' => 1,
                'sessionId' => (string)$loginResult
            ];
        } elseif ($loginResult && isset($loginResult['faultcode']) && isset($loginResult['faultstring'])) {
            $outputData = [
                'status' => 0,
                'errCode' => $loginResult['faultcode'],
                'errStr' => $loginResult['faultstring']
            ];
        } else {
            $outputData = [
                'status' => '-1',
                'errCode' => 'Unknown',
                'errString' => 'Unexpected error.'
            ];
        }
        break;
    }

    case COMMAND_USERID: {
        $sid = $_POST['session'];
        $checkParams = [
            "sid" => $sid,
        ];
        $checkResult = $client->call(COMMAND_USERID, $checkParams);
        if ($checkResult && is_int($checkResult) && $checkResult > 0) {
            $outputData = [
                'status' => 1,
                'userId' => (int)$checkResult
            ];
        } elseif ($checkResult && isset($checkResult['faultcode']) && isset($checkResult['faultstring'])) {
            $outputData = [
                'status' => 0,
                'errCode' => $checkResult['faultcode'],
                'errStr' => $checkResult['faultstring']
            ];
        } else {
            $outputData = [
                'status' => '-1',
                'errCode' => 'Unknown',
                'errString' => 'Unexpected error.'
            ];
        }
        break;
    }

    case COMMAND_GETUSERXML: {
        $sid = $_POST['session'];
        $user_id = $_POST['user_id'];
        $attach_roles = $_POST['attach_roles'];
        $checkParams = [
            "sid" => $sid,
            "user_ids" => [$user_id],
            "attach_roles" => $attach_roles,
        ];
        $getResult = $client->call(COMMAND_GETUSERXML, $checkParams);
        if ($getResult && is_string($getResult)) {
            $outputData = [
                'status' => 1,
                'xml' => (string)$getResult
            ];
        } elseif ($getResult && isset($getResult['faultcode']) && isset($getResult['faultstring'])) {
            $outputData = [
                'status' => 0,
                'errCode' => $getResult['faultcode'],
                'errStr' => $getResult['faultstring']
            ];
        } else {
            $outputData = [
                'status' => '-1',
                'errCode' => 'Unknown',
                'errString' => 'Unexpected error.'
            ];
        }
        break;
    }

    case COMMAND_GETUSERSFORCONTAINER: {
        $sid = $_POST['session'];
        $ref_id = $_POST['ref_id'];
        $attach_roles = $_POST['attach_roles'];
        $active = $_POST['active'];
        $checkParams = [
            "sid" => $sid,
            "ref_id" => $ref_id,
            "attach_roles" => $attach_roles,
            "active" => $active,
        ];
        $getResult = $client->call(COMMAND_GETUSERSFORCONTAINER, $checkParams);
        if ($getResult && is_string($getResult)) {
            $outputData = [
                'status' => 1,
                'xml' => (string)$getResult
            ];
        } elseif ($getResult && isset($getResult['faultcode']) && isset($getResult['faultstring'])) {
            $outputData = [
                'status' => 0,
                'errCode' => $getResult['faultcode'],
                'errStr' => $getResult['faultstring']
            ];
        } else {
            $outputData = [
                'status' => '-1',
                'errCode' => 'Unknown',
                'errString' => 'Unexpected error.'
            ];
        }
        break;
    }

    case COMMAND_GETTREECHILDS: {
        $sid = $_POST['session'];
        $ref_id = $_POST['ref_id'];
        $types = $_POST['types'];
        $user_id = $_POST['user_id'];
        $checkParams = [
            "sid" => $sid,
            "ref_id" => $ref_id,
            "types" => [$types],
            "user_id" => $user_id,
        ];
        $getResult = $client->call(COMMAND_GETTREECHILDS, $checkParams);
        if ($getResult && is_string($getResult)) {
            $outputData = [
                'status' => 1,
                'xml' => (string)$getResult
            ];
        } elseif ($getResult && isset($getResult['faultcode']) && isset($getResult['faultstring'])) {
            $outputData = [
                'status' => 0,
                'errCode' => $getResult['faultcode'],
                'errStr' => $getResult['faultstring']
            ];
        } else {
            $outputData = [
                'status' => '-1',
                'errCode' => 'Unknown',
                'errString' => 'Unexpected error.'
            ];
        }
        break;
    }

    case COMMAND_LOGOUT: {
        $sid = $_POST['session'];
        $logoutParams = [
            "sid" => $sid,
        ];
        $logoutResult = $client->call(COMMAND_LOGOUT, $logoutParams);
        $outputData = $logoutResult;
        break;
    }
}

//ob_clean();
$output = json_encode($outputData);
//die($client->getDebug());
die($output);

/*
// import user, see xml/ilias_user_x_y.dtd in your ilias installation (note that the version 4_5 refers to ILIAS 5.0)
// a simple way to create the XML for the import is the excel sheet, provided at http://www.ilias.de/docu/goto_docu_grp_4626.html
// another way to get an example is to export existing users in the ILIAS administration
$par = array(
        "sid" => $session_id,
        "folder_id" => -1,                                                      // system user folder
        "usr_xml" => '<?xml version="1.0" encoding="UTF-8"?>'.
                '<Users>'.
                        '<User Id="tim.thaler" Language="de" Action="Insert">'.
                                '<Active><![CDATA[true]]></Active>'.
                                '<Role Id="2" Type="Global" Action="Assign"><![CDATA[Administrator]]></Role>'.
                                '<Login><![CDATA[tim.thaler]]></Login>'.
                                '<Password Type="PLAIN"><![CDATA[testpassword]]></Password>'.
                                '<Gender><![CDATA[m]]></Gender>'.
                                '<Firstname><![CDATA[Tim]]></Firstname>'.
                                '<Lastname><![CDATA[Thaler]]></Lastname>'.
                                '<Email><![CDATA[doesnotexist@ilias.de]]></Email>'.
                        '</User>'.
                '</Users>',
        "conflict_role" => 3,                                           // ignore on conflict
        "send_account_mail" => 0                                        // no account mail
);
$ret = $client->call("importUsers", $par);
echo "Called importUsers.";
var_dump($ret);


// last call: logout
$par = array(
        "sid" => $session_id
);
$ret = $client->call("logout", $par);
echo "Called logout.";
var_dump($ret);
*/

?>