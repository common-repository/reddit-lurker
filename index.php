<?php
/*
Plugin Name: Reddit Lurker
Description: Shortcode for reading a Reddit subreddit with comments
Version: 1.1
Author: Outplug
Author URI: http://outplug.net
License: GPL2
Text Domain: reddit-lurker
*/

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


function option_create()
// sets default values for the option when the plugin is first activated
{
    add_option('reddit_lurker', array('limit_overview' => 0, 'limit_comments' => 0));
}

function reddit_lurker_plugin_loaded()
// loads the selected translation (.mo) file
{
    load_plugin_textdomain('reddit-lurker', false, basename(dirname(__FILE__)));
}

function reddit_lurker_query_vars($vars)
{
    array_push($vars, 'redditlurkerid');
    return $vars;
}

function reddit_lurker_func($attr)
// deals with the reddit-lurker shortcode
{
    require_once plugin_dir_path(__FILE__) . '/RedditControl.php';

    $redditcontrol = new RedditControl;
    $subreddit = 'wordpress'; // default if there is no subreddit attribute
    if (isset($attr['subreddit'])) {
        $subreddit = $attr['subreddit'];
    }
    $subreddit = $redditcontrol->clean_subreddit($subreddit);
    $id = get_query_var('redditlurkerid');
    $option = get_option('reddit_lurker');

    $out = '';
    if ($id !== '') { // this is a reddit comment page
        $id = $redditcontrol->clean_id($id);
        $limitc = (int) $option['limit_comments'];
        try {
            $comments = $redditcontrol->fetch_comments_subreddit_id(
                                        $subreddit, $id, $limitc
                                        );
        } catch (Exception $e) {
            return '[Reddit Lurker: network error]';
        }

        $out .= '<p>';
        foreach ($comments as $onecomment) {
            $out .= '<div style="margin-left: ' . ($onecomment['level'] * 30) . 'px">' . // indentation
                    '<b>' . esc_html($onecomment['author']) .
                    ' (' . esc_html($onecomment['points']) .
                    ')</b><br />' .
                    nl2br(esc_html($onecomment['body'])) .
                    '</div>' . PHP_EOL;
        }
        $out .= '</p>';
    } else { // this is a reddit overview page
        $limito = (int) $option['limit_overview'];
        try {
            $overview = $redditcontrol->fetch_overview_subreddit($subreddit, $limito);
        } catch (Exception $e) {
            return '[Reddit Lurker: net error]';
        }

        foreach ($overview as $oneoverview) {
            $out .= '<p>';

            if ($oneoverview['url'] !== '') {
                $out .= '<a href="' . esc_attr($oneoverview['url']) . '">';
            }
            $out .= esc_html($oneoverview['title']);
            if ($oneoverview['url'] !== '') {
                $out .= '</a>';
            }

            $out .= ' ';
            $url = get_permalink();
            $param = array('redditlurkerid' => $oneoverview['id']);
            $url2 = add_query_arg($param, $url);

            $out .= '<a href="' .
                    esc_attr($url2) .
                    '"'.
                    ($oneoverview['selftext'] !== '' ? ' title="' . esc_attr($oneoverview['selftext']) . '"' : '') .
                    '>' . __('(comments)', 'reddit-lurker') . '</a>';

            $out .= '</p>' . PHP_EOL;
       }
   }

   return $out;
}

function reddit_lurker_admin_add_page()
// this and the following functions handles the settings
{
    add_options_page('Reddit Lurker', 'Reddit Lurker', 'manage_options', 'reddit_lurker', 'reddit_lurker_options_page');
}

function reddit_lurker_options_page()
{
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2>Reddit Lurker</h2>
<form method="post" action="options.php">
<?php
settings_fields('reddit_lurker');
do_settings_sections('reddit_lurker');
?>
<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes', 'reddit-lurker'); ?>" />
</form>
</div>
<?php
}

function reddit_lurker_admin_init()
{
    register_setting('reddit_lurker', 'reddit_lurker', 'reddit_lurker_options_validate');
    add_settings_section('reddit_lurker_main', __('Settings', 'reddit-lurker'), 'reddit_lurker_section_one', 'reddit_lurker');
    add_settings_field('reddit_lurker_limit_overview', __('Limit Overview', 'reddit-lurker'), 'reddit_lurker_setting_limit_overview', 'reddit_lurker', 'reddit_lurker_main');
    add_settings_field('reddit_lurker_limit_comments', __('Limit Comments', 'reddit-lurker'), 'reddit_lurker_setting_limit_comments', 'reddit_lurker', 'reddit_lurker_main');
}

function reddit_lurker_section_one()
{
    echo __('<p>You start using this plugin by creating a page containing the shortcode <i>[reddit-lurker subreddit="<b>name</b>"]</i>.</p>', 'reddit-lurker');
    echo __('<p>Here you can set the limits for how many items to fetch for overview and comment pages (or 0 for Reddit\'s defaults).</p>', 'reddit-lurker');
}

function reddit_lurker_setting_limit_overview()
{
    $options = get_option('reddit_lurker');
    echo '<input id="reddit_lurker_limit_overview" name="reddit_lurker[limit_overview]" size="10" type="text" value="' .
         esc_attr($options['limit_overview']) .
         '" />';
}

function reddit_lurker_setting_limit_comments()
{
    $options = get_option('reddit_lurker');
    echo '<input id="reddit_lurker_limit_comments" name="reddit_lurker[limit_comments]" size="10" type="text" value="' .
         esc_attr($options['limit_comments']) .
         '" />';
}

function reddit_lurker_options_validate($input)
{
    $newinput = array();
    $newinput['limit_overview'] = (int) $input['limit_overview'];
    $newinput['limit_comments'] = (int) $input['limit_comments'];
    return $newinput;
}


if (!function_exists('register_activation_hook')) {
    die("Don't call this script directly." . PHP_EOL);
}

register_activation_hook(__FILE__, 'option_create');
add_action('plugins_loaded', 'reddit_lurker_plugin_loaded');
add_action('admin_init', 'reddit_lurker_admin_init');
add_action('admin_menu', 'reddit_lurker_admin_add_page');

add_filter('query_vars', 'reddit_lurker_query_vars');
add_shortcode('reddit-lurker', 'reddit_lurker_func');

