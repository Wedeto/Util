<?php
/*
This is part of WASP, the Web Application Software Platform.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace WASP\Util;

use WASP\is_array_like;
use WASP\Request;
use WASP\Debug;

class Redirection
{
    public static function redirect($url, $status_code = 302, $timeout = false)
    {
        http_response_code($status_code);
        if ($timeout)
        {
            header("Refresh:$timeout; url=" . $url);
        }
        else
        {
            header("Location: " . $url);
            die();
        }
    }

    private static function analyzeHost($hostname, array $hosts)
    {
        $hostname = strtolower($hostname);
        foreach ($hosts as $lang => $host)
        {
            $host = strtolower($host);
            if (substr($hostname, -strlen($host)) === $host)
            {
                return array(
                    'language' => $lang,
                    'hostname' => $host,
                    'subdomain' => str_replace($host, '', $hostname)
                );
            }
        }
        return null;
    }

    public static function checkRedirectAlternative()
    {
        $config = \WASP\Config::getConfig();

        $use_ssl = $config->dget('site', 'secure', false) == true;
        $use_www = $config->dget('site', 'www', true);
        $hosts = $config->get('site', 'url');
        $redirect_unknown = $config->get('site', 'redirect_unknown');

        if (is_array_like($hosts))
            $hosts = \to_array($hosts);
        else
            $hosts = array();

        if (empty($hosts))
        {
            $hosts = array('en' => $url->host);
            $use_www = false;
            $redirect_unknown = false;
        }
        else
            $first_host = reset($hosts);

        $url = new URL(Request::$uri);
        $analysis = self::analyzeHost($url->host, $hosts);

        $url->scheme = ($use_ssl) ? "https" : "http";
        $is_unknown = empty($analysis) || ($analysis['subdomain'] !== 'www.' && !empty($analysis['subdomain']));

        if ($is_unknown && $redirect_unknown)
            $url->host = $first_host;
        else
            $url->host = ($use_www) ? "www." . $cur_domain : $cur_domain;

        $url = $url->toString();
        if ($url !== Request::$uri)
            self::redirect($url);

        Request::$domain = $analysis['hostname'];
        Request::$subdomain = $analysis['subdomain'];
        Request::$language = $analysis['language'];
    }

    public static function checkRedirect()
    {
        $config = \WASP\Config::getConfig();

        $host = Request::$host;
        $https = Request::$secure;
        
        $needs_redirect = false;
        
        // Whether we prefer www. in front of the domain or not
        $use_www = $config->dget('site', 'www', true) == true;

        // Whether we prefer HTTPS
        $use_ssl = $config->dget('site', 'secure', false) == true;
        
        // Detect the domain in use
        $domain = null;
        $domains = $config->dget('site', 'url', $host);
        $domains = explode(";", $domains);
        foreach ($domains as $d)
        {
            if (strpos($host, $d) !== false)
            {
                $domain = $d;
                break;
            }
        }

        // Find out browser preferred language
        $http_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : "";
        $language = substr($http_lang, 0, 2);
        if (empty($language) || ($language !== "nl" && $language !== "en"))
            $language = $config->dget('site', 'default_language', 'en');

        if ($domain === null)
        {
            // Unknown domain name - log and possibly redirect
            Debug\info("Util.Redirection", "Unknown domain name in use: {0}", [$host]);
            $subdomain = null;
            
            if ($config->has('site', 'redirect_unknown'))
            {
                $preferred = reset($domains);
                if ($use_www)
                    $preferred = "www." . $preferred;
                $url = ($use_ssl ? "https://" : "http://") . $preferred . Request::$uri;
                self::redirect($url, 302);
            }
            $domain = $host;
        }
        else
        {
            // Known domain name - see if we need to redirect based on language preference, or www prefix

            // Determine the subdomain in use
            $subdomain = trim(str_replace($domain, "", $host), ".");

            // Detect language based on domain name
            $preferred = reset($domains);
            $site_config = $config->get('domainlanguage');
            foreach ($site_config as $key => $domains)
            {
                if (!substr($key, 0, 4) == "url_")
                    continue;

                $lang = substr($key, 4);
                $domains = explode(";", $domains);
                if (in_array($domain, $domains))
                {
                    $language = $lang;
                    $preferred = reset($domains);
                    break;
                }
            }

            if ($domain !== $preferred)
            {
                $url = ($use_ssl ? "https://" : "http://") . ($use_www ? "www." : "") . $preferred . Request::$uri;
                self::redirect($url, 302);
            }

            if ($subdomain === "www" && !$use_www)
            {
                $url = ($use_ssl ? "https://" : "http://") . $preferred . Request::$uri;
                self::redirect($url, 302);
            }

            if ($subdomain === "" && $use_www)
            {
                $url = ($use_ssl ? "https://" : "http://") . "www." . $preferred . Request::$uri;
                self::redirect($url, 302);
            }

            if ($use_ssl && !Request::$secure)
            {
                $url = "https://" . ($use_www ? "www." : "") . $preferred . Request::$uri;
                self::redirect($url, 301);
            }
        }

        // All is well
        if ($domain !== null)
            Debug\debug("Util.Redirection", "Detected subdomain '{0}' and domain '{1}'", [$subdomain, $domain]);

        Debug\debug("Util.Redirection", "Setting language to '{0}'", [$language]);
        Request::$language = $language;
        Request::$domain = $domain;
        Request::$subdomain = $subdomain;
    }
}

?>
