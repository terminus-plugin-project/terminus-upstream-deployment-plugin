#!/usr/bin/env bats

#
# test-default-no-args.bats
#
# Test to check default command with no arguments
#

@test "output of plugin 'site:update' command with no arguments" {
  run terminus site:update $TERMINUS_SITE
  [[ "$output" == *"${TERMINUS_SITE_LABEL} Starting"* ]]
  [[ "$output" == *"${TERMINUS_SITE_LABEL}: dev creating backup"* ]]
  [[ "$output" == *"${TERMINUS_SITE_LABEL}: dev backup created"* ]]
  [[ "$output" == *"${TERMINUS_SITE_LABEL}: dev drush updatedb"* ]]
  [[ "$output" == *"${TERMINUS_SITE_LABEL}: dev drush clear cache"* ]]
  [[ "$output" == *"Completed Upstream Update for ${TERMINUS_SITE_LABEL}"* ]]
  [ "$status" -eq 0 ]
}