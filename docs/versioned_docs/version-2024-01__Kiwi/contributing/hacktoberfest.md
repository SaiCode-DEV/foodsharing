# Hacktoberfest

It's time again for Hacktoberfest. Foodsharing participates for the first time with the front- and backend of the website. We are looking forward to your commitment!

## What is foodsharing?
foodsharing is an initiative that came into being in 2012 and is committed to the responsible use of resources and a sustainable food system. The goal of ending food waste is the top priority.

Over 450,000 registered users and more than 100,000 volunteers, so-called food savers, have made the initiative an international movement. More than 11,000 businesses cooperate in the initiative, and 65 million kilograms of food have already been saved from waste. Every day, about 5,000 more pick-ups take place.

The foodsharing.de platform is based on voluntary commitment and is open source. The saving and sharing of food takes place free of charge. The non-profit foodsharing e.V. as umbrella organization and operator of the website ensures that it remains non-commercial and without advertising.
foodsharing brings people from a wide variety of backgrounds together and encourages them to join in, think about and use our planet's resources responsibly.

## This repository ...
This repository contains the front- and backend (HTML, CSS, JS, VueJS, PHP 8, NodeJS) of the websites foodsharing.de, foodsharing.ch and foodsharing.at. The platform is primarily German-language, but registered users can switch to other languages such as English, French and Co. We operate internationally, but are most strongly represented in the DACH region (Germany, Austria and Switzerland) and other EU countries.

## What is the best way to participate?
The project has some legacy issues but the developers who are actively working on it are trying to rid this platform of them. In addition, there are [issues](https://gitlab.com/foodsharing-dev/foodsharing/-/issues) from over three years ago and more. To make it easier for you to find issues, we have labeled issues. Depending on your skill level, pick issues labeled `starter task`, `hacktoberfest` (preferred issues with low to medium barrier and manageable scope) or `help wanted`. But of course you can also take other issues too, any help is welcome! 


:::info

Write a comment under the issue that you like to take over the issue. We can then assign you. This way we can prevent two people from working on the same issue at the same time.

:::

## Get started
1. fork the repository
2. clone your forked repository
3. add the original repository as upstream `git remote add upstream https://gitlab.com/foodsharing-dev/foodsharing.git` (sync the repository every time you are working on it to prevent working on old files with `git checkout master && git fetch upstream && git merge upstream/master`)
4. install needed software (docker and so on) [check out the getting ready part](../getting-ready).
5. navigate to the directory with foodsharing content
6. start the Docker containers via `./scripts/start` and checkout the [backend](../category/backend) or [frontend](../category/frontend/) docs.
7. call `http://localhost:18080`. Do you see the page? **Note that you should not clone the original foodsharing repository, but your fork.**

## Make a (pull) merge request
If you have worked on an issue, feel free to create a merge request. Make sure that the target of your MR is the original foodsharing-dev/foodsharing repository. Also, please fill out the template in the large editor field. We will then look at it and give feedback in the code review. If we know we can't/won't merge your MR directly, we'll give the label hacktoberfest-accepted to be able to count it for hacktoberfest 2022. [see how it works (gif)](https://i.imgur.com/8sJwF8k.mp4)
Do not assign the label yourself, it will not count anyway.

## Do you have questions or problems while or before contributing? Connect with active contributors!
We who are actively working on foodsharing know that the project is very branched and it can be difficult to get started. If you have questions, you can browse the [devdocs.foodsharing.network](https://devdocs.foodsharing.network) (en). You might find an answer there.

If not, post comments under your issue / merge request or join on Slack and get in touch with us there. :)
Slack: https://slackin.yunity.org/ and join the `#foodsharing-dev` and `#fs-dev-hacktoberfest-2022` channels and feel free to get in touch with us. We write in German and English.

## Thank you for contributing!
