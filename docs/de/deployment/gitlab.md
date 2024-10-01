# Gitlab Runner

If you want to configure the GitLab-CI, check the [Gitlab Docs](https://docs.gitlab.com/ee/ci/yaml/gitlab_ci_yaml.html) out.

```bash title="shell"
curl -L "https://packages.gitlab.com/install/repositories/runner/gitlab-runner/script.deb.sh" | sudo bash && sudo apt-get install gitlab-runner

gitlab-runner exec docker [job]
```

