<?php

namespace Esyede;

defined('DS') or exit('No direct access.');

final class Gists
{
    private $title;
    private $language;
    private $html = [];
    private $pdo;

    public function __construct($title, $appkey, $language = 'en', $timezone = 'UTC')
    {
        $this->language($language);

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'on') {
            exit($this->language['error_suspicious_access']);
        }

        if (! $appkey || '' === trim($appkey)) {
            exit($this->language['error_appkey']);
        }

        date_default_timezone_set($timezone);
        $this->title = $title;

        try {
            if (! $this->pdo) {
                $appkey = md5(rtrim($appkey, '.sqlite')).'.sqlite';
                $this->pdo = new \PDO('sqlite:'.$appkey);
                $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);
                $this->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
            }
        } catch (\PDOException $e) {
            exit($this->language['error_db_load']);
        }

        $this->pdo->exec('pragma auto_vacuum = 1');
        $this->pdo->exec('create table if not exists `gists` (`id` primary key, `expiry` timestamp, `prettify` integer, `wrap` integer, `data` blob);');
        $this->pdo->exec('create table if not exists `users` (`hash` primary key, `waiting` timestamp, `degree` integer);');
    }

    public function __destruct()
    {
        $this->pdo = null;
    }

    private function html($content, $prepend = false)
    {
        if (! $prepend) {
            $this->html[] = $content;
        } else {
            array_unshift($this->html, $content);
        }
    }

    private function render()
    {
        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>'.htmlentities($this->title).'</title>';
        echo '<link rel="icon" href="data:;base64,iVBORw0KGgo=">';
        echo '<link href="./assets/css/style.css" rel="stylesheet" type="text/css" />';
        echo '<link href="./assets/css/prettify.css" rel="stylesheet" type="text/css" />';
        echo '</head>';
        echo '<body>';
        echo '<h3>'.$this->title.'</h3>';

        foreach ($this->html as $key => $value) {
            echo $value;
        }

        echo '<div id="footer">';
        echo $this->language['footer_text'];
        echo '<p><a href="./">'.$this->language['back_home'].'</a></p>';
        echo '</div>';
        echo '</body>';
        echo '</html>';
        exit;
    }

    private function remaining($time)
    {
        if ($time == -1) {
            return $this->language['never_expired'];
        } elseif ($time == 0) {
            return $this->language['already_expired'];
        } elseif ($time == -2) {
            return $this->language['one_time_gist'];
        }

        $expiration = new \DateTime('@'.$time);
        $interval = $expiration->diff(new \DateTime(), true);

        $ret = $this->language['expired_in'].$interval->d.' '.$this->language['days'];
        $ret .= $interval->h.' '.$this->language['hours'];

        if ($interval->d === 0) {
            $ret .= $interval->i.' '.$this->language['minutes'];
            if ($interval->h === 0) {
                $ret .= $interval->i.' '.$this->language['seconds'];
            }
        }

        return rtrim($ret).'.';
    }

    public function prompt()
    {
        $this->html(
            '<form method="post" action=".">
                 <div id="left">
                     <select name="d" id="d">
                         <option value="-1" selected>'.$this->language['never_expired'].'</option>
                         <option value="2592000">1 '.$this->language['months'].'</option>
                         <option value="604800">1 '.$this->language['weeks'].'</option>
                         <option value="86400">1 '.$this->language['days'].'</option>
                         <option value="3600">1 '.$this->language['hours'].'</option>
                         <option value="600">10 '.$this->language['minutes'].'</option>
                         <option value="-2">'.$this->language['one_time_gist'].'</option>
                     </select>
                     <button type="submit">'.$this->language['save'].'</button>
                 </div>
                 <ul id="right">
                     <li>
                         <input type="checkbox" id="wrap" name="wrap" checked="checked">
                         <label for="wrap">'.$this->language['wrap_code'].'</label>
                     </li>
                     <li>
                         <input type="checkbox" id="prettify" name="prettify" checked="checked">
                         <label for="prettify">'.$this->language['syntax_highlighter'].'</label>
                     </li>
                 </ul>
                 <input type="text" id="content" name="content"
                 placeholder="Do not fill me!" style="display: none;"/>
                 <textarea autofocus required name="p" placeholder="'.$this->language['tab_allowed'].'.."></textarea>
            </form>'
        );
        $this->html('<script src="./assets/js/textarea.js"></script>');

        $this->render();
    }

    private function id()
    {
        $query = $this->pdo->prepare('select `id` from `gists` where id = :uniqid;');
        $query->bindParam(':uniqid', $uniqid, \PDO::PARAM_STR, 16);

        do {
            $uniqid = static::uniqid();
            $query->execute();
        } while ($query->fetch() !== false);

        return $uniqid;
    }

    private function waiting($degree)
    {
        return time() + (int) (pow($degree, 2.5));
    }

    private function nospammer()
    {
        $hash = sha1($_SERVER['REMOTE_ADDR']);

        $query = $this->pdo->prepare('select * from `users` where hash = :hash');
        $query->bindValue(':hash', $hash, \PDO::PARAM_STR);
        $query->execute();

        $result = $query->fetch();

        $toofast = (! empty($result) && time() < $result['waiting']);
        $spammy = (! isset($_POST['content']) || ! empty($_POST['content']));

        $degree = $toofast ? $result['degree'] + 1 : ($spammy ? 512 : 1);
        $waiting = $this->waiting($degree);

        $query = $this->pdo->prepare('replace into `users` (`hash`, `waiting`, `degree`) values (:hash, :waiting, :degree);');
        $query->bindValue(':hash', $hash, \PDO::PARAM_STR);
        $query->bindValue(':waiting', $waiting, \PDO::PARAM_INT);
        $query->bindValue(':degree', $degree, \PDO::PARAM_INT);
        $query->execute();

        if ($toofast || $spammy) {
            exit($this->language['error_suspicious_access']);
        }
    }

    public function make($expiry, $prettify, $wrap, $data)
    {
        $this->nospammer();

        $expiry = (int) $expiry;

        if ($expiry > 0) {
            $expiry += time();
        }

        $uniqid = $this->id();
        $query = $this->pdo->prepare('insert into `gists` (`id`, `expiry`, `prettify`, `wrap`, `data`) values (:uniqid, :expiry, :prettify, :wrap, :data);');

        $query->bindValue(':uniqid', $uniqid, \PDO::PARAM_STR);
        $query->bindValue(':expiry', $expiry, \PDO::PARAM_INT);
        $query->bindValue(':prettify', $prettify, \PDO::PARAM_INT);
        $query->bindValue(':wrap', $wrap, \PDO::PARAM_INT);
        $query->bindValue(':data', $data, \PDO::PARAM_STR);
        $query->execute();

        if (is_file(__DIR__.DS.'.htaccess')) {
            header('Location: ./'.$uniqid);
            exit;
        }
        header('Location: ./index.php?p='.$uniqid);
        exit;
    }

    public function show($param)
    {
        $id = str_replace('@raw', '', $param);
        $is_raw = (int) (strtolower(substr($param, -4)) == '@raw');

        $fail = false;
        $query = $this->pdo->prepare('select * from `gists` where id = :id;');
        $query->bindValue(':id', $id, \PDO::PARAM_STR);
        $query->execute();
        $result = $query->fetch();

        if ($result == null) {
            $fail = true;
        } elseif ($result['expiry'] < time()
                and $result['expiry'] >= 0) {
            $query = $this->pdo->prepare('delete from `gists` where id = :id;');
            $query->bindValue(':id', $id, \PDO::PARAM_STR);
            $query->execute();

            if ($result['expiry'] != 0) {
                $fail = true;
            }
        } elseif ($result['expiry'] == -2) {
            $query = $this->pdo->prepare('update `gists` set expiry=0 where id = :id;');
            $query->bindValue(':id', $id, \PDO::PARAM_STR);
            $query->execute();
        }

        if ($fail) {
            $this->html('<div id="error">'.$this->language['gist_not_found'].'</div>');
            $this->prompt();
        } else {
            header('X-Content-Type-Options: nosniff');

            if ($is_raw) {
                header('Content-Type: text/plain; charset=utf-8');
                echo $result['data'];
                exit;
            }
            header('Content-Type: text/html; charset=utf-8');

            if ($result['prettify']) {
                $this->html('<script>window.onload=function(){prettyPrint();}</script>');
                $this->html('<script src="./assets/js/prettify.js"></script>', true);
            }

            $this->html(
                    '<div id="left"><a href="./'.$id.'@raw">'.$this->language['raw'].'</a> | <a href="./">'.$this->language['write_new'].'</a></div>
                     <div id="right">'.$this->remaining($result['expiry']).'</div>'
                );

            $class = 'prettyprint linenums';
            if ($result['wrap']) {
                $class .= ' wrap';
            }

            $this->html('<pre class="'.$class.'">'.htmlentities($result['data']).'</pre>');
        }

        $this->render();
    }

    public function sweep()
    {
        $this->pdo->exec("delete from `gists` where expiry > 0 and strftime('%s','now') > expiry;");
        $this->pdo->exec("delete from `users` where strftime('%s','now') > waiting;");
    }

    public function destroy()
    {
        return (false !== @unlink(__DIR__.DS.'database.sqlite'));
    }

    public static function uniqid($length = 16)
    {
        $string = '';

        while (($len = strlen($string)) < $length) {
            $size = $length - $len;
            $bytes = openssl_random_pseudo_bytes($size);
            $string .= substr(str_replace(['/', '+', '='], '', \base64_encode($bytes)), 0, $size);
        }

        return $string;
    }

    public function language($language)
    {
        $language = __DIR__.DS.'lang'.DS.$language.'.php';

        if (! is_file($language)) {
            exit('Desired language file not found. Please contact administrator.');
        }

        if (! is_readable($language)) {
            exit('Unable to read language file. Please contact administrator.');
        }

        if (! $this->language) {
            $this->language = require $language;
        }
    }
}
