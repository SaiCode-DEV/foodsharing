# Releasing

We want to release the beta version of the foodsharing website to production every two months.
For one to two weeks, we stop merging features and focus on testing and bug fixing. If there are features not to be used for the next release, they are disabled.
If there are still release blockers, we will talk about them in a call and decide what to do.

As soon as everything is ready, the master branch can be merged into the production branch.
People with rights for this can be found in the [gitlab-Wiki](https://gitlab.com/foodsharing-dev/foodsharing/-/wikis/responsibilities).

For organization, we use [gitlab milestones](https://gitlab.com/foodsharing-dev/foodsharing/-/milestones). Their titles consist of the month and the year of the release and a fruit. If you are working on something which belongs to the next release, feel free to add it to the milestone.
Please don't recycle milestones, but always use a new one for each release.

## Release Notes

The script release-notes-merge is executed shortly before the release and merges all markdown files from release-notes/milestone into a single file. The files are grouped by topic (by tags) and merge request numbers are correctly formatted (e.g. (!4067)). This automatically creates a clear and structured release overview.

The file header.md in the release-notes/milestone directory contains introduction text for the release notes.

### Adding Release Notes for a Merge Request

For each relevant merge request (MR), create a separate markdown file in the `release-notes/release-p/` directory. The filename can be chosen freely, but it should include at least the first MR number (e.g. `4489.md` or, for multiple related MRs, `4360-4462-4480.md`).

The structure of the file should be:

```markdown
---
text: Short description of the change (in German)
mr: [MR_NUMBER, ...]
tag: Category (e.g. Account, Backend, UI, ...)
---
```

**Example:**

```markdown
---
text: Login per Passkey ist jetzt möglich. Auf Handys außerdem per Fingerabdruck oder FaceID.
mr: [4489]
tag: Account
---
```

These files are automatically included when generating the release notes.

## Workflow

Since we can tag the latest commit of the release on branch master, it is not necessary to create a new branch for the release. Also, in contrast to hotfixes which are cherry-picked, it is not necessary to merge the changes back into master after the release.

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
