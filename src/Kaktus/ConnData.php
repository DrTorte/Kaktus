<?php

namespace Kaktus;


class ConnData
{
    public $connection; // the unique WS connection.
    public $token; //a token to prevent XSS attacks
    public $sessionId; //this is stored so that we can resume a lost connection if required.
    public $loggedIn = false; // unsure if we'll use this...
    public $userId = -1; //definitely set this.
    public $board = null; //current group.

}