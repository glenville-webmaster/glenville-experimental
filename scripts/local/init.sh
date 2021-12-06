#!/usr/bin/env bash

## Initialize stack and site (full reset)
##
## Usage: fin init

# Abort if anything fails
set -e

#-------------------------- vars --------------------------------
REPOROOT=$(pwd)
DOCROOT="$REPOROOT/docroot"
SITE_DIR="$DOCROOT/sites"
THEME_DIR="$DOCROOT/themes/custom/glenville"
SERVER_ROOT="/var/www"
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

  echo-green "Composer install"
  composer install --no-interaction --optimize-autoloader

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

# Main site install
site_install ()
{
  cd "$DOCROOT"

  echo-green "Getting latest database"
  ../vendor/bin/drush sql:sync @gsc.prod @self --create-db -y

  echo-green "Getting latest files"
  ../vendor/bin/drush -y rsync @gsc.prod:%files @self:%files
}

# Compile theme and enable watcher.
compile_theme ()
{

  echo-green "Getting dependencies"
  cd "$THEME_DIR"
  npm install

  # Compile assets.
  grunt build

}

# Config / DB / Cache
post_install () {

  cd "$DOCROOT"

  echo-green "Clearing cache"
  ../vendor/bin/drush cr

}

#-------------------------- Execution --------------------------------
# Site initialization

echo -e "${green_bg} STEP 1: Initializing site...${NC}"
time pre_install

echo -e "${green_bg} STEP 2: Syncing Database and Files...${NC}"
time site_install

echo -e "${green_bg} STEP 3: Compiling theme...${NC}"
time compile_theme

echo -e "${green_bg} STEP 4: Post Install...${NC}"
time post_install

echo -en "${green_bg} DONE! ${NC} "
#-------------------------- END: Execution --------------------------------
