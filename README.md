# Terminus Upstream Dev Tools

[![Terminus v1.x Compatible](https://img.shields.io/badge/terminus-v1.x-green.svg)](https://github.com/terminus-plugin-project/terminus-upstream-dev/tree/master)

Terminus plugin to automate the process of updating a site through the upstream. This performs a backup before applying upstream updates.

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
$ terminus site:update companysite-33.dev --use_git
```

### Perform updates using a separate repository
```
$ terminus site:update companysite-33.dev --use_git --repo="git://github.com/pantheon-systems/drops-7.git"
```

### Perform updates using a separate repository and a separate branch
```
$ terminus site:update companysite-33.dev --use_git --repo="git://github.com/pantheon-systems/drops-7.git" --branch="dev"
```

### Apply updates through git using a particular branch of the upstream
```
$ terminus site:update companysite-33.dev --use_git --branch="dev"
```

## Installation
For help installing, see [Manage Plugins](https://pantheon.io/docs/terminus/plugins/)
```
mkdir -p ~/.terminus/plugins
composer create-project -d ~/.terminus/plugins pantheon-systems/terminus-build-tools-plugin:~1
```

## Help
Run `terminus list site:update` for a complete list of available commands. Use `terminus help <command>` to get help on one command.