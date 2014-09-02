<?php 
/**
 *  A Wordpress Client for the Wolfnet API
 */



class Wolfnet_Api_Wp_Client
{
        
    /**
     * if set to true print extended information about the request and the response as well as the data
     * @var boolean
     */
    private $debug = false;

    /**
     * Defins how long, in seconds, we should cache api responses before we query the server again
     * @var int
     */
    // private $requestTtl = (30 * 60); // 30 min
    private $requestTtl = 1800; // 30 min

    /**
     * The hostname for the API server.
     * @var string
     */
    private $host = 'api.dev.wolfnet.com';

    /**
     * The version of the API to make requests of.
     * @var string
     */
    private $version = "1";

    /**
     * The temporary token retrieved after authorizing the with the Api key
     * @var string
     */
    private $apiToken; 

    /**
     * This property is a unique identifier for a value in the WordPress Transient API where
     * references to other transient values are stored.
     * must be less than 12 characters 
     * @var string
     */
    public $transientIndexKey = 'wnt_tran_';


    /**
     * This method is the public interface for making requests of the API.
     * @param  string $key      The client's API key.
     * @param  string $resource The URI endpoint being requested from the API.
     * @param  string $method   The HTTP method the request should be submitted as.
     * @param  array  $data     Any query string or body data to be include with the request.
     * @param  array  $headers  Any header data to be included with the request.
     * @return [type]           Data returned by the API server.
     */
    public function sendRequest(
        $key, 
        $resource, 
        $method = "GET", 
        $data = array(), 
        $headers = array()
    )
    {
        return $this->rawRequest($key, $resource, $method, $data, $headers);
    }

    
    /**
     * [rawRequest description]
     * @param  string  $key      The client's API key.
     * @param  string  $resource The URI endpoint being requested from the API.
     * @param  string  $method   The HTTP method the request should be submitted as.
     * @param  array   $data     Any query string or body data to be include with the request.
     * @param  array   $headers  Any header data to be included with the request.
     * @param  boolean $skipAuth Should this request skip authentication with the API? This
     *                           parameter is used as part of the automatic authentication
     *                           process. The same function is used to perform authentication
     *                           but should not be authenticated itself.
     * @param  boolean $reAuth   Is this current function call an attempt to re-authenticate
     *                           after an initial failed attempt to retrieve data from the API.
     *                           This attempt will only be made once before throwing an exception.
     * @return array             A representation of the data that was returned successfully.
     *                              
     *                           
     */
    private function rawRequest(
        $key, 
        $resource, 
        $method = "GET", 
        $data = array(), 
        $headers = array(), 
        $skipAuth =  false,
        $reAuth = false
    )
    {
        $method = strtoupper($method);


        // Make sure the resource is valid.
        $err = $this->isValidResource($resource);
        if (is_wp_error($err))  return $err;

        // Make sure the method is valid.
        $err = $this->isValidMethod($method);
        if (is_wp_error($err))  return $err;

        // Make sure the data is valid.
        $err = $this->isValidData($data);
        if (is_wp_error($err))  return $err;

        // Retrieve a fully qualified URL for the request based on the requested resource.
        $full_url = $this->buildFullUrl($resource);

        // Unless told otherwise, attempt to retrieve an API token.
        if (!$skipAuth) {
            
            $api_token = $this->getApiToken( $key, $reAuth);
            if (is_wp_error($api_token))  return $api_token;
            $headers['api_token'] = $api_token;
            $headers['Accept-Encoding'] = 'gzip, deflate';
            //$headers['v'] = "1";
        }


        $args = array(
                'method'   => $method,
                'headers'  => $headers,
        );

        //set up headers, body, and url data as needed
        switch ($method) {
            case 'GET':
                $full_url = add_query_arg($data, $full_url);
                break;
            case 'POST':

                $args['body'] = $data;
                break;
            case 'PUT':
                $args['headers']['Content-Type'] = "application/json";
                $args['body'] = json_encode($data);
                break;
        }

        // check to see if we have this cached:
        $transient_key = $this->transientIndexKey . md5($full_url . json_encode($args));

        // set response to the value of the transient if it is valid
        if ( ($response = get_transient($transient_key)) === false ) {

            $api_response = wp_remote_request($full_url, $args);

            // echo "<pre>full_url:\n";
            // print_r($full_url);

            // echo "\n\nargs\n";
            // print_r($args);

            // echo "\n\napi_response: \n";
            // print_r($api_response);
            // echo "</pre>";
    
            if (is_wp_error($api_response)) {
                return $api_response;
            }

// COLD FUSION CODE
//             // The API returned a 401 Unauthorized so throw an exception.
// if (apiResponse.responseStatusCode == 401) {
//     throw(type="wolfnet.api.client.Unauthorized",
//         message=httpPrefix.status_text,
//         extendedInfo=serializeJSON(apiResponse));
            
            // The API returned a 401 Unauthorized
            if ($api_response['response']['code'] == 401)
                return new WP_Error( '401', __( "401 Unauthorized" ), $api_response );
            
            // The API returned a 503 Forbidden
            if ($api_response['response']['code'] == 503)
                return new WP_Error( '503', __( "503 Service Unavailable" ), $api_response );

            // The API returned a 403 Forbidden 
            if ($api_response['response']['code'] == 403)
                return new WP_Error( '403', __( "403 Forbidden" ), $api_response );

            // if ($api_response['response']['code'] == xxx)
            //     return new WP_Error( 'xxx', __( "xxx" ), $api_response );
            

            // TODO: rewrite in php
            // The API returned a 400 Bad Response because the token it was given was not valid, so attempt to re-authenticated and perform the request again.
            // if ($api_response['response']['code'] == 400)
//                && ( 
//                    (structKeyExists(apiResponse.responseData.metadata.status, "errorCode") && apiResponse.responseData.metadata.status.errorCode == "Auth1005")
//         //|| (structKeyExists(apiResponse.responseData.metadata.status, "statusCode") && apiResponse.responseData.metadata.status.statusCode == "Auth1005")
//     )
//     && !arguments.reAuth) {
//     return rawRequest(argumentCollection=arguments, reAuth=true);
                
            //    return new WP_Error( 'xxx', __( "xxx" ), $api_response );

            // if ($api_response['response']['code'] == xxx)
            //     return new WP_Error( 'xxx', __( "xxx" ), $api_response );

           


// // The API returned a 403 Forbidden so throw an exception
// } else if (apiResponse.responseStatusCode == 403) {
//     throw(type="wolfnet.api.client.Forbidden",
//         message=httpPrefix.status_text,
//         extendedInfo=serializeJSON(apiResponse));

// // The API returned a 400 Bad Response because the token it was given was not valid, so attempt to re-authenticated and perform the request again.
// } else if (apiResponse.responseStatusCode == 400
//     && (
//         (structKeyExists(apiResponse.responseData.metadata.status, "errorCode") && apiResponse.responseData.metadata.status.errorCode == "Auth1005")
//         || (structKeyExists(apiResponse.responseData.metadata.status, "statusCode") && apiResponse.responseData.metadata.status.statusCode == "Auth1005")
//     )
//     && !arguments.reAuth) {
//     return rawRequest(argumentCollection=arguments, reAuth=true);

// // We received an unexpected response from the API so throw an exception.
// } else if (apiResponse.responseStatusCode != 200) {
//     throw(type="wolfnet.api.client.BadResponse",
//         message=httpPrefix.status_text,
//         extendedInfo=serializeJSON(apiResponse));

// }
    
            // build an array with useful representation of the response 
            $response = array(
                'requestUrl' => $full_url,
                'requestMethod' => $method,
                'requestData' => $data,
                'responseStatusCode' => $api_response['response']['code'],
                'responseData' => $api_response['body'],
                'timestamp' => time(),
                );
    
            // If the response type is JSON convert it to an array.
            // ie if the response content type contains the string 'application/json'
            if ( strpos($api_response['headers']['content-type'], 'application/json') == "application/json") {
                $response['responseData'] = json_decode( $response['responseData'], true ); 
            }

            set_transient($transient_key, $response, $this->requestTtl);

        }

        return $response;

    }


    private function buildFullUrl( $resource )
    {
        // TODO: The environment configuration needs to be updated to be only a host name and not include protocol.
        // variables.apiHostName & arguments.resource;
        // ?? protocol  needed http://?
        return 'http://' .$this->host . $resource;

    }


    /**
     * This method validates that a provided resource string is formatted correctly.
     * @param  string  $resource The URI endpoint being requested from the API.
     * @return bool|WP_Error     True if ok. WP_Error on failure 
     */
    private function isValidResource($resource)
    {
        // TODO: Add more validation criteria.

        // If the resource does not start with a leading slash it is not valid.
        if (substr($resource, 0, 1) !== "/") {
            return new WP_Error('badResource', __("The Resource does not start with a leading slash."));
        } else {
            return true;
        }

    }

    /**
     * This method validates that a given method string matches one that is supported by the API.
     * @param  string  $method    The HTTP method the request should be submitted as.
     * @return boolean|WP_Error   Is the method valid? true or WP_Error if not
     */
    private function isValidMethod( $method )
    {
        $valid = array('GET','POST','PUT','DELETE');
        if (in_array($method, $valid)) {
            return true;
        } else {
            return new WP_Error('badMethod', __("$method is not a valid http method"));
        }

    }


    /**
     * This method validates that the given data can be used with the API request.
     * @param  array   $data     Any query string or body data to be include with the request.
     * @return boolean|WP_Error  Is the data valid? true or WP_Error if not
     */
    private function isValidData( $data = array() )
    {
        $valid = true;

        // Ensure that only simple values are included in the data. ie. strings, numbers, and booleans.
        foreach  ($data as $key => $value) {
            if (!is_scalar($value)) {
                if (is_wp_error($valid)) { // if we already have error add a message to it
                    $valid->add('badData', __("invalid data type $key : $value"));
                } else {
                    $valid = new WP_Error('badData', __("invalid data type $key : $value"), $data);
                }
            }
        }

        return $valid;

    }


    private function getApiToken( $key, $force = false)
    {
        // Unless forced to do otherwise, attempt to retrieve the token from a cache.
        $transient_key = $this->transientIndexKey . $key;
        $token = get_transient( $transient_key );
        //$token = $force ? "" : $this->retrieveApiTokenDataFromCache($key);

         
        // If a token was not retrieved from the cache perform an API request to retrieve a new one.
        if ($token == "") {
            $data = array(
                'key' => $key,
                'v' => $this->version,
            );
            // echo "how about here?<br>";
            $auth_response = $this->rawRequest(
                $key,
                '/core/auth',
                "POST",
                $data,
                array(),
                true // Since we don't have a valid token we don't want to attempt to include it.
                );
            
            // check if valid response
            if (is_wp_error($auth_response))  return $auth_response;
                    
            // TODO: Validate that the response includes the data we need.
            $token = $auth_response['responseData']['data']['api_token'];

            // time to live. when should this transient expire?
            // expiration time - time created - 5
            $ttl = ( strtotime($auth_response['responseData']['data']['expiration']) - strtotime($auth_response['responseData']['data']['date_created']) - 5 );

            // check if valid int greater than 0 less then #seconds in 7 days
            if (!is_int($ttl) && $ttl < 0 && $ttl > (60*60*24*7)) 
                $ttl = 60*60; // if we cant calculate then default to an hour 

            $transient_key = $this->transientIndexKey . $key;

            set_transient( $transient_key, $token, $ttl );

        }

        return $token;

    }


}

