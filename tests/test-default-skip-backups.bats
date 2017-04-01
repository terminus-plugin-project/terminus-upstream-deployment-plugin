#!/usr/bin/env bats

#
# test-default-skip-backups.bats
#
# Test to check default command with --skip_backups arguments
#

@test "output of plugin 'site:update' command with --skip_backups" {
  run terminus site:update $TERMINUS_SITE --skip_backups
  [[ "$output" == *"${TERMINUS_SITE_LABEL} Starting"* ]]
  [[ "$output" != *"${TERMINUS_SITE_LABEL}: dev creating backup"* ]]
  [[ "$output" == *"${TERMINUS_SITE_LABEL}: dev drush updatedb"* ]]
  [[ "$output" == *"${TERMINUS_SITE_LABEL}: dev drush clear cache"* ]]
  [[ "$output" == *"Completed Upstream Update for ${TERMINUS_SITE_LABEL}"* ]]
  [ "$status" -eq 0 ]
}