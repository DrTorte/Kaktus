<?php
namespace Kaktus;


class Message
{
    public $type;
    public $message;

    public function __construction($msg){
        $type = "Error";
        $message = $msg;
    }
}