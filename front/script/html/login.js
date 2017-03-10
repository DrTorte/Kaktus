$(document).ready(function(){
    "use strict";

    $("#loginTabs").tabs({
        active:0,
        classes:{
            "ui-tabs-nav":"no-tab-bkg",
            "ui-tabs-tab":"twoTabs"
        }
    });
    $("body").on("keydown", ".loginButtons", function(e){
        if (e.which == 13){
            $("#loginButton").trigger("click");
        }
    });

    $("body").on("click", "#loginButton", function(e){
        var msg = {
            type: "login",
            user: $("#username").val(),
            password: $("#password").val()
        };
        $.post("index.php", msg)
            .done(function (data) {
                processReply(data);
            });
    });

    $("body").on("keydown", ".registerButtons", function(e){
        if (e.which == 13) {
            $("#registerButton").trigger("click");
        }
    });

    $("body").on("click", "#registerButton", function(e){
        var msg = {
            type: "register",
            user: $("#regUser").val(),
            password: $("#regPwd").val()
        };
        $.post("index.php", msg)
            .done(function (data) {
                if(processReply(data)){
                    msg.type = "login";
                    $.post("index.php", msg)
                        .done(function(data2){
                        {
                            processReply(data2);
                        }
                    });
                };
            });
    });

    $("body").on("click", "#logoutButton", function(e){
        var msg = {
            type: "logout"
        };
        $.post("index.php", msg)
            .done(function(data){
               processReply(data);
            });
    });

    $("body").on("keyup", "#regPwd", function(e){
        $("#regPwdRepeat").trigger("keyup");
    });

    $("body").on("keyup","#regPwdRepeat", function(e){
        if ($("#regPwdRepeat").val() != $("#regPwd").val()){
            $("#regPwdRepeatNotes").html("Passwords do not match");
        } else {
            $("#regPwdRepeatNotes").html("");
        }
    });
});