This plugin creates an admin-side interface for programatically adding new Game posts. For the new games.studlife.com crossword functionality (Launched 10/31/2204), we use custom posts ("games") to allow users to navigate directly to a specific game, embeded on that post via an iframe.

This plugin helps users create those posts.

This plugin requires [The iframe plugin](https://wordpress.org/plugins/iframe/)

## Installation

To install, upload this directory to the "plugins" directory of the Wordpress site.

In order to work correctly, you must include the `functions.php` code from this plugin in the theme's `functions.php`:
```php
require_once ABSPATH . '/wp-content/plugins/games-post-manager/functions.php';
```
