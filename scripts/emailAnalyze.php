<?php
define('ROOT', realpath(dirname(__FILE__).'/../'));

require ROOT . '/vendor/autoload.php';
require ROOT . '/scripts/simple_html_dom.php';

$config = new HackerNews\Config('../config.ini');
$base_url = $config['site.base_url'] . $config['site.base_path'] . '/';
$db = HackerNews\Common::database($config);

$host = "imap.163.com";
$username = "maosea01251@163.com";
$password = "qzxsdpngwmfxsqig";

$mailBox  = imap_open ("{pop.163.com:110/pop3}INBOX", $username, $password) or die("can't connect: " . imap_last_error());
//$mailBox = imap_open("{imap.gmail.com:993/imap/ssl/novalidate-cert}", 'maosea0125@gmail.com', '840918@hhf');

//mail total
$total = imap_num_msg($mailBox);

for ( $messagenumber = $total; $messagenumber >= 1; $messagenumber-- ){
    $message = trim(imap_body($mailBox, $messagenumber));
    
    $message = quoted_printable_decode($message);
    
    $regex = "%http://mp.weixin.qq.com(.*)%";
    preg_match($regex, $message, $match);
    
    if( !empty($match) ){
        $url = $match['0'];
        
        $pageHtml = file_get_html($url);
        
        //check duplicate
        $db->where("story_url", HackerNews\Common::validate_input($url));
        $duplicateStories = $db->get("stories", 10,
            array(
                "story_id",
            )
        );
        
        if(count($duplicateStories) == 0){
            if($pageHtml){
                $titleObject = $pageHtml->find('.rich_media_title', 0);
                $title = $titleObject ? $titleObject->plaintext : "";
                
                $contentObject = $pageHtml->find('.rich_media_content', 0);
                $content = $contentObject ? $contentObject->plaintext : "";
            }
            
            $storyDetails = array(
                "story_id"    => "",
                "user_id"     => '1',
                "user_name"   => 'maosea0125',
                "story_url"   => HackerNews\Common::validate_input($url),
                "story_title" => HackerNews\Common::validate_input($title),
                "story_desc"  => HackerNews\Common::validate_input($content),
                "story_cat"   => '',
                "story_score" => 9999,
                "story_votes" => 1,
                "story_time"  => time(),
                "story_tags"  => ''
            );
            $storyId = $db->insert("stories", $storyDetails);
        }
        
        imap_delete($mailBox, $messagenumber);
    }
}
imap_close($mailBox, CL_EXPUNGE);