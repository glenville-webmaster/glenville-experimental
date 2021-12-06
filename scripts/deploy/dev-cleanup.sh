#!/bin/bash

### Using Acquia CLI 1.3. This is required for Drupal 8.x. Use Acquia CLI 2.x for Drupal 9.x ###

# Abort if anything fails
set -e

echo "============"
echo "Begin: Dev - Cleanup"
echo "============"

#-------------------------- vars --------------------------------
DOCROOT="$BITBUCKET_CLONE_DIR/docroot"
THEME_DIR="$DOCROOT/themes/custom/glenville"
DATE=`date +%Y-%m-%d`
KILLDEV=true

# Select Dev environment, fail if not dev
case $BITBUCKET_BRANCH in
  develop)
    ALIAS='dev'
esac

if [ -z "$ALIAS" ]; then
  echo "Bad Branch name $BITBUCKET_BRANCH"
  exit 1
fi

# Make sure we're in the right directory
cd $BITBUCKET_CLONE_DIR

# DRUSH TASKS
echo "===== Turning on Maintenance Mode ====="
./vendor/bin/drush @$ALIAS -vvv state:set system.maintenance_mode 1 --input-format=integer
echo "===== Cache Rebuild ====="
./vendor/bin/drush @$ALIAS cache:rebuild
echo "===== Updating Database ====="
./vendor/bin/drush @$ALIAS updb -y
echo "===== Turning off Maintenance Mode ====="
./vendor/bin/drush @$ALIAS state:set system.maintenance_mode 0 --input-format=integer
echo "===== Cache Rebuild ====="
./vendor/bin/drush @$ALIAS cache:rebuild

# Clear varnish
# THERE IS CURRENTLY NO PURGE VARNISH COMMAND IN ACQUIA CLI 2.X
./vendor/bin/acquiacli preprod:purgevarnish $ACQUIA_UUID $ALIAS

echo "============"
echo "Complete: Dev - Cleanup"
echo "============"

# Done
echo "============"
echo "YAY! The build is complete"
echo "============"
