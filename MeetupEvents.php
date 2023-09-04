<?php

/**
 * Class MeetupEvents
 *
 * A scraper class for getting a multi-dimensional array of events for a given group.
 * This is likely very fragile, as all HTML scrapers are ;) If you want more stability, Meetup
 * has an API: https://www.meetup.com/meetup_api/
 *
 * This plugin requires php-xml: https://www.php.net/manual/en/dom.installation.php
 */
class MeetupEvents {

    var $cachePath;
    var $cacheAge;
    var $meetupBase;
    var $eventsUri;

    /**
     * MeetupEvents constructor.
     * @param string $cacheAge argument to pass to strtotime() - defaults to '-1 hour'
     * @param string $cachePath path to where cache files will be kept - defaults to '/tmp/'
     */
    public function __construct($cacheAge = null, $cachePath = null) {
        if ($cachePath != null){
            $this->cachePath = $cachePath;
        } else {
            $this->cachePath = '/tmp/';
        }
        if($cacheAge !== null){
            $this->cacheAge = strtotime($cacheAge);
        } else {
            $this->cacheAge = strtotime('-1 hour');
        }

        $this->meetupBase = 'https://www.meetup.com'; // no traling slash
        $this->eventsUri = '/events/'; // leading and trailing slash
    }

    /**
     * Accepts an RSS feed URL, an age of cache and a path to store cache,
     * returns SimpleXMLElement from simplexml_load_string()
     * @param string $url
     * @param int $cacheAge in the form of epoch. to use 1 hour, do: strtotime('-1 hour')
     * @return string or boolean false
     */
    function getAndCacheUrl($url, $cacheAge, $cachePath) {
        $cacheFile = $cachePath . 'MeetupEvents_cache_' .  md5($url);

        if (!is_file($cacheFile) || filectime($cacheFile) < $cacheAge) {
            // todo - switch this to use cURL call and check for 200s and such
            $result = file_get_contents($url);
            if (is_writable($cachePath)) {
                $save_result = file_put_contents($cacheFile, serialize($result));
            } else {
                $save_result = false;
            }
            if ($result === false){
                error_log("getAndCacheUrl() can't fetch from $url - this is bad!");
            }
            if ($save_result === false) {
                error_log("getAndCacheUrl() can't write to $cacheFile - this is bad!");
            }
        } else {
            $fetched = file_get_contents($cacheFile);
            if ($fetched === false) {
                error_log("getAndCacheUrl() can't retrieve data from $cacheFile - this is bad!");
            } else {
                $result = unserialize($fetched);
            }
        }
        return $result;
    }

    /**
     * Get future events for a group
     * @param $group string group name to fetch events for
     * @return array $events with each member being an array with keys of link, title, epoch, human_date and description
     */
    function get_future_meetup_events($group)
    {
        $url = $this->meetupBase . '/' . $group . $this->eventsUri;
        return $this->get_meetup_events($group, $url);
    }


    /**
     * Get past events for a group
     * @param $group string group name to fetch events for
     * @return array $events with each member being an array with keys of link, title, epoch, human_date and description
     */
    function get_past_meetup_events($group)
    {
        $url = $this->meetupBase . '/' . $group . $this->eventsUri . 'past/';
        return $this->get_meetup_events($group, $url);
    }

    /**
     * Likely a very fragile method to get past/future events in a multi-dimensional array. uses getAndCacheUrl()
     * @param $group string group name to use in URL
     * @param $url string URL as derived from past or future events functions
     * @return array
     */
    private function get_meetup_events($group, $url){

        $meetupHtml = $this->getAndCacheUrl($url, $this->cacheAge, $this->cachePath);

        $events = array();
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($meetupHtml);
        libxml_clear_errors();

        $finder = new DomXPath($dom);
        $finder->registerNamespace('html','http://www.w3.org/1999/xhtml' );
        $nodes = $finder->query('//div[contains(@class,"sm:p-5")]');

        // DEBUG!
//        print "<pre>";

        foreach ($nodes as $node) {
            $event = array();
            foreach ($node->getElementsByTagName('span') as $link) {
                if (strstr($link->getAttribute('class'), 'ds-font-title-3')) {
                    $event['title'] = $link->nodeValue;
                    break;
                }
            }
            foreach ($node->getElementsByTagName('time') as $link) {
                $event['epoch'] = strtotime($link->nodeValue);
                $event['human_date'] = $link->nodeValue;
            }
            foreach ($node->getElementsByTagName('a') as $link) {
                // DEBUGS
//                print '$link->getAttribute(href):' .  $link->getAttribute('href') . '</br>';
//                print '<p>$link->nodeValue:::' .  $link->nodeValue . ':::</br>'. "\n\n\n";
                $event['link'] = $link->getAttribute('href');
                if (strstr($link->getAttribute('class'), 'text--strikethrough')) {
                    $event['status'] = 'cancelled';
                    break;
                } else {
                    $event['status'] = 'active';
                }
            }
            foreach ($node->getElementsByTagName('div') as $link) {
                if (strstr($link->getAttribute('class'), 'hidden md:block')) {
                    $event['description'] = $link->nodeValue;
                }
            }
            $events[$event['epoch'] . '-' . rand(100000, 888888)] = $event;
        }
        ksort($events);
        // DEBUG
//        print_r($events);
//        exit('dead!');
        return $events;
    }


}
