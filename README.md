This is a Composer-based project from the [OpenEDU](https://www.drupal.org/project/openedu) Drupal distribution. 

## Pre-reqs
- PHP 7.1

##Get Started
- Fork the repository
- Add the main repository as 'upstream'
- run `composer gv` (composer install, compile theme, sql sync)

###GIT Workflow
- `git pull upstream develop`
- `git push`
- `git checkout -b GSO-000-description`
- Commit code, export config if necessary `../bin/drush cex vcs`
- Ensure you are in sync with upstream before submitting PR
  - `git checkout develop`
  - `git pull upstream develop`
  - `git push`
  - `git checkout <feature branch name>`
  - `git rebase -i upstream/develop`
- Submit a PR from feature branch to upstream develop branch
  

