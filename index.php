<?php
namespace Kaktus;
    //setup a session.
    require_once("src/Kaktus/SQL.php");

    session_start();
    $sql = new SQL();
    $ses_id = session_id();
    if(empty($ses_id)){
        $ses_id = session_id();
        $_SESSION["loggedIn"] = false;
    }

    $sessionUpdate = $sql->updateSession();
    if ($sessionUpdate['errors']) {
        print_r($sessionUpdate["errors"]);
        die();
    }

    if (isset($_POST['type'])) {
        $returnData = new \stdClass();
        //get the "type" into a more easy to access format.
        $postType = $_POST['type'];

        //login
        if ($postType == "login" && isset($_POST['user']) && isset($_POST['password'])) {
            if ($_SESSION["loggedIn"] == true) {
                $returnData->errors[] = "Already logged in - please log out first.";
            } else {
                $result = $sql->login($_POST["user"], $_POST["password"]);
                if ($result['success']) {
                    $_SESSION["loggedIn"] = true;
                    $_SESSION["username"] = $_POST['user'];
                    $_SESSION["userId"] = $result['userId'];
                    $returnData->success = $result['success'];
                    $returnData->action = "reload";
                } else {
                    $returnData->fail = true;
                    $returnData->errors = $result["errors"];
                }
            }
        }

        //register
        else if ($postType == "register"){
            if (!$_POST['user'] || !$_POST['password']){
                $returnData->errors[] = "Missing registration information.";
            } else if ($_SESSION["loggedIn"] == true){
                $returnData->errors[] = "Already logged in - please log out first.";
            } else {
                $result = $sql->createUser($_POST["user"], $_POST["password"]);
                if ($result['success']) {
                    //after register, send login from client side again.
                    $returnData->success = $result['success'];
                } else {
                    $returnData->fail = true;
                    $returnData->errors = $result["errors"];
                }
            }
        }
        //simple ping.

        else if($postType == "ping"){
            return json_encode("Ping!");
        }
        //all following tasks require user to be logged in.
         else if ($_SESSION["loggedIn"]){
             //logout
            if ($postType == "logout"){
                 $_SESSION["loggedIn"] = false;
                 $_SESSION["username"] = "";
                 $_SESSION["userId"] = "";
                 $returnData->success = "Logging out...";
                 $returnData->action = "reload";
            }
            //create a new group
            else if ($postType == "newGroup") {
                //group must have a name.
                if (isset($_POST['name']) && $_POST['name'] != "") {
                    $result = $sql->createBoard($_POST['name'], $_POST['description']);
                    if ($result['success']) {
                        $returnData->success = $result['success'];
                        $returnData->action = "reload";
                    } else {
                        $returnData->errors[] = $result['errors'];
                        $returnData->fail = true;
                    }
                } else {
                    $returnData->errors[] = "A name is required.";
                    $returnData->fail = true;
                }
            }
            //invite.
            else if ($postType=="inviteBoard"){
                //board ID and username must be specified
                if (isset($_POST['boardId']) && isset($_POST['userName'])){

                    $result = $sql->sendInvite($_SESSION['userId'], $_POST['boardId'], $_POST['userName']);
                    if (isset($result['success']) && $result['success']){
                        $returnData->success = $result['success'];
                        $returnData->action = "reload";
                    } else if(!isset($result['errors'])) {
                        $returnData->errors[] = "Unspecified error.";
                        $returnData->fail = true;
                    } else {
                        $returnData->errors[] = $result['errors'];
                        $returnData->fail = true;
                    }
                } else {
                    $returnData->errors[] = "Missing invite data.";
                    $returnData->fail = true;
                }
            }

            else if ($postType=="inviteAction"){
                if (isset($_POST['inviteId']) && isset($_POST['action'])){
                    $result = $sql->updateInvite($_SESSION['userId'], $_POST['action'], $_POST['inviteId']);
                    if (isset($result['success']) && $result['success']){
                        $returnData->success = $result['success'];
                        $returnData->action = "reload";
                    } else if(!isset($result['errors'])) {
                        $returnData->errors[] = "Unspecified error.";
                        $returnData->fail = true;
                    } else {
                        $returnData->errors[] = $result['errors'];
                        $returnData->fail = true;
                    }
                } else {
                    $returnData->errors[] = "Invalid request.";
                    $returnData->fail = true;
                }
            }
            else {
                $returnData->errors[] = "Invalid command.";
                $returnData->fail = true;
            }
        }

        else{
            $returnData->errors[] = sprintf("Unknown command sent: %s", $postType);
        }

        echo(json_encode($returnData));
    }
    else {
        if ($_SESSION['loggedIn'] == false) {
            $bodyData = "front/html/login.php";
            include("front/html/body.php");
            return;
        }

        $bodyData="";
        //the regular "gets" go here.
        if(!isset($_GET['page'])) {
            //load these by default. That way, if nothing is loaded, we will revert to these.
            $myBoards = $sql->getBoards($_SESSION['userId']);
            $myInvites = $sql->getInvites($_SESSION['userId']);
            $bodyData = "front/html/dashboard.php";
        } else {
            $targetPage = $_GET['page'];
            if ($targetPage == "board") {
                //get the ID.
                $targetId = $_GET['board'];
                //if accessible, load the board. Otherwise refuse.
                if($sql->isInBoard($_SESSION['userId'], $targetId)){
                    $bodyData = "front/html/board.php";
                } else{
                    echo("No access to board.");
                }
            }
        }

        include("front/html/body.php");
    }
?>