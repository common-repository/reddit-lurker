<?php
/*
Reddit Lurker - WordPress plugin for reading Reddit subreddits with comments
Copyright (C) 2013  Outplug

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/


class RedditControl {
    public function fetch_json_decode($url)
    // fetches JSON data from a URL and decodes it to an array
    {
        $args = array('user-agent' => 'Reddit Lurker/1.1');
        $fetchdata = wp_remote_get($url, $args);

        if ((is_wp_error($fetchdata)) ||
            ($fetchdata['response']['code'] !== 200)) {
            throw new \Exception('Transport error or status code not 200');
        }

        $jsondata = $fetchdata['body'];
        $json = json_decode($jsondata, true);
        if ($json === null) {
            throw new \Exception("Can't decode JSON");
        }
        return $json;
    } // function fetch_json_decode


    public function clean_subreddit($subreddit)
    // cleans subreddit names from untrusted sources
    {
        $subreddit = preg_replace('%[^-_a-zA-Z0-9]%s', '', $subreddit);
        return substr($subreddit, 0, 15);
    } // function clean_subreddit


    public function clean_id($id)
    // cleans IDs from untrusted sources
    {
        $id = preg_replace('%[^a-z0-9]%s', '', $id);
        return substr($id, 0, 15);
    } // function clean_id


    public function fetch_overview_subreddit($subreddit, $limit)
    // fetches an overview of a subreddit in a specific array based format
    {
        $overview_raw = $this->fetch_json_decode(
            'http://www.reddit.com/r/' . $subreddit . '.json' .
            ($limit ? "?limit=$limit" : '')
        );

        $overview = array();
        foreach ($overview_raw['data']['children'] as $onechild) {
            $id = trim($onechild['data']['id']);
            $title = trim($onechild['data']['title']);
            $url = trim($onechild['data']['url']);
            $selftext = trim($onechild['data']['selftext']);

            if (($selftext != '') &&
                (preg_match('%^https?://www\.reddit\.com/%', $url))) {
                $url = '';
            } // we don't need a url for self posts

            $selftext = preg_replace('%\n{2,}%s', "\n", $selftext);

            array_push($overview,
                array(
                    'id'       => $id,
                    'title'    => $title,
                    'url'      => $url,
                    'selftext' => $selftext
                )
            );
        }

        return $overview;
    } // function fetch_overview_subreddit


    public function fetch_comments_subreddit_id($subreddit, $id, $limit)
    // fetches comments for an ID in a subreddit, in a specific array based
    // format
    {
        $comments_raw = $this->fetch_json_decode(
            'http://www.reddit.com/r/' . $subreddit . '/comments/' .
            $id . '.json' .
            ($limit ? "?limit=$limit" : '')
        );

        return $this->commentrecurser($comments_raw[1]['data']['children'],
                               0, array());
    } // function fetch_comments_subreddit_id


    private function commentrecurser($comments, $level, array $cl)
    // converts one level of comments to a specific format
    // calls itself recursively to handle the next level of comments
    {
        foreach ($comments as $onecomment) {
            if ((isset($onecomment['kind'])) && ($onecomment['kind'] === 'more')) {
                continue;
            }

            $body = trim($onecomment['data']['body']);
            $body = preg_replace('%\n{2,}%s', "\n", $body);

            $data = array('author' => trim($onecomment['data']['author']),
                          'body'   => $body,
                          'points' => (int) $onecomment['data']['ups'] -
                                            $onecomment['data']['downs'],
                          'level'  => $level);
            array_push($cl, $data);

            if ((isset($onecomment['data']['replies'])) &&
                (is_array($onecomment['data']['replies']))) {
                $cl = $this->commentrecurser(
                    $onecomment['data']['replies']['data']['children'],
                    $level + 1, $cl
                );
            }
        }

        return $cl;
    } // function commentrecurser
} // class RedditControl

