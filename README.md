# Terminus Upstream Deployment Plugin

[![CircleCI](https://circleci.com/gh/terminus-plugin-project/terminus-upstream-deployment-plugin/tree/1.x.svg?style=svg)](https://circleci.com/gh/terminus-plugin-project/terminus-upstream-deployment-plugin/tree/1.x)
[![Terminus v1.x Compatible](https://img.shields.io/badge/terminus-v1.x-green.svg)](https://github.com/terminus-plugin-project/terminus-upstream-deployment-plugin/tree/1.x)

Terminus plugin to automate the process of updating a site through the upstream. This performs a backup before applying upstream updates.

This plugin differs from [terminus-mass-update](https://github.com/pantheon-systems/terminus-mass-update) 
as this will only take a single site at a time along with the following features:
 
* take a backup of your dev environment
* pull any updates from the upstream and apply them on dev
* run updatedb on your dev environment
* run clear cache on your dev environment
* check to see if your test environment initialized
* take a backup of your test environment
* run updatedb on your test environment
* run clear cache on your test environment
* check to see if youur live environment is initialized
* take a backup of your live environment
* run updatedb on your test environment
* run clear cache on your live environment

## Examples
### Default Running
```
$ terminus site:update companysite-33.dev
```

### Skip Backups
```
$ terminus site:update companysite-33.dev --skip_backups
```

### Apply updates through git using the default upstream branch
```
$ terminus site:update companysite-33.dev --git
```

### Perform updates using a separate repository
```
$ terminus site:update companysite-33.dev --ugit --repo="git://github.com/pantheon-systems/drops-7.git"
```

### Perform updates using a separate repository and a separate branch
```
$ terminus site:update companysite-33.dev --git --repo="git://github.com/pantheon-systems/drops-7.git" --branch="dev"
```

### Apply updates through git using a particular branch of the upstream
```
$ terminus site:update companysite-33.dev --git --branch="dev"
```

## Installation
For help installing, see [Manage Plugins](https://pantheon.io/docs/terminus/plugins/)
```
mkdir -p ~/.terminus/plugins
composer create-project -d ~/.terminus/plugins terminus-plugin-project/terminus-upstream-deployment-plugin:~1
```

## Help
Run `terminus list site:update` for a complete list of available commands. Use `terminus help <command>` to get help on one command.
