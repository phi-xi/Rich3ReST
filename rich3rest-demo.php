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

/*
        Implement each resource as a child class of RestResource,
        implement its callback function and define property 'data'
*/

    class MyResource1 extends RestResource {

        public $data = array(
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

    /*
            Implement the callback which is executed after
            successful validation of the arguments passed
            according to the specification in '$this->data'
    */

        public function callback( $api, $method, $data ){
        // get parameter 'id' passed
            $id = $data[ "id" ];
        // append this id to the URIs of the two linked resources 'delete' and 'msg'
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


/*
        Implement two more RestResource children
*/

    class MyResource2 extends RestResource {

        public $data = array(
            "uri" => "my/box/msg",
            "methods" => array( "POST" ),
            "data" => array(),
            "args" => array(
                "POST" => array(
                    "uri" => array(
                        "id" => "number"
                    ),
                    "body" => array(
                        "text" => "string",
                        "permanent" => "bool"
                    )
                )
            ),
            "links" => array(
                array(
                    "uri" => "my/box/view",
                    "rel" => "content",
                    "method" => "GET",
                    "args" => array(
                        "id" => "number"
                    )
                ),
                array(
                    "uri" => "my/box/delete",
                    "rel" => "content",
                    "method" => "DELETE",
                    "args" => array(
                        "id" => "number"
                    )
                )
            )
        );

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

        public $data = array(
            "uri" => "my/box/delete",
            "methods" => array( "DELETE" ),
            "data" => array(),
            "args" => array(
                "DELETE" => array(
                    "id" => "number"
                )
            ),
            "links" => array(
                array(
                    "uri" => "my/box/view",
                    "rel" => "content",
                    "method" => "GET",
                    "args" => array(
                        "id" => "number"
                    )
                ),
                array(
                    "uri" => "my/box/msg",
                    "rel" => "content",
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


/*
        Create a RestAPI instance, add the resources
        defined above and set the output format
*/
    $api = new RestAPI( "/api/pwa" );
    $api->addResource( new MyResource1() );
    $api->addResource( new MyResource2() );
    $api->addResource( new MyResource3() );
    $api->execute();

?>
