<?php
/**
 *  This file is a part of IM-CMS 4 Content Management System.
 *
 * @since 4.0
 * @author Ilya Moiseev aka Non Grata <ilyamoiseev@inbox.ru>
 * @copyright Copyright (c) 2010-2018, Ilya Moiseev
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace ivmoiseev\uriparser;

/**
 * Class URIParserStatic
 * @package ivmoiseev\uriparser
 * @copyright (c) 2016 - 2018, Ilya V. Moiseev
 */
class URIParserStatic
{
    const URLFilterString = "/[^-_0-9a-z]/u";
    const URLFilterLength = 32;

    private static $rules = array();
    private static $uri;
    private static $needles = array();
    private static $path_offset = 0;

    // Will not allow to create instances of this class as it is static.
    private final function __construct()
    {
    }

    /**
     * This init method takes an URL string and produces its analysis,
     * breaking into components and writing to the array.
     * @param string $uri
     * @param array $modes
     * @param array $langs
     * @return URIClass
     * @throws \Exception;
     * @version 22.08.2016 - 16.01.2018
     * @static
     */
    public static function init(
        string $uri,
        array $modes = array(),
        array $langs = array()): URIClass
    {
        self::$uri = new URIClass($uri);

        // Find mode and language, if exist:
        self::$uri->mode = self::findNeedle($modes, "mode");
        self::$uri->lang = self::findNeedle($langs, "lang");
        // Rewrite rules:
        self::$uri->rewrited = self::hostToPath();
        return self::$uri;
    }

    /**
     *
     * @param mixed $needle
     * @param string $property
     * @param int $position
     * @return boolean
     * @static
     * @version 12.12.2017 - 16.01.2018
     */
    private static function findNeedle($needle, string $property, int $position = 0)
    {
        // Check URI path and needles collection:
        if (empty(self::$uri->path) || empty($needle)) {
            return false;
        }
        // The needle collection must be an array:
        if (!is_array($needle)) {
            $needle_array[] = $needle;
        } else {
            $needle_array = $needle;
        }
        // If the needle collection is not an array, than something goes wrong:
        if (!is_array($needle_array)) {
            return false;
        }
        // Save needle for security check in future:
        self::$needles[$property] = $needle_array;

        foreach ($needle_array as $needle_item) {
            // Find a needle:
            if (count(self::$uri->path) > $position
                && self::$uri->path[$position] == $needle_item) {
                $value = self::$uri->path[$position];
                // Then splice the array.
                array_splice(self::$uri->path, $position, 1);
                return $value;
            }
        }
        return false;
    }

    /**
     * Method converts host name to path elements.
     * @return boolean
     * @static
     * @version 06.06.2017 - 21.02.2018
     */
    private static function hostToPath()
    {
        // Check that the hosts list is not empty:
        $host_count = count(self::$uri->host);
        if ($host_count < 1) return false;

        // Convert $host_count to array_keys:
        $host_count--;

        // Check the rules:
        if (array_key_exists(self::$uri->host[$host_count], self::$rules)) {
            $alias = self::$uri->host[$host_count];
            // Add elements to path:
            array_splice(self::$uri->path, 0, 0, self::$rules[$alias]);
            // Delete the host:
            unset(self::$uri->host[$host_count]);
            return true;
        }
        return false;
    }

    /**
     * The function returns one of the values from an array of GET parameters
     * and increment the array index ($offset).
     * @param int $offset
     * @return string
     * @version 05.11.2012 - 02.04.2018
     * @static
     */
    public static function current($offset = null): string
    {
        if (!is_array(self::$uri->path)) {
            return "";
        }
        if (!is_int($offset)) {
            $offset = self::$path_offset;
            self::$path_offset++;
        }
        if (key_exists($offset, self::$uri->path)) {
            return strval(self::$uri->path[$offset]);
        } else {
            return "";
        }
    }

    /**
     *
     * @param string $alias
     * @param array $path
     * @return bool
     * @static
     * @version 12.12.2017
     */
    public static function addRule(string $alias, array $path): bool
    {
        if (empty($alias) || empty(($path))) return false;
        self::$rules[$alias] = $path;
        return true;
    }

    /**
     * This method is a powerfull hyperlinks generator.
     * @param mixed $path
     * @param mixed $query Add array for extra query. Set to NULL to delete all query variables.
     * @param mixed $lang
     * @param mixed $mode
     * @return string
     * @version 07.06.2017 - 09.01.19
     * @static
     */
    public static function link($path = null, $query = array(), $lang = null, $mode = null): string
    {
        $uri = clone self::$uri;

        // Convert path string to array, if need:
        if (is_string($path)) {
            $path = explode("/", trim($path, "/"));
        }
        if (is_array($path) && count($path) > 0) {
            $uri->path = $path;
        }
        // Check URI queri variables: array - add a query, null - delete all query variables:
        if (is_array($query) && count($query) > 0) {
            $uri->query = array_merge(self::$uri->query, $query);
        } elseif (is_null($query)) {
            // 09.01.19 NULL - delete all query variables.
            $uri->query = array();
        } else {
            // 14.11.17 - added "else" to the "if" statement.
            $uri->query = self::$uri->query;
        }

        self::pathToHost($uri);
        self::insertNeedle($uri, "lang", $lang);
        self::insertNeedle($uri, "mode", $mode);

        $link = $uri->scheme . "://";
        if (!empty($uri->host)) {
            $rsorted_host = $uri->host;
            krsort($rsorted_host);
            $link .= implode(".", $rsorted_host) . "/";
        }
        // This is unused now:
        // $link .= $uri->server . "/";
        if (!empty($uri->path)) {
            $link .= rtrim(implode("/", $uri->path), "/") . "/";
        }
        if (!empty($uri->query)) {
            $link .= self::buildQuery($uri->query);
        }
        if (!empty($uri->fragment)) {
            $link .= "#" . $uri->fragment;
        }

        return $link;
    }

    /**
     * Method converts path elements to host name.
     * @param URIClass $uri
     * @return boolean
     * @static
     * @version 06.06.2017 - 21.02.2018
     */
    private static function pathToHost(URIClass $uri)
    {
        foreach (self::$rules as $host => $path) {
            $intersect = array_intersect($path, $uri->path);
            if (count($intersect) > 0 && count($intersect) == count($path)) {
                foreach ($intersect as $item) {
                    // Find a key, to delete:
                    $key = array_search($item, $uri->path);
                    // Delete this key:
                    array_splice($uri->path, $key, 1);
                }
                // Add a subdomain (at the moment only last level domains enabled):
                array_push($uri->host, $host);
                //array_splice($uri->host, 0, 1, $host);
                return true;
            }
        }
        return false;
    }

    /**
     * Inserts a needle to the link.
     * @param URIClass $uri
     * @param string $property
     * @param string $value
     * @param int $position
     * @return boolean
     * @static
     * @version 12.12.2017 - 18.05.2018
     */
    private static function insertNeedle(
        URIClass $uri,
        string $property,
        string $value = null,
        int $position = 0)
    {
        // TODO Refactor this!
        // If not null, but string is empty - ignore the needle:
        if (!is_null($value) && empty($value)) return true;
        // If not null, and exist in needles array - replace from value:
        if (!is_null($value) && in_array($value, self::$needles[$property])) {
            // Add elements to path:
            array_splice($uri->path, $position, 0, $value);
            return true;
        } elseif (is_string($property) && property_exists($uri, $property)) {
            // Add elements to path:
            if (is_string($uri->$property)) {
                array_splice($uri->path, $position, 0, $uri->$property);
            }
            unset($uri->$property);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Helper method, for link building.
     * @param array $query
     * @return array | string
     * @static
     * @version 12.12.2017
     */
    private static function buildQuery(array $query)
    {
        if (empty($query)) {
            return array();
        }
        $return = array();
        foreach ($query as $key => $value) {
            $return[] = implode("=", array($key, $value));
        }
        return "?" . implode("&", $return);
    }

    /**
     * The method returns an array with path parameters.
     * @return array
     * @static
     * @version 24.04.2018
     */
    public static function getPath(): array
    {
        return self::$uri->path;
    }

    /**
     * The method returns an array with GET Query parameters, or string with
     * selected GET Query parameter, if the $key is not null.
     * The method returns null, if the GET Query parameter is not exist.
     * @param string $key
     * @param bool $clear If true - delete selected value from the collection.
     * @return array | string | null
     * @static
     * @version 18.08.2017 - 18.12.2018
     */
    public static function getQuery($key = null, bool $clear = false)
    {
        if (is_null($key)) {
            return self::$uri->query;
        }
        if (!is_array(self::$uri->query) ||
            !array_key_exists($key, self::$uri->query)) {
            return null;
        }
        $var = self::$uri->query[$key];
        if ($clear) self::unsetQuery($key);
        return $var;
    }

    /**
     * The method removes the unused GET Query variables.
     * @param string $key The name of the GET Query variable. If empty - clears all query vars.
     * @return boolean
     * @static
     * @version 25.11.2016 - 09.01.2019
     */
    public static function unsetQuery($key = "")
    {
        if (!is_array(self::$uri->query)) {
            return false;
        }
        if (!empty($key)) {
            // Unset selected query variable:
            if (!array_key_exists($key, self::$uri->query)) {
                return false;
            }
            unset(self::$uri->query[$key]);
            return true;
        }
        // Unset ALL query variables:
        self::$uri->query = array();
        return true;
    }

    /**
     * Method returns TRUE, if the URL was rewrited, FALSE otherwise.
     * @return boolean
     * @static
     * @version 09.08.2017 - 12.12.2017
     */
    public static function rewrited()
    {
        return boolval(self::$uri->rewrited);
    }

    /**
     * Method returns an object with POST input data in properties.
     * @param array $fields
     * @return \stdClass | bool
     * @static
     * @version 09.08.2017 - 16.01.2018
     * @deprecated
     */
    public static function inputPost(array $fields)
    {
        $input = new \stdClass();
        $empty = true;

        foreach ($fields as $field) {
            $input->$field = filter_input(INPUT_POST, $field);
            if (!empty($input->$field)) {
                $empty = false;
            }
        }

        return ($empty) ? false : $input;
    }

    /**
     * Write full url (referer) to the cookie.
     * @return bool
     * @version 18.05.2018 - 19.05.2018
     */
    public static function saveRefererInCookies(): bool
    {
        setcookie("referer", self::getFullURL(), 0, "/");
        return true;
    }

    /**
     * The method returns a full url of the requested page.
     * @return string
     * @version 30.05.2017 - 31.05.2017
     * @static
     */
    public static function getFullURL(): string
    {
        /*
         * 21.09.2017 FastCGI seems to cause strange side-effects with
         * unexpected null values when using INPUT_SERVER and INPUT_ENV
         * with this function. If you want to be on the safe side,
         * using the superglobal $_SERVER and $_ENV variables will always work.
         * You can still use the filter_* functions for Get/Post/Cookie
         * without a problem, which is the important part!
         */
        $FullUrl = (!empty($_SERVER['HTTPS'])
            && filter_var($_SERVER['HTTPS']) ? "https" : "http")
            . "://" . filter_var($_SERVER['HTTP_HOST'])
            . filter_var($_SERVER['REQUEST_URI']);
        return $FullUrl;
    }

    /**
     * @return string|false
     * @version 31.05.2017
     */
    public static function getLang()
    {
        return (property_exists(self::$uri, 'lang')) ? self::$uri->lang : false;
    }

    /**
     * @return string|false
     * @version 31.05.2017
     */
    public static function getMode()
    {
        return (property_exists(self::$uri, 'mode')) ? self::$uri->mode : false;
    }

    private final function __clone()
    {
    }
}