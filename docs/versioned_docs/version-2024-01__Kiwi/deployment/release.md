# Releasing

We want to release the beta version of the foodsharing website to production every two months.
For one to two weeks, we stop merging features and focus on testing and bug fixing. If there are features not to be used for the next release, they are disabled.
If there are still release blockers, we will talk about them in a call and decide what to do.

As soon as everything is ready, the master branch can be merged into the production branch.
People with rights for this can be found in the [gitlab-Wiki](https://gitlab.com/foodsharing-dev/foodsharing/-/wikis/responsibilities).

For organization, we use [gitlab milestones](https://gitlab.com/foodsharing-dev/foodsharing/-/milestones). Their titles consist of the month and the year of the release and a fruit. If you are working on something which belongs to the next release, feel free to add it to the milestone.
Please don't recycle milestones, but always use a new one for each release.

## Release Notes

We build a small python script to collect all german release notes text and images. Create gitlab in your profile in menu "Access Tokens" with permission level "read_api".
Script command is: python3 ./scripts/releaseNotes.py

## Workflow

Since we can tag the latest commit of the release on branch master, it is not necessary to create a new branch for the release. Also, in contrast to hotfixes which are cherry-picked, it is not necessary to merge the changes back into master after the release.

* Update the date of the release in the changelog and commit it to branch master.

* Create a new docs tag with
  * `install yarn` on your local system
  * `./scripts/db-docs-build`
  * `cd docs && yarn && yarn run version YYYY-MM__NAME`
  * `./scripts/restart`
  * check is http://localhost:13000 running
  * check is http://localhost:13000/docs/current/backend/database/database-tables-columns available

* Add a new tag on the latest commit in master. We use the date in the format `YYYY-MM` for the tag name.
  * either on the command line using `git tag YYYY-MM-DD` and `git push –tags`
  * or in the project's tag list on GitLab
* Create a new merge request in GitLab from `master` to `production`. Merge it and wait for the deployment to finish.
* Add a new section to the changelog and commit it to master.
