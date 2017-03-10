$(document).ready(function(){
    "use strict";
    $(".boardMembersButton").click(function(e){
        var dataId = $(e.currentTarget).attr("data-boardid");
        $(".boardMembers[data-boardId='"+dataId+"']").toggle();
    });

    $(".inviteAction").click(function(e){
        var inviteId = $(e.currentTarget).attr("data-inviteId");
        var action = $(e.currentTarget).attr("data-type");
        var msg = {
            type:"inviteAction",
            inviteId:inviteId,
            action:action
        };
        $.post("index.php", msg).done(function(data){
            processReply(data);
        });
    });
});