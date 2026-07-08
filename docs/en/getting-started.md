---
sidebar_position: 2
---

# Getting Ready
Foodsharing is mostly written in [PHP](https://www.php.net/docs.php) and [vue.js](https://vuejs.org).


### Requirements
- **UNIX** (or [WSL](https://ubuntu.com/tutorials/install-ubuntu-on-wsl2-on-windows-10#1-overview))
- [Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git)
- [Docker](https://docs.docker.com/) — or [Podman](https://podman.io/) with [`podman-compose`](https://github.com/containers/podman-compose) as a rootless alternative; the `./scripts/*` wrappers auto-detect either provider.


## Project structure

::: details

```
Foodsharing
├── client
│   ├── lib
│   ├── src
│   │   ├── api
│   │   ├── components
│   │   ├── fonts
│   │   ├── helper
│   │   ├── mixins
│   │   ├── scss
│   │   ├── stores
│   │   └── views
│   └── test
├── docker
│   └── conf
├── migrations
├── scripts
├── translations
├── src
│   ├── Command
│   ├── Dev
│   ├── Lib
│   ├── Modules
│   ├── Permissions
│   ├── RestApi
│   └── Utility
├── templates
├── tests
│   ├── acceptance
│   ├── api
│   ├── cli
│   ├── functional
│   └── unit
└── websocket
    └── src
```

:::


## Start the local engine

Developing on a local basis requires a UNIX base system with docker installed.

::: warning

When you develop on **Windows**, [WSL](https://ubuntu.com/tutorials/install-ubuntu-on-wsl2-on-windows-10) with [Docker Desktop](https://www.docker.com/products/docker-desktop/) is required.

---

When you develop on **Linux**, [Docker](https://docs.docker.com/desktop/install/linux-install/) is required.

:::

```bash title="shell"
git clone git@gitlab.com:foodsharing-dev/foodsharing.git foodsharing
cd foodsharing && ./scripts/start
```


::: info

You need an ssh key on your system to clone the repository with SSH ([set up guide](https://docs.gitlab.com/ee/user/ssh.html#generate-an-ssh-key-pair)).

:::

Now go and visit [localhost:18080](http://localhost:18080) in your browser. You should see a foodsharing instance running on your local machine :)

Continue on [Seed Overview](backend/database/seed-overview).

