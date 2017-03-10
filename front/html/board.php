<form id="boardForm" class="hidden">
    <input type="text" name="type" value="setBoard" />
    <input id="board" type="hidden" value="<?php echo($targetId) ?>" name="board"/>
</form>

<div class="col-md-4 taskGroup" data-type="ownerId" data-valueOwner="<?php echo($_SESSION["userId"]);?>" data-contains="myTasks">
    <div class="colHeader">
        My Tasks
    </div>
</div>
<div class="col-md-4 taskGroup" data-type="ownerId" data-valueOwner="null" data-contains="unassignedTasks">
    <div class="colHeader">
        Unassigned Tasks
    </div>
</div>
<div class="col-md-4 taskGroup" data-type="ownerId" data-valueOwner="all" data-skipOwner="null <?php echo($_SESSION["userId"]);?>" id="othersTasks" data-contains="allTasks">
    <div class="colHeader">
        Tasks for Others
    </div>
</div>



<div id="newTasks" class="col-md-12">
    <div class="col-md-4 groupControls">
    <form id="newTasks" data-clearOnSubmit="true">
        <input type="hidden" name="type" value="newTask" />
        <input type="text" name="name" class="form-control" placeholder="Name of task..."/>
        <textarea name="description" class="form-control" placeholder="Further details..."></textarea>
        <input type="text" name="dueDate" class="form-control datePicker" placeholder="Due by..."/>
        <button class="btn btn-submit submitWS">New Task</button>
    </form>
    </div>
</div>

<div id="connectingDialog">
    Connecting...
</div>

<script src="front/script/tasks.js" async></script>
<script src="front/script/debug.js" async></script>