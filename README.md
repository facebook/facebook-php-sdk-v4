Facebook SDK for PHP
====================

[![Latest Stable Version](http://img.shields.io/packagist/v/facebook/php-sdk-v4.svg)](https://packagist.org/packages/facebook/php-sdk-v4)


This repository contains the open source PHP SDK that allows you to access Facebook
Platform from your PHP app.


Usage
-----

Minimal example:

```php
using Facebook\FacebookSession;

FacebookSession::setDefaultApplication('YOUR_APP_ID','YOUR_APP_SECRET');

// Use one of the helper classes to get a FacebookSession object.
//   FacebookRedirectLoginHelper
//   FacebookCanvasLoginHelper
//   FacebookJavaScriptLoginHelper
// or create a FacebookSession with a valid access token:
$session = new FacebookSession('access-token-here');

// Get the GraphUser object for the current user:

try {
  $me = (new FacebookRequest(
    $session, 'GET', '/me'
  ))->execute()->getGraphObject(GraphUser::className());
  echo $me->getName();
} catch (FacebookRequestException $e) {
  // The Graph API returned an error
} catch (\Exception $e) {
  // Some other error occurred
}

```

Complete documentation, installation instructions, and examples are available at:
[https://developers.facebook.com/docs/php](https://developers.facebook.com/docs/php)


Tests
-----

Create a test app on [Facebook Developers](https://developers.facebook.com) and add
your credentials to `tests/FacebookTestCredentials.php`.

The tests can be executed by running this command from the tests directory:

```bash
../vendor/bin/phpunit --stderr .
```


Contributing
------------

For us to accept contributions you will have to first have signed the
[Contributor License Agreement](https://developers.facebook.com/opensource/cla).

When committing, keep all lines to less than 80 characters, and try to
follow the existing style.

Before creating a pull request, squash your commits into a single commit.

Add the comments where needed, and provide sample explanation in the
commit message.
