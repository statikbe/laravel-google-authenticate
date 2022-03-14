# Changelog

All notable changes to `GoogleAuthenticator` will be documented in this file.
## Version 4.1.0
- Updated version illumninate/support to use Laravel 8  or 9
- Updated auth templates with new format from Laravel + google login button
- Removed Laravel support for version 6 and 7 
- Removed google/apiclient
## Version 4.0.0
### Updated
- Updated structure to be more consistent with other packages
- Updated upgrade-guide.md file for new version
- Removed `spatie/laravel-permission` implementation and dependency
  - This makes the `GoogleAuthenticator` less opinionated
  - Assigning roles can be done on the "created" method of your user
## Version 3.3.1
### Updated
- Updated version for laravel/socialite & google/apiclient
## Version 3.3.0
### Updated
- Updated version for illumninate/support to work with php 8*
## Version 3.2.1
### Changed
- Removed the `getLogout` action as it was not used
- Removed the `/logout` route as it would interfere with the base laravel route.
## Version 3.2.0
### Updated
- User model will now check which namespace is used for the model and then creates a user from there

## Version 3.1.2
### Updated
- Updated version for illumninate/support to work with php 7*

## Version 3.1.0
### Changed
- Switched the `user_columns` array key and value.
### Added
- Comments in our Controller
- `!` option for domains to make them be ignored: f.e. `!statik.be` will ignore 'statik.be' addresses.
- The use of multiple google values for 1 user table.
- Login with google even if the user account pre-existed (based on email)

## Version 3.0.1
### Changed
- Fixed markdown typos
- Removed statik.be from roles array in config file 

## Version 3.0.0
### Changed
- Rewrote config file to revive it's relevance
- Moved following values from .env to config:
```    
    - AUTH_ROLE_ADMIN="admin"
    - AUTH_ROLE_DOMAIN="statik.be"
    - AUTH_REDIRECT_URL = "/admin"
```
- Renamed Statikbe to statikbe (in the service provider)
- Changed publish views tag from GoogleAuthenticate:views to laravel-google-authenticate.views
### Added
- Multiple roles support
- Multiple domains support
- Custom user table columns support

## Version 2.0.3
### Changed
- Fixed bug in authentication check on role

## Version 2.0.2
### Changed
- Added nullable to provider and provider_id columns
- Fixed readme for publishing migration and config

## Version 2.0.1
### Changed
- Updated composer package illuminate/support for Laravel 6

## Version 2.0.0
### Added
- Added the config file
- Auto publish config file
- Added names for routes & use them in overwritten auth views
### Changed
- Updated packages to work with laravel 6
- Cleaned up some code 

## Version 1.0.9
### Added
- Added a way to change the redirect url after login

## Version 1.0.8
### Added
- Correct namespace class for Controller extend

## Version 1.0.7
### Added
- Added the values that need to be in the services.php class
- Changed Controller route namespacing

## Version 1.0.6
### Removed
- Removed column from migration, should not have been there in the first place

## Version 1.0.5
### Changed
- Added dependency for doctrine/dbal
- Fixed migration for users

## Version 1.0.4
### Changed
- Changed the name of the migration file
- Typo in readme

## Version 1.0.3
### Changed
- Made some fixes to the readme

## Version 1.0.2
### Removed
- Removed unrelated string

## Version 1.0.1
### Changed
- rework of the migration load in the ServiceProvider

## Version 1.0
### Added
- Everything
