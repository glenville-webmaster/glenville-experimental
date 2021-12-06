#!/usr/bin/env bash

## Backup and Update Local Site (@self)

# Abort if anything fails
set -e

#-------------------------- vars --------------------------------
REPOROOT=$(pwd)
DOCROOT="$REPOROOT/docroot"
SITE_DIR="$DOCROOT/sites"
LOCAL_SETTINGS="scripts/local/settings.local.php"
LOCAL_SERVICES="scripts/local/local.services.yml"


# Console colors
red='\033[0;31m'
green='\033[0;32m'
green_bg='\033[42m'
yellow='\033[1;33m'
NC='\033[0m'

echo-red () { echo -e "${red}$1${NC}"; }
echo-green () { echo -e "${green}$1${NC}"; }
echo-green-bg () { echo -e "${green_bg}$1${NC}"; }
echo-yellow () { echo -e "${yellow}$1${NC}"; }

# composer / settings
pre_install ()
{

  cd "$REPOROOT"

  echo-green "Copy settings"
  if [[ -f $SITE_DIR/default/settings.local.php ]]; then
    echo-yellow "Settings already exists"
  else
    cp $LOCAL_SETTINGS $SITE_DIR/default/
  fi

  echo-green "Copy services"
  if [[ -f $SITE_DIR/local.services.yml ]]; then
    echo-yellow "Services file already exists"
  else
    cp $LOCAL_SERVICES $SITE_DIR/
  fi

}

# Update Local Site
site_update ()
{

  echo-green "Backup Local Database"
  vendor/bin/drush sql:dump --gzip --result-file=../../dump.sql
  
  echo-green "Update Composer Modules"
  composer update --with-dependencies --optimize-autoloader
  
  echo-green "Update Database"
  vendor/bin/drush updatedb -y
  
  echo-green "Clear Cache"
  vendor/bin/drush cr
}

# Copy the newly updated Database
update_db () {

  echo-green "Copy Updated Database"
  vendor/bin/drush sql:dump --gzip --result-file=../../dump.sql

}

#-------------------------- Execution --------------------------------
# Site initialization

echo -e "${green_bg} STEP 1: Initializing site...${NC}"
time pre_install

echo -e "${green_bg} STEP 2: Updating Local Site...${NC}"
time site_update

echo -e "${green_bg} STEP 3: Copy Updated DB${NC}"
time update_db

echo -en "${green_bg} DONE! ${NC} "
#-------------------------- END: Execution --------------------------------
