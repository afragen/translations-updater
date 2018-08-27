#### [unreleased]
* updated error handling in Singleton factory

#### 2.2.1 / 2018-07-01
* updated readme(s)

#### 2.2.0 / 2018-06-23
* refactored for traits, now requires PHP 5.4
* updated for new `Singleton` factory
* updated for GitLab API v4

#### 1.1.0 / 2017-10-30
* refactored `class API` to not be dependent on `class Base`

#### 1.0.0 / 2017-10-05
* fixed to not create generic global variables accidentally
* refactor to remove most constructors
* changed name of options from `ghu-*` to `tu-*`

#### 0.9
* added factory for creating singletons
* OOPified constructors to remove hooks, etc
* fixed some linter issues

#### 0.8 / 2017-08-03
* fix reference to `Base::sanitize()`

#### 0.7 / 2017-07-02
* update `Base->parse_header_uri()` for GitLab Groups

#### 0.6 / 2017-06-04
* simplify uninstall
* update rand to mt_rand

#### 0.5 / 2017-04-10
* cleanup uninstall

#### 0.4 / 2017-04-02
* make Autoloader a drop-in

#### 0.3 / 2017-03-10
* simplify calls to `class Language_Pack` for inclusion back to GitHub Updater

#### 0.2 / 2017-03-10
* major refactor to single API class handling all git hosts

#### 0.1 / 2017-03-09
* initial commit
