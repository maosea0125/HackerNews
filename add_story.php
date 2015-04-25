<?php
require 'vendor/autoload.php';

// Start the session
session_start();

$config = new HackerNews\Config('config.ini');
$base_url = $config['site.base_url'] . $config['site.base_path'] . '/';
$db = HackerNews\Common::database($config);

HackerNews\Common::logincheck('add_story.php');

$loggedin = 0;
if(isset($_SESSION['hn_login']['id'])){
    $loggedin = 1;
}

// Check if the user has a remember cookie set
HackerNews\Common::checkremember($config, $db);

array_walk($_POST, create_function('&$val', '$val = trim($val);'));
array_walk($_GET, create_function('&$val', '$val = trim($val);'));

if(isset($_GET['ref'])) {
    if(isset($_SERVER['HTTP_REFERER'])) {
        $_POST['story_url'] = $_SERVER['HTTP_REFERER'];
    }
}

if(isset($_GET['story_url'])) {
    $_POST['story_url'] = $_GET['story_url'];
}

if(!isset($_POST['submit']) || empty($_POST['submit'])){
    $twig = new HackerNews\TwigEngine($config);
    $vars = array(
        'loggedin'    => $loggedin,
        'username'    => $_SESSION['hn_login']['name'],
        'userid'      => $_SESSION['hn_login']['id'],
    );
    echo $twig->setVars($vars)->render('new_story');
    exit;
}

$_POST['story_title'] = strip_tags($_POST['story_title']);
$_POST['story_desc'] = strip_tags($_POST['story_desc']);

// Check title & url & desc
$errors = 0;
if(strlen($_POST['story_title']) < 4) {
    $errors++;
    $titleError = "文章标题太短啦，最少4个字符";
}
if(empty($_POST['story_desc']) || (strlen($_POST['story_desc']) < 10)) {
    $errors++;
    $descError = "描述太短啦，最少10个字符";
}
if(!preg_match('/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $_POST['story_url'])) {
    $errors++;
    $urlError = "请输入有效的URL地址";
}

if($errors != 0){
    $twig = new HackerNews\TwigEngine($config);
    $vars = array(
        'loggedin'    => $loggedin,
        'username'    => $_SESSION['hn_login']['name'],
        'userid'      => $_SESSION['hn_login']['id'],
        'story_url'   => $_POST['story_url'],
        'story_title' => $_POST['story_title'],
        'story_desc'  => $_POST['story_desc'],
        'title_error' => $titleError,
        'url_error'   => $urlError,
        'desc_error'  => $descError,
    );
    echo $twig->setVars($vars)->render('new_story');
    exit;
}

// validate duplicate
if(!isset($_POST['dupe'])) {
    $db->where("story_url", HackerNews\Common::validate_input($_POST['story_url']));
    $dup = $db->getOne("stories", array("story_id"));

    if($dup['story_id']) {
        // Initiate stories array
        $stories = array();

        $db->where("story_url", HackerNews\Common::validate_input($_POST['story_url']));
        $dup_stories = $db->get("stories", 10,
            array(
                "story_id",
                "story_title",
                "story_desc",
                "story_cat",
                "story_votes",
                "story_url",
                "story_comments",
                "user_name",
                "user_id",
                "story_time",
                "story_tags",
            )
        );

        foreach($dup_stories as $info){
            $stories[$info['story_id']] = $info;
            $stories[$info['story_id']]['voted'] = 0;
            $stories[$info['story_id']]['ago'] = HackerNews\Common::time_taken((time()-$info['story_time']));
            $stories[$info['story_id']]['domain'] = HackerNews\Common::getDomain($info['story_url']);

            $stories[$info['story_id']]['story_link'] = $base_url.'story.php?id='.$info['story_id'];
            $stories[$info['story_id']]['user_link'] = $base_url.'profile.php?id='.$info['user_id'];
        }

        $twig = new HackerNews\TwigEngine($config);
        echo $twig->setVars(array(
            'stories' => $stories,
            'loggedin' => $loggedin,
            'username' => $_SESSION['hn_login']['name'],
            'userid' => $_SESSION['hn_login']['id'],
            'dup_stories' => $stories,
            'story_url' => $_POST['story_url'],
            'story_title' => $_POST['story_title'],
            'story_desc' => $_POST['story_desc']
        ))->render('new_story_dup');

        exit;
    }
}

$story_details = array(
    "story_id"    => "",
    "user_id"     => $_SESSION['hn_login']['id'],
    "user_name"   => $_SESSION['hn_login']['name'],
    "story_url"   => HackerNews\Common::validate_input($_POST['story_url']),
    "story_title" => HackerNews\Common::validate_input($_POST['story_title']),
    "story_desc"  => HackerNews\Common::validate_input($_POST['story_desc']),
    "story_cat"   => '',
    "story_score" => 9999,
    "story_votes" => 1,
    "story_time"  => time(),
    "story_tags"  => ''
);
$story_id = $db->insert("stories", $story_details);

// Self-Vote
$vote_details = array(
    "story_id" => HackerNews\Common::validate_input($story_id),
    "user_id" => $_SESSION['hn_login']['id']
);
$db->insert("votes", $vote_details);

// Redirect them to the story
header("Location: story.php?id=".$story_id);