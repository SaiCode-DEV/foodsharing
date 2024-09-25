---
title: Erste Schritte
sidebar_position: 2
---

# Erste Schritte

Foodsharing ist hauptsächlich in [PHP](https://www.php.net/docs.php) und [Vue.js](https://vuejs.org) geschrieben.

## Anforderungen

- **UNIX** (oder [WSL](https://ubuntu.com/desktop/wsl))
- [Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git)
- [Node.js (20.16.0)](https://nodejs.org)
- [Docker](https://docs.docker.com/)

::: info
Um zwischen Node.js-Versionen auf einem **UNIX**-System zu wechseln, kannst du [nvm](https://github.com/nvm-sh/nvm) verwenden.
:::

## Projektstruktur

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

## Starte die lokale Umgebung

Die Entwicklung auf lokaler Basis erfordert ein UNIX-Betriebssystem mit installiertem Docker.

::: warning

Wenn du unter **Windows** entwickelst, ist [WSL](https://ubuntu.com/tutorials/install-ubuntu-on-wsl2-on-windows-10) mit [Docker Desktop](https://www.docker.com/products/docker-desktop/) erforderlich.

Wenn du unter **Linux** entwickelst, ist [Docker](https://docs.docker.com/desktop/install/linux-install/) erforderlich.
:::

```bash title="shell"
git clone git@gitlab.com:foodsharing-dev/foodsharing.git foodsharing
cd foodsharing && ./scripts/start
```

::: info
Du benötigst einen SSH-Schlüssel auf deinem System, um das Repository mit SSH zu klonen ([Einrichtungsanleitung](https://docs.gitlab.com/ee/user/ssh.html#generate-an-ssh-key-pair)).
:::

Fahre fort mit [Seed Overview](backend/database/seed-overview).

## Go Mobile Wild

Eine vorkonfigurierte **Visual Studio Code**-Umgebung erfordert ein [GitLab](https://gitlab.com)-Konto.

[![Open in Gitpod](https://gitpod.io/button/open-in-gitpod.svg)](https://gitpod.io/#https://gitlab.com/foodsharing-dev/foodsharing/-/tree/master/)
