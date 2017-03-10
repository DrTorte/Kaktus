$(document).ready(function() {
//chat, send chat, send command, command.
    $("body").on('keydown', "#chat", function (e) {
        if (e.which == 13) {
            $("#sendChat").trigger("click");
            return false;
        }
    });

    $("body").on('click', "#sendChat", function (e) {
        var msg = {
            'type': 'msg',
            'value': $("#chat").val()
        };
        sendMessage(msg);
        //alert("goat!");
        return false;
    });

    $("body").on('keydown', "#command", function (e) {
        if (e.which == 13) {
            $("#sendCmd").trigger("click");
            return false;
        }
    });

    $("body").on('click', "#sendCmd", function (e) {
        var msg = {
            'type': $("#command").val(),
            'value': $("#chat").val()

        };
        sendMessage(msg);
        //alert("goat!");
        return false;
    });
    /**
     * Created by koffe on 2017-02-09.
     */
});