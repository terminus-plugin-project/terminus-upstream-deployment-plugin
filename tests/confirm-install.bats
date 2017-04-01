#!/usr/bin/env bats

#
# confirm-install.bats
#
# Ensure that Terminus and the Composer plugin have been installed correctly
#

@test "confirm terminus version" {
  terminus --version
}

@test "get help on plugin command" {
  run terminus help site:update
  [[ $output == *"Pushed updates from upstream to highest environment"* ]]
  [ "$status" -eq 0 ]
}