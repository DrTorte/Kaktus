<div class="col-md-6">
    <div class="colHeader">
        Boards
    </div>
    <div id="myBoardList">
        <!--entries go here-->

        <?php
            foreach($myBoards as $key => $value){
                ?>
                    <div class="listContainer">
                        <span class="listItem" data-boardid="<?php echo($value['boardId'])?>">
                            <span class="listName">
                                <?php
                                    echo(sprintf("<a href='?page=board&board=%s'>", $value['boardId']));
                                    echo(sprintf("%s", $value['name']));
                                    echo(sprintf("</a>"));
                                ?>
                            </span>
                            <span class="actionButtons">
                                <span class="actionButton">
                                    <i class="fa fa-users boardMembersButton" data-boardId="<?php echo($value['boardId'])?>">

                                    </i>
                                </span>
                            </span>
                            <span hidden class="boardMembers" data-boardid="<?php echo($value['boardId'])?>">
                                <ul>
                                <?php
                                    foreach($value['members'] as $member){
                                        echo(sprintf("<li>%s</li>", $member['username']));
                                    }
                                ?>
                                </ul>
                                <form id="inviteNewUser">
                                    <input type="hidden" id="inviteBoard" value="inviteBoard" name="type"/>
                                    <input type="hidden" id="inviteBoardId" value="<?php echo($value['boardId']);?>" name="boardId"/>
                                    <div class="col-md-8">
                                        <input class="form-control submitOnEnter" type="text" id="inviteUserName"
                                               name="userName" placeholder="Username..."/>
                                    </div>
                                    <div class="col-md-4">
                                        <button class="btn btn-success submitButton">
                                            <i class="fa fa-envelope" aria-hidden="true"></i> Invite
                                        </button>
                                    </div>
                                </form>
                            </span>
                        </span>
                    </div>
            <?php }
        ?>
    </div>
    <hr>
    <form id="createNewBoard">
        <input type="hidden" value="newGroup" name="type"/>
        <div id="createGroupDiv" class="col-md-8">
            <input class="form-control submitOnEnter" type="text" id="newGroupName" name="name" placeholder="Name of new group..."/>
            <textarea class="form-control"  name="description" placeholder="Describe group..."></textarea>
        </div>
        <div id="myGroupControls" class="groupControls col-md-4">
            <button class="btn btn-success submitButton">
                <i class="fa fa-plus" aria-hidden="true"></i> New Group
            </button>
        </div>
    </form>
</div>

<div class="col-md-3">
    <div class="colHeader">
        My Invites
        <?php
            foreach($myInvites['myInvites'] as $key=>$value){
                if ($value['receiverId'] == $_SESSION['userId']){
                    ?>
                    <div class='listContainer'>
                        <span class="listItem" data-inviteId="<?php echo($value['inviteId'])?>">
                            <span class="listName">
                                <b><?php echo($value['name'])?></b>
                            </span>
                            <span class="actionButtons">
                                <span class="actionButton">
                                    <i class="fa fa-check inviteAction" data-type="acceptInvite" data-inviteId="<?php echo($value['inviteId'])?>">
                                    </i>
                                </span>
                                <span class="actionButton">
                                    <i class="fa fa-times inviteAction" data-type="declineInvite" data-inviteId="<?php echo($value['inviteId'])?>">
                                    </i>
                                </span>
                            </span>
                        </span>
                    </div>
                <?php }
            }
        ?>
    </div>
</div>

<div class="col-md-3">
    <div class="colHeader">
        Sent Invites
        <?php
            foreach($myInvites['sentInvites'] as $key=>$value){
                if ($value['senderId'] == $_SESSION['userId']){
                    ?>
                    <div class='listContainer'>
                            <span class="listItem" data-inviteId="<?php echo($value['inviteId'])?>">
                                <span class="listName">
                                    <b><?php echo($value['name'])?></b>
                                </span>
                                <span class="actionButtons">
                                    <span class="actionButton">
                                        <i class="fa fa-times inviteAction" data-type="withdrawInvite" data-inviteId="<?php echo($value['inviteId'])?>">
                                        </i>
                                    </span>
                                </span>
                            </span>
                    </div>
                <?php }
            }
        ?>
    </div>
</div>

<script src="front/script/html/dashboard.js"></script>