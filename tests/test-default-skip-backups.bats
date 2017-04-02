#!/usr/bin/env bats

#
# test-default-skip-backups.bats
#
# Test to check default command with --skip_backups arguments
#
setup(){
  GITURL=$(terminus connection:info $TERMINUS_SITE.dev --field="git_url")
  rm -rf /tmp/${TERMINUS_SITE}
  git clone ${GITURL} /tmp/${TERMINUS_SITE}
  git -C "/tmp/${TERMINUS_SITE}/.git" --work-tree="/tmp/${TERMINUS_SITE}" reset --hard HEAD~1
  git -C "/tmp/${TERMINUS_SITE}/.git" --work-tree="/tmp/${TERMINUS_SITE}" push -f origin master
  rm -rf /tmp/$TERMINUS_SITE
}

@test "output of plugin 'site:update' command with --skip_backups" {
  run terminus site:update $TERMINUS_SITE --skip_backups
  [[ "$output" == *"${TERMINUS_SITE} Starting"* ]]
  [[ "$output" != *"${TERMINUS_SITE}: dev creating backup"* ]]
  [[ "$output" == *"${TERMINUS_SITE}: dev drush updatedb"* ]]
  [[ "$output" == *"${TERMINUS_SITE}: dev drush clear cache"* ]]
  [[ "$output" == *"Completed Upstream Update for ${TERMINUS_SITE}"* ]]
  [ "$status" -eq 0 ]
}