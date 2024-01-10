# Main entry point

There is a single entry point for all requests at `/public/index.php`.
It is a (mostly) standard part of Symfony.

For what URLs go where, have a brief look at the `routes` yaml file and directory in /config.
These determine what paths go where for important (user facing) parts of the website.
Other routes are defined by annotations near their controller code - this mostly applies to the REST API.

The relevant (custom) code has been moved to `src/Entrypoint`, where three respective controllers do the actual work.
For the `IndexController`, a large part of the code has been moved to `src/EventSubscriber/RenderControllerSetupSubscriber.php`.
