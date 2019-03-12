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

namespace ivmoiseev\uriparser;

/**
 * Universal resource indicator type class.
 *
 * @author Ilya V. Moiseev
 * @package ivmoiseev\uriparser
 * @copyright (c) 2016 - 2018, Ilya V. Moiseev
 */
class URIClass
{
    const URLFilterString = "/[^-_0-9a-z]/u";
    const URLFilterLength = 32;

    public $full_url = "";
    public $server = "";
    public $scheme = "";
    public $host = array();
    public $path = array();
    public $query = array();
    public $fragment = "";
    public $rewrited = false;

    public $mode = "";
    public $lang = "";

    /**
     * URIClass constructor.
     * @param string $uri
     * @throws \Exception
     * @version 16.01.2018
     */
    public function __construct(string $uri)
    {
        $this->full_url = strval($uri);
        $this->server = $this->findServer();

        // Decode (RFC 3986) and parse the URL:
        $parsed_url = parse_url(rawurldecode($uri));
        if (!$parsed_url) {
            return;
        }

        $this->scheme = $this->findScheme($parsed_url);
        $this->host = $this->findHost($parsed_url);
        $this->path = $this->findPath($parsed_url);
        $this->query = $this->findQuery($parsed_url);
        $this->fragment = $this->findFragment($parsed_url);

        // If $this->host is empty, then something goes wrong.
        // We must throw an exception.
        if (empty($this->host)) {
            throw new \Exception("URL is not valid.");
        }
    }

    /**
     * This method returns a string with server name from Config settings.
     * @return string
     * @version 30.11.2017
     */
    private function findServer(): string
    {
        if (!isset($_SERVER['SERVER_NAME'])) return "";
        /*
         * 21.09.2017 FastCGI seems to cause strange side-effects with
         * unexpected null values when using INPUT_SERVER and INPUT_ENV
         * with this static function. If you want to be on the safe side,
         * using the superglobal $_SERVER and $_ENV variables will always work.
         * You can still use the filter_* static functions for Get/Post/Cookie
         * without a problem, which is the important part!
         */
        // The "server" setting is need for link builder:
        $server = filter_var($_SERVER['SERVER_NAME']);
        return ($server) ? $server : "";
    }

    /**
     * This method returns a string with cleaned scheme from parsed URL.
     * @param array $parsed_url
     * @return string
     * @version 30.11.2017
     */
    private function findScheme(array $parsed_url): string
    {
        $valid_schemes = array("http", "https", "ftp");
        if (!is_array($parsed_url)
            || !array_key_exists("scheme", $parsed_url)
            // add security check:
            || !in_array($parsed_url['scheme'], $valid_schemes)) {
            return $valid_schemes[0];
        } else {
            return $parsed_url['scheme'];
        }
    }

    /**
     * This method returns an array with cleaned subdomains from parsed URL.
     * @param array $parsed_url
     * @return array
     * @version 30.05.2017 - 21.02.2018
     */
    private function findHost(array $parsed_url): array
    {
        if (!array_key_exists("host", $parsed_url)) {
            return array();
        }
        // This function is unused now:
        // $host = str_replace($this->findServer(),null, $parsed_url['host']);
        $host = $parsed_url['host'];
        if (empty(trim($host, "."))) {
            return array();
        }
        $host_array = array_reverse(explode(".", rtrim($host, ".")));

        // Each parameter passed through url_filter method for security issues.
        foreach ($host_array as $key => $value) {
            $host_array[$key] = self::URLFilter($value);
        }
        return $host_array;
    }

    /**
     * The method checks a GET parameter, passed through the address bar (URL)
     * using the Apache module mod_rewrite. Method removes the file extension,
     * removes unsafe characters, truncates the string at first 15 characters.
     * @param string $param GET parameter for filtering.
     * @return string
     * @since 4.0
     * @version 05.11.2012 - 30.11.2017
     */
    private function URLFilter(string $param): string
    {
        $param = explode(".", $param);
        $param[0] = preg_replace(self::URLFilterString, "", $param[0]);
        return substr($param[0], 0, self::URLFilterLength);
    }

    /**
     * This method returns an array with cleaned parameters
     * that are passed through mod_rewrite.
     * @param array $parsed_url
     * @return array
     * @version 30.05.2017 - 30.11.2017
     */
    private function findPath(array $parsed_url)
    {
        if (empty($parsed_url['path'])) {
            return array();
        }
        $PURI = explode("/", trim($parsed_url['path'], "/"));
        // Each parameter passed through url_filter method for security issues.
        foreach ($PURI as $key => $value) {
            $PURI[$key] = $this->URLFilter($value);
        }
        return $PURI;
    }

    /**
     * This method returns an array with cleaned GET parameters.
     * @param array $parsed_url
     * @return array
     * @version 30.05.2017 - 30.11.2017
     */
    private function findQuery(array $parsed_url): array
    {
        $query = array();
        if (!isset($parsed_url['query'])) {
            return $query;
        }
        $PGET = explode("&", $parsed_url['query']);
        foreach ($PGET as $value) {
            $DevGET = explode("=", $value);
            $GetKey = self::URLFilter($DevGET[0]);
            if (!empty($DevGET[1])) {
                $query[$GetKey] = $DevGET[1];
            }
        }
        return $query;
    }

    /**
     * This method returns a string with "fragment" of the URL.
     * @param array $parsed_url
     * @return string
     * @version 30.05.2017 - 30.11.2017
     */
    private function findFragment(array $parsed_url): string
    {
        if (!is_array($parsed_url)
            || !array_key_exists("fragment", $parsed_url)) {
            return "";
        }
        $fragment = $this->URLFilter($parsed_url['fragment']);
        return $fragment;
    }
}
