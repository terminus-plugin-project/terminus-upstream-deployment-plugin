#!/usr/bin/env bats

#
# test-default-no-args.bats
#
# Test to check default command with no arguments
#

@test "output of plugin 'create' command" {
  run terminus site:update --name=$TERMINUS_SITE
  [[ "$output" == *"${TERMINUS_SITE} Starting"* ]]
  [ "$status" -eq 0 ]
}