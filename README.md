![](http://i.imgur.com/8SZWa2h.gif)

This project adheres to the Contributor Covenant, version 1.3.0. See
[`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) for more information. By
participating, you are expected to uphold these protocols.

#Installation
* Install dependencies with [composer](https://getcomposer.org/doc/00-intro.md) using `composer install`
* Make an empty MySQL database.
* Copy `includes/config.example.php` to `includes/config.php` and get editing.
* Run `php includes/upgrade.php` from the command line.
* Optional: Edit `.htaccess`

On a fresh Ubuntu/Debian installation you will likely want `apt-get install apache2 php5 php5-curl php5-mysql mysql-server mysql-client` and configure your `php.ini` as follows:

````
display_errors = On
display_startup_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE
````

Depending on your setup it may also be necessary to configure your `php.ini` to use UTF-8 internally so exotic tripcodes get converted correctly:

````
[mbstring]
mbstring.internal_encoding = UTF-8
````

It is also possible to use nginx, and a sample `rewrite.conf` is available, but this is currently not officially supported.

#Updating
Update your working tree and run `php includes/upgrade.php`. See the wiki for scripts used by http://minichan.org

#"Support"
If you have any questions you can try your luck on the issue tracker or `##minichan @ irc.freenode.net`. Note that development is primarily geared towards running a functional [http://minichan.org](http://minichan.org) so your feature requests may not be a priority. Pull requests are welcome if they are discussed on the issue tracker or IRC (with r04r) first.

And yes. The code is shit. :-)

#Branches
This repository consists of four primary branches, of which all but `master` may have their histories rewritten at any time:

* `master`: The main development branch. The code on this branch should work, but may not be fully functional or stable when development is happening. If you intend to submit pull requests base them off of this branch.
* `testing`: http://test.minichan.org - For testing new changes in a staging setup
* `beta`: http://beta.minichan.org - For testing new changes in a staging setup, with a shared database with http://minichan.org. Database changes will not be tested here.
* `minichan`: http://minichan.org
