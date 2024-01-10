---
sidebar_position: 2
---

# Getting Ready
Foodsharing is mostly written in [PHP](https://www.php.net/docs.php) and [vue.js](https://vuejs.org).


### Requirements
- **UNIX** (or [WSL](https://ubuntu.com/tutorials/install-ubuntu-on-wsl2-on-windows-10#1-overview))
- [Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git)
- [Node.js (16.18)](https://nodejs.org/download/release/v16.18.0/)
    - Newer versions can cause conflicts with the legacy code.
- [Docker](https://docs.docker.com/)

:::note

To switch between Node.js versions on a **UNIX** system, you can use [nvm](https://github.com/nvm-sh/nvm).

:::

## Project structure

<details>

<summary>Toggle me!</summary>

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

</details>


## Start the local engine

Developing on a local basis requires a UNIX base system with docker installed.

:::caution

When you develop on **Windows**, [WSL](https://ubuntu.com/tutorials/install-ubuntu-on-wsl2-on-windows-10) with [Docker Desktop](https://www.docker.com/products/docker-desktop/) is required.

---

When you develop on **Linux**, [Docker](https://docs.docker.com/desktop/install/linux-install/) is required.

:::

```bash title="shell"
git clone git@gitlab.com:foodsharing-dev/foodsharing.git foodsharing
cd foodsharing && ./scripts/start
```


:::note

You need an ssh key on your system to clone the repository with SSH ([set up guide](https://docs.gitlab.com/ee/user/ssh.html#generate-an-ssh-key-pair)).

:::

Continue on [Seed Overview](backend/database/seed-overview).

## Go mobile wild

A pre-configured **Visual Studio Code**, requires a [GitLab](https://gitlab.com) account.

[![Open in Gitpod](https://gitpod.io/button/open-in-gitpod.svg)](https://gitpod.io/#https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/)

