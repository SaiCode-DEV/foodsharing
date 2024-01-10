# Workflow for Merge requests

## Introduction

In order to use our scarce and precious time efficiently, it is advantageous to work in a structured and uniform manner.
This also applies to the creation of questions and merge requests.
The document describes the way and the basic conditions. For the detailed implementation of individual parts
see the corresponding chapters.

## Issues

An issue should be created in advance for each change to the project.
Assign a user or yourself to the issue to show other developers that the issue is already being worked on.

See also [How to report an issue](issue.md#How-to-report-an-issue) 

## Branch

The best way is to create a new branch from the current master branch and reference the output for your changes.
The branch name contains the issues number and describes what the branch contains.

Sample:
```bash
git checkout master
git pull --rebase
git checkout -b 89-create-emails-for-invitations
```

or with GitLab handle
```bash
git checkout master
git pull --rebase
git checkout -b 89-inktrap-create-emails-for-invitations
```

See also [How to fix an issue](issue.md#How-to-fix-an-issue)

## Commit

Better create multiple commits but smaller and clearer.
Give this a meaningful description of what or why something was changed, added or removed.

Before you push a commit the following points should be done

- check for code and style errors `./scripts/lint`
  see also [Scripts](deployment/scripts.md#codestyle-scripts)
- check if the tests run without errors `./scripts/test`
  see also [Testing](backend/testing.md#running-tests) and [Scripts](deployment/scripts.md#testing-scripts)


## Merge Request

A merge request is not created until it is ready for a code review.
If this results in larger or longer lasting changes, the merge request is set to draft.
Requests that have no activity for over a month without justification should be closed and the assignment to the 
associated issues removed from the user.

See also [Creating merge request](https://docs.gitlab.com/ee/user/project/merge_requests/creating_merge_requests.html)


## More information on the topic

- [diesdas.digital Pull Request Guideline](https://www.diesdas.digital/wiki/life-as-a-developer/pull-request-guidelines)
- [Atlassian Pull Request Guides](https://www.atlassian.com/blog/git/written-unwritten-guide-pull-requests)
