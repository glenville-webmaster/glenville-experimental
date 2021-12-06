#!/bin/bash

### Using Acquia CLI 1.3. This is required for Drupal 8.x. Use Acquia CLI 2.x for Drupal 9.x ###

# Abort if anything fails
set -e

echo "============"
echo "Begin: Dev - Deploy"
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

# If dev, clone from production
echo "============"
echo "Migrating from production to $ALIAS"
echo "============"

if [ "$KILLDEV" = true ]; then
  ./vendor/bin/acquiacli preprod:prepare $ACQUIA_UUID prod $ALIAS
  sleep 15
else
  echo "============"
  echo " KILLDEV IS FALSE!! Skipping Migration..."
  echo "============"
fi

# Git Setup
git config --global user.email "josh.chambers@glenville.edu"
git config --global user.name "Josh Chambers"
git config --global push.default simple

# Clone the Acquia Repo
git clone gsc@svn-4707.devcloud.hosting.acquia.com:gsc.git ~/acquia

# Switch to the proper branch
cd ~/acquia
git checkout $BITBUCKET_BRANCH 2>/dev/null || git checkout -b $BITBUCKET_BRANCH

# Copy all files except ignored
echo "============"
echo "rsync files over to acquia repo."
echo "============"
cd $BITBUCKET_CLONE_DIR
rsync -av --no-perms --no-owner --no-group --exclude-from 'scripts/deploy/.ci-rsync-exclude' --exclude '.git' --delete ./ ~/acquia

# Move over custom git ignore.
echo "============"
echo "Copy git ignore"
echo "============"
cp scripts/deploy/deploy_gitignore ~/acquia/.gitignore

# Commit and push changes to Acquia
cd ~/acquia
git status

echo "============"
echo "Add all files to git"
echo "============"
git add .

echo "============"
echo "Committing files to branch"
echo "============"
git commit -am "Bitbucket build - $BITBUCKET_BUILD_NUMBER, Commit $BITBUCKET_COMMIT"

echo "============"
echo "Pushing to Acquia"
echo "============"
git push origin $BITBUCKET_BRANCH

echo "============"
echo "Completed: Dev - Deploy"
echo "============"
