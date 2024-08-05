<?php
/*
    _____    __    ___ __   __ ____   _____             ____ ________
    ||  \\   []   //// ||   || \\\\\  ||  \\           ///// ||||||||
    ||   \\      //    ||   ||     \\ ||   \\  ______ //        ||
    ||   //  || ||     ||   ||     // ||   //  ||//// \\        ||
    ||__//   || ||     |||||||  ||||  ||__//   ||      \\\\\    ||
    ||  \\   || ||     ||   ||     \\ ||  \\   ||||        \\   ||
    ||   \\  ||  \\    ||   ||     // ||   \\  ||          //   ||
    ||    \\ ||   \\\\ ||   || /////  ||    \\ ||\\\\ //////    ||


                R i c h 3 R e S T
                
                
        Copyright 2024 PhiXi (phi.xi@aol.com),
        released under MIT license (opensource.org/license/mit)

    Implements a JSON ReST API on Richardson Maturity Model level 3;
    may return resources represented as JSON or HTML.
    In HTML representation, links are shown as hyperlinks leading
    to a generic form that allows to interact with the resource using
    all supported request methods.
*/



/*
    Provides a generic HTML form.

    JSON object expected in POST request body to define the form:
    {
        "action": "/api/get-icon/v1/",
        "method": "POST",
        "fields":[
            {"name": "Temperature", "type": "input"},
            {"name": "Pressure", "type": "input"},
            {"name": "Color", "type": "select", "options": ["Green", "Blue", "Red"]},
            {"name": "Done", "type": "checkbox"},
            {"name": "Pressure", "type": "input"}
        ]}
*/
    error_reporting(0);
    $body = file_get_contents( "php://input" );
    $body = $_GET[ "template" ];
    $data = json_decode( $body, true );
    $originalMethod = $data[ "method" ];
    $action = $data[ "action" ];
    $cssFile = "";
    if ( file_exists( "rich3rest.json" ) ){
        $config = json_decode( file_get_contents( "rich3rest.json" ), true );
    }
    if ( $config !== null ) $cssFile = $config[ "style" ][ "form" ];
?>
<html><head><meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0"><link rel="stylesheet" href="/style/generic-form.css"></head><body>
<form method="POST" action="<?php echo($action); ?>">
<?php
    foreach ( $data["fields"] as $p ){
        $type = $p[ "type" ];
        $name = $p[ "name" ];
        if ( $type == "select" ){
            $options = $p[ "options" ]; ?>
            <label style="display:inline-block;width:10em;"><?php echo($name); ?></label>
            <select name="<?php echo($name); ?>" id="<?php echo($name); ?>" style="margin-top:0.5em;font-size:0.8em;height:1.4em;">
            <?php foreach( $options as $opt ){ ?>
                <option><?php echo( $opt ); ?></option><?php
            } ?>
            </select><br><?php
        } elseif ( $type == "input" ){ ?>
            <label style="display:inline-block;width:10em;"><?php echo($p["name"]); ?></label>
            <input type="text" id="<?php echo($name); ?>" name="<?php echo($name); ?>" style="margin-top:0.5em;"><br><?php
        } elseif ( $type == "checkbox" ){ ?>
            <label style="display:inline-block;width:10em;"><?php echo($p["name"]); ?></label>
            <input type="checkbox" id="<?php echo($name); ?>" name="<?php echo($name); ?>" style="margin-top:0.5em;width:1.25em;height:1.25em;"><br><?php
        }
    }
?>
<input type="hidden" name="originalRequestMethod" value="<?php echo( $originalMethod ); ?>" >
<input id="submit" type="submit" value="Send" style="margin-top:1em;"></form></body><script>
    window.addEventListener( "load", ()=>{
        const form = document.getElementsByTagName("form")[0],
            btn = document.getElementById("submit");
        if ( form.getElementsByTagName("label").length == 0 ) btn.click();
    } );
</script></html>
