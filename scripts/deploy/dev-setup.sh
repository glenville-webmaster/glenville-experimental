#!/bin/bash

### Using Acquia CLI 1.3. This is required for Drupal 8.x. Use Acquia CLI 2.x for Drupal 9.x ###

# Abort if anything fails
set -e

echo "============"
echo "Begin: Dev - Setup"
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

# Install from composer.json
echo "============"
echo "Running composer install"
echo "============"
composer install --no-interaction --optimize-autoloader

# Compile SCSS / Minify CSS/JS
echo "============"
echo "Compiling dist"
echo "============"
cd $THEME_DIR
npm ci && grunt build

# Add drush aliases
echo "============"
echo "Creating Drush files"
echo "============"
cd $BITBUCKET_CLONE_DIR
mkdir -p ~/.drush
cp -rf drush/sites ~/.drush/sites

cat > ./acquiacli.yml <<EOL
acquia:
  key: '${ACQUIA_KEY}'
  secret: '${ACQUIA_SECRET}'
extraconfig:
  timeout: 150
  configsyncdir: 'vcs'
EOL

echo "============"
echo "Completed: Dev - Setup"
echo "============"
