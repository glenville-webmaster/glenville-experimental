#!/bin/bash

# Abort if anything fails
set -e

#-------------------------- vars --------------------------------
DOCROOT="$BITBUCKET_CLONE_DIR/docroot"
THEME_DIR="$DOCROOT/themes/custom/glenville"
DATE=`date +%Y-%m-%d`
KILLDEV=true

# For now, Prod ENV is not used
case $BITBUCKET_BRANCH in
  develop)
    ALIAS='dev'
    ;;
  test)
    ALIAS='test'
    ;;
  master)
    ALIAS='prod'
    ;;
  experimental)
    ALIAS='exp' #for new environment when we get it
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
if [ $BITBUCKET_BRANCH = "develop" ]; then
  composer install --no-interaction --optimize-autoloader
else
  composer install --no-interaction --optimize-autoloader --no-dev
fi

# Compile SCSS / Minify CSS/JS
echo "============"
echo "Compiling dist"
echo "============"
cd $THEME_DIR
npm config set nodedir /usr/bin 
npm ci && grunt build --debug

# Add drush aliases
echo "============"
echo "Creating Drush files"
echo "============"
cd $BITBUCKET_CLONE_DIR
mkdir -p ~/.drush
cp -rf drush/sites ~/.drush/sites

mkdir -p ~/.acquia/pipelines/credentials
cd ~/.acquia/pipelines/credentials
cat > cloud_api.conf <<EOL
{
  "send_telemetry":false,
  "keys":{
    "${ACQUIA_KEY}":
    {
      "uuid":"${ACQUIA_KEY}",
      "secret":"${ACQUIA_SECRET}"
    }
  },
  "acli_key":"${ACQUIA_KEY}"
}
EOL

# Log into the Cloud Platform
acli auth:login --key=${ACQUIA_KEY} --secret=${ACQUIA_SECRET} --no-interaction

# Function to check if task is finished before running the next one
# Needs to be after login but before any function calls
task_status () {

    # Grap the short name (e.g. "Files Copied") and long name (e.g. "Files copied from Prod to Dev.")
    longName=$1
    
    # Print out what task is being performed
    echo "Preparing for task: $longName"
    echo "------------------------------------"
    echo "Pausing 30 seconds to check status to ensure task begins."
    
    # Sleep for 30 seconds to make sure task begins
    sleep 30s
    
    # Output latest task status to file
    echo "Saving current task JSON to file"
    acli api:applications:task-list --limit=1 ${ACQUIA_UUID} --no-interaction > task_status.json
    
    # Grab current task status, description, and UUID
    echo "Setting Task variables from file"
    taskDescription=$(jq '.[] | .description' task_status.json -r | sed 's/&quot;//g')
    echo "Task Description: $taskDescription"
    taskUUID=$(jq '.[] | .uuid' -r task_status.json)
    echo "Task UUID: $taskUUID"
    taskStatus=$(jq '.[] | .status' -r task_status.json)
    echo "Task Status: $taskStatus"
    
    # Check if task performed last is NOT the one that was supposed to run.
    # If it isn't, quit the script and print an error
    # If it is same task, continue
    # This also means we can do further checks based on the taskUUID
    if [ "$taskDescription" != "$longName" ]
    then
    	echo "Task running is $taskDescription. Was expecting $longName."
    	exit 1
    fi
    
    # Check Task Status.
    # If Completed, Go ahead and Say so and exit the function
    if [ $taskStatus = 'completed' ]
    then
        echo "Task \"$longName\" has completed successfully"
        echo ""
        echo ""
    # If Failed, return an error and exit out of the script
    elif [ $taskStatus = 'failed' ]
    then
        echo "Task \"$longName\" failed to complete. Exiting."
        exit 1
    # If in progress or starting, start monitoring loop
    elif [ $taskStatus = 'in-progress' ] || [ $taskStatus = 'starting' ]
    then
    	while [ $taskStatus = 'in-progress' ] || [ $taskStatus = 'starting' ]
    	do
    	    # Say that task is still in progress
    	    echo "Task is still in progress"
    	
    	    # Wait 30 more seconds and check status again
    	    sleep 30s
    	    
    	    # Check Status and dump to file
    	    acli api:applications:task-list --limit=1 ${ACQUIA_UUID} --no-interaction > task_status.json
    	    
    	    # If current task is not expected task, throw error and exit
    	    newUUID=$(jq '.[] | .uuid' -r task_status.json)
    	    if [ $taskUUID != $newUUID ]
    	    then
    	        echo "Unknown Error. Unmatched Task UUIDs. Exiting."
    	        exit 1
    	    fi
    	    
    	    # Set $taskStatus to current status
    	    taskStatus=$(jq '.[] | .status' -r task_status.json)
    	    
    	    # If task is still in starting or in-progress, loop will run again
    	done
    	
    	# If Completed, Go ahead and Say so and exit the function
        if [ $taskStatus = 'completed' ]
        then
            echo "Task \"$longName\" has completed successfully"
            echo ""
            echo ""
        # If Failed, return an error and exit out of the script
        elif [ $taskStatus = 'failed' ]
        then
            echo "Task \"$longName\" failed to complete. Exiting."
            exit 1
        # Catch-all Status. Print out status and quit
        else
            echo "Unexpected Task Status: $taskStatus. Exiting."
            exit 1
        fi
    # Catch-all Status. Print out status and quit
    else
        echo "Unexpected Task Status: $taskStatus. Exiting."
        exit 1
    fi
}

# If dev, clone from production
if [ $BITBUCKET_BRANCH = "develop" ]; then
  echo "============"
  echo "Migrating from production to $ALIAS"
  echo "============"

# Temporarily disable copying database and files to make testing faster

  if [ "$KILLDEV" = true ]; then
    # Make Backup of Database
    acli api:environments:database-backup-create gsc.dev gsc --no-interaction --quiet
    task_status "A gsc database backup has been created for Dev."
    
    # Copy Database from prod to dev
    # acli api:environments:database-copy gsc.dev gsc ${PROD_ENV} --no-interaction --quiet
    # task_status "Database gsc copied from Prod to Dev."
    
    # Copy Files from prod to dev
    # acli api:environments:file-copy gsc.dev --source="${PROD_ENV}" --no-interaction --quiet
    # task_status "Files copied from Prod to Dev."
    
    #./vendor/bin/acquiacli preprod:prepare $ACQUIA_UUID prod $ALIAS
    sleep 15
  else
    echo "============"
    echo " KILLDEV IS FALSE!! Skipping Migratioacli api:environments:database-copy gsc.dev gsc gsc.prodn..."
    echo "============"
  fi
fi

# If test, temporarily clone for upgrade testing purposes, but in the long run the idea is NOT to clone on test
if [ $BITBUCKET_BRANCH = "test" ]; then
  echo "============"
  echo "Migrating from production to $ALIAS"
  echo "============"

  if [ "$KILLDEV" = true ]; then
     # Make Backup of Database
    acli api:environments:database-backup-create gsc.test gsc --no-interaction --quiet
    task_status "A gsc database backup has been created for Stage."
    
    # Copy Database from prod to test (stage)
    acli api:environments:database-copy gsc.test gsc ${PROD_ENV} --no-interaction --quiet
    task_status "Database gsc copied from Prod to Stage."
    
    #Copy Files from prod to dev
    acli api:environments:file-copy gsc.test --source="${PROD_ENV}" --no-interaction --quiet
    task_status "Files copied from Prod to Stage."
    
    # ./vendor/bin/acquiacli preprod:prepare $ACQUIA_UUID prod $ALIAS
    sleep 15
  else
    echo "============"
    echo " KILLDEV IS FALSE!! Skipping Migration..."
    echo "============"
  fi
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

# Move over custom git igore.
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
git add --all

echo "============"
echo "Committing files to branch"
echo "============"
git commit -m "Bitbucket build - $BITBUCKET_BUILD_NUMBER, Commit $BITBUCKET_COMMIT"

echo "============"
echo "Pushing to Acquia"
echo "============"
git push origin $BITBUCKET_BRANCH

# For master, we also create a release tag
if [ $BITBUCKET_BRANCH = "master" ]; then
  echo "============"
  echo "Checking for existing Tags from today"
  echo "============"
  if [ $(git ls-remote origin refs/tags/$DATE | wc -l) -gt 0 ]; then
    TAG_COUNT=0
    TAG=$DATE.$TAG_COUNT
    echo "Trying $TAG"
    while [ $(git ls-remote origin refs/tags/$TAG | wc -l) -gt 0 ]; do
      TAG_COUNT=$[$TAG_COUNT +1]
      TAG=$DATE.$TAG_COUNT
      echo "Trying $TAG"
    done
  else
    TAG=$DATE
  fi
  git tag $TAG
  echo "============"
  echo "Pushing $TAG to Acquia"
  echo "============"
  git push --tags

  # Master branch deploys via Tag
  echo "============"
  echo "Deploying $TAG to $ALIAS"
  echo "============"
  acli api:environments:code-switch gsc.prod tags/$TAG --no-interaction --quiet
  echo "Switching to code $TAG to Production."
  sleep 30s
  
  #./vendor/bin/acquiacli prod:deploy $ACQUIA_UUID tags/$TAG true -y
  EXITCODE=$?

  # Check Exit from previous sub-command.
  echo "Exit code was: $EXITCODE"
  if [ $EXITCODE != 0 ]; then exit $EXITCODE; fi

  # DRUSH TASKS
  echo "===== Turning on Maintenance Mode ====="
  ./vendor/bin/drush @$ALIAS state:set system.maintenance_mode 1 --input-format=integer
  echo "===== Cache Rebuild ====="
  ./vendor/bin/drush @$ALIAS cache:rebuild
  echo "===== Updating Database ====="
  ./vendor/bin/drush @$ALIAS updatedb --yes
  echo "===== Turning off Maintenance Mode ====="
  ./vendor/bin/drush @$ALIAS state:set system.maintenance_mode 0 --input-format=integer
  echo "===== Cache Rebuild ====="
  ./vendor/bin/drush @$ALIAS cache:rebuild

  # Clear varnish
  # THERE IS CURRENTLY NO PURGE VARNISH COMMAND IN ACQUIA CLI 2.X
  acli api:environments:domains-clear-varnish gsc.prod --domains="www.glenville.edu" --no-interaction --quiet
  task_status "Varnish cache has been cleared on the Prod environment."
  
  # ./vendor/bin/acquiacli prod:purgevarnish $ACQUIA_UUID -y

elif [ $BITBUCKET_BRANCH = "test" ]; then

  # If we are moving to stage, it probably means we want to do some extra testing or there is a problem
  # As a result, it is probably a good idea to create a release tag when pushing to stage

  echo "============"
  echo "Checking for existing Tags from today"
  echo "============"
  if [ $(git ls-remote origin refs/tags/$DATE."STAGE" | wc -l) -gt 0 ]; then
    TAG_COUNT=0
    TAG=$DATE."STAGE".$TAG_COUNT
    echo "Trying $TAG"
    while [ $(git ls-remote origin refs/tags/$TAG | wc -l) -gt 0 ]; do
      TAG_COUNT=$[$TAG_COUNT +1]
      TAG=$DATE."STAGE".$TAG_COUNT
      echo "Trying $TAG"
    done
  else
    TAG=$DATE."STAGE"
  fi
  git tag $TAG
  echo "============"
  echo "Pushing $TAG to Acquia"
  echo "============"
  git push --tags

  # Stage branch deploys via Tag
  echo "============"
  echo "Deploying $TAG to $ALIAS"
  echo "============"
  acli api:environments:code-switch gsc.test tags/$TAG --no-interaction
  echo "Switching to code $TAG to Stage."
  sleep 30s
  
  #./vendor/bin/acquiacli preprod:deploy $ACQUIA_UUID test tags/$TAG true -y
  EXITCODE=$?

  # Check Exit from previous sub-command.
  echo "Exit code was: $EXITCODE"
  if [ $EXITCODE != 0 ]; then exit $EXITCODE; fi

  #DRUSH TASKS
  echo "====== Turning on Maintenance Mode ======"
  ./vendor/bin/drush @$ALIAS -vvv state:set system.maintenance_mode 1 --input-format=integer
  echo "====== Cache Rebuild ======"
  ./vendor/bin/drush @$ALIAS cache:rebuild
  echo "====== Updating Database ======"
  ./vendor/bin/drush @$ALIAS updatedb --yes
  echo "====== Turning off Maintenance Mode ======"
  ./vendor/bin/drush @$ALIAS state:set system.maintenance_mode 0 --input-format=integer
  echo "====== Cache Rebuild ======"
  ./vendor/bin/drush @$ALIAS cache:rebuild

  #CLEAR VARNISH
  # THERE IS CURRENTLY NO PURGE VARNISH COMMAND IN ACQUIA CLI 2.X
  acli api:environments:domains-clear-varnish gsc.test --domains="stage.glenville.edu" --no-interaction
  task_status "Varnish cache has been cleared on the Stage environment."
  
  # ./vendor/bin/acquiacli preprod:purgevarnish $ACQUIA_UUID $ALIAS

elif [ $BITBUCKET_BRANCH = "develop" ]; then

  # DRUSH TASKS
  echo "===== Turning on Maintenance Mode ====="
  ./vendor/bin/drush @$ALIAS state:set system.maintenance_mode 1 --input-format=integer
  echo "===== Cache Rebuild ====="
  ./vendor/bin/drush @$ALIAS cache:rebuild
  echo "===== Updating Database ====="
  ./vendor/bin/drush @$ALIAS updatedb --yes
  echo "===== Turning off Maintenance Mode ====="
  ./vendor/bin/drush @$ALIAS state:set system.maintenance_mode 0 --input-format=integer
  echo "===== Cache Rebuild ====="
  ./vendor/bin/drush @$ALIAS cache:rebuild

  # Clear varnish
  # THERE IS CURRENTLY NO PURGE VARNISH COMMAND IN ACQUIA CLI 2.X
  acli api:environments:domains-clear-varnish gsc.dev --domains="dev.glenville.edu" --no-interaction --quiet
  task_status "Varnish cache has been cleared on the Dev environment."
  
  #./vendor/bin/acquiacli preprod:purgevarnish $ACQUIA_UUID $ALIAS
#
fi

# Run Drush tasks

# Done
echo "============"
echo "YAY! The build is complete"
echo "============"
