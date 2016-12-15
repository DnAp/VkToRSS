<?php

/**
 * Created by PhpStorm.
 * User: DnAp
 * Date: 21.08.2016
 * Time: 12:08
 */
class VkConfig
{
    public $tokenFileName = __DIR__ . '/.accessToken';
    protected $key;
    protected $host;

    /**
     * VkConfig constructor.
     * @param $host
     */
    public function __construct($host)
    {
        $this->host = $host;
    }


    public function getKey()
    {
        if ($this->key)
            return $this->key;
        if (is_file($this->tokenFileName)) {
            return trim(file_get_contents($this->tokenFileName));
        }
        return null;
    }

    public function newKey(Vk $vk)
    {
        if(isset($_POST['token'])) {
            file_put_contents($this->tokenFileName, $_POST['token']);
            $this->key = $_POST['token'];
            $vk->access_token = $_POST['token'];
            return false;
        }

        $return = '<a href="'.htmlspecialchars($vk->get_code_token()).'" target="_blank">Get new token</a>'.
'<form method="post" target="_blank" action="'.$this->host.'">
    <input name="token"/><input type="submit">
</form>';
        return $return;
    }
}