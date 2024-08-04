<?php

    include_once "/var/www/html/script/rich3rest.php";


    trait Aux {

        public $isLeapYear = true;
        public $dataDir = "/var/www/html/data";
        public $blankLog = array(
            "title" => "",
            "description" => "",
            "originDate" => "",
            "records" => array()
        );

        public function julianDate(){
            $date = explode( ".", date( "d.m.y" ) );
            $d = intval( $date[0] );
            $m = intval( $date[1] );
            $monthDays = [ 0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
            if ( $this->isLeapYear ) $monthDays[2] = 29;
            for ( $i=1; $i < $m; $i++ ) $d += $monthDays[ $i ];
            return $d;
        }
        public function getLog( $logName ){
            $f = "$this->dataDir/$logName.json";
            try {
                $cnt = file_get_contents( $f );
            } catch( Exception $e ){
                return false;
            }
            if ( !$cnt ) return false;
            return json_decode( $cnt, true );
        }
        public function getLogList(){
            $dir = scandir( $this->dataDir );
            $out = array();
            foreach ( $dir as $logFile ){
                if ( $logFile[0] != "." ){
                    $out[] = explode( ".", $logFile )[0];
                }
            }
            return $out;
        }
        public function writeIntoLog( $logName, $data ){
            if ( !$log = $this->getLog( $logName ) ) return false;
            $logFile = "$this->dataDir/$logName.json";
            $r = file_put_contents( $logFile, json_encode( $data ), LOCK_EX );
            return $r;
        }
        public function createNewLog( $logName, $logInfo ){
            $this->blankLog[ "title" ] = $logName;
            $this->blankLog[ "description" ] = $logInfo;
            $json = json_encode( $this->blankLog, JSON_FORCE_OBJECT );
            return file_put_contents( "$this->dataDir/$logName.json", $json, LOCK_EX );
        }
        public function extractLogDataType( $logName, $dataType ){
            $log = $this->getLog( $logName );
            $out = array();
            foreach ( $log[ "records" ] as $d => $r ){
                if ( isset( $r[ $dataType ] )
                    && count( $r[ $dataType ] ) > 0 ){
                        $out[ $d ][] = $r[ $dataType ];
                }
            }
            return $out;
        }
        public function addLogNameToLinkUris( $logName ){
            $res = [ "log/get", "list/comments", "list/events", "record/new" ];
            foreach ( $res as $uri ){
                $this->addParametersToLinkUri( [ $logName ], $uri );
            }
        }
    }



    $CONFIG = array(
        array(
            "uri" => "log/get",
            "rel" => "Get complete log",
            "methods" => array( "GET" ),
            "args" => array(
                "GET" => array(
                    "logName" => "string"
                )
            )
        ),
        array(
            "uri" => "log/new",
            "rel" => "Create a new log",
            "methods" => array( "POST" ),
            "args" => array(
                "POST" => array(
                    "body" => array(
                        "logName" => "string",
                        "logInfo" => "string"
                    )
                )
            )
        ),
        array(
            "uri" => "list/logs",
            "rel" => "Get a list with all available logs",
            "methods" => array( "GET" ),
            "args" => array()
        ),
        array(
            "uri" => "list/events",
            "rel" => "Get a list with all events in a given log",
            "methods" => array( "GET" ),
            "args" => array(
                "GET" => array(
                    "logName" => "string"
                )
            )
        ),
        array(
            "uri" => "list/comments",
            "rel" => "Get a list with all comments in a given log",
            "methods" => array( "GET" ),
            "args" => array(
                "GET" => array(
                    "logName" => "string"
                )
            )
        ),
        array(
            "uri" => "record/get",
            "rel" => "Get a record",
            "methods" => array( "GET" ),
            "args" => array(
                "GET" => array(
                    "recordId" => "number"
                )
            )
        ),
        array(
            "uri" => "record/new",
            "rel" => "Create a new record",
            "methods" => array( "POST" ),
            "args" => array(
                "POST" => array(
                    "body" => array(
                        "recType" => "string",
                        "recData" => "string"
                    ),
                    "uri" => array(
                        "logName" => "string"
                    )
                )
            )
        )
    );
    $DIR = new RestResourceDirectory( $CONFIG );




    class LogGet extends RestResource {

        use Aux;

        public function callback( $api, $method, $data ){
            $logName = $data[ "logName" ];
            if ( !$logData = $this->getLog( $logName ) ) $api->throwHttpError(404);
            $this->addLogNameToLinkUris( $logName );
            $outputData = array(
                "julianDate" => $this->julianDate(),
                "logName" => $logName,
                "logData" => $logData
            );
            $api->setOutputData( $outputData );
            $api->sendResponse( $this );
        }
    }

    class LogNew extends RestResource {

        use Aux;

        public function callback( $api, $method, $data ){
            $logName = $data[ "logName" ];
            $logInfo = $data[ "logInfo" ];
            if ( $this->getLog( $logName ) ) $api->throwHttpError(409);
            $this->addLogNameToLinkUris( $logName );
            if ( !$this->createNewLog( $logName, $logInfo ) ) $api->throwHttpError(500);
            $api->setOutputData( array(
                "data" => $data,
            ) );
            $api->sendResponse( $this );
        }
    }

    class ListLogs extends RestResource {

        use Aux;

        public function callback( $api, $method, $data ){
            $logList = $this->getLogList();
            $api->setOutputData( $logList );
            $api->sendResponse( $this );
        }
    }

    class ListEvents extends RestResource {

        use Aux;

        public function callback( $api, $method, $data ){
            $logName = $data[ "logName" ];
            $events = $this->extractLogDataType( $logName, "events" );
            $this->addLogNameToLinkUris( $logName );
            $api->setOutputData( $events );
            $api->sendResponse( $this );
        }
    }

    class ListComments extends RestResource {

        use Aux;

        public function callback( $api, $method, $data ){
            $logName = $data[ "logName" ];
            $comments = $this->extractLogDataType( $logName, "comments" );
            $this->addLogNameToLinkUris( $logName );
            $api->setOutputData( $comments );
            $api->sendResponse( $this );
        }
    }

    class RecordGet extends RestResource {  //TODO

        use Aux;

        public function callback( $api, $method, $data ){
            $logName = "";
            $this->addLogNameToLinkUris( $logName );
            $api->setOutputData( $data );
            $api->sendResponse( $this );
        }
    }

    class RecordNew extends RestResource {

        use Aux;

        public function callback( $api, $method, $data ){
            $logName = $data[ "logName" ];
            $recData = $data[ "recData" ];
            $recType = strtolower( $data[ "recType" ] );
            if ( !$logData = $this->getLog( $logName ) ) $api->throwHttpError(404);
            $jdate = $this->julianDate();
            $data[ "julianDate" ] = $jdate;
            if ( in_array( $recType, ["event","comment"] ) ){
                $logData[ "records" ][ $jdate ][ $recType . "s" ][] = $recData;
            } else {
                $logData[ "records" ][ $jdate ][ $recType ] = $recData;
            }
            if ( !$this->writeIntoLog( $logName, $logData ) ) $api->throwHttpError(404);
            $this->addLogNameToLinkUris( $logName );
            $api->setOutputData( $data );
            $api->sendResponse( $this );
        }
    }


    $API = new RestAPI( "/api/grow-log" );
    $API->addResource( new RecordGet( $DIR, "record/get" ) );
    $API->addResource( new RecordNew( $DIR, "record/new" ) );
    $API->addResource( new LogGet( $DIR, "log/get" ) );
    $API->addResource( new LogNew( $DIR, "log/new" ) );
    $API->addResource( new ListLogs( $DIR, "list/logs" ) );
    $API->addResource( new ListEvents( $DIR, "list/events" ) );
    $API->addResource( new ListComments( $DIR, "list/comments" ) );
    $API->execute();

?>
