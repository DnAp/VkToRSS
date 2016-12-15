<?php

/**
 * Created by PhpStorm.
 * User: dnap
 * Date: 07.07.16
 * Time: 18:28
 */
class VkRender
{
    /**
     * @var array
     */
    private $profiles;
    /**
     * @var array
     */
    private $groups;
    public $allowGeocoding = true;

    /**
     * VkRender constructor.
     *
     * @param array $profiles
     * @param array $groups
     */
    public function __construct($profiles, $groups)
    {
        $this->profiles = $profiles;
        $this->groups = $groups;
    }

    private function getImageFromSize(array $data, $size = 604, $prefix = 'photo_')
    {
        if ($size != 0 && isset($data[$prefix . $size])) {
            return $data[$prefix . $size];
        }
        $maxSizeValue = '';
        //ksort($data);
        foreach ($data as $key => $value) {
            if (substr($key, 0, strlen($prefix)) == $prefix) {
                if ($size != 0 && (int)substr($key, strlen($prefix)) > $size) {
                    return $value;
                }
                $maxSizeValue = $value;
            }
        }

        return $maxSizeValue;
    }

    /**
     * @param int $sourceId
     * @param int|null $date timestamp
     * @return string
     */
    private function renderSource($sourceId, $date)
    {
        $data = ['photo_50' => 'http://vk.com/images/deactivated_50.png'];
        if ($sourceId > 0) {
            foreach ($this->profiles as $curSource) {
                if ($curSource['id'] == $sourceId) {
                    $data = $curSource;
                    break;
                }
            }
            $name = $data['first_name'] . ' ' . $data['last_name'];
            $link = 'https://vk.com/id' . $data['id'];
        } else {
            $sourceId = -$sourceId;
            foreach ($this->groups as $curSource) {
                if ($curSource['id'] == $sourceId) {
                    $data = $curSource;
                    break;
                }
            }
            $name = $data['name'];
            $link = 'https://vk.com/club' . $data['id'];
        }

        return sprintf('<p style="padding-bottom: 5px;"><img src="%s" align="left" /><a href="%s" target="_blank">%s</a><br/><small>%s</small></p>' . PHP_EOL,
            htmlspecialchars($this->getImageFromSize($data, 50)),
            htmlspecialchars($link),
            htmlspecialchars($name),
            $date ? date('Y-m-d H:i:s', $date) : '' // @todo to str
        );
    }

    public function renderTitle($item)
    {
        $type = isset($item['type']) ? $item['type'] : '';
        foreach (['source_id', 'owner_id'] as $key) {
            if (!empty($item[$key])) {
                $sourceId = $item[$key];
                if ($sourceId > 0) {
                    foreach ($this->profiles as $curSource) {
                        if ($curSource['id'] == $sourceId) {
                            return $curSource['first_name'] . ' ' . $curSource['last_name'] . ' ' . $type;
                        }
                    }
                } else {
                    $sourceId = -$sourceId;
                    foreach ($this->groups as $curSource) {
                        if ($curSource['id'] == $sourceId) {
                            return $curSource['name'] . ' ' . $type;
                        }
                    }
                }
            }
        }
        return 'Unknown ' . $type;
    }

    protected $knowledgeFields = ['id', 'from_id', 'signer_id', 'type', 'post_id', 'post_type',
        'date', 'owner_id', 'source_id', 'copy_owner_id', 'copy_history',
        'text', 'photos', 'photo', 'friends', 'audio', 'video', 'attachments', 'geo', 'comments', 'likes', 'reposts', 'post_source'
    ];

    /**
     * @param $item
     *
     * @return string
     */
    public function renderItem($item)
    {
        $result = '';

        // copy_post_date - todo
        $link = $this->getLink($item);
        if (!empty($item['owner_id'])) {
            $result .= $this->renderSource($item['owner_id'], $item['date'], $link);
        }
        if (!empty($item['source_id'])) {
            $result .= $this->renderSource($item['source_id'], $item['date'], $link);
        }
        if (!empty($item['copy_owner_id'])) {
            // copy_post_id - содержит идентификатор скопированного сообщения на стене его владельца
            $result .= $this->renderSource($item['copy_owner_id'], $item['date'], $link);
        }

        if (isset($item['text']) && $item['text'] != '') {
            $result .= nl2br(htmlspecialchars($item['text'])) . "<br/>";
        }

        if (isset($item['photos'])) {
            foreach ($item['photos']['items'] as $photo) {
                $result .= $this->renderPhoto($photo);
            }
            $count = $item['photos']['count'] - count($item['photos']['items']);
            if ($count > 0) {
                $result .= "<br/><a target=\"_blank\" href='" . $link . "'>The remaining " . $count . " entries</a>";
            }
            $result .= '<br/>';
        }
        if (isset($item['photo_tags'])) {
            foreach ($item['photo_tags']['items'] as $photo) {
                $result .= $this->renderPhoto($photo);
            }
            $count = $item['photo_tags']['count'] - count($item['photo_tags']['items']);
            if ($count > 0) {
                $result .= "<br/><a target=\"_blank\" href='" . $link . "'>The remaining " . $count . " entries</a>";
            }
            $result .= '<br/>';
        }
        if (isset($item['photo'])) {
            $result .= $this->renderPhoto($item['photo']);
        }
        if (isset($item['friends'])) {
            $result .= '<p>Add friends:</p>';
            foreach ($item['friends']['items'] as $friend) {
                $result .= $this->renderSource($friend['user_id'], null);
            }
        }
        if (isset($item['audio'])) {
            if (!isset($item['audio']['items'])) {
                $result .= $this->renderAudio($item['audio']);
            } else {
                foreach ($item['audio']['items'] as $audio) {
                    $result .= $this->renderAudio($audio);
                }
            }
        }
        if (isset($item['video'])) {
            if (!isset($item['video']['items'])) {
                $result .= $this->renderVideo($item['video']);
            } else {
                foreach ($item['video']['items'] as $video) {
                    $result .= $this->renderVideo($video);
                }
            }
        }

        if (isset($item['copy_history'])) {
            foreach ($item['copy_history'] as $copyItem) {
                $result .= '<div style="padding-left: 50px;">' . $this->renderItem($copyItem) . '</div>';
            }
        }

        if (!empty($item['attachments'])) {
            $attach = [];
            foreach ($item['attachments'] as $attachment) {
                $attach[] = $this->renderAttachment($attachment);
            }
            $result .= '<div style="padding-left: 50px;">' . implode('', $attach) . '</div>';
        }

        if (!empty($item['geo'])) {
            $result .= $this->renderGeo($item['geo']) . '<br>';
        }
        if (isset($item['comments'])) {
            $result .= $this->renderCommentCount($item['comments']['count'], $item['comments']['can_post']) . '<br/>';
        }
        if (isset($item['likes'])) {
            $result .= $this->renderLikesCount($item['likes']) . ' <br/>';
        }
        if (isset($item['reposts'])) {
            $result .= $this->repostsNotifyRender($item['reposts']) . ' <br/>';
        }
        if (isset($item['post_source'])) {
            $result .= $this->postSourceRender($item['post_source']) . ' <br/>';
        }

        $unknownFields = [];
        foreach ($item as $key => $value) {
            if (!in_array($key, $this->knowledgeFields)) {
                $unknownFields[] = $key . ' => ' . json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        if (!empty($unknownFields)) {
            $result .= '<pre>Unsupprted: <br>' . implode('<br/>', $unknownFields) . '</pre>';
        }

        return $result;
    }

    /**
     * @param int $count
     * @param bool $canPost
     *
     * @return string
     */
    private function renderCommentCount($count, $canPost)
    {
        return ($canPost ? '&#128275;' : '&#128274;') . ' Comment: ' . $count;
    }

    /**
     * @param array $likes
     *
     * @return string
     */
    private function renderLikesCount($likes)
    {
        return 'Like: ' . $likes['count'];
    }

    /**
     * @param array $reposts
     *
     * @return string
     */
    private function repostsNotifyRender($reposts)
    {
        return 'Repost: ' . $reposts['count'];
    }

    private function renderAudio($audio)
    {
        if (!$audio) {
            return '';
        }
        //$genres = $this->getGenreList();
        return sprintf('<p>%s - %s<br><audio controls preload="none"><source src="%s" type="audio/ogg"></audio></p>',
            htmlspecialchars($audio['artist']),
            htmlspecialchars($audio['title']),
            htmlspecialchars($audio['url'])
        );
    }

    private function renderVideo($video)
    {
        if (!$video) {
            return '';
        }

        return sprintf('<p>%s<br><img src="%s" width="640"><br>%s</p>',
            htmlspecialchars($video['title']),
            htmlspecialchars($this->getImageFromSize($video, 640)),
            htmlspecialchars($video['description'])
        );

    }

    private function renderPhoto($photo, $prefix = 'photo_')
    {
        $link = sprintf('https://vk.com/feed?z=photo%s_%s%%2Ffeed1_', $photo['owner_id'], $photo['id'], $photo['owner_id'], time());
        $postText = sprintf('<br/><a href="%s">See on vk</a>', $link);

        if (isset($photo['lat'])) {
            $postText = '<br>&#10164; ' . $this->reverseGeocoding($photo['lat'], $photo['long']);
        }
        $text = !empty($photo['text']) ? htmlspecialchars($photo['text']) . '<br/>' : '';
        // @todo auto select prefix
        return sprintf(
            '<div style="display: inline-block; max-width: 610px">%s<a href="%s" target="_blank"><img src="%s" style="max-height: 340px"/></a>%s</div> ',
            $text,
            htmlspecialchars($this->getImageFromSize($photo, 0, $prefix)),
            htmlspecialchars($this->getImageFromSize($photo, 604, $prefix)),
            $postText
        );
    }


    public function renderItemOld($item)
    {
        $type = isset($item['type']) ? $item['type'] : $item['post_type'];
        if (in_array($type, ['friend'])) {
            return '';
        }
        $result = '';
        switch ($type) {
            case 'post':
                $result .= nl2br(htmlspecialchars($item['text']));
                if (!empty($item['copy_history'])) {
                    //$result .= $this->renderAttachments($item['copy_history']);
                }
                break;
            /** @noinspection PhpMissingBreakStatementInspection */
            case 'photo':
                if (isset($item['photo'])) {
                    $item['photos']['items'] = [$item['photo']];
                }
            case 'wall_photo':
            case 'photo_tag':
                foreach (['photos', 'wall_photo', 'photo_tags'] as $key) {
                    if (isset($item[$key])) {
                        break;
                    }
                }

                /** @noinspection PhpUndefinedVariableInspection */
                foreach ($item[$key]['items'] as $photo) {
                    $result .= sprintf('<img src="%s" style="max-height: 340px"/> ', htmlspecialchars($this->getImageFromSize($photo, 604)));
                }
                break;
            case 'audio':
                if (empty($item['audio']['count'])) {
                    $item['audio']['items'] = [$item['audio']];
                } else {
                    foreach ($item['audio']['items'] as $audio) {
                        if (!$audio) {
                            continue;
                        }
                        $result .= sprintf('<p>%s - %s<br><audio controls preload="none"><source src="%s" type="audio/ogg"></audio></p>',
                            htmlspecialchars($audio['artist']),
                            htmlspecialchars($audio['title']),
                            htmlspecialchars($audio['url'])
                        );
                    }
                }

                break;
            case 'video':
                if (!isset($item['video']['items'])) {
                    $item['video']['items'] = [$item['video']];
                }
                foreach ($item['video']['items'] as $video) {
                    if (!$video) {
                        continue;
                    }
                    $result .= print_r($video, 1);
                    $result .= sprintf('<p>%s<br><img src="%s" width="640"><br>%s</p>',
                        htmlspecialchars($video['title']),
                        htmlspecialchars($this->getImageFromSize($video, 640)),
                        htmlspecialchars($video['description'])
                    );
                }
                break;
            case 'link':
                if (isset($item['link']['photo'])) {
                    $result .= sprintf('<img src="%s" style="max-height: 340px;"/><br/>', htmlspecialchars($this->getImageFromSize($item['link']['photo'], 604)));
                }
                $result .= sprintf('<a href="%s" target="_blank">%s</a>', htmlspecialchars($item['link']['url']), htmlspecialchars($item['link']['title']));
                if (!empty($item['link']['description'])) {
                    $result .= "<br/>" . htmlspecialchars($item['link']['description']);
                }
                break;
            case
            'doc': // @todo use icon type
                $doc = $item['doc'];
                $result .= sprintf('<div style="padding-top: 5px"><img src="%s" style="float: left; margin-right: 5px;"/> <a href="%s" target="_blank">%s</a></div>',
                    htmlspecialchars($this->getIconFromType($doc['ext'])),
                    htmlspecialchars($doc['url']),
                    htmlspecialchars($doc['title'])
                );
                break;
            default:
                $result .= "Unsupported type: " . $item['type'] . '<pre>' . print_r($item, true) . '</pre>';
                break;
        }

        if (!empty($item['source_id']) && !empty($result)) {
            $result = $this->renderSource($item['source_id'], $item['date']) . $result;
        }
        if (!empty($item['owner_id']) && !empty($result)) {
            $result = $this->renderSource($item['owner_id'], $item['date']) . $result;
        }

        return $result;
    }


    /**
     * @param array $postSource
     * @return string
     */
    private function postSourceRender($postSource)
    {
        $result = 'Posted from: ';
        switch ($postSource['type']) {
            case 'vk':
                $result .= 'vk';
                break;
            case 'api':
                if (isset($postSource['url'])) {
                    $result .= sprintf('<a href="%s" target="_blank">%s</a>', htmlspecialchars($postSource['url']), htmlspecialchars($postSource['platform']));
                } else if (isset($postSource['platform'])) {
                    $result .= htmlspecialchars($postSource['platform']);
                } else {
                    $result = '';
                }
                break;
            default:
                $result .= json_encode($postSource, JSON_UNESCAPED_UNICODE);
        }
        return $result;
    }

    private function renderGeo($geo)
    {
        $result = '';
        if ($geo['type'] == 'point') {
            $coordinates = explode(' ', $geo['coordinates']);
            // @todo mybe use field place
            $result = $this->reverseGeocoding($coordinates[0], $coordinates[1]);
        } else {
            $result .= print_r($geo, true);
        }
        return $result;
    }

    /**
     * Reverse geocoding coodinats to text addres
     * @todo add link to map
     *
     * @param $lat
     * @param $lng
     * @return string
     */
    public function reverseGeocoding($lat, $lng)
    {
        if (!$this->allowGeocoding)
            return '';
        $data = file_get_contents(sprintf('http://maps.googleapis.com/maps/api/geocode/json?latlng=%s,%s&language=ru', $lat, $lng));
        if (empty($data)) {
            return 'Error:(';
        }
        $data = json_decode($data, true);
        if ($data['status'] != 'OK') {
            return 'Unknown position ' . $data['status'];
        }
        $address = [];
        foreach ($data['results'][0]['address_components'] as $item) {
            if (!in_array($item['types'][0], ['administrative_area_level_2', 'administrative_area_level_3', 'postal_code'])) {
                $address[] = $item['short_name'];
            }
        }
        return implode(', ', $address);
    }

    /**
     * @param array $item
     * @return string
     */
    public function getLink($item)
    {
        if (isset($item['type']) && $item['type'] == 'photo') {
            return 'https://vk.com/';
        }
        if (isset($item['post_id'])) {
            return 'https://vk.com/feed?w=wall' . $item['source_id'] . '_' . $item['post_id'];
        }

        return 'https://vk.com/';
    }

    private function getIconFromType($ext)
    {
        $homePath = 'https://raw.githubusercontent.com/teambox/Free-file-icons/master/16px/';
        if (in_array($ext, ['html', 'htm'])) {
            return $homePath . '_page.png';
        }
        $allowExt = ['aac', 'ai', 'aiff', 'avi', 'bmp', 'c', 'cpp', 'css', 'dat', 'dmg', 'doc', 'dotx', 'dwg', 'dxf',
            'eps', 'exe', 'flv', 'gif', 'h', 'hpp', 'html', 'ics', 'iso', 'java', 'jpg', 'js', 'key', 'less', 'mid',
            'mp3', 'mp4', 'mpg', 'odf', 'ods', 'odt', 'otp', 'ots', 'ott', 'pdf', 'php', 'png', 'ppt', 'psd', 'py',
            'qt', 'rar', 'rb', 'rtf', 'sass', 'scss', 'sql', 'tga', 'tgz', 'tiff', 'txt', 'wav', 'xls', 'xlsx', 'xml',
            'yml', 'zip'];
        if (in_array($ext, $allowExt)) {
            return $homePath . $ext . '.png';
        }

        return $homePath . '_blank.png';
    }

    public function getGenreList()
    {
        return [
            1 => 'Rock',
            2 => 'Pop',
            3 => 'Rap & Hip-Hop',
            4 => 'Easy Listening',
            5 => 'Dance & House',
            6 => 'Instrumental',
            7 => 'Metal',
            21 => 'Alternative',
            8 => 'Dubstep',
            1001 => 'Jazz & Blues',
            10 => 'Drum & Bass',
            11 => 'Trance',
            12 => 'Chanson',
            13 => 'Ethnic',
            14 => 'Acoustic & Vocal',
            15 => 'Reggae',
            16 => 'Classical',
            17 => 'Indie Pop',
            19 => 'Speech',
            22 => 'Electropop & Disco',
            18 => 'Other',
        ];
    }

    /**
     * @param array $attachment
     * @return string
     */
    private function renderAttachment($attachment)
    {
        $result = '';
        switch ($attachment['type']) {
            case 'photo':
                $result = $this->renderPhoto($attachment['photo']);
                break;
            case 'link':
                $result = $this->renderLink($attachment['link']);
                break;
            case 'audio':
                $result .= $this->renderAudio($attachment['audio']);
                break;
            case 'video':
                $result .= $this->renderVideo($attachment['video']);
                break;
            case 'album':
                $result .= $this->renderAlbum($attachment['album']);
                break;
            /*case 'doc': @todo test me
                $doc = $attachment['doc'];
                $result .= sprintf('<div style="padding-top: 5px"><img src="%s" style="float: left; margin-right: 5px;"/> <a href="%s" target="_blank">%s</a></div>',
                    htmlspecialchars($this->getIconFromType($doc['ext'])),
                    htmlspecialchars($doc['url']),
                    htmlspecialchars($doc['title'])
                );
                break;*/
            default:
                $result .= print_r($attachment, true);
        }
        return $result;
    }

    /**
     * @param array $link
     * @return string
     */
    private function renderLink($link)
    {
        $result = '';
        if (isset($link['photo'])) {
            $result .= sprintf('<img src="%s" style="max-height: 340px;"/><br/>', htmlspecialchars($this->getImageFromSize($link['photo'], 604)));
        }
        $result .= sprintf('<a href="%s" target="_blank">%s</a>', htmlspecialchars($link['url']), htmlspecialchars($link['title']));
        if (!empty($link['description'])) {
            $result .= "<br/>" . htmlspecialchars($link['description']);
        }
        return $result;
    }

    private function renderAlbum($album)
    {
        //https://vk.com/feed?z=album-53998807_230549957
        return print_r($album, true);

    }


}