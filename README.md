# gists
Simple pastebin / github gist clone with sqlite database.



### Requirements
  - PHP 5.4 or newer
  - PDO extension for SQLite



### Installation
  1. Download the file from [release page](https://github.com/esyede/gists/releases).
  2. Add your application key to `config.php` file.
  3. Rename `sample.htaccess` to `.htaccess` if you prefer pretty url.

### Housekeeping
By default, any expired gist won't be removed. You need to remove it manually by visiting this link:
```
https://yoursite.com/path/to/gists/?sweep=vJ0cJiA6kcua

// or, if you aren't using pretty url:

https://yoursite.com/path/to/gists/index.php?sweep=vJ0cJiA6kcua
```
Where `vJ0cJiA6kcua` is your application key (can be found in `config.php` file).


That's pretty much it. Thank you for stopping by!



### License
This library is licensed under the [MIT License](http://opensource.org/licenses/MIT)
