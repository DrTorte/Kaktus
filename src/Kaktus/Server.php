<?php
namespace Kaktus;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Kaktus;
use Kaktus\SQL;

class Server implements MessageComponentInterface {
    protected $clients;
    protected $clientData;
    protected $sql;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->clientData = new \SplObjectStorage;
        $this->sql = new SQL();
    }

    private function getClient(ConnectionInterface $from){
        //this will return the client from the clients list in question.
        for ($i = 0; $i < $this->clientData->count(); $i++){
            foreach ($this->clientData as $d) {
                if ($d->connection == $from) {
                    return $d;
                }
            }
        }
        return null;//if not found, return null.
    }

    public function onOpen(ConnectionInterface $conn) {
        //grab session from apache to facilitate communication between it and WS
        $sessionId = $conn->WebSocket->request->getCookies()['PHPSESSID'];
        // Store the new connection to send messages to later
        $this->clients->attach($conn);

        //see if there is a session already active. if so, destroy it.
        /*
        foreach($this->clientData as $key => $value){
            if ($value->sessionId == $sessionId){
                $value = null; //null it out.
            }
        }*/

        $token = base64_encode(random_bytes(16));
        $thisData = new ConnData();
        $thisData->connection = $conn;
        $thisData->resourceID = $conn->resourceId;
        $thisData->token = $token;
        $thisData->sessionId = $sessionId;

        $this->clientData->attach($thisData);
        echo "New connection! ({$conn->resourceId})\n";

        $reply = new \stdClass();
        $reply->type = "token";
        $reply->data = $token;
        $conn->send(json_encode($reply));
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        //first grab data from DB to ensure user is loggedi n.
        //decode.
        $rcvd = json_decode($msg);
        //keep alives are tolerated without a token... for now?
        $rcvdType = $rcvd->type;
        if ($rcvdType == "ping"){
            //reply with type pong.
            $reply['type'] = "pong";
            $from->send(json_encode($reply));
            return;
        }

        //first, find sender.
        $thisClient = null;
        $thisClientIndex = "";
        $thisClientSession = "";
        foreach ($this->clientData as $key => $value){
            if ($value->connection == $from){
                $thisClient = $value;
                $thisClientIndex = $key;
                $thisClientSession = $thisClient->sessionId;
                break;
            }
        }

        if ($thisClient == null){
            //no client found.
            echo sprintf("Error! No client found for %s!", $from->resourceId);
            return;
        }

        //compare token next.
        if (!isset($rcvd->token) || $rcvd->token != $thisClient->token){
            echo sprintf("Invalid token from %s!", $from->resourceId);
            return;
        }

        //and now check if logged in.
        $sessionData = $this->sql->getSession($thisClientSession);
        if ($sessionData['loggedIn'] == 0){
            $thisClient->loggedIn = false;
            $thisClient->userId = -1;
        } else if ($sessionData['loggedIn'] == 1) {
            $thisClient->loggedIn = true;
            $thisClient->userId = $sessionData['userId'];
            //$thisClient->userId = $sessionData['userId'];
        }

        //sendData is sent to all users, by default, all in the current board.
        //Includes sender.
        $sendData = new \stdClass();
        $sendData->ready = false;
        $sendData->targetBoard = $thisClient->board;
        $sendData->boardOnly = true;

        //this is sent back to the sender only.
        $replyData = new \stdClass();
        $replyData->ready = false;

        //set the board. Required.
        if ($rcvdType == "setBoard"){
            //required: BoardID.
            if (!$rcvd->board){
                echo("No board found. :(\n");
            } else {
                $boardId = $rcvd->board;
                if ($this->sql->isInBoard($thisClient->userId,$boardId)) {
                    $thisClient->board = $boardId;
                    $replyData->type="board";
                    $replyData->ready = true;
                    echo("board set!\n");
                } else {
                    //force out code here...
                }
            }
        }

        //fetch all tasks. Used primarily when getting data upon initial connection.
        if($rcvdType == "getAllTasks"){
            if (!isset($rcvd->board) && !$thisClient->board){
                    $replyData->type="error";
                    $replyData->msg[]= "No board selected.";;
                    $replyData->ready = true;
            } else if (!isset($rcvd->board)){
                $rcvd->board = $thisClient->board;
            }
            //only proceed if no error.
            if (!isset($replyData->type) || $replyData->type != "error"){
                $result = $this->sql->getTasks($thisClient->userId, $rcvd->board);
                //var_dump($result);
                if (isset($result['errors'])){
                    $replyData->type="error";
                    $replyData->msg=$result['errors'];
                    $replyData->ready = true;
                } else {
                    $replyData->tasks=$result['tasks'];
                    $replyData->type="listTasks";
                    $replyData->ready = true;
                }
            }
        }

        //Creates a new task.
        if ($rcvdType == "newTask"){
            //Required: boardId, Name. Optional: Description.
            if ($rcvd->boardId !== "" && $rcvd->name !== ""){
                if (!$rcvd->description){
                    $rcvd->description = "";
                }
                //if no date selected, assume for tomorrow.
                if (!$rcvd->dueDate){
                    $rcvd->dueDate = new \DateTime('tomorrow');
                    $rcvd->dueDate = $rcvd->dueDate->format('Y-m-d');
                    echo("\nDue Date:" . $rcvd->dueDate);
                }
                $result = $this->sql->createTask($rcvd->name, $rcvd->description, $rcvd->boardId, $thisClient->userId, $rcvd->dueDate);
                if (isset($result['errors'])){
                    $replyData->type="error";
                    $replyData->msg=$result['errors'];
                    $replyData->ready = true;
                } else {
                    $sendData->task=$result['task'];
                    $sendData->type="newTask";
                    $sendData->ready = true;
                    $sendData->targetBoard = $result['task']['boardId'];
                }
            } else {
                $replyData->type="error";
                $replyData->msg="Missing data.";
                $replyData->ready = true;
            }
        }

        //assign or unassign. Same code, just different target.
        if ($rcvdType == "assign" || $rcvdType =="unassign"){
            if ($rcvd->task == ""){
                $replyData->type="error";
                $replyData->msg="Invalid Task ID Selected";
                $replyData->ready = true;
            }
            $targetId = null;
            if ($rcvdType == "assign"){
                $targetId=$thisClient->userId;
            }
            $result = $this->sql->assignTask($thisClient->userId, $targetId, $rcvd->task);
            if (isset($result['errors'])){
                $replyData->type="error";
                $replyData->msg=$result['errors'];
                $replyData->ready = true;
            } else {
                $sendData->type="updateTask";
                $sendData->task = $result['task'];
                $sendData->targetBoard = $result['task']['boardId'];
                $sendData->ready = true;
            }
        }

        //mark a task as completed.
        if ($rcvdType == "complete"){
            if ($rcvd->task == ""){
                $replyData->type="error";
                $replyData->msg="Invalid Task ID Selected";
                $replyData->ready = true;
            } else {
                $result = $this->sql->completeTask($thisClient->userId, $rcvd->task);
                if (isset($result['errors'])){
                    $replyData->type="error";
                    $replyData->msg="Unable to complete task";
                    $replyData->ready = true;
                } else {
                    $sendData->type="updateTask";
                    $sendData->task = $result['task'];
                    $sendData->targetBoard = $result['task']['boardId'];
                    $sendData->ready = true;
                }
            }
        }

        //reply data. never reaches anyone except the sender - primarily for error messages.
        if($replyData->ready == true){
            //echo("\n replying!\n");
            //var_dump($replyData);
            $from->send(json_encode($replyData));
        }

        //data to be sent. by default targets the sender as well.
        if($sendData->ready == true){
            //echo("\nSending!\n");
            //var_dump($sendData);
            foreach ($this->clientData as $key => $value){
                if($value->board == $sendData->targetBoard){
                    $value->connection->send(json_encode($sendData));
                }
            }
        }


    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);
        foreach ($this->clientData as $key => $value){
            if ($value->connection == $conn){
                $value = null; //null it out completely.
            }
        }

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }


}