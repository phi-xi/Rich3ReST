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
        private $outputFormat = "json";
        private $outputContentType = array(
            "json" => "application/json",
            "html" => "text/html",
            "csv" => "text/csv",
            "txt" => "text/plain"
        );
        private $error = array(
            "400" => "Bad Request",
            "403" => "Forbidden",
            "404" => "Not Found",
            "405" => "Method Not Allowed",
            "409" => "Conflict",
            "410" => "Gone",
            "412" => "Precondition Failed",
            "500" => "Internal Server Error"
        );
        private $configFile = null;
        private $config = array();

        function __construct(){
            $apiUrl = "/api/" . RestUtils::getCurrentWorkDirName();
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
            $out = array(
                "resource" => RestUtils::getRequestURI(),
                "method" => RestUtils::getRequestMethod(),
                "data" => RestUtils::removeEmptyArrays( $dataArrayOrString )
            );
            $this->outputData = $out;
        }
        public function setOutputFormat( $format ){
            $this->outputFormat = strtolower( $format );
        }
        public function sendResponse( $resource=null ){
            if ( $resource !== null ){
                $this->outputData[ "links" ] = $this->createHyperlinks( $resource->data[ "links" ] );
            }
            if ( isset( $this->outputData[ "data" ] ) ){
                unset( $this->outputData[ "data" ][ "originalRequestMethod" ] );
                array_values( $this->outputData[ "data" ] );
            }
            $out = $this->getOutputAs();
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
            $reqResponseFormat = RestUtils::getRequestedResponseFormat();
            $reqURI = RestUtils::getRequestURI();
            $reqBody = RestUtils::getRequestBody();
            $reqParams = array(
                "body" => array(),
                "uri" => array()
            );
            $this->setOutputFormat( $reqResponseFormat );
            $resource = $this->getResourceByURI( $reqURI );
            if ( $resource === null ) $this->throwHttpError(404);
            if ( !$resource->supportsRequestMethod( $reqMethod ) ) $this->throwHttpError(405);
            $reqParams[ "uri" ] = $resource->extractUriParameters( $reqURI, $reqMethod );
            if ( $isFormReq ){
                parse_str( $reqBody, $reqParams[ "body" ] );
                $reqParams = RestUtils::combineUriAndBodyArgs( $reqParams );
                $reqParams = RestUtils::sanitizeHtmlSpecialChars( $reqParams );
                if ( !$resource->inputIsValid( $reqParams, $reqMethod ) ) $this->throwHttpError(400);
            } else {
                $reqParams[ "body" ] = json_decode( $reqBody, true );
                $reqParams = RestUtils::combineUriAndBodyArgs( $reqParams );
            }
            $this->outputData[ "resource" ] = $reqURI;
            $this->outputData[ "method" ] = $reqMethod;
            $this->outputData[ "links" ] = $resource->getLinks();
            $resource->callback( $this, $reqMethod, $reqParams );
        }
        public function throwHttpError( $errorCode ){
            $this->setOutputStatus( $errorCode );
            $msg = $this->error[ $errorCode ];
            $this->setOutputData( $msg );
            $this->sendResponse();
        }

        private function getOutputAsHtml(){
            $html = "<html><head><meta name='viewport' content='width=device-width,initial-scale=1,user-scalable=0' /><link rel='stylesheet' href='"
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
        private function getOutputAsCsv( $separator=";" ){
            return "status $separator$separator"
                . $this->outputStatus
                . "\nresponse\n"
                . RestUtils::convertJsonToCsv( $this->outputData, $separator, 1 );
        }
        private function getOutputAsTxt( $separator="\t" ){
            return RestUtils::convertJsonToTxt( $this->outputData, $separator, 1 );
        }
        private function getOutputAs(){
            if ( $this->outputFormat == "json" ) return $this->getOutputAsJson();
            if ( $this->outputFormat == "html" ) return $this->getOutputAsHtml();
            if ( $this->outputFormat == "csv" ) return $this->getOutputAsCsv();
            if ( $this->outputFormat == "txt" ) return $this->getOutputAsTxt();
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
                        "action" => "$this->apiURL/$this->outputFormat/$uri",
                        "method" => $method,
                        "fields" => $formFields
                    ) ) );
                    $uri = "<a class='richrest-link' href='"
                        . $this->config[ "form" ]
                        . "/?template=$formObj'>$uri</a>";
                } else {
                    $uri = "<a class='richrest-link' href='$this->apiURL/$this->outputFormat/$uri'>$uri</a>";
                }
                $links[ $i ][ "uri" ] = $uri;
            }
            return $links;
        }
    }




    abstract class RestResource {

        function __construct( $resourceDirectory, $uri ){
            $this->resDir = $resourceDirectory;
            $this->data = array(
                "uri" => $uri,
                "methods" => $resourceDirectory->getResourceMethods( $uri ),
                "data" => array(),
                "args" => $resourceDirectory->getResourceArgs( $uri ),
                "links" => $resourceDirectory->getResourceLinks( $uri )
            );
        }

        abstract public function callback( $api, $method, $data );

        public $data = null;
        public $resDir = null;

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




    class RestResourceDirectory {

        function __construct( $config ){
            $this->config = $config;
        }

        public $config = array();

        public function getResourceMethods( $uri ){
            return $this->getArrayElement( $uri, "methods" );
        }
        public function getResourceArgs( $uri ){
            return $this->getArrayElement( $uri, "args" );
        }
        public function getResourceArgsForMethod( $uri, $method ){
            return $this->getResourceArgs( $uri )[ $method ];
        }
        public function getResourceLinks( $uri ){
            $links = array();
            foreach ( $this->config as $res ){
                $resUri = $res[ "uri" ];
                $methods = $this->getResourceMethods( $resUri );
                foreach ( $methods as $m ){
                    if ( !( $resUri == $uri && RestUtils::getRequestMethod() == $m ) ){
                        $links[] = array(
                            "uri" => $resUri,
                            "rel" => $res[ "rel" ],
                            "method" => $m,
                            "args" => $this->getResourceArgsForMethod( $resUri, $m )
                        );
                    }
                }
            }
            return $links;
        }

        private function getArrayElement( $uri, $key ){
            foreach ( $this->config as $res ){
                if ( $res[ "uri" ] == $uri ) return $res[ $key ];
            }
            return array();
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
                if ( $p != "p1" ) $in = "$in$v/";
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
        public static function convertJsonToCsv( $array, $separator=";", $indent=0 ){
            $csv = "";
            foreach ( $array as $k => $v ){
                $csv .= str_repeat( $separator, $indent + 1 ) . "$k$separator";
                if ( is_array( $v ) ){
                    $csv .= ( "\n" . RestUtils::convertJsonToCsv( $v, $separator, $indent + 1 ) );
                } else {
                    $csv .= "$v";
                }
                $csv .= "\n";
            }
            return $csv;
        }
        public static function convertJsonToTxt( $array, $separator="\t", $indent=0 ){
            $txt = "";
            foreach ( $array as $k => $v ){
                $padding = 2;
                if ( strlen( $k ) >= 8 ) $padding = 1;
                $txt .= str_repeat( $separator, $indent ) . $k;
                if ( is_array( $v ) ){
                    $txt .= ( str_repeat( $separator, $indent + $padding )
                        . "\n"
                        . RestUtils::convertJsonToTxt( $v, $separator, $indent + 1 ) );
                } else {
                    $txt .= str_repeat( $separator, $padding ) . "$v";
                }
                $txt .= "\n";
            }
            return $txt;
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
            if ( strtolower( gettype( $body ) ) == "string" ) $body = json_decode( $body, true );
            foreach ( $body as $argName => $argType ){
                $out[ $argName ] = $argType;
            }
            foreach ( $uri as $argName => $argType ){
                $out[ $argName ] = $argType;
            }
            return $out;
        }
        public static function getRequestedResponseFormat(){
            return strtoupper( $_GET[ "p1" ] );
        }
        public static function sanitizeHtmlSpecialChars( $array ){
            foreach ( $array as $k => $v ){
                $array[ $k ] = htmlspecialchars( $v );
            }
            return $array;
        }
        public static function removeEmptyArrays( $array ){
            $out = array();
            foreach ( $array as $k => $v ){
                if ( is_array( $v ) ){
                    if ( count( $v ) > 0 ){
                        $out[ $k ] = RestUtils::removeEmptyArrays( $v );
                    }
                } else {
                    if ( $v != "" && $v !== null ){
                        $out[ $k ] = $v;
                    }
                }
            }
            return $out;
        }
        public static function getCurrentWorkDirName(){
            $cwdAbs = explode( DIRECTORY_SEPARATOR, getcwd() );
            $cwd = $cwdAbs[ count( $cwdAbs ) - 1 ];
            return $cwd;
        }
    }

?>
