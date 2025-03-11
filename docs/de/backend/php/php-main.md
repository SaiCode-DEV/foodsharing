# Main entry point

There is a single entry point for all requests at `/public/index.php`.
It is a (mostly) standard part of Symfony.

Most routes are defined by attributes near their controller code.
A few routes are also definied in the `routes` yaml file and directory in /config.

Common code for all "render controllers" (i.e. controllers that template HTML in some form)
is located in `src/EventSubscriber/RenderControllerSetupSubscriber.php`.
