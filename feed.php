<?php
/**
 * Created by PhpStorm.
 * User: dnap
 * Date: 07.07.16
 * Time: 17:59
 */

use Zelenin\RSSBuilder;

require_once('Vk.php');
require_once('RSSBuilder.php');
require_once('VkRender.php');
require_once('VkConfig.php');

$debug = $_GET['debug'] ?? false;

$channelHref = 'http://dnap.su/rssfilter/VkToRss/feed.php';
$config = new VkConfig($channelHref);
$vk = new Vk([
    'client_id'    => 3159394, // (required) app id
    'secret_key'   => 'nnjbGNj48fD7mdHleJi0', // (required) get on https://vk.com/editapp?id=12345&section=options
    'user_id'      => 86504, // your user id on vk.com
    'scope'        => 'wall,friends', // scope access
    'v'            => '5.52', // vk api version
    'access_token' => $config->getKey(),
]);

try{
    if (empty($vk->api('users.get')) || !empty($_GET['newToken'])) {
        $html = $config->newKey($vk);
    }
} catch (Exception $ex) {
    $html = $config->newKey($vk);
}

if ($debug) {
    echo $html;
    return;
}

$RB = new RSSBuilder();
$RB->addChannel($channelHref);
$RB->addChannelElement('title', 'Vk rss');

if (!empty($html)) {
    $RB->addItem();
    $RB->addItemElement('title', 'Fail access');
    $RB->addItemElement('link', $channelHref . '?debug=1');
    $RB->addItemElement('description', $html);
    $RB->addItemElement('pubDate', date('r'));
    $RB->addItemElement('guid', 'error');
} else {
    try{
        $newsFeed = $vk->api("newsfeed.get", [
            'fields' => 'photo_50'
        ]);
        //var_dump(array_keys($newsFeed));
        //var_dump($newsFeed['next_from']);
        $vkRender = new VkRender($newsFeed['profiles'], $newsFeed['groups']);
        //$vkRender->allowGeocoding = false;
        $guids = [];
        foreach ($newsFeed['items'] as $item) {
            $guid = md5($item['post_id'] ?? $item['date'] . ':' . $item['source_id']);
            if (in_array($guid, $guids)) {
                continue;
            }
            $guids[] = $guid;
            $itemString = $vkRender->renderItem($item);
            $itemTitle = $vkRender->renderTitle($item);
            $link = $vkRender->getLink($item);
            $RB->addItem();
            $RB->addItemElement('title', $itemTitle);
            $RB->addItemElement('link', $link);
            $RB->addItemElement('description', $itemString);
            $RB->addItemElement('pubDate', date('r', $item['date']));
            $RB->addItemElement('guid', 'vkfeed.' . $guid);
        }
    } catch (VkException $ex) {
        $RB->addItem();
        $RB->addItemElement('title', 'Unknown error');
        $RB->addItemElement('link', '');
        $RB->addItemElement('description', $ex->getMessage());
        $RB->addItemElement('pubDate', date('r'));
        $RB->addItemElement('guid', 'vk_error');
    }
}
echo $RB;
