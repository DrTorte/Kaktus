var pingInterval;

$(document).ready(function(){
    "use strict";
    //$("body").on("form", "submit", function(e){
    $("form").submit(function(e){
        e.preventDefault();
        return false;
    });

    $(".datePicker").datepicker({
        dateFormat: 'yy-mm-dd'
    });

    //submit form buttons.
    $("body").on("click", ".submitButton", function(e){
        //disable button.
        $(this).prop("disabled", true);

        var button = $(this);

        //grab the form and serialize.
        var form = $(this.form).serialize();

        $.post("index.php", form, function(data){
            if (processReply(data)){
                if ($(this).hasClass("button-dialog-close")) {
                    button.closest(".dialog").dialog("close");
                } else if (button.hasClass("close-success")) {
                    $("#" + button.attr("data-close")).slideUp(100);
                }
            }
        })

        $(this).prop("disabled", false);
    });

    //keep alive.
    pingInterval = setInterval(keepAlive, 300000)

});

function processReply(data){
    "use strict";
    var success = false;
    var parsed = $.parseJSON(data);
    var alerts;
    if (parsed['success'] != null && parsed['success'] != ""){
        prepAlert(parsed['success'], "success");
        success = true;
    }
    if (parsed['errors'] != null){
        prepAlert(parsed['errors']);
        success = false;
    }

    if (parsed['action'] != null){
        if (parsed['action'] == "reload"){
            location.reload();
        }
    }

    return success;
};

function prepAlert(alerts,alertType){
    "use strict";
    if (alertType == null){
        alertType="danger"
    }
    if (alerts == null){
        alerts = [];
        alerts.push("Unspecified error.");
    }

    var html = "<div class='alert alert-" + alertType + " alert dismissable'>";
    html += "<a href='#' class='close' data-dismiss='alert' aria-label='close'>&times;</a>";

    if (alerts.constructor === Array){
        for (var e in alerts)
        {
            html += alerts[e] + "<br />";
        }
    } else {
        html += alerts + "<br />";
    }

    html += "</div>"
    $("#errors").html(html);
}

function keepAlive(){
    "use strict";
    //just basically go fetch nothingness to maintain session activity.
    var msg = {
        type:"ping"
    };
    $.post("index.php", msg).done(function(data){
       //stuff maybe.
    }).fail(function(data){
        location.reload();
    });
}