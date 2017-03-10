<?php
namespace Kaktus;
use \PDO;

class SQL
{
    private $conn;

    private function connect(){
        $this->conn = new PDO(sprintf('mysql:host=%s;dbname=%s', DBHOST, DBNAME), DBUSER,
            DBPWD, array(PDO::ATTR_PERSISTENT => true));
    }

    //user interactions.

    //login.
    public function login($usr, $pwd){
        $returnData = [];
        $errors = [];

        $this->connect();
        $user = $this->conn->prepare("select `userId`, `username`, `password`, `active` from users where username = :username");
        $user->bindParam(':username', $usr);
        $user->execute();

        $data = $user->fetch();
        if (!$data){
            //no user found.
            $errors[] = "Invalid login info - try again!";
        } else {
            //now let's compare the password.
            if (!password_verify($pwd, $data['password'])){
                $errors[] = "Invalid login info - try again!";
            } else {
                //if it's disabled, it gets a different result for once.
                if ($data['active']==0) {
                    $errors[] = "Account disabled. Please contact kaktus@drtorte.net for details.";
                } else {
                    $returnData['success'] = "Logged in!";
                    $returnData['userId'] = $data['userId'];
                }
            }
        }

        $returnData['errors'] = $errors;
        return $returnData;
    }

    //create a user, usually by registration.
    public function createUser($usr, $pwd){
        $returnData = [];
        $errors = [];
        //check that user is a valid email.
        if(!filter_var($usr, FILTER_VALIDATE_EMAIL)){
            $errors[] = ("Invalid email.");
        }

        //now do a regex check for the password security. Quick and easy rip from
        // http://stackoverflow.com/questions/10752862/password-strength-check-in-php

        if (strlen($pwd) < 8) {
            $errors[] = "Password too short!";
        }

        if (!preg_match("#[0-9]+#", $pwd)) {
            $errors[] = "Password must include at least one number!";
        }

        if (!preg_match("#[a-zA-Z]+#", $pwd)) {
            $errors[] = "Password must include at least one letter!";
        }

        if (count($errors) > 0) {
            $returnData['errors'] = $errors;
            return ($returnData);
        }

        $this->connect();
        $users = $this->conn->prepare("select `id` from users where username = :username");
        $users->bindParam(':username', $usr);
        $users->execute();
        $data = $users->fetchAll();
        if (count($data) > 0){
            $errors[] = "Username already exists!";
        }

        if (count($errors) > 0) {
            $returnData['errors'] = $errors;
            return ($returnData);
        }

        $query= $this->conn->prepare("INSERT INTO `users` (`username`, `password`, `name`)
VALUES (:username, :password, :username)");
        $password = password_hash($pwd, PASSWORD_DEFAULT);
        $query->bindParam(':username', $usr);
        $query->bindParam(':password', $password);
        $query->execute();

        $returnData['success'] = "Registered.";
        return ($returnData);
    }

    //locate user by username.
    public function findUser($usr){
        $returnData = [];
        $errors = [];
        $this->connect();
        $user = $this->conn->prepare('SELECT * FROM `users` WHERE `username`=:userName AND `active`=1');
        $user->bindParam(':userName', $usr);
        if (!$user->execute()){
            return $this->error("DQL Error");
        }
        $userData = $user->fetch();
        if (!$userData){
            return $this->error("No user");
        } else {
            $returnData['success'] = $userData['userId'];
        }
        return $returnData;
    }
    //board interactions

    //create a board.
    public function createBoard($name, $description = "") {
        $returnData = [];
        $errors = [];
        $this->connect();
        $board = $this->conn->prepare('INSERT INTO `boards` (`name`, `description`, `createdBy`, `owner`) VALUES
          (:name, :description, :thisId, :thisId)');
        $board->bindParam(':name', $name);
        $board->bindParam(':description', $description);
        $board->bindParam(':thisId', $_SESSION['userId']);
        if (!$board->execute()) {
            $errors[]= "Unable  to add Board";
            $returnData['errors'] = $errors;
        }else {
            $lastID = $this->conn->lastInsertId();
            //auto create owner entry.
            if (!$this->addUserToBoard($_SESSION['userId'], $lastID)) {
                $errors[] = "Unable to add Board User";
            }
        }
        if (count($errors) > 0) {
            $returnData['errors'] = $errors;
            return ($returnData);
        }
        $returnData['success'] = "Board created!";
        return $returnData;
    }

    //add user to board. false on fail, true on success.
    private function addUserToBoard($userId, $boardId){
        $this->connect();
        $sql = $this->conn->prepare("INSERT INTO `boardMembers` (`boardId`,`userId`,`active`) VALUES(:boardId, :userId, true)");
        $sql->bindParam(":boardId", $boardId);
        $sql->bindParam(":userId", $userId);

        if (!$sql->execute()){
            return false;
        }
        return true;
    }

    //fetches board that user is a member of.
    public function getBoards($userId){
        $this->connect();

        $boards = $this->conn->prepare("select * from `boards` LEFT JOIN `boardMembers`
ON `boardMembers`.`boardId`=`boards`.`boardId` WHERE `boardMembers`.`userId` = :userId AND `boardMembers`.`active` = 1");
        $boards->bindParam(":userId", $userId);
        if (!$boards->execute()){
            return $this->error("SQL Error");
        };
        $result = $boards->fetchAll();
        //get all users.
        $boardUsers = $this->conn->prepare("SELECT `users`.`userId`, `users`.`username`, `boardMembers`.`userId`, `boardMembers`.`boardId` from `users` left join `boardMembers`
on `users`.`userId`=`boardMembers`.`userId` WHERE `boardMembers`.`active`=1 and `users`.`active` =1 AND `boardMembers`.`boardId`=:boardId");
        foreach($result as $key=>$value){
            $boardUsers->bindParam(":boardId", $value['boardId']);
            $boardUsers->execute();
            $result[$key]['members'] =$boardUsers->fetchAll();
        }
        //var_dump($result);
        return ($result);
    }

    //fetches all a user's invites.
    public function getInvites($userId){
        $returnData = [];
        $this->connect();
        $invites = $this->conn->prepare("SELECT DISTINCT `invites`.*, `users`.`username`, `users`.`userId`,
`boards`.`name`, `boards`.`description` from `invites`
left join `users` on `users`.`userId`=`invites`.`receiverId`
left join `boards` on `boards`.`boardId` = `invites`.`boardId`
WHERE `invites`.`receiverId`=:userId
AND `users`.`active`=1 AND `invites`.`active`=1 AND `invites`.`accepted` IS NULL");
        $invites->bindParam("userId", $userId);
        if (!$invites->execute()){
            return $this->error("SQL Error");
        }
        $returnData['myInvites'] = $invites->fetchAll();

        $invites = $this->conn->prepare("SELECT DISTINCT `invites`.*, `users`.`username`, `users`.`userId`,
`boards`.`name`, `boards`.`description` from `invites`
left join `users` on `users`.`userId`=`invites`.`senderId`
left join `boards` on `boards`.`boardId` = `invites`.`boardId`
WHERE `invites`.`senderId`=:userId
AND `users`.`active`=1 AND `invites`.`active`=1 AND `invites`.`accepted` IS NULL");
        $invites->bindParam("userId", $userId);
        if (!$invites->execute()){
            return $this->error("SQL Error");
        }

        $returnData['sentInvites'] = $invites->fetchAll();
        return ($returnData);
    }

    //update invite.
    public function updateInvite($userId, $action, $inviteId){
        $returnData = [];
        $this->connect();
        $sql = $this->conn->prepare("SELECT * FROM `invites` where `inviteId`=:inviteId AND `active`=1
AND `accepted` IS NULL and `senderId`=:userId OR `receiverId`=:userId");
        $sql->bindParam(":inviteId", $inviteId);
        $sql->bindParam(":userId", $userId);
        if (!$sql->execute()){
            return $this->error("SQL Error on 230");
        };

        $invite = $sql->fetch();
        if (!$invite){
            return $this->error("No invite found.");
        }
        $acceptedOn = (new \DateTime())->format('Y-m-d H:i:s');
        if ($action == "acceptInvite" && $invite['receiverId'] == $userId){
            $sql = $this->conn->prepare("UPDATE `invites` set `accepted`=1 AND `acceptedOn`=:acceptedOn WHERE `inviteId`=:inviteId");
            $sql->bindParam(":inviteId", $inviteId);
            $sql->bindParam(":acceptedOn", $acceptedOn);
            if (!$this->addUserToBoard($userId, $invite['boardId'])){
                return $this->error("Unable to add user to board");
            }
            if (!$sql->execute()){
                return $this->error("SQL Error 241 " . $acceptedOn);
            }
            $returnData['success'] = "Accepted invite.";
        } else if ($action == "declineInvite" && $invite['receiverId'] == $userId){
            $sql = $this->conn->prepare("UPDATE `invites` set `accepted`=0 AND `acceptedOn`=:acceptedOn WHERE `inviteId`=:inviteId");
            $sql->bindParam(":inviteId", $inviteId);
            $sql->bindParam(":acceptedOn", $acceptedOn);
            if (!$sql->execute()){
                return $this->error("SQL Error 247");
            };
            $returnData['success'] = "Declined invite.";
        } else if ($action == "withdrawInvite" && $invite['senderId'] == $userId){
            $sql = $this->conn->prepare("UPDATE `invites` set `active`=0 AND `acceptedOn`=:acceptedOn WHERE `inviteId`=:inviteId");
            $sql->bindParam(":inviteId", $inviteId);
            $sql->bindParam(":acceptedOn", $acceptedOn);
            if (!$sql->execute()){
                return $this->error("SQL Error 253");
            };
            $returnData['success'] = "Invite withdrawn.";
        } else {
            return $this->error("Invalid invite command");
        }

        return ($returnData);
    }

    //boolean return on whether user is in board or not.
    public function isInBoard($userId, $boardId){
        $this->connect();
        $boards = $this->conn->prepare("select * from `boards` LEFT JOIN `boardMembers`
        ON `boardMembers`.`boardId`=`boards`.`boardId` WHERE `boardMembers`.`userId` = :userId AND `boardMembers`.`active` = 1
        AND `boards`.`boardId` = :boardId");

        $boards->bindParam(":userId", $userId);
        $boards->bindParam(":boardId", $boardId);
        $boards->execute();
        $data = $boards->fetch();
        if (!$data){
            return false;
        }
        return true;
    }

    //task interactions

    //create a task.
    public function createTask($name, $description, $boardId, $userId, $dueDate = "", $active = true, $parentTaskId = null){
        $returnData = [];
        $errors = [];

        //echo($userId . "    " . $boardId);
        $this->connect();
        //first confirm that the user is in board.
        if (!$this->isInBoard($userId, $boardId)){
            $errors[] = "Board, user, or link not found.";
            $returnData['errors'] = $errors;
            return ($returnData);
        }

        //try to use the date.
        try {
            $date = \DateTime::createFromFormat('Y-m-d', $dueDate);
            echo($date->format('Y-m-d'));
        } catch (Exception $e) {
            $errors[] = "Invalid date format.";
            $returnData['errors'] = $errors;
        }
        $dueDate = $date->format('Y-m-d H:i:s');
        echo("\nDue Date:" . $dueDate);
        $tasks = $this->conn->prepare("INSERT into `tasks` (`boardId`, `name`, `description`, `active`, `parentTaskId`,`dueDate`)
VALUES (:boardId, :name, :description, :active, :parentTaskId, :dueDate)");
        $tasks->bindParam(":boardId", $boardId);
        $tasks->bindParam(":name", $name);
        $tasks->bindParam(":description", $description);
        $tasks->bindParam(":active", $active);
        $tasks->bindParam(":parentTaskId", $parentTaskId);
        $tasks->bindParam(":dueDate", $dueDate);

        if (!$tasks->execute()) {
            $errors[] = "Unable to create task.";
            $returnData['errors'] = $errors;
            return ($returnData);
        }

        $lastId = $this->conn->lastInsertId();

        $task = $this->conn->prepare("SELECT * from `tasks` where `taskId`=:taskId");
        $task->bindParam(":taskId", $lastId);
        $task->execute();

        $data = $task->fetch();
        if (!$data){
            $errors[] = "Error retrieving task.";
            $returnData['errors'] = $errors;
            return ($returnData);
        } else {
            $returnData['task'] = $data;
        }

        return ($returnData);
    }

    //assign a task to a user, or no user.
    public function assignTask($senderUserId, $userId, $taskId){
        //first, confirm that task exists.
        $this->connect();

        $task = $this->conn->prepare("select * from `tasks` LEFT JOIN `boardMembers` on `boardMembers`.`boardId` = `tasks`.`boardId`
          where `tasks`.`taskId`=:taskId AND `boardMembers`.`userId` = :userId AND `boardMembers`.`active`=1");
        $task->bindParam(":taskId",$taskId);
        $task->bindParam(":userId",$senderUserId);
        $task->execute();
        $thisTask = $task->fetch();

        if (!$thisTask){
            return error("Task not found");
        }

        //repeat as above, but with the new user ID as the result, unless it's null.
        //echo("\nUser ID: "+ $userId + "\n");
        if ($userId != null) {
            $task->bindParam(":userId", $userId);
            $task->execute();
            $thisTask = $task->fetch();

            if (!$thisTask){
                return error("Target not found");
            }
        }

        //and now update.;
        $taskUpdate = $this->conn->prepare("update `tasks` set `ownerId`=:userId where `taskId`=:taskId");
        $taskUpdate->bindParam(":taskId",$taskId);
        $taskUpdate->bindParam(":userId",$userId);
        if (!$taskUpdate->execute()){
            $errors[] = "SQL Error on task update.";
            $returnData['errors'] = $errors;
            return ($returnData);
        }

        $tasks = $this->conn->prepare("SELECT * from `tasks` where `taskId` = :taskId");
        $tasks->bindParam(":taskId",$taskId);

        $tasks->execute();

        $returnData['task'] = $tasks->fetch();
        return $returnData;
    }

    //mark task as complete
    //could benefit from a more... encompassing method.
    public function completeTask($userId, $taskId){
        $returnData = [];
        $this->connect();

        $tasks = $this->getTasks($userId, null, $taskId);
        if (isset ($tasks['errors'])){
            $returnData['errors'] = $tasks['errors'];
        } else {
            $task = $this->conn->prepare("UPDATE `tasks` set `completed` = '1' where `taskId`=:taskId");
            $task->bindParam(":taskId", $taskId);
            if (!$task->execute()){
                $returnData['errors'] = $this->sqlError();
            } else {
                //mark it as complete without having to page DB again.
                $tasks['tasks'][0]['completed'] = 1;
                $returnData['task'] = $tasks['tasks'][0];
            }
        }

        return $returnData;

    }

    //fetches tasks.
    public function getTasks($userId = null, $boardId = null, $taskId = null, $ownerId = null){
         $this->connect();
         //set an SQL string.
         $returnData = [];
         $sqlString = "select * from `tasks`";
         $sqlVars = [];
         $sqlBind = [];
         if ($taskId != null){
             if ($userId != null){
                 $sqlTest = $this->conn->prepare("SELECT * FROM `tasks` left join `boardMembers` on `tasks`.`boardId` AND `boardMembers`.`boardId`
WHERE `boardMembers`.`userId` = :userId AND `boardMembers`.`boardId`=`tasks`.`boardId` AND `boardMembers`.`active`=1 AND `tasks`.`taskId`=:taskId");
                 $sqlTest->bindParam(":userId", $userId);
                 $sqlTest->bindParam(":taskId", $taskId);
                 if (!$sqlTest->execute()) {
                     $returnData['errors'] = $this->sqlError();
                     return $returnData;
                 }
                 $data = $sqlTest->fetch();
                 if (!$data){
                     $returnData['errors'] = "Task not found.";
                     return $returnData;
                 }
             }
             $sqlVars[] = "`taskId`=:taskId";
             $sqlBind[] = (object) array(
                 'name' => ":taskId",
                 'value' => $taskId
             );
         }

         if ($boardId != null) {
             if ($userId != null){
                 if (!$this->isInBoard($userId, $boardId)){
                     $returnData['errors'] = "No access for user to board.";
                     return $returnData;
                 }
             }
             $sqlVars[] = "`boardId`=:boardId";
             $sqlBind[] = (object) array(
                 'name' => ":boardId",
                 'value' => $boardId
             );
         }

         if ($ownerId != null){
             $sqlVars[] = "`ownerId`=:ownerId";
             $sqlBind[] = (object) array(
                 'name' => ":ownerId",
                 'value' => $ownerId
             );
         }

         foreach ($sqlVars as $key => $value){
             if ($key == 0){
                 $sqlString .= " where ";
             } else {
                 $sqlString .= " AND ";
             }
             $sqlString .= $value;
         }

         $tasks = $this->conn->prepare($sqlString);

         foreach($sqlBind as $key=>$value){
             echo ("\n". $value->name . " " . $value->value);
             $tasks->bindParam($value->name, $value->value);
         }
         if (!$tasks->execute()){
             $returnData['errors'] = $this->sqlError();
             return $returnData;
         };

         $returnData['tasks'] = $tasks->fetchAll();
         return $returnData;
    }

    //creates an invite.
    public function sendInvite($senderId, $boardId, $receiver){
        $returnData = [];
        $errors = [];
        $this->connect();
        //confirm user exists.
        $result = $this->findUser($receiver);
        if (!isset($result['success'])){
            return $this->error("Recipient not found");
        }
        $receiverId = $result['success'];
        //confirm access.
        if (!$this->isInBoard($senderId, $boardId)){
            return $this->error("Unable to access board, or board does not exist. " . $senderId . " " . $boardId );
        }
        //check for target user already being in board
        else if ($this->isInBoard($receiverId, $boardId)){
            return $this->error("User is already in board");
        }
        //check for pending invite.
        else if ($this->inviteExists($receiverId, $boardId)){
            return $this->error("User has pending invite");
        }

        $sql = $this->conn->prepare("INSERT INTO `invites` (`senderId`,`receiverId`,`boardId`) VALUES
(:senderId, :receiverId, :boardId)");
        $sql->bindParam(":senderId", $senderId);
        $sql->bindParam(":receiverId", $receiverId);
        $sql->bindParam(":boardId", $boardId);
        if (!$sql->execute()) {
            return $this->error("SQL Error.");
        }

        $returnData['success'] = "Invite sent.";
        return($returnData);
    }

    //return true on inviting existing and being active and not accepted/rejected.
    public function inviteExists($receiverId, $boardId){
        $sql = $this->conn->prepare("SELECT * from `invites` where `active`=1 and `accepted` IS NULL and
`receiverId`=:receiverId and `boardId`=:boardId");
        $sql->bindParam(":receiverId", $receiverId);
        $sql->bindParam(":boardId", $boardId);
        if (!$sql->execute()){
            return $this->error("SQL Error");
        }
        $data = $sql->fetch();
        if (!$data){
            return false;
        }
        return true;
    }

    //update session data here for cross communication.
    public function updateSession($board = null){
        $returnData = [];
        $errors = [];
        $this->connect();
        //purge all with lastActivity over 1 hour... eventually change this.
        $toClear = $this->conn->prepare("delete from `sessions` where `lastActivity` < now() - INTERVAL 1 HOUR");
        $toClear->execute();

        $query = $this->conn->prepare("select `id`, `sessionId` from `sessions` where sessionId = :sessionId");

        $sessionID = session_id();
        $query->bindParam(':sessionId', $sessionID);
        $query->execute();
        $data = $query->fetch();

        if (!$data){
            //create a new session entry.
            $sessionCreate=$this->conn->prepare("INSERT into `sessions` (`sessionId`) VALUES (:sessionId)");
            $sessionCreate->bindParam(':sessionId', $sessionID);
            $sessionCreate->execute();

            //and refetch.
            $query->execute();
            $data = $query->fetch();
        }

        if (!$data){
            $errors[] = "Unable to create/retrieve session from DB.";
        }

        if (count($errors) > 0) {
            $returnData['errors'] = $errors;
            return ($returnData);
        }

        //now update.
        $query = $this->conn->prepare("UPDATE `sessions` SET `loggedIn` = :loggedIn, `lastActivity` = now(), `boardId` = :boardId, `userId` = :userId
          WHERE `sessionId` = :sessionId");

        $query->bindParam(':loggedIn', $_SESSION['loggedIn']);
        $query->bindParam(':sessionId', $sessionID);
        $query->bindParam(':userId', $_SESSION['userId']);
        if ($board != null){
            //if they don't actually have access, prevent it! Huah.
            if (!$this->isInBoard($_SESSION['userId'], $board)){
                $board = null;
            }
        }
        $query->bindParam(':boardId', $board);

        $query->execute();

        $returnData['success'] = true;
        return $returnData;
    }

    public function getSession($sessionId){
        $returnData = [];
        $errors = [];
        $this->connect();
        $query= $this->conn->prepare("SELECT `id`, `loggedIn`, `boardId`, `userId` from `sessions` WHERE `loggedIn` = 1
AND `sessionId` = :session ");
        $query->bindParam(':session', $sessionId);
        $query->execute();
        $data = $query->fetch();
        if (!$data){
            $returnData['loggedIn'] = 0;
            $returnData['userId'] = null;
            //echo("Not logged in.");
        } else {
            $returnData['loggedIn'] = 1;
            $returnData['userId'] = $data['userId'];
            //echo $data['userId'];
        }

        $query = $this->conn->prepare("UPDATE `sessions` SET `lastActivity` = now() WHERE `sessionId` = :session");
        $query->bindParam(':session', $sessionId);
        $query->execute();
        return ($returnData);
    }

    //error logging and such
    private function sqlError(){
        $returnData = [];
        $errors[] = "SQL Error";
        $returnData['errors'] = $errors;
        return $returnData;
    }

    //quick error messages.
    private static function error($errorString){
        $returnData = [];
        $errors[] = $errorString;
        $returnData['errors'] = $errors;
        return $returnData;
    }
}