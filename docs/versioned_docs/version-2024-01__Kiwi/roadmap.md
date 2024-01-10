# Road map
In order to speed up development and motivate more developers, we want to concentrate on removing the technical legacy (refactoring) and not add any new functions for the time being.

## Info for non-developers
> We first have to renovate our house (foodsharing.de). This means that we first have to rebuild the foundation and the roof, because it drips in all sorts of places when we want to build or change a function.

## Main goal
> - [ ] Refactor XHR endpoints to rest APIs endpoints, to cover the website functionality
> - [x] Upgrade to PHP 8.1 (10.2022)

## Second goal
> - [ ] Refactor old php / inline HTML / JavaScript to Vue.JS
> - [ ] Get rid of unnecessary packages like jQuery

## Other topics

### Role of the product AG?
> Some delegates (preferably ambassadors) should be available to us in Slack and be able to explain the processes of the platform or bring open questions to the community for a vote so that a consensus can be reached.

### Global architecture
> - [ ] Persistent sessions are a problem for the last login date https://gitlab.com/foodsharing-dev/foodsharing/-/issues/956
> - [ ] Refactor “Betriebe” and “Fairteiler” to “Orte” / places.
> - [ ] Refactor “Regions” and “Groups” to a unified name.
> - [ ] Unifying name conventions (Company, Stores and Betriebe are the same)

### Database
> - [ ] Maintenance task to clean up unused and archived accounts

### Notification
> - [x] Better rate limiting for mail system https://gitlab.com/foodsharing-dev/foodsharing/-/issues/886 (11.2022)
> - [ ] Notification for empty ambassador, group admin or inactive store manager 
> - [ ] Better notification system to cover different endpoints, for more flexibility

### Dev env
> - [ ] Alternative for our docker bash script – for example https://ddev.com
> - [ ] Building a modern frontend stack with example nuxt.js and storybook.js
