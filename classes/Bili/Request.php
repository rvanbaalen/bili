<?php

namespace Bili;

/**
 * Request Class v0.1.4
 * Holds methods for request related methods.
 *
 * CHANGELOG
 * version 0.1.4, 7 Feb 2008
 *   CHG: Changed the redirect method.
 *   CHG: Changed the getProtocol method.
 * version 0.1.3, 10 Jan 2008
 *   CHG: Changed the redirectInternal method.
 * version 0.1.2, 10 Sep 2007
 *   CHG: Changed the redirect method.
 *   CHG: Changed the redirectInternal method.
 * version 0.1.1, 14 Aug 2007
 *   CHG: Changed the redirect method.
 * version 0.1.0, 20 Oct 2006
 *   NEW: Created class.
 */

class Request
{
    public static function redirectInternal($intId)
    {
        /***
         * This method rewrites the querystring to provide an internal link structure.
         * A link like http://www.domain.com?eid=59&iid=48 will be rewritten to
         * http://www.domain.com?eid=59#label_48
         */

        if ($intId > 0) {
            $arrNeedle = array('iid' => $intId);

            $strQuery = self::implodeWithKeys(array_diff($_GET, $arrNeedle), "&") . "#label_{$intId}";
            $strQuery = (strstr($strQuery, "rewrite=") !== false) ?
                str_replace("rewrite=", "/", $strQuery) : "?" . $strQuery;

            self::redirect($strQuery);

            exit();
        }
    }

    public static function redirect($strQuery)
    {
        if (!empty($strQuery)) {
            if (is_numeric($strQuery)) {
                $strLocation = "?eid=" . $strQuery;
            } else {
                $strLocation = $strQuery;
            }

            header("Location: " . self::getURI() . $strLocation);

            exit();
        }
    }

    public static function getURI()
    {
        return self::getRootURI() . self::getSubURI();
    }

    public static function getProtocol()
    {
        if (array_key_exists("HTTPS", $_SERVER) && $_SERVER["HTTPS"] == "on") {
            $strReturn = "https";
        } else {
            $strReturn = "http";
        }

        return $strReturn;
    }

    public static function getRootURI()
    {
        return self::getProtocol() . "://" . $_SERVER["HTTP_HOST"];
    }

    public static function getSubURI()
    {
        return (strlen(dirname($_SERVER['PHP_SELF'])) > 1) ?
            dirname($_SERVER['PHP_SELF']) : substr(dirname($_SERVER['PHP_SELF']), 0, -1);
    }

    public static function getVar($strRequest, $strVarName)
    {
        parse_str(array_pop(explode("?", $strRequest)), $arrRequest);
        foreach ($arrRequest as $key => $value) {
            if (strtolower($key) == strtolower($strVarName)) {
                return $value;
            }
        }
    }

    public static function get($strParam, $strReplaceEmpty = "")
    {
        (isset($_REQUEST[$strParam])) ? $strReturn = $_REQUEST[$strParam] : $strReturn = "";

        if (empty($strReturn) && !is_numeric($strReturn) && $strReturn !== 0) {
            $strReturn = $strReplaceEmpty;
        }

        return $strReturn;
    }

    private static function implodeWithKeys($array, $glue, $is_query = false)
    {
        if ($is_query == true) {
            return str_replace(array('[', ']', '&'), array('%5B', '%5D', '&amp;'), http_build_query($array));
        } else {
            return urldecode(str_replace("&", $glue, http_build_query($array)));
        }
    }
}