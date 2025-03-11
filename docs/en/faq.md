---
title: FAQ
---

# Frequently asked questions

# Basic layout
Q: The architecture is not clear to me. Could you explain the basic layout?

A: The main architecture goals came from this book "[Modernizing Legacy Applications In PHP](https://leanpub.com/mlaphp)", although we deviate in some place. There is also a great [1h video](https://www.youtube.com/watch?v=65NrzJ_5j58) describing rough details about what the book is talking about.

A few current architecture goals would be:

* create [REST](backend/api/introduction) controllers for all API use, remove all other API stuff
* remove all html/js in php strings
* use [vue.js](frontend/javascript#vuejs) for all dynamic kind of templates
* remove global eval stuff  ... goes with the only [REST API](backend/api/introduction) endpoints ...
* modernize a lot more of the frontend code
* replace years long outdated flourish lib

So the preferred approach would be [*Model* to *Gateway*](backend/php/php-modules#newer-module-structure) classes, see here: [Issue 9](https://gitlab.com/foodsharing-dev/foodsharing/issues/9)

## Getting started
Q: I would really love to do anything, but when I look at the repo I can't even find a thing I could change just for testing! How do you start?

A: One technique is to 

  1. find some text that is clearly visible on the page, and 
  2. search the codebase for it, which might point to a translation string, then 
  3. search for that translation key, 
  4. repeat until finding it. 

In foodsharing some of the longer content comes from the database, so you won't find that in the the codebase (except for maybe in an sql seed file), so try things more like buttons, or menu items...

## Technical constraints
Q: What are our technical constraints? (such as server, storage. memory and communication interfaces)

A: The server has 64G of memory, and 20 Cores currently. Server stats:

* [munin](https://onion.foodsharing.network/foodsharing.network/onion.foodsharing.network/index.html)

The current email load is high, we get spam-flagged a lot, so in the future we need to introduce (more) granular email settings to users. 
(E-mail handling: we have currently more than one server for mails.)
When we use a third party service, we sometimes run into problems (photon, map tiles).

## Visual guidelines
Q: Are there visual guidelines? (User interface, colors, buttons, etc.)

A: There are guidelines. Basically, we use common sense.

The frontend needs rewriting as well and currently we're mostly working on & refactoring the backend.

## Structure
Q: So if we break the homepage down into its parts - how is it structured?

A: For some of the php code, see [the php page](backend/php/php-main).

Most of our functions can be found in `/src/Helpers/`.

Book: https://leanpub.com/mlaphp

We still have some of the functions that bit by bit get replaced during refactoring.

## Our tech
Q: [it-tasks](./contributing) lists a number of tech stuff we use on the page. How is their relationship to each other / what do we use them for? What do we want to remove from our codebase?

A: We use ...
* [PHP](backend/php/php-main) (Symfony 5) ... we also use a lot of non-symfony-php, that we want to refactor
* [JavaScript](frontend/javascript) (Webpack, Vue.js) ... we're also modernizing some old JavaScript. Vue impacts mainly just one thing. CSS mostly has an effect on other stuff as well. (see below)
* HTML (Twig) ... there is also the old way with string contatination.  Twig is the new way. We're moving more towards vue.
* CSS (Bootstrap) ... move from global CSS to vue components
* MariaDB (which is basically MySQL) currently has a very lax error handling configuration. We try to set a more strict config here.
* Redis is also very low maintenancy. It's a cache, where the session is stored.
* socket.io [nodejs](deployment/requests) server - the chat server. Low maintenance.
* [RESTful APIs](backend/api/introduction) - We also have old ones but are moving towards REST.
* the Docker Compose development environment
* [Codeception](backend/testing) for Unit-, API-, and Acceptance testing (with Selenium) - 
* [Git](deployment/git)
* GitLab CI for tests and automatic deployment (with php deployer) - this is a nice, stable setup through which multiple people can deploy stuff. (Not bottle-necking through one person as jobs are usually splitted between at least two servers.)

## Helping hands
Q: Who can I ask for help with what? Who is part of the team with which focus and which skills? (volunteer list)

A: The common and most efficient way is to ask the dev channel a detailed question - so everyone who might have the knowledge and time can help.

Also we have team members with special responsibilities, see here: https://gitlab.com/foodsharing-dev/foodsharing/-/wikis/Information/responsibilities

This means that there currently is no one head of the foodsharing IT. We decide with votes & vetos. Therefore currently there is no roadmap. The responsibles have lately said, that cleaning up old code and finishing open Merge Requests has priority over new features. But basically if you like an idea and are willing to work on your code - you're welcome to join. :-)
