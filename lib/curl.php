<?php
namespace tools;

/**
 * Call Web services with Curl
 * PATCH | PUT | POST | GET
 *
 * @author Daniel Gomes
 */
class curl
{
    /* generate body content from different data type encodings (JSON, form encoding, etc.) */
    private static function encode(?array $postFields, bool $json, bool $formEncoded)
    {
        if ($json && $postFields) {
            $postFields = json_encode($postFields);
        } elseif ($formEncoded && $postFields) {
            $postFields =  http_build_query($postFields);
        }
        return $postFields;
    }

    /* PATCH request with cURL */
    public static function curlPatch(
        string $url,
        ?array $postFields,
        ?array $addCurlOptions=null,
        bool $json=true,
        bool $formEncoded=false
    ) : array {
        $curlOption = [
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_URL             => $url,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (Android 4.4; Mobile; rv:41.0) Gecko/41.0 Firefox/41.0',
            CURLOPT_CUSTOMREQUEST   => 'PATCH',
            CURLOPT_POSTFIELDS      => self::encode($postFields, $json, $formEncoded),
        ];
        return self::curlAux($url, $addCurlOptions, $curlOption);
    }

    /* PUT request with cURL */
    public static function curlPut(
        string $url,
        ?array $postFields,
        ?array $addCurlOptions=null,
        bool $json=true,
        bool $formEncoded=false
    ) : array {
        $curlOption = [
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_URL             => $url,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (Android 4.4; Mobile; rv:41.0) Gecko/41.0 Firefox/41.0',
            CURLOPT_CUSTOMREQUEST   => 'PUT',
            CURLOPT_POSTFIELDS      =>  self::encode($postFields, $json, $formEncoded),
        ];
        return self::curlAux($url, $addCurlOptions, $curlOption);
    }

    /* POST request with cURL */
    public static function curlPost(
        string $url,
        ?array $postFields,
        ?array $addCurlOptions=null,
        bool $json=true,
        bool $formEncoded=false
    ) : array {
        $curlOption = [
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_URL             => $url,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (Android 4.4; Mobile; rv:41.0) Gecko/41.0 Firefox/41.0',
            CURLOPT_POST            => 1,
            CURLOPT_POSTFIELDS      =>  self::encode($postFields, $json, $formEncoded),
        ];
        return self::curlAux($url, $addCurlOptions, $curlOption);
    }

    /* GET request with cURL */
    public static function curlGet(
        string $url,
        ?array $getFields=null,
        ?array $addCurlOptions=null
    ) : array {
        // URL composition
        if (!is_null($getFields) and sizeof($getFields) != 0) {
            if (!strstr($url, '?')) {
                $url .= '?';
            } else {
                $url .= '&';
            }
            $url .= http_build_query($getFields, null, '&', PHP_QUERY_RFC3986);
        }

        // default options
        $curlOption = [
            CURLOPT_RETURNTRANSFER  => 1,
            CURLOPT_URL             => $url,
            CURLOPT_USERAGENT       => 'Mozilla/5.0 (Android 4.4; Mobile; rv:41.0) Gecko/41.0 Firefox/41.0',
        ];
        return self::curlAux($url, $addCurlOptions, $curlOption);
    }


    /* Auxiliary method called by all Curl Methods */
    private static function curlAux(string $url, ?array $addCurlOptions, ?array $curlOption): array
    {
        // Initialize Curl
        $curl = curl_init();

        // Insert supplementary cURL options
        if (! is_null($addCurlOptions) && is_array($addCurlOptions)) {
            foreach ($addCurlOptions as $k=> $v) {
                $curlOption[$k] = $v;
            }
        }
        $curlOption[CURLOPT_HEADER] = 1;
        $curlOption[CURLOPT_VERBOSE] = 0;

        // Handle HTTPS calls
        if (stristr($url, 'https')) {
            $curlOption[CURLOPT_SSL_VERIFYHOST] = false;
            $curlOption[CURLOPT_SSL_VERIFYPEER] = false;
        }

        curl_setopt_array($curl, $curlOption);

        // send cURL request
        $res = curl_exec($curl);

        // Process output
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $header = substr($res, 0, $headerSize);
        $body = substr($res, $headerSize);
        $httpReturnCode = null;
        $httpReturnCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close
        curl_close($curl);

        return [
            'httpCode'  => $httpReturnCode,
            'res'       => $body,
            'header'    => $header
        ];
    }
}
