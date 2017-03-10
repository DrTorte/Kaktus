"use strict";
var conn = new WebSocket('wss://www.drtorte.net:8080'); //do the initial connect.
var token;
var board;
var tasks = [];
var locked = true;
var setBoardLoop = null;

$(document).ready(function(){
    //show the dialog to show that we're connecting.
    $("#connectingDialog").dialog({
        autoOpen:true,
        modal:true,
        classes: {
            "ui-dialog": "no-close"
        }
    });
    //set the board value.
    board = $("#board").val();
    //readTasks();
    ping();
    $("body").on("click", ".submitWS", function(e){
        //disable button.
        $(this).prop("disabled", true);

        var button = $(this);
        //grab the form and serialize.
        var form = $(this.form).serializeArray();

        //console.log(form);
        sendMessage(form);
        if ($(this.form).attr("data-clearOnSubmit") === "true"){
            $(this.form).children(".form-control").val("");
        }
        $(this).prop("disabled", false);
    });

    $("body").on("click", ".completeTask", function(e){
        var taskId = $(this).attr("data-taskId");
        var msg={
            'type':"complete",
            'task':taskId
        };
        sendMessage(msg);
    });

    $(".taskGroup").on("click", ".assignTask", function(e){
        //prep message to be sent.
        var taskId = $(this).attr("data-taskId");
        var msg = {
            'type':"assign",
            'task':taskId
        };
        sendMessage(msg);
    });

    $(".taskGroup").on("click", ".unassignTask", function(e){
        //prep message to be sent.
        var taskId = $(this).attr("data-taskId");
        var msg = {
            'type':"unassign",
            'task':taskId
        };
        sendMessage(msg);
    });

    $(".taskGroup").on("click", ".taskName", function(e){
        var taskId = $(this).attr("data-taskId");
        $(".taskDescription[data-taskid='" + taskId + "']").toggle();
    });
});

conn.onopen = function(e){
    //hooray! opened!
    //send a setboard request. repeat every .5 seconds.
    $("#connectingDialog").html("Connected. Setting board...");
}

conn.onmessage = function(e){
    var data = JSON.parse(e.data);
    if (!data.type){
        console.log(data);
        return;
    }

    //check for pongs.
    if (data.type == "pong"){
        //console.log("Pong!");
        return;
    }
    //log data if it's not a boring old ping.
    //console.log(data);

    if (data.type == "board"){
        locked = false;
        clearInterval(setBoardLoop);
        $("#connectingDialog").html("Board set! Fetching tasks.");
        //now go get the tasks.
        fetchTasks();

    }
    //check for errors.
    if (data.type == "error"){
        prepAlert(data.msg);
    }

    //if HTML, change body content as appropriate.
    if (data.type == "html"){
        //stuff? maybe?
    }

    if (data.type == "token"){
        token = data['data'];
        //get the board setup.
        if (!setBoard()) {
            setBoardLoop = setInterval(setBoard, 1000); //try again every second if it failed.
        }
    }
    if (data.type == "message"){
        $('#heyyou').html(data['msg']);
    }

    if (data.type == "newTask") {
        tasks.push(data.task);
        processTasks(data.task);

        // add: a check.

    }

    if (data.type == "updateTask"){
        tasks.push(data.task);
        processTasks(data.task);

        // add: a check.
    }

    if (data.type == "listTasks"){
        tasks = data.tasks;
        showTasks();
        $("#connectingDialog").dialog("close");
    }
}

conn.onclose = function(e){
    //stuff? reconnect?
}



function sendMessage(data){
    var jsonData = {};
    console.log(Array.isArray(data));
    if (Array.isArray(data) === true) {
        for (var key in data) {
            var obj = data[key];
            jsonData[obj.name] = obj.value;
        }
    } else{
        jsonData=data;
    }
    jsonData['token'] = token;
    jsonData['boardId'] = board;
    console.log(jsonData);
    var json = JSON.stringify(jsonData);
    conn.send(json);
}

function setBoard(){
    console.log(token);
    if (typeof(token) === 'undefined'){
        return false;
    }
    var msg = $("#boardForm").serializeArray();
    sendMessage(msg);
}

//keep-alive pings.
function ping(){
    setInterval(function(){
        //reconnect
        if (conn.readyState != 1){
            //try to reconnect.
            console.log("Reconnecting...")
            conn = new WebSocket('wss://www.drtorte.net:8080');
        } else {
            var msg = {
                'type': "ping"
            };
            var json = JSON.stringify(msg);
            conn.send(json);
            //console.log("Ping!");
        }
    }, 3000);
}

function fetchTasks() {
    var msg = {
        type:"getAllTasks"
    };
    sendMessage(msg);
}

function showTasks(){
    //this will go through each container and add the appropriate divs to those containers.
    //there's some potential other stuff here, later, for processing. Though right now... not so much.
    tasks.forEach(processTasks);
}

function processTasks(element, index, array){
    //scan if task exists or not already.
    //if it does, remove.
    $(".taskItemContainer[data-taskid='" + element['taskId'] + "']").slideUp("fast", function(){
        $(this).remove();
    });

    //skip if complete.
    if (element['completed'] == true){
        return;
    }
    var target = [];
    var targetDivs = $(".taskGroup");
    //at this point we'll go through each of the columns and determine in whcih ones this belongs.
    var ownerId = element['ownerId'];
    if (ownerId == null){
        ownerId = "null";
    }
    for (var i = 0; i < targetDivs.length; i++){
        var dataType = $(targetDivs[i]).attr("data-type");
        //alert(dataType);
        //first, go by "OwnerId".
        if (dataType =="ownerId"){
            var targetOwners = $(targetDivs[i]).attr("data-valueOwner").split(" ");
            if (typeof($(targetDivs[i]).attr("data-skipOwner")) != "undefined") {
                var targetSkips = $(targetDivs[i]).attr("data-skipOwner").split(" ");
            } else {
                var targetSkips = null;
            }
            $.each(targetOwners, function(index,targetOwner){
                if (targetOwner == ownerId || targetOwner == "all") {
                    var skip = false;
                    $.each(targetSkips, function(indexSkip, targetSkip) {
                        if (targetSkip == ownerId) {
                            skip = true;
                        }
                    });
                    if (!skip) {
                        target.push(targetDivs[i]);
                    }
                }
            });
        }
    }
    /*
    if(element['ownerId'] == $("#userId").val()){
        target.push($("#myTasks"));
    } else if (element['ownerId'] == "" || element['ownerId'] == null){
        target.push($("#unassignedTasks"));
    } else {
        target.push($("#othersTasks"));
    }*/

    for (var i = 0; i < target.length; i++){
        addTask(target[i],element);
    }
}

function addTask(target, task){
    //compare date to this date to determine if it's past due or not.
    var thisDate = new Date();
    var targetDate = new Date(task.dueDate);
    var isDue = thisDate > targetDate ? " taskDue" : "";
    var taskHtml = "<div hidden class='taskItemContainer" + isDue +"' data-taskid='" + task.taskId + "' data-dueDate='"
        + task.dueDate + "'>";
    taskHtml += "<span class='taskItem activeTask' data-taskid='"+task.taskId+"' data-ownerid='" + task.ownerId + "'>";
    taskHtml += "<span class='taskName' data-taskId='" + task.taskId + "'>" + task.name + "</span>";
    taskHtml += "<span class='actionButtons'>"
    //now add the action buttons.
    if (task.ownerId == $("#userId").val()) {
        //add "unassign" and "complete".
        taskHtml += "<span class='actionButton'><a class='completeTask' data-taskId='" + task.taskId + "'>";
        taskHtml += "<i class='fa fa-check' aria-hidden='true'></i></a></span>";

        taskHtml += "<span class='actionButton'><a class='unassignTask' data-taskId='" + task.taskId + "'>";
        taskHtml += "<i class='fa fa-user-times' aria-hidden='true'></i></a></span>";
    }
    else {
        //add "take"
        taskHtml += "<span class='actionButton'><a class='assignTask' data-taskId='" + task.taskId + "'>";
        taskHtml += "<i class='fa fa-user-plus' aria-hidden='true'></i></a></span>";
    }
    taskHtml +="</span></span>"
    taskHtml +="<div hidden class='taskDescription' data-taskId='" + task.taskId + "'>"
        taskHtml+="<span class='taskDetails'>" + task.description + "</span><br>";
        taskHtml+="<span class='taskDueDate'>" + task.dueDate + "</span>";
    taskHtml +="</div></div>"

    //find the first item that is either with a later date and insert before that, or just insert at the bottom.
    var taskList = $(target).children(".taskItemContainer");
    var i =0;
    while (i < taskList.length){
        //alert($(taskList[i]).attr("data-duedate"));
        if (new Date($(taskList[i]).attr("data-duedate")) - new Date(task.dueDate) > 0) {
            $(taskList[i]).before(taskHtml);
            break;
        } else {
            i++;
        }
    }

    //if you've reached the end or somehow skipped over it, it goes at the bottom.
    if (i >= taskList.length){
        $(target).append(taskHtml);
    }

    //do a slide down.
    $(".taskItemContainer[data-taskid='"+task.taskId+"']").slideDown("fast");
    //$(".taskDescription[data-taskid='" + task.taskId + "']").hide();
}

