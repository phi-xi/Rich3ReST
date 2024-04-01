    _____    __    ___ __   __ ____   _____             ____ ________
    ||  \\   []   //// ||   || \\\\\  ||  \\           ///// ||||||||
    ||   \\      //    ||   ||     \\ ||   \\  ______ //        ||
    ||   //  || ||     ||   ||     // ||   //  ||//// \\        ||
    ||__//   || ||     |||||||  ||||  ||__//   ||      \\\\\    ||
    ||  \\   || ||     ||   ||     \\ ||  \\   ||||        \\   ||
    ||   \\  ||  \\    ||   ||     // ||   \\  ||          //   ||
    ||    \\ ||   \\\\ ||   || /////  ||    \\ ||\\\\ //////    ||


# Rich3ReST
### (c) PhiXi, 2024

## Abstract
Rich3ReST provides a tiny interface for ReST API development. Rich3ReST implements ReST on Richardson Maturity level 3 (including the HATEOAS paradigm), offering the option to serve resources as JSON or HTML representation. An HTML representation of a resource shows its links as 'clickable' hyperlinks, allowing users to browse the API. If a hyperlink refers to a resource that requires parameters, the hyperlink leads to a generic HTML form providing users with a UI to pass the required parameters. Rich3ReST supports common HTTP methods (GET, POST, PUT, DELETE, HEAD) and makes use of HTTP error codes 400, 404 and 405 when validating input.

## License
Rich3ReST is released under MIT license (https://opensource.org/license/mit): \
\
Copyright 2024 PhiXi (phi.xi@aol.com)\
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:\
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.\
THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

## Installation
Without modification of 'rich3rest.php' and/or 'rich3rest-config.json', the files
- rich3rest.php
- rich3rest-config.json
- rich3rest-form.php



MUST be located in the same directory.\
Custom CSS style for the generic HTML form and the HTML resource representation MAY be included by referencing the URLs to the CSS files in 'rich3rest-config.json'.\
Custom path to the generic HTML form MAY be defined in the same file.\
The redirection rules in the provided .htaccess file MUST be applied (this is required for correct URI argument parsing).

## Documentation
The well-commented file 'rich3rest-example.php' demonstrates the API usage.

### Basic Concept
A ReST API has a URL which is the location of the PHP script that implements the API and includes 'rich3rest.php'. A ReST API is implemented as an instance of class RestAPI.\
A ReST API provides at least one resource. A resource is identified by its URI and can be represented as JSON or HTML. Each resource has a callback that implements the logic of the resource. A resource MUST accept at least one HTTP request method. A resource MAY accept arguments in the request uri and/or the request body. A resource MAY have links pointing to other resources of the same RestAPI.\
Each resource is implemented as a child class of the abstract class RestResource. This child class MUST define the public property 'data' and MUST implement the public callback method 'callback'.\
Finally, an instance of each resource class is created and passed to the RestAPI instance using RestAPI::addResource().\
When the API receives a request, the RestAPI instance will read and parse all parameters in the request URI and in the request body, then the resource matching the request URI (including required arguments, if any) is selected and its callback executed, passing the RestAPI instance, the request method and the request data.\
In the event of failure, the RestAPI instance responds with
- 404 error, if no resource was found
- 405 error, if the request method is not supported by the resource
- 400 error, if validation of the arguments passed fails*



\* validation checks for types "number" (=float/int/...), "bool" (="True","true",true,...) and "string"; if RestAPI is set to HTML output, it will perform a rudimentary input sanitizing, applying htmlspecialchars()
\
\
\
NOTE: The following documentation lists only public methods.

### Class RestAPI
#### RestAPI::__contruct( String apiUrl )
|Parameter|Description|
|---|---|
|apiUrl|The base URL of this api (typically the URL of the API's script)|
***
#### RestAPI::addResource( RestResource res )
Add a resource to the RestAPI; see documentation of class RestResource for details
|Parameter|Description|
|---|---|
|res|An instance of a RestResource child class|
|RETURN||
***
#### RestAPI::setOutputStatus( Integer status )
Set the HTTP response status code.
|Parameter|Description|
|---|---|
|status|The HTTP status code to return (0 will be considered as 200)|
|RETURN||
***
#### RestAPI::setOutputData( Array|String data )
Set the data for the response.
|Parameter|Description|
|---|---|
|data|The response data, may be string or associative array|
|RETURN||
***
#### RestAPI::setOutputFormat( String format )
Specify HTML or JSON representation of the resources of this RestAPI.
|Parameter|Description|
|---|---|
|format|Valid values are "JSON" or "HTML"|
|RETURN||
***
#### RestAPI::sendResponse( RestResource res )
Send the response to the client (note this includes script termination).
|Parameter|Description|
|---|---|
|res|(optional) If given, the links of the RestResource will be added to the response|
|RETURN||
***
#### RestAPI::execute()
Execute the API; this includes selection of the resource matching to the request URI, error handling, extraction of request parameters (from both URI and request body) and calling the callback method of the resource found, passing the RestAPI object, request method and request data to it.
***
***
***
### Abstract Class RestResource
#### RestResource::__contruct()
***
#### RestResource::getURI() : String
Get the URI of this resource.
|Parameter|Description|
|---|---|
|RETURN|The URI of this resource as relative path to this script's path|
***
#### RestResource::getLinks() : Array
Get the links assigned to this resource.
|Parameter|Description|
|---|---|
|RETURN|An associative array with the links assigned to this resource (empty array if it has no links)|
***
#### RestResource::getMethods() : Array
Get the methods this resource supports.
|Parameter|Description|
|---|---|
|RETURN|An array with the request methods this resource supports|
***
#### RestResource::supportsRequestMethod( String method ) : Bool
Check if a request method is supported by the resource.
|Parameter|Description|
|---|---|
|method|The HTTP request method to check for support (capital letters, e.g. "GET")|
|RETURN|True if the given method is supported, otherwise false|
***
#### RestResource::inputIsValid( Array params, String method ) : Bool
Validate the request parameters for a given request method.
|Parameter|Description|
|---|---|
|params|Associative array with request parameters|
|method|The HTTP request method (capital letters, e.g. "GET")|
|RETURN|True if all expected parameters are passed in the correct format, otherwise false|
***
#### RestResource::extractUriParameters( String uri, String method ) : Array
Get the request parameters passed in the request URI.
|Parameter|Description|
|---|---|
|uri|The request URI|
|method|The HTTP request method (capital letters, e.g. "GET")|
|RETURN|An array with request parameters passed in URI (empty array if no parameters passed)|
***
#### RestResource::addParametersToLinkUri( Array params, String uri )
Add the parameters in params to uri, separated by '/'; typically used inside the callback of the RestResource.
|Parameter|Description|
|---|---|
|params|Associative array with request parameters|
|uri|The request URI|
|RETURN||
***
***
***
### Class RestUtils
#### static RestUtils::hasMethodRequestBody( String method ) : Bool
Check if a given HTTP method may have a request body.
|Parameter|Description|
|---|---|
|method|The HTTP request method (capital letters, e.g. "GET")|
|RETURN|False for methods GET/HEAD/DELETE, otherwise true|
***
#### static RestUtils::getRequestURI() : String
Get the request URI.
|Parameter|Description|
|---|---|
|RETURN|The URI as relative path to this script's path|
***
#### static RestUtils::getRequestMethod() : String
Get the request method.
|Parameter|Description|
|---|---|
|RETURN|The HTTP request method in capital letters|
***
#### static RestUtils::getRequestBody() : String
Get the request body.
|Parameter|Description|
|---|---|
|RETURN|The request body as raw string, empty string if no request body (reads from php://input)|
***
#### static RestUtils::convertJsonToHtml( Array json, indent=0 ) : String
Get an HTML representation of a given JSON.
|Parameter|Description|
|---|---|
|json|Associative array|
|indent|(optional) The text indentation level, default is 0|
|RETURN|An HTML representation of the JSON data in indented style|
***
#### static RestUtils::requestOriginIsForm() : Bool
Check if the request is originated from the generic form.
|Parameter|Description|
|---|---|
|RETURN|True if the request comes from the generic form, otherwise false|
***
#### static RestUtils::combineUriAndBodyArgs( Array arguments ) : Array
Get all arguments passed (in URI and request body) in one array
|Parameter|Description|
|---|---|
|arguments|Associative array that includes two arrays with keys "uri" and "body" that hold the URI/body arguments|
|RETURN|Array with arguments of URI and body|
***
