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


    include_once "/script/rich3rest.php";

    $CONFIG = array(
        "uri" => "my/box/view",         // full URI is /api/<html|json>/my/box/view
        "methods" => array( "GET", "POST" ),
        "data" => array(),
        "args" => array(
            "POST" => array(            // POST can receive args from...
                "uri" => array(         // ...URI...
                    "id" => "number"
                ),
                "body" => array(        // ...or request body
                    "text" => "string"
                )
            ),
            "GET" => array(             // GET has no request body, GET args are passed in URI always
                "id" => "number"
            )
        ),
        "links" => array(               // if defined, links will be appended to the response of this resource
            array(
                "uri" => "my/box/delete",   // relative from this script file
                "rel" => "content",
                "method" => "DELETE",
                "args" => array(
                    "id" => "number"        // DELETE has no request body
                )
            ),
            array(
                "uri" => "my/box/msg",
                "rel" => "activity",
                "method" => "POST",
                "args" => array(
                    "uri" => array(
                        "id" => "number"
                    ),
                    "body" => array(
                        "text" => "string",
                        "permanent" => "bool"
                    )
                )
            )
        )
    );
    $DIR = new RestResourceDirectory( $CONFIG );




    class MyResource1 extends RestResource {

        public function callback( $api, $method, $data ){
        // get parameter 'id' passed
            $id = $data[ "id" ];
        // append this id to the URIs of the two linked resources 'delete' and 'msg'
            $this->addParametersToLinkUri( [ $id ], "my/box/view" );
            $this->addParametersToLinkUri( [ $id ], "my/box/delete" );
            $this->addParametersToLinkUri( [ $id ], "my/box/msg" );
        // prepare the response (this one just echoes the data passed)
            $api->setOutputData( array(
                "method" => $method,
                "data" => $data,
                "resource" => $this->data[ "uri" ]
            ) );
        // send the response (this will terminate this script!)
            $api->sendResponse( $this );
        }
    }


    class MyResource2 extends RestResource {

        public function callback( $api, $method, $data ){
            $id = $data[ "id" ];
            $this->addParametersToLinkUri( [ $id ], "my/box/view" );
            $this->addParametersToLinkUri( [ $id ], "my/box/delete" );
            $api->setOutputData( array(
                "method" => $method,
                "data" => $data,
                "resource" => $this->data[ "uri" ]
            ) );
            $api->sendResponse( $this );
        }
    }


    class MyResource3 extends RestResource {

        public function callback( $api, $method, $data ){
            $id = $data[ "id" ];
            $this->addParametersToLinkUri( [ $id ], "my/box/view" );
            $this->addParametersToLinkUri( [ $id ], "my/box/msg" );
            $api->setOutputData( array(
                "method" => $method,
                "data" => $data,
                "resource" => $this->data[ "uri" ]
            ) );
            $api->sendResponse( $this );
        }
    }




    $api = new RestAPI( "/api/test" );
    $api->addResource( new MyResource1( $DIR, "my/box/view" ) );
    $api->addResource( new MyResource2( $DIR, "my/box/msg" ) );
    $api->addResource( new MyResource3( $DIR, "my/box/delete" ) );
    $api->execute();

?>
