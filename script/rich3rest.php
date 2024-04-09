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
    NOTICE
        - generic form never sends GET requests,
          in HTML representation of a resource,
          GET requests are linked to the URI directly
        - in Resource->data, the field "args" must not
          be omitted; if no arguments required, "args"
          must be empty array
*/


    class RestAPI {

        private $apiURL = "";
        private $outputStatus = 200;
        private $outputData = array();
        private $resources = [];
        private $outputFormat = "JSON";
        private $outputContentType = array(
            "JSON" => "application/json",
            "HTML" => "text/html"
        );
        private $error = array(
            "400" => "Bad Request",
            "403" => "Forbidden",
            "404" => "Not Found",
            "405" => "Method Not Allowed"
        );
        private $configFile = null;
        private $config = array();

        function __construct( $apiUrl ){
            if ( substr( $apiUrl, -1 ) == "/" ) $apiUrl = substr( $apiUrl, 0, -1 );
            $this->apiURL = $apiUrl;
            $this->configFile = dirname(__FILE__) . "/rich3rest-config.json";
            if ( file_exists( $this->configFile ) ){
                $cfg = json_decode( file_get_contents( $this->configFile ), true );
                if ( $cfg === null ) $cfg = array();
                $this->config = $cfg;
            }
        }

        public function addResource( $restResource ){
            $this->resources[] = $restResource;
        }
        public function setOutputStatus( $status ){
            if ( $status == 0 ) $status = 200;
            $this->outputStatus = $status;
        }
        public function setOutputData( $dataArrayOrString ){
            if ( isset( $dataArrayOrString[ "links" ] ) ) $links = $dataArrayOrString[ "links" ];
            $this->outputData = $dataArrayOrString;
        }
        public function setOutputFormat( $format ){
            $this->outputFormat = $format;
        }
        public function sendResponse( $resource=null ){
            if ( $resource !== null ){
                $this->outputData[ "links" ] = $this->createHyperlinks( $resource->data[ "links" ] );
            }
            if ( isset( $this->outputData[ "data" ] ) ){
                unset( $this->outputData[ "data" ][ "originalRequestMethod" ] );
                array_values( $this->outputData[ "data" ] );
            }
            if ( $this->outputFormat == "JSON" ){
                $out = $this->getOutputAsJson();
            }
            if ( $this->outputFormat == "HTML" ){
                $out = $this->getOutputAsHtml();
            }
            http_response_code( $this->outputStatus );
            if ( !headers_sent() ){
                $cntType = $this->outputContentType[ $this->outputFormat ];
                header( "Content-type:$cntType" );
            }
            exit( $out );
        }
        public function execute(){
            $isFormReq = RestUtils::requestOriginIsForm();
            $reqMethod = RestUtils::getRequestMethod();
            $reqURI = RestUtils::getRequestURI();
            $reqBody = RestUtils::getRequestBody();
            $reqParams = array(
                "body" => array(),
                "uri" => array()
            );
            $resource = $this->getResourceByURI( $reqURI );
            if ( $resource === null ) $this->throwHttpError(404);
            if ( !$resource->supportsRequestMethod( $reqMethod ) ) $this->throwHttpError(405);
            $reqParams[ "uri" ] = $resource->extractUriParameters( $reqURI, $reqMethod );
            if ( $isFormReq ){
                parse_str( $reqBody, $reqParams[ "body" ] );
            } else {
                $reqParams[ "body" ] = json_decode( $reqBody, true );
            }
            $reqParams = RestUtils::combineUriAndBodyArgs( $reqParams );
            $reqParams = $this->sanitizeHtmlSpecialChars( $reqParams );
            if ( !$resource->inputIsValid( $reqParams, $reqMethod ) ) $this->throwHttpError(400);
            $this->outputData[ "links" ] = $resource->getLinks();
            $resource->callback( $this, $reqMethod, $reqParams );
        }

        private function getOutputAsHtml(){
            $html = "<html><head><link rel='stylesheet' href='"
                . $this->config[ "style" ][ "resource" ]
                . "'></head><body><h3>Status</h3>$this->outputStatus<br><h3>Response</h3>";
            if ( is_string( $this->outputData ) ){
                $response = $this->outputData;
            } else {
                $response = RestUtils::convertJsonToHtml( $this->outputData );
            }
            $html = "$html$response</body></html>";
            return $html;
        }
        private function getOutputAsJson(){
            $json = json_decode( "{}", true );
            $json[ "status" ] = $this->outputStatus;
            $d = $this->outputData;
            $json[ "response" ] = $d;
            return json_encode( $json );
        }
        private function getResourceByURI( $uri ){
            foreach ( $this->resources as $r ){
                if ( strpos( $uri, $r->getURI() ) === 0 ){
                    return $r;
                }
            }
            return null;
        }
        private function createHyperlinks( $links ){
            if ( $this->outputFormat == "JSON" ) return $links;
            for ( $i=0; $i < count( $links ); $i++ ){
                $l = $links[ $i ];
                $uri = $l[ "uri" ];
                $method = $l[ "method" ];
                $formFields = array();
                $uriParams = array();
                if ( isset( $l[ "args" ] ) ){
                    $args = $l[ "args" ];
                    if ( isset( $args[ "body" ] ) && is_array( $args[ "body" ] ) ){
                        foreach ( $args[ "body" ] as $k => $v ){
                            $formFields[] = array(
                                "name" => $k,
                                "type" => "input"
                            );
                        }
                    }
                }
                if ( RestUtils::hasMethodRequestBody( $method ) || $method == "DELETE" ){
                    $formObj = urlencode( json_encode( array(
                        "action" => "$this->apiURL/$uri",
                        "method" => $method,
                        "fields" => $formFields
                    ) ) );
                    $uri = "<a class='richrest-link' href='"
                        . $this->config[ "form" ]
                        . "/?template=$formObj'>$uri</a>";
                } else {
                    $uri = "<a class='richrest-link' href='$this->apiURL/$uri'>$uri</a>";
                }
                $links[ $i ][ "uri" ] = $uri;
            }
            return $links;
        }
        private function sanitizeHtmlSpecialChars( $array ){
            foreach ( $array as $k => $v ){
                $array[ $k ] = htmlspecialchars( $v );
            }
            return $array;
        }
        private function throwHttpError( $errorCode ){
            $this->setOutputStatus( $errorCode );
            $msg = $this->error[ $errorCode ];
            $this->setOutputData( $msg );
            $this->sendResponse();
        }
    }




    abstract class RestResource {

        function __construct(){}

        abstract public function callback( $api, $method, $data );

        public function getURI(){
            $uri = $this->data[ "uri" ];
            if ( substr( $uri, -1 ) == "/" ) $uri = substr( $uri, 0, -1 );
            return $uri;
        }
        public function getLinks(){
            return $this->data[ "links" ];
        }
        public function getMethods(){
            return $this->data[ "methods" ];
        }
        public function supportsRequestMethod( $method ){
            return in_array( $method, $this->data[ "methods" ] );
        }
        public function inputIsValid( $params, $method ){
            $hasBody = RestUtils::hasMethodRequestBody( $method );
            $filterArgs = $this->getAllArgumentsForMethod( $method );
            $validated = filter_var_array( $params, $filterArgs );
            foreach ( $params as $name => $value ){
                if ( $name != "originalRequestMethod" && !isset( $filterArgs[ $name ] ) ) return false;
            }
            foreach ( $validated as $name => $value ){
                if ( $value === null ) return false;
                if ( $value === false ) return false;
                if ( $filterArgs[ $name ] == "number" && !is_numeric( $value ) ) return false;
                if ( $filterArgs[ $name ] == "bool" ){
                    if ( is_string( $value ) ) $value = strtolower( $value );
                    if ( array_search( $value, ["true","false"] ) === false
                        && gettype( $value ) != "boolean"
                    ) return false;
                }
            }
            return true;
        }
        public function extractUriParameters( $uri, $method ){
            if ( $uri == $this->data[ "uri" ] ) return array();
            $uriArgs = explode( $this->data[ "uri" ] . "/", $uri );
            if ( $uriArgs === null ) return array();
            $uriArgs = $uriArgs[1];
            if ( isset( $this->data[ "args" ][ $method ] ) ){
                if ( isset( $this->data[ "args" ][ $method ][ "uri" ] )
                    && is_array( $this->data[ "args" ][ $method ][ "uri" ] )
                ){
                    $paramNames = array_keys( $this->data[ "args" ][ $method ][ "uri" ] );
                } else {
                    $paramNames = array_keys( $this->data[ "args" ][ $method ] );
                }
            } else {
                $paramNames = array();
            }
            $values = explode( "/", $uriArgs );
            if ( $values[0] == "" ) return array();
            $params = array();
            if ( count( $paramNames ) == 0 ) return array();
            for ( $i=0; $i < count( $values ); $i++ ){
                if ( isset( $paramNames[ $i ] ) ){
                    $params[ $paramNames[ $i ] ] = $values[ $i ];
                }
            }
            return $params;
        }
        public function addParametersToLinkUri( $params, $linkUri ){
            for ( $i=0; $i < count( $this->data[ "links" ] ); $i++ ){
                if ( $this->data[ "links" ][ $i ][ "uri" ] == $linkUri ){
                    foreach ( $params as $p ){
                        $this->data[ "links" ][ $i ][ "uri" ] .= "/$p";
                    }
                }
            }
        }

        private function getAllArgumentsForMethod( $method ){
            return RestUtils::combineUriAndBodyArgs( $this->data[ "args" ][ $method ] );
        }
    }




    class RestUtils {

        public static function hasMethodRequestBody( $requestMethod ){
            return !in_array( $requestMethod, ["GET","HEAD","DELETE"] );
        }
        public static function getRequestMethod(){
            if ( RestUtils::requestOriginIsForm() ) return $_POST[ "originalRequestMethod" ];
            return $_SERVER[ "REQUEST_METHOD" ];
        }
        public static function getRequestURI(){
            $in = "";
            foreach( $_GET as $p => $v ){
                $in = "$in$v/";
            }
            return substr( $in, 0, -1 );
        }
        public static function getRequestBody(){
            return file_get_contents( "php://input" );
        }
        public static function convertJsonToHtml( $array, $indent=0 ){
            $html = "<ul>\n";
            foreach ( $array as $k => $v ){
                $html .= str_repeat( "\t", $indent + 1 ) . "<li><span class='richrest-key'>$k:</span> ";
                if ( is_array( $v ) ){
                    $html .= ( "<span class='richrest-value'>" . RestUtils::convertJsonToHtml( $v, $indent + 1 ) . "</span>" );
                } else {
                    $html .= ( "<span class='richrest-value'>$v</span>" );
                }
                $html .= "</li>\n";
            }
            $html .= str_repeat( "\t", $indent ) . "</ul>\n";
            return $html;
        }
        public static function requestOriginIsForm(){
            return isset( $_POST[ "originalRequestMethod" ] );
        }
        public static function combineUriAndBodyArgs( $array ){
            $out = array();
            $issetBody = isset( $array[ "body" ] );
            $issetUri = isset( $array[ "uri" ] );
        // exception for args format: array( "id" => "number" )
            if ( !$issetBody && !$issetUri && count( $array ) > 0 ) return $array;
        // handle args format: array( "uri" => array(....), "body" => array(....) )
            if ( $issetBody ){
                $body = $array[ "body" ];
            } else {
                $body = array();
            }
            $uri = array();
            if ( $issetUri ) $uri = $array[ "uri" ];
            foreach ( $body as $argName => $argType ){
                $out[ $argName ] = $argType;
            }
            foreach ( $uri as $argName => $argType ){
                $out[ $argName ] = $argType;
            }
            return $out;
        }
    }
?>
